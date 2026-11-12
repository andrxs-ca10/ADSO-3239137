# Modelo de datos — Board Games

Este documento define la información que almacena el sistema. Las tablas y columnas usan nombres en inglés. Las reglas funcionales se especifican en [`spec.md`](spec.md).

## 1. Convenciones

- Identificadores principales con UUID.
- Nombres de tablas y columnas en inglés y `snake_case`.
- EAN se almacena como texto para conservar posibles ceros iniciales.
- Las fechas y horas se almacenan en UTC.
- Los préstamos y las devoluciones conservan su historial.

## 2. Entidades y relaciones

El modelo contiene seis entidades:

- `categories` clasifica juegos.
- `games` contiene los juegos del catálogo.
- `members` contiene los socios.
- `users` contiene administradores y operadores.
- `loans` registra cada préstamo.
- `loan_items` registra cada juego prestado y su devolución.

Relaciones:

- Una categoría puede clasificar muchos juegos.
- Un socio puede tener muchos préstamos.
- Un usuario puede registrar muchos préstamos.
- Un préstamo pertenece a un socio y a un usuario.
- Un préstamo tiene entre una y cuatro líneas.
- Cada línea corresponde a un juego.

## 3. Tablas y campos

### `categories`

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | UUID | Clave primaria. |
| `name` | VARCHAR | Obligatorio y único. |

Las cinco categorías se cargan como datos iniciales: estrategia, familiares, cartas, rol e infantiles.

### `games`

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | UUID | Clave primaria. |
| `title` | VARCHAR | Obligatorio. |
| `designer` | VARCHAR | Diseñador del juego. |
| `ean` | VARCHAR | Obligatorio y único entre juegos activos. |
| `category_id` | UUID | Clave foránea a `categories.id`. |
| `total_units` | INTEGER | Debe ser al menos uno. |
| `available_units` | INTEGER | Debe estar entre cero y `total_units`. |
| `cover_key` | VARCHAR nullable | Clave de la portada, si existe. |
| `deleted_at` | TIMESTAMP nullable | Fecha de baja lógica. Nulo significa que está activo. |

El formato del EAN se valida en el backend. El catálogo no elimina físicamente los juegos.

### `members`

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | UUID | Clave primaria. |
| `full_name` | VARCHAR | Obligatorio. |
| `document_number` | VARCHAR | Obligatorio y único. |

Los socios no se eliminan porque pueden estar asociados a operaciones históricas.

### `users`

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | UUID | Clave primaria. |
| `username` | VARCHAR | Obligatorio y único. |
| `password_hash` | VARCHAR | Obligatorio. Nunca se devuelve ni se registra en logs. |
| `role` | VARCHAR | Solo admite `admin` u `operator`. |

### `loans`

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | UUID | Clave primaria. |
| `member_id` | UUID | Clave foránea a `members.id`. |
| `loaned_by_user_id` | UUID | Clave foránea a `users.id`. |
| `loaned_at` | TIMESTAMP | Fecha y hora del préstamo en UTC. |

Los préstamos registrados no se editan ni se eliminan.

### `loan_items`

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | UUID | Clave primaria. |
| `loan_id` | UUID | Clave foránea a `loans.id`. |
| `game_id` | UUID | Clave foránea a `games.id`. |
| `game_title_snapshot` | VARCHAR | Título del juego al momento del préstamo. |
| `ean_snapshot` | VARCHAR | EAN al momento del préstamo. |
| `category_name_snapshot` | VARCHAR | Categoría al momento del préstamo. |
| `due_at` | TIMESTAMP | Vencimiento en UTC, diez días después del préstamo. |
| `returned_at` | TIMESTAMP nullable | Nulo mientras no se haya devuelto. |

Los datos de la línea se conservan aunque el catálogo del juego cambie. La devolución solo actualiza `returned_at`.

## 4. Restricciones e invariantes

La base de datos debe imponer las reglas que pueda comprobar:

- Cada tabla tiene clave primaria.
- `categories.name`, `members.document_number` y `users.username` son únicos.
- El EAN es único entre juegos activos.
- `games.category_id` debe referenciar una categoría existente.
- `loans.member_id` debe referenciar un socio existente.
- `loans.loaned_by_user_id` debe referenciar un usuario existente.
- `loan_items.loan_id` y `loan_items.game_id` deben referenciar registros existentes.
- `games.total_units` debe ser al menos uno.
- `games.available_units` debe ser mayor o igual a cero y menor o igual a `games.total_units`.
- La combinación `loan_id` y `game_id` es única para impedir repetir un juego en el mismo préstamo.

El máximo de cuatro juegos sin devolver por socio y el bloqueo por vencimiento se validan en Laravel dentro de una transacción. El mecanismo para proteger préstamos simultáneos se documenta en el ADR de concurrencia.

## 5. Estado de préstamos

El estado no se almacena como columna:

- `closed` cuando todas las líneas tienen `returned_at`.
- `overdue` cuando al menos una línea no devuelta superó `due_at`.
- `open` cuando hay líneas sin devolver y ninguna está vencida.

## 6. Historial y conservación

Los préstamos y las líneas de préstamo no se eliminan. Las devoluciones tampoco se borran. Los juegos usan baja lógica mediante `deleted_at`.

Las claves foráneas deben proteger los datos históricos e impedir que se borren físicamente socios, usuarios, juegos o préstamos relacionados.

## 7. Reporte de juegos más solicitados

El reporte agrupa los préstamos por juego e incluye los valores históricos conservados en `loan_items`. No muestra datos personales de socios ni operadores.

## 8. Verificación

Las migraciones y las pruebas deben comprobar las claves foráneas, unicidad, rangos de unidades, conservación histórica y reglas de préstamo descritas en este documento.