# Operación local con Docker

Ejecuta los comandos desde `plan-mejoramiento` con Docker Desktop abierto. En una instalación nueva, configura `api/.env` siguiendo el [README principal](../README.md).

## Arranque y verificación

```powershell
docker compose -f .\infra\compose.yaml up -d --build
docker compose -f .\infra\compose.yaml ps
```

Cuando `db` y `api` aparezcan como `healthy`, comprueba que la API devuelve `ok`:

```powershell
(Invoke-RestMethod 'http://127.0.0.1:8000/api/v1/health').status
```

Con `web` en ejecución, abre el panel en http://127.0.0.1:5173/.

## Pruebas y diagnóstico

Ejecuta `.\infra\test-api.ps1` para correr PHPUnit en PostgreSQL con la base de pruebas `board_games_concurrency_test`.

Si falla el arranque, consulta los registros del servicio:

```powershell
docker compose -f .\infra\compose.yaml logs --tail 80 api db
docker compose -f .\infra\compose.yaml logs --tail 80 web
```
