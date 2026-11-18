$ErrorActionPreference = 'Stop'

$compose = Join-Path $PSScriptRoot 'compose.yaml'
$testDatabase = 'board_games_concurrency_test'

docker compose -f $compose up -d --wait db
if ($LASTEXITCODE -ne 0) {
    throw 'No se pudo iniciar PostgreSQL.'
}

$databaseExists = docker compose -f $compose exec -T db psql -U board_games -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = '$testDatabase'"
if ($LASTEXITCODE -ne 0) {
    throw 'No se pudo consultar la base de pruebas.'
}

if ($databaseExists -ne '1') {
    docker compose -f $compose exec -T db createdb -U board_games $testDatabase
    if ($LASTEXITCODE -ne 0) {
        throw "No se pudo crear la base de pruebas $testDatabase."
    }
}

$testOptions = @(
    '-e', 'APP_ENV=testing'
    '-e', 'APP_CONFIG_CACHE=/tmp/board-games-test-config.php'
    '-e', 'DB_CONNECTION=pgsql'
    '-e', "DB_DATABASE=$testDatabase"
    '-e', 'DB_URL='
)

$testCommand = 'if [ ! -f vendor/autoload.php ]; then composer install --no-interaction --prefer-dist; fi; php artisan migrate --force; exec php vendor/bin/phpunit'
docker compose -f $compose run --rm --no-deps -T --name board-games-api-test @testOptions api sh -ec $testCommand
if ($LASTEXITCODE -ne 0) {
    throw "La ejecucion de las pruebas termino con codigo $LASTEXITCODE."
}
