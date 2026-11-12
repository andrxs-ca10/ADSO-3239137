# ADR-003 — Baja lógica y conservación del historial

- Estado: Aceptada
- Fecha: 2026-11-12

## Contexto

Los préstamos y las devoluciones son operaciones históricas. Si se elimina físicamente un juego o se consulta su información solo desde el catálogo actual, los registros anteriores pueden perder su contexto.

## Decisión

Los juegos se dan de baja de forma lógica mediante `deleted_at`. Las búsquedas del catálogo excluyen los juegos dados de baja.

Un juego con préstamos sin devolver no puede darse de baja. Su información histórica continúa disponible después de la baja.

Los préstamos y las devoluciones no se eliminan. Cada línea de préstamo conserva una copia del título, EAN y categoría del juego al momento de la operación. Los reportes históricos consultan esos valores guardados en la línea.

El EAN debe ser único entre los juegos activos.

## Alternativas consideradas

- Eliminar físicamente los juegos y sus operaciones relacionadas.
- Conservar las operaciones, pero obtener siempre el título, EAN y categoría del catálogo actual.
- Dar de baja lógicamente los juegos y guardar en cada línea los datos históricos de la operación.

Se elige la tercera alternativa para preservar el historial y permitir que el catálogo cambie sin alterar préstamos anteriores.

## Consecuencias

- `games` incluye `deleted_at`.
- Las consultas del catálogo filtran los juegos dados de baja.
- `loan_items` conserva `game_title_snapshot`, `ean_snapshot` y `category_name_snapshot`.
- Las operaciones históricas siguen consultables aunque el juego ya no esté activo.
- La baja de un juego no borra sus préstamos ni libera unidades que todavía estén prestadas.

## Verificación

Una prueba crea un préstamo, modifica o da de baja el juego y confirma que la línea y el reporte conservan el título, EAN y categoría originales. Otra prueba verifica que un juego con préstamos abiertos no pueda darse de baja.

## Tareas relacionadas

`T-03`, `T-08`, `T-09`, `T-11` y `T-18`.