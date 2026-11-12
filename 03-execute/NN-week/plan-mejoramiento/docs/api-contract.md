# Contrato de la API — Board Games

Este documento define las rutas y respuestas que implementa Laravel y consume React. Las reglas funcionales están en [`spec.md`](spec.md) y las tablas en [`data-model.md`](data-model.md).

## 1. Convenciones

| Tema | Regla |
|---|---|
| Ruta base | `/api/v1`. Las rutas de este documento son relativas a esa base. |
| Formato | JSON en UTF-8. Los campos usan `snake_case`. La carga y descarga de portadas usan el formato indicado en la sección de juegos. |
| Identificadores | UUID como texto. |
| Fechas y horas | ISO 8601 en UTC con `Z`. Los filtros que reciben solo una fecha usan `YYYY-MM-DD`. |
| Autenticación | `Authorization: Bearer <token>`, excepto en `POST /auth/login` y `GET /health`. |
| Paginación | `page` empieza en 1. `size` tiene un valor predeterminado de 20 y un máximo de 50. |
| Idioma | Campos y códigos en inglés. Mensajes para el usuario en español. |

Las listas paginadas usan esta estructura:

```json
{
  "items": [],
  "page": 1,
  "size": 20,
  "total": 0
}
```

### Cuerpo uniforme de error

```json
{
  "code": "GAME_NOT_AVAILABLE",
  "message": "No hay unidades disponibles de este juego.",
  "details": [
    {
      "field": "items[0].game_id",
      "issue": "sin unidades disponibles"
    }
  ]
}
```

`details` puede ser una lista vacía. Las respuestas de error excluyen contraseñas, trazas, consultas SQL y rutas internas.

| HTTP | Uso |
|---|---|
| `200` | Operación exitosa. |
| `201` | Recurso creado. |
| `204` | Operación exitosa sin cuerpo. |
| `401` | Credenciales incorrectas o token ausente, inválido o vencido. |
| `403` | Rol insuficiente. |
| `404` | Recurso inexistente o dado de baja. |
| `409` | Conflicto con el estado actual del recurso. |
| `422` | Datos inválidos o incumplimiento de una regla de entrada. |

## 2. Códigos de error

| Código | HTTP | Situación |
|---|---|---|
| `INVALID_CREDENTIALS` | `401` | Usuario o contraseña incorrectos. |
| `UNAUTHENTICATED` | `401` | Token ausente, inválido o vencido. |
| `FORBIDDEN_ROLE` | `403` | Rol insuficiente. |
| `NOT_FOUND` | `404` | Recurso inexistente o dado de baja. |
| `USERNAME_TAKEN` | `409` | Nombre de usuario duplicado. |
| `EAN_TAKEN` | `409` | EAN duplicado entre juegos activos. |
| `DOCUMENT_TAKEN` | `409` | Documento de socio duplicado. |
| `GAME_HAS_OPEN_LOANS` | `409` | El juego tiene unidades prestadas y no puede darse de baja. |
| `GAME_NOT_AVAILABLE` | `409` | No hay unidades disponibles para completar el préstamo. |
| `MEMBER_HAS_OVERDUE` | `409` | El socio tiene juegos vencidos sin devolver. |
| `ITEM_ALREADY_RETURNED` | `409` | La línea ya fue devuelta. |
| `MEMBER_LIMIT_EXCEEDED` | `422` | El préstamo superaría el límite de cuatro juegos sin devolver. |
| `DUPLICATE_GAME_IN_LOAN` | `422` | El mismo juego aparece más de una vez en la solicitud. |
| `VALIDATION_ERROR` | `422` | Uno o más campos son inválidos. |
| `INVALID_DATE_RANGE` | `422` | La fecha final es anterior a la inicial. |

## 3. Rutas

### 3.1 Autenticación y salud

| Método | Ruta | Acceso | Éxito |
|---|---|---|---|
| `POST` | `/auth/login` | Público | `200` con token y rol. |
| `GET` | `/health` | Público | `200` con `{"status":"ok"}`. |

Solicitud de inicio de sesión:

```json
{
  "username": "ana",
  "password": "********"
}
```

Respuesta:

```json
{
  "access_token": "token",
  "token_type": "Bearer",
  "expires_in": 28800,
  "role": "operator"
}
```

Si las credenciales fallan, el mensaje es el mismo para un usuario inexistente y una contraseña incorrecta.

### 3.2 Usuarios

| Método | Ruta | Acceso | Éxito |
|---|---|---|---|
| `POST` | `/users` | `admin` | `201` con `id`, `username` y `role`. |

La solicitud recibe `username`, `password` y `role`. El único rol permitido al crear un usuario por esta ruta es `operator`. El nombre se guarda recortado y en minúsculas.

### 3.3 Categorías

| Método | Ruta | Acceso | Éxito |
|---|---|---|---|
| `GET` | `/categories` | `admin`, `operator` | `200` con las categorías, ordenadas por nombre. |

Cada categoría incluye `id` y `name`. Las cinco categorías iniciales son estrategia, familiares, cartas, rol e infantiles.

### 3.4 Juegos

