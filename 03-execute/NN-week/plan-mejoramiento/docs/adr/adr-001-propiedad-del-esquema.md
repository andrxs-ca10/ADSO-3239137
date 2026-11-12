# ADR-001 — Propiedad del esquema de datos

- Estado: Aceptada
- Fecha: 2026-11-12

## Contexto

El sistema usa Laravel y PostgreSQL. Las tablas y restricciones deben poder reconstruirse de forma consistente en un entorno nuevo y mantenerse versionadas junto al backend.

## Decisión

El esquema de la base de datos pertenece al componente `api`. Sus cambios se realizan mediante migraciones de Laravel, guardadas en `api/database/migrations`.

Las categorías iniciales se cargan con los seeders de Laravel. El componente `infra` configura los contenedores y la conexión a la base de datos, sin crear tablas mediante scripts independientes.

## Alternativas consideradas

- Crear o modificar las tablas manualmente en cada entorno.
- Mantener el esquema únicamente en scripts SQL separados de Laravel.
- Versionar el esquema mediante migraciones de Laravel.

Se elige la tercera alternativa porque permite mantener los cambios del esquema junto al código que los utiliza y aplicarlos de forma repetible.

## Consecuencias

- Las migraciones forman parte de las revisiones del código.
- Un entorno nuevo puede reconstruir el esquema en el orden requerido.
- Las pruebas pueden crear la base aplicando las mismas migraciones.
- Los seeders cargan las categorías iniciales.
- Los cambios de esquema no se aplican editando manualmente la base de datos.

## Verificación

En una base vacía, ejecutar las migraciones y los seeders debe crear las tablas requeridas y cargar las cinco categorías definidas en `data-model.md`.

## Tareas relacionadas

`T-02`, `T-06`, `T-18` y `T-20`.