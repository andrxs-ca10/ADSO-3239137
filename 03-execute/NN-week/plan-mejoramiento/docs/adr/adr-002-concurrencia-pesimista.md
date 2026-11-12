# ADR-002 — Control de concurrencia en préstamos

- Estado: Aceptada
- Fecha: 2026-11-12

## Contexto

Dos operadores pueden intentar prestar al mismo tiempo la última unidad de un juego. También pueden registrar préstamos simultáneos para un mismo socio. El sistema debe mantener el inventario y el límite de juegos sin devolver, incluso con solicitudes concurrentes.

## Decisión

Laravel procesa el registro del préstamo dentro de una transacción de PostgreSQL y bloquea las filas que necesita validar hasta confirmar o revertir la operación.

Se bloquea el socio para volver a comprobar sus préstamos activos y vencidos dentro de la transacción. También se bloquean los juegos solicitados, ordenados por identificador, para revisar y actualizar su disponibilidad de forma segura.

La transacción registra el préstamo y sus líneas, conserva los datos históricos de cada juego y descuenta las unidades disponibles. Si una unidad ya no está disponible, se revierte la transacción y la API responde `409` con el código `GAME_NOT_AVAILABLE`.

Las devoluciones bloquean la línea y el juego correspondiente. La operación verifica que la línea no haya sido devuelta, registra `returned_at` y aumenta la disponibilidad dentro de la misma transacción.

## Alternativas consideradas

- Actualizar las unidades sin bloquear filas.
- Usar un campo de versión y control optimista.
- Bloquear las filas afectadas durante la transacción.

Se elige el bloqueo de filas porque permite validar disponibilidad y actualizar inventario como una sola operación en PostgreSQL.

## Consecuencias

- El backend debe mantener la transacción durante la validación y escritura del préstamo.
- Las unidades disponibles nunca deben quedar negativas ni superar las unidades totales.
- Ante dos solicitudes por la última unidad, una puede completarse y la otra responde `409`.
- Las solicitudes que contengan varios juegos bloquean sus filas en orden estable para reducir el riesgo de interbloqueo.
- Los errores durante la operación revierten los cambios de la transacción.

## Verificación

Una prueba automatizada envía dos solicitudes concurrentes para prestar la última unidad. El resultado esperado es un préstamo exitoso y una respuesta `409`. Otras pruebas verifican que una devolución duplicada no aumente dos veces la disponibilidad.

## Tareas relacionadas

`T-04`, `T-09`, `T-10`, `T-13` y `T-18`.