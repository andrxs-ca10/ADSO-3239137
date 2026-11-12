# Contrato de la API — Board Games

El contrato define las rutas y respuestas que implementa Laravel y consume React. Las reglas funcionales están en [`spec.md`](spec.md), y las tablas en [`data-model.md`](data-model.md).

## 1. Convenciones

| Tema | Regla |
|---|---|
| Base | `/api/v1` |
| Formato | JSON en UTF-8. Campos en `snake_case`. |
| Identificadores | UUID como texto. |
| Fechas y horas | ISO 8601 en UTC con `Z`. |
| Autenticación | `Authorization: Bearer <token>`, excepto `POST /auth/login` y `GET /health`. |
| Paginación | `?page=1&size=20`. El tamaño máximo es 50. |
| Idioma | Campos y códigos en inglés. Mensajes dirigidos al usuario en español. |

Las respuestas paginadas tienen esta forma:

```json
{
  "items": [],
  "page": 1,
  "size": 20,
  "total": 0
}