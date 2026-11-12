# Plan técnico y de ejecución — Board Games

Este documento describe las decisiones técnicas y el orden de construcción del sistema `board-games`. Las reglas funcionales están en [`spec.md`](spec.md), el modelo de datos en [`data-model.md`](data-model.md) y la arquitectura en [`architecture.md`](architecture.md).

## 1. Decisiones técnicas

| Decisión | Elección | Motivo |
|---|---|---|
| Interfaz de operación | React | Tecnología requerida para el frontend. |
| Backend | PHP con Laravel | Tecnología requerida para la API y las reglas de negocio. |
| Base de datos | PostgreSQL 16 | Motor utilizado en la especificación de referencia. |
| Contrato HTTP | API REST con JSON bajo `/api/v1` | Permite que React y el sembrador consuman las mismas operaciones. |
| Autenticación | Token JWT con vigencia de ocho horas | Mantiene la equivalencia con el contrato de referencia. |
| Persistencia | Migraciones de Laravel | Mantienen el esquema versionado junto al backend. |
| Fechas | Instantes en UTC | Evita diferencias horarias en préstamos y vencimientos. |
| Concurrencia | La operación de préstamo debe proteger la disponibilidad dentro de una transacción | Evita entregar dos veces la última unidad. El conflicto responde `409`. |
| Datos de demostración | El sembrador usa la API | Mantiene las validaciones y reglas del sistema. |
| Contenedores | Docker Compose para levantar la aplicación y la base con un solo comando | Cumple el requisito de ejecución reproducible. |
| Sitio público | Página estática que presenta la ludoteca | Presenta el producto sin depender de la API. |

## 2. Configuración

La configuración local se mantiene en variables de entorno. El repositorio puede incluir `.env.example` con nombres de variables y valores de ejemplo sin secretos reales.

No se suben archivos `.env` ni carpetas `node_modules`.

## 3. Estrategia de pruebas

| Tipo de prueba | Qué comprueba |
|---|---|
| Reglas del backend | Límites de préstamo, vencimientos, devoluciones y disponibilidad. |
| API | Rutas, permisos, validaciones, respuestas y errores definidos en `api-contract.md`. |
| Persistencia | Migraciones, restricciones y relaciones con PostgreSQL. |
| Concurrencia | Dos operadores intentan prestar la última unidad. Uno obtiene éxito y el otro recibe `409`. |
| Interfaz | Formularios, mensajes en español y recorridos de préstamo y devolución. |
| Proyecto completo | Al menos 15 archivos de pruebas automatizadas en el conjunto del proyecto. |

## 4. Orden de ejecución

1. Completar y revisar los documentos del directorio `docs`.
2. Definir el modelo y crear las migraciones de la base de datos.
3. Implementar autenticación, catálogo, socios, préstamos, devoluciones, vencidos y reporte en Laravel.
4. Implementar en React las pantallas que corresponden a esos casos de uso.
5. Construir la página pública estática.
6. Configurar los contenedores y verificar el arranque con un solo comando.
7. Crear el sembrador y comprobar que cargue información mediante la API.
8. Ejecutar las pruebas y revisar la trazabilidad con las historias, reglas, requisitos y tareas.