| Método | Ruta | Acceso | Éxito |
|---|---|---|---|
| `GET` | `/games` | `admin`, `operator` | `200` con lista paginada de juegos activos. |
| `GET` | `/games/{id}` | `admin`, `operator` | `200` con el detalle del juego. |
| `POST` | `/games` | `admin` | `201` con el juego creado. |
| `PUT` | `/games/{id}` | `admin` | `200` con el juego actualizado. |
| `DELETE` | `/games/{id}` | `admin` | `204` tras la baja lógica. |
| `PUT` | `/games/{id}/cover` | `admin` | `200` con el juego y la clave de la portada. |
| `GET` | `/games/{id}/cover` | `admin`, `operator` | `200` con la imagen. |
| `DELETE` | `/games/{id}/cover` | `admin` | `204` tras quitar la portada. |

`GET /games` admite `q`, `category_id`, `page` y `size`. `q` busca coincidencias parciales en `title` o `designer`. El listado se ordena por título.

`POST /games` recibe `title`, `designer`, `ean`, `category_id` y `total_units`. El servidor inicializa `available_units` con el mismo valor de `total_units`.

`PUT /games/{id}` recibe `title`, `designer`, `category_id` y `total_units`. Si cambia el total, el servidor ajusta las unidades disponibles por la misma diferencia y devuelve `422` si el ajuste deja la disponibilidad fuera del rango permitido.

La representación del juego incluye `id`, `title`, `designer`, `ean`, `category` con `id` y `name`, `total_units`, `available_units` y `cover_key`. Si el juego carece de portada, `cover_key` vale `null`.

La carga de portada usa `multipart/form-data` con el campo `file`. Acepta archivos JPEG o PNG de hasta 2 MB. La API valida el tipo por contenido. La descarga devuelve el archivo con su tipo de contenido y responde `404` si el juego carece de portada.

### 3.5 Socios

| Método | Ruta | Acceso | Éxito |
|---|---|---|---|
| `GET` | `/members` | `admin`, `operator` | `200` con lista paginada de socios. |
| `GET` | `/members/{id}` | `admin`, `operator` | `200` con detalle y conteo de juegos sin devolver. |
| `POST` | `/members` | `admin`, `operator` | `201` con el socio creado. |

La creación recibe `full_name` y `document_number`. La representación del socio incluye estos campos y `id`. El detalle añade `active_loans`, que cuenta los juegos sin devolver. El listado admite `q`, `page` y `size`. `q` busca por nombre o documento.

### 3.6 Préstamos y devoluciones

| Método | Ruta | Acceso | Éxito |
|---|---|---|---|
| `POST` | `/loans` | `admin`, `operator` | `201` con el préstamo y sus líneas. |
| `GET` | `/loans` | `admin`, `operator` | `200` con lista paginada de préstamos. |
| `GET` | `/loans/overdue` | `admin`, `operator` | `200` con las líneas vencidas sin devolver. |
| `GET` | `/loans/{id}` | `admin`, `operator` | `200` con el préstamo y sus líneas. |
| `POST` | `/loans/{loan_id}/items/{item_id}/return` | `admin`, `operator` | `200` con la línea devuelta y `returned_at` actualizado. |

`POST /loans` recibe el socio y de uno a cuatro juegos distintos:

```json
{
  "member_id": "uuid",
  "items": [
    { "game_id": "uuid" }
  ]
}
```

La respuesta incluye `id`, `member_id`, `loaned_at`, `status` e `items`. Cada línea incluye `id`, `game_id`, `game_title_snapshot`, `ean_snapshot`, `category_name_snapshot`, `due_at` y `returned_at`.

El vencimiento se calcula diez días después de `loaned_at`. El valor de `returned_at` es `null` mientras el juego siga prestado. Si la operación excede el límite del socio, la API devuelve `422` e incluye el conteo actual en `details`. Si dos operadores solicitan simultáneamente la última unidad, uno obtiene `201` y el otro obtiene `409` con `GAME_NOT_AVAILABLE`.

El estado se calcula según las líneas:

- `open` cuando quedan juegos sin devolver y ninguno está vencido.
- `overdue` cuando al menos un juego sin devolver ya venció.
- `closed` cuando todos los juegos fueron devueltos.

`GET /loans` admite `from`, `to`, `status`, `member_id`, `page` y `size`. El listado se ordena por `loaned_at` descendente.

`GET /loans/overdue` devuelve `items` con `loan_id`, `item_id`, `game_title`, `member_name`, `due_at` y `days_late`. Ordena primero las líneas con mayor retraso.

### 3.7 Reporte de juegos más solicitados

| Método | Ruta | Acceso | Éxito |
|---|---|---|---|
| `GET` | `/reports/most-requested` | `admin` | `200` con el reporte del periodo. |

`from` y `to` son obligatorios y ambos días se incluyen en el periodo UTC. La respuesta contiene `from`, `to` y `rows`. Si no hay préstamos en el periodo, `rows` es una lista vacía.

Cada fila contiene `game_id`, `game_title`, `category_name` y `times_requested`. El reporte agrupa por identificador del juego, título histórico y categoría histórica. Ordena por `times_requested` descendente y usa el título como desempate. El reporte presenta datos agregados del catálogo y excluye información personal de socios y operadores.

## 4. Seguridad

- Los JWT firmados vencen después de ocho horas e incluyen `sub` y `role`. Los tokens excluyen datos personales.
- Las contraseñas se almacenan como hash. Las respuestas y los registros excluyen las contraseñas y sus hashes.
- Laravel verifica la autorización en cada ruta protegida. Una respuesta `403` no revela si existe el recurso solicitado.
- CORS permite el origen configurado para el frontend.