Este proyecto es la solución del plan de mejoramiento del aprendiz A09, ficha 3239137, dominio board-games

# Board Games

Sistema web para administrar una ludoteca de juegos de mesa. El alcance incluye el catálogo, los socios, los préstamos, las devoluciones, los juegos vencidos y el reporte de juegos más solicitados.

## Reglas principales

- Cada socio puede tener hasta cuatro juegos sin devolver.
- El plazo de préstamo es de diez días.
- Un socio con juegos vencidos no recibe nuevos préstamos hasta devolverlos.
- Las unidades disponibles deben permanecer entre cero y las unidades totales.
- Los préstamos y las devoluciones conservan su historial.
- Los juegos se identifican por EAN y pertenecen a una de las cinco categorías definidas.

## Tecnologías

- Frontend de operación con React.
- Backend y API con PHP y Laravel.
- Base de datos PostgreSQL.
- Contenedores Docker Compose.

## Estructura del proyecto

- `docs/` — especificación, arquitectura, modelo de datos, contrato de API, tareas y ADR.
- `api/` — backend Laravel.
- `app/` — interfaz de operación React.
- `page/` — sitio público estático.
- `infra/` — contenedores y configuración de ejecución.
- `tool/` — sembrador de datos de demostración mediante la API.

## Documentación

La documentación del proyecto está en [`docs/`](docs/). La implementación debe cumplir las historias, reglas, requisitos y tareas descritos allí.

## Ejecución

Las instrucciones de instalación y arranque se completarán cuando esté configurada la carpeta `infra`.