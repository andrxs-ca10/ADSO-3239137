# Tareas — Board Games

Cada tarea se completa cuando existe evidencia verificable. El estado inicial es **pendiente**.

| ID | Tarea | Depende de | Evidencia de finalización |
|---|---|---|---|
| T-01 | Crear la estructura inicial de Laravel y React, e implementar `GET /health`. | — | La ruta responde `200` con `{"status":"ok"}`. |
| T-02 | Crear las migraciones para usuarios, categorías, juegos, socios, préstamos y líneas de préstamo. | T-01 | Las migraciones crean las tablas, relaciones e índices definidos en `data-model.md`. |
| T-03 | Implementar las reglas del juego, el EAN, las unidades y la baja lógica. | T-02 | Pruebas verifican EAN válido, EAN único y unidades dentro del rango permitido. |
| T-04 | Implementar préstamo, líneas, vencimiento a diez días, límite de cuatro juegos y estado calculado. | T-03 | Pruebas cubren `open`, `overdue`, `closed` y el límite por socio. |
| T-05 | Implementar socios y usuarios con validación de campos y unicidad. | T-02 | No se crean socios con documentos duplicados ni usuarios con nombres repetidos. |
| T-06 | Sembrar las cinco categorías iniciales. | T-02 | La base contiene estrategia, familiares, cartas, rol e infantiles. |
| T-07 | Implementar inicio de sesión y creación de operadores con control de roles. | T-05 | El administrador puede crear operadores y las credenciales inválidas responden `401`. |
| T-08 | Implementar creación, edición, búsqueda, consulta y baja lógica de juegos. | T-03, T-06 | Se verifican filtros, paginación, unicidad del EAN y juegos dados de baja. |
| T-09 | Implementar el registro de préstamos mediante la API. | T-04, T-05, T-08 | Se validan unidades disponibles, límite de cuatro, vencidos y ausencia de juegos repetidos. |
| T-10 | Implementar devoluciones y consulta de juegos vencidos. | T-09 | Una devolución aumenta una sola unidad. Una devolución repetida responde `409`. |
| T-11 | Implementar el reporte de juegos más solicitados por rango de fechas. | T-09 | El reporte ordena resultados y conserva los datos históricos de cada operación. |
| T-12 | Implementar la persistencia y las consultas para juegos, categorías, socios y usuarios. | T-02, T-05, T-06, T-08 | Pruebas de integración verifican consultas y restricciones en PostgreSQL. |
| T-13 | Implementar la persistencia transaccional de préstamos, líneas y devoluciones. | T-04, T-09, T-10, T-12 | Dos operadores intentan prestar la última unidad. Uno obtiene éxito y el otro recibe `409`. |
| T-14 | Proteger contraseñas y emitir y validar tokens de acceso. | T-07 | Contraseñas como hash, token válido y ninguna contraseña en respuestas o logs. |
| T-15 | Implementar carga, consulta y eliminación de portadas. | T-08 | Se validan el formato JPG o PNG y el límite de 2 MB. |
| T-16 | Completar las rutas de la API, validaciones, permisos y errores del contrato. | T-07, T-09, T-10, T-11, T-14, T-15 | Pruebas de API cubren rutas, roles, códigos HTTP y estructura uniforme de errores. |
| T-17 | Configurar el administrador inicial y el arranque de la aplicación desde variables de entorno. | T-14, T-16 | Se puede iniciar sesión con el administrador configurado sin subir secretos al repositorio. |
| T-18 | Aplicar en la base las restricciones que correspondan a sus capacidades. | T-12, T-13 | Pruebas verifican claves foráneas, unicidad y rango de unidades disponibles. |
| T-19 | Completar las pruebas automatizadas del proyecto. | T-03 a T-18 | Hay al menos 15 archivos de pruebas automatizadas que cubren historias, reglas y requisitos no funcionales. |
| T-20 | Configurar los contenedores para Laravel, React y PostgreSQL. | T-01, T-17 | Un solo comando levanta los servicios y la base de datos. |
| T-21 | Crear el sembrador de demostración que carga información mediante la API. | T-16, T-20 | Carga al menos 40 juegos, 10 socios y 15 préstamos, incluidos 3 vencidos, sin escribir directamente en la base. |
| T-22 | Implementar en React las pantallas de inicio de sesión, catálogo, socios, préstamos, devoluciones, vencidos y reporte. | T-16, T-20 | Se puede completar el recorrido de operación con datos de demostración. |
| T-23 | Crear el sitio público estático de presentación del producto. | — | La página presenta la ludoteca en escritorio y móvil, y no depende de la API. |

## Definición de terminado

Una tarea se considera terminada cuando su evidencia está disponible y puede verificarse. Las pruebas automatizadas se mantienen en el proyecto y se pueden ejecutar según las instrucciones del repositorio.