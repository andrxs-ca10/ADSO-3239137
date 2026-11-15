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