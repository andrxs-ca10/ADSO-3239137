param(
    [string]$ApiBaseUrl = 'http://127.0.0.1:8000/api/v1'
)

$ErrorActionPreference = 'Stop'
$ApiBaseUrl = $ApiBaseUrl.TrimEnd('/')

$username = Read-Host 'Usuario administrador'
$password = Read-Host 'Contrasena del administrador' -AsSecureString
$credential = [pscredential]::new($username, $password)

$session = Invoke-RestMethod -Method Post `
    -Uri "$ApiBaseUrl/auth/login" `
    -ContentType 'application/json' `
    -Headers @{ Accept = 'application/json' } `
    -Body (@{
        username = $credential.UserName
        password = $credential.GetNetworkCredential().Password
    } | ConvertTo-Json -Compress) `
    -TimeoutSec 15

if (-not $session.access_token -or $session.role -ne 'admin') {
    throw 'El cargador requiere una cuenta con rol admin.'
}

Write-Host 'Autenticacion correcta. Rol admin confirmado.'
$headers = @{
    Accept = 'application/json'
    Authorization = "Bearer $($session.access_token)"
}

$categories = Invoke-RestMethod -Method Get `
    -Uri "$ApiBaseUrl/categories" -Headers $headers -TimeoutSec 15

$categoryNames = @('estrategia', 'familiares', 'cartas', 'rol', 'infantiles')
$categoryIds = @{}

foreach ($category in $categories) {
    $categoryIds[$category.name] = $category.id
}

foreach ($name in $categoryNames) {
    if (-not $categoryIds.ContainsKey($name)) {
        throw "Falta la categoria '$name' en la API."
    }
}

$gamesByEan = @{}
$page = 1

do {
    $catalog = Invoke-RestMethod -Method Get `
        -Uri "$ApiBaseUrl/games?page=$page&size=50" `
        -Headers $headers -TimeoutSec 15

    foreach ($game in $catalog.items) {
        $gamesByEan[[string]$game.ean] = $game
    }

    $page++
} while ($catalog.page * $catalog.size -lt $catalog.total)

$demoGames = foreach ($number in 1..40) {
    $base = '20000000{0:D4}' -f $number
    $sum = 0
    $weight = 3

    for ($index = $base.Length - 1; $index -ge 0; $index--) {
        $sum += [int]::Parse($base.Substring($index, 1)) * $weight
        $weight = 4 - $weight
    }

    $ean = $base + [string]((10 - ($sum % 10)) % 10)
    $title = 'Juego Demo {0:D2}' -f $number

    if ($gamesByEan.ContainsKey($ean)) {
        $game = $gamesByEan[$ean]

        if ($game.title -ne $title) {
            throw "El EAN $ean ya pertenece a '$($game.title)'."
        }

        $game
        continue
    }

    $categoryName = $categoryNames[($number - 1) % $categoryNames.Count]
    $body = @{
        title = $title
        designer = 'Equipo Demo'
        ean = $ean
        category_id = $categoryIds[$categoryName]
        total_units = 3
    } | ConvertTo-Json -Compress

    Invoke-RestMethod -Method Post -Uri "$ApiBaseUrl/games" `
        -Headers $headers -ContentType 'application/json' `
        -Body $body -TimeoutSec 15
}

Write-Host "Juegos de demostracion disponibles: $($demoGames.Count)"

$demoMembers = foreach ($number in 1..10) {
    $document = 'BG-DEMO-{0:D2}' -f $number
    $fullName = 'Socio Demo {0:D2}' -f $number

    $search = Invoke-RestMethod -Method Get `
        -Uri "$ApiBaseUrl/members?q=$document&size=50" `
        -Headers $headers -TimeoutSec 15

    $existingMember = $search.items |
        Where-Object { $_.document_number -eq $document } |
        Select-Object -First 1

    if ($existingMember) {
        if ($existingMember.full_name -ne $fullName) {
            throw "El documento $document ya pertenece a '$($existingMember.full_name)'."
        }

        $existingMember
        continue
    }

    $body = @{
        full_name = $fullName
        document_number = $document
    } | ConvertTo-Json -Compress

    Invoke-RestMethod -Method Post -Uri "$ApiBaseUrl/members" `
        -Headers $headers -ContentType 'application/json' `
        -Body $body -TimeoutSec 15
}

Write-Host "Socios de demostracion disponibles: $($demoMembers.Count)"

$demoLoanIds = foreach ($index in 0..14) {
    $isOverdueDemo = $index -lt 3
    $memberIndex = if ($isOverdueDemo) {
        $index
    } else {
        3 + (($index - 3) % 7)
    }

    $memberId = $demoMembers[$memberIndex].id
    $gameId = $demoGames[$index].id
    $loanPage = 1
    $existingLoan = $null

    do {
        $loans = Invoke-RestMethod -Method Get `
            -Uri "$ApiBaseUrl/loans?member_id=$memberId&page=$loanPage&size=50" `
            -Headers $headers -TimeoutSec 15

        foreach ($loan in $loans.items) {
            $detail = Invoke-RestMethod -Method Get `
                -Uri "$ApiBaseUrl/loans/$($loan.id)" `
                -Headers $headers -TimeoutSec 15

            if (@($detail.items).Count -eq 1 -and $detail.items[0].game_id -eq $gameId) {
                $existingLoan = $detail
                break
            }
        }

        $loanPage++
    } while (-not $existingLoan -and ($loans.page * $loans.size -lt $loans.total))

    if ($existingLoan) {
        $existingLoan.id
        continue
    }

    $loanHeaders = $headers.Clone()
    if ($isOverdueDemo) {
        $loanHeaders['X-Demo-Loan-Clock'] = '11-days-ago'
    }

    $body = @{
        member_id = $memberId
        items = @(@{ game_id = $gameId })
    } | ConvertTo-Json -Depth 4 -Compress

    $created = Invoke-RestMethod -Method Post `
        -Uri "$ApiBaseUrl/loans" `
        -Headers $loanHeaders -ContentType 'application/json' `
        -Body $body -TimeoutSec 15

    $created.id
}

$overdue = Invoke-RestMethod -Method Get `
    -Uri "$ApiBaseUrl/loans/overdue" -Headers $headers -TimeoutSec 15

$expectedOverdueIds = $demoLoanIds | Select-Object -First 3
$verifiedOverdue = @($overdue.items | Where-Object {
    $_.loan_id -in $expectedOverdueIds
})

if ($demoLoanIds.Count -ne 15 -or $verifiedOverdue.Count -ne 3) {
    throw 'La API no confirmo los 15 prestamos y los 3 vencidos esperados.'
}

Write-Host "Prestamos de demostracion: $($demoLoanIds.Count)"
Write-Host "Vencidos de demostracion confirmados: $($verifiedOverdue.Count)"