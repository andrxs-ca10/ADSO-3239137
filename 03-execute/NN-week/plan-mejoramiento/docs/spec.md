# Especificación — Board Games

**Identificador del proyecto:** `board-games`

Sistema para administrar el catálogo de juegos de mesa, los socios, los préstamos y las devoluciones de una ludoteca, y consultar cuáles juegos han sido más solicitados.

Esta especificación se rige por [`constitution.md`](constitution.md). El modelo de datos se define en [`data-model.md`](data-model.md) y las rutas y respuestas HTTP en [`api-contract.md`](api-contract.md).

## 1. Actores

| Actor | Funciones | Acceso |
|---|---|---|
| Administrador (`admin`) | Administra el catálogo, los socios y los operadores. Consulta el reporte de juegos más solicitados. | Inicio de sesión |
| Operador de ludoteca (`operator`) | Registra préstamos y devoluciones. Consulta el catálogo, socios y juegos vencidos. | Inicio de sesión |
| Socio | Persona que recibe juegos prestados. No inicia sesión en el sistema. | Sin acceso |

El administrador inicial se configura durante el despliegue. El rol `admin` no se asigna mediante una solicitud de creación de usuario.

## 2. Alcance

El sistema incluye catálogo de juegos con cinco categorías fijas, socios, préstamos con varias líneas, devolución por línea, consulta de préstamos vencidos, reporte de juegos más solicitados e inicio de sesión con roles.

Las categorías sembradas son estrategia, familiares, cartas, rol e infantiles.

El alcance no incluye multas, pagos, reservas, renovaciones, notificaciones ni operación entre varias sedes. No se agregan funciones que no estén respaldadas por este plan.

## 3. Historias de usuario y criterios de aceptación

### HU-01 · Iniciar sesión

Como administrador u operador, quiero iniciar sesión para usar las funciones permitidas según mi rol.

- **CA-01.1** Con credenciales válidas, el sistema entrega un token de acceso.
- **CA-01.2** Con credenciales inválidas, responde `401` con un mensaje que no revela si falló el usuario o la contraseña.
- **CA-01.3** Todas las rutas protegidas exigen un token. Sin token responden `401` y con un rol insuficiente responden `403`.

### HU-02 · Crear operadores

Como administrador, quiero crear operadores para permitirles registrar préstamos y devoluciones.

- **CA-02.1** El administrador crea un operador con nombre de usuario, contraseña y rol permitido.
- **CA-02.2** El nombre de usuario se guarda normalizado y es único. Si ya existe, responde `409`.
- **CA-02.3** La solicitud no puede crear otro administrador. La contraseña y su hash nunca se devuelven ni se escriben en los registros de ejecución.

### HU-03 · Administrar juegos

Como administrador, quiero crear y actualizar juegos para mantener el catálogo de la ludoteca.

- **CA-03.1** Un juego registra, como mínimo, título, diseñador, EAN, categoría y unidades totales. Las unidades disponibles comienzan iguales al total.
- **CA-03.2** El EAN debe ser válido y único entre los juegos activos. Si ya está registrado, responde `409`.
- **CA-03.3** El total de unidades debe ser al menos uno. Al cambiarlo, las unidades disponibles se ajustan sin quedar por debajo de cero ni por encima del total. Una modificación inválida responde `422`.
- **CA-03.4** Si se incluye una imagen de portada, el sistema acepta JPG o PNG de hasta 2 MB y guarda una clave de almacenamiento. Si no hay imagen, el valor es `null`.
- **CA-03.5** La baja de un juego es lógica. El juego deja de aparecer en el catálogo, pero conserva su historial. No se da de baja si tiene préstamos sin devolver. En ese caso responde `409`.

### HU-04 · Buscar juegos

Como administrador u operador, quiero buscar y filtrar juegos para consultar rápidamente su información y disponibilidad.

- **CA-04.1** La búsqueda admite texto parcial en el título o el diseñador, filtro por categoría, orden por título y paginación. Solo incluye juegos activos.
- **CA-04.2** La respuesta incluye el total de resultados.
- **CA-04.3** Consultar un juego inexistente o dado de baja responde `404`.

### HU-05 · Administrar socios

Como administrador u operador, quiero registrar y buscar socios para asociarlos a los préstamos.

- **CA-05.1** Un socio registra nombre y número de documento. El documento es único. Si ya existe, responde `409`.
- **CA-05.2** La lista permite buscar por nombre o documento y admite paginación.
- **CA-05.3** Los socios no se eliminan.

### HU-06 · Registrar un préstamo

Como administrador u operador, quiero registrar un préstamo para entregar juegos a un socio y mantener el inventario actualizado.

- **CA-06.1** Cada préstamo pertenece a un socio, queda asociado al usuario que lo registró y contiene de uno a cuatro juegos.
- **CA-06.2** Un mismo juego no puede repetirse dentro del préstamo. Si se repite, responde `422`.
- **CA-06.3** Cada juego prestado descuenta una unidad disponible en la misma operación. Si no hay unidades, responde `409` y no registra parcialmente el préstamo.
- **CA-06.4** Un socio no puede tener más de cuatro juegos sin devolver, contando sus préstamos activos. Si excede el límite, responde `422` e informa el conteo actual.
- **CA-06.5** Un socio con juegos vencidos no puede recibir nuevos préstamos hasta devolverlos. El sistema responde `409`.
- **CA-06.6** Cada línea vence diez días después del préstamo. La línea conserva los datos del juego y la categoría que correspondían al momento de la operación.
- **CA-06.7** Los préstamos registrados no se editan ni se eliminan.

### HU-07 · Registrar una devolución

Como administrador u operador, quiero registrar la devolución de un juego para actualizar la disponibilidad.

- **CA-07.1** Se devuelve una línea del préstamo a la vez. La devolución registra su fecha y suma una unidad disponible.
- **CA-07.2** Devolver una línea que ya se devolvió responde `409` y no suma otra unidad.
- **CA-07.3** Las unidades disponibles nunca superan las unidades totales.
- **CA-07.4** Cuando todas las líneas del préstamo están devueltas, el préstamo aparece como cerrado.

### HU-08 · Consultar préstamos

Como administrador u operador, quiero consultar préstamos para conocer sus juegos, fechas y estado.

- **CA-08.1** El detalle incluye las líneas y el estado calculado: `open`, `overdue` o `closed`.
- **CA-08.2** La lista permite filtrar por rango de fechas, estado y socio. Se ordena por fecha descendente y admite paginación.
- **CA-08.3** El estado se calcula a partir de las fechas y devoluciones. No se almacena como un valor independiente.

### HU-09 · Consultar juegos vencidos

Como administrador u operador, quiero ver los juegos que no han sido devueltos a tiempo para gestionar su devolución.

- **CA-09.1** La lista incluye las líneas sin devolver cuya fecha de vencimiento ya pasó. Presenta el juego, el socio y los días de retraso.
- **CA-09.2** Los resultados se ordenan desde el mayor retraso y solo están disponibles para usuarios autenticados con rol permitido.

### HU-10 · Consultar los juegos más solicitados

Como administrador, quiero consultar un reporte por rango de fechas para identificar los juegos más solicitados.

- **CA-10.1** El reporte muestra una fila por juego, el nombre de su categoría conservado en la operación y la cantidad de préstamos en el rango.
- **CA-10.2** Si la fecha final es anterior a la inicial, responde `422`. Si no hubo préstamos, devuelve una lista vacía.
- **CA-10.3** El reporte no expone información personal de socios ni operadores.
- **CA-10.4** Cambiar el título o la categoría del catálogo no modifica los datos históricos del reporte.
- **CA-10.5** El reporte está disponible para el administrador.

## 4. Reglas de negocio

| Código | Regla |
|---|---|
| RN-1 | Las unidades disponibles siempre están entre cero y las unidades totales. |
| RN-2 | El plazo del préstamo es de diez días y cada socio puede tener hasta cuatro juegos sin devolver. |
| RN-3 | Un socio con juegos vencidos no recibe nuevos préstamos hasta devolverlos. |
| RN-4 | Los datos conservados en una línea de préstamo no cambian si luego se modifica el catálogo. |
| RN-5 | Los préstamos y las devoluciones no se borran. El catálogo solo permite baja lógica de juegos. |
| RN-6 | Las fechas se manejan en UTC. Los identificadores del código están en inglés y los mensajes para el usuario en español. |

## 5. Requisitos no funcionales

| Código | Requisito | Verificación |
|---|---|---|
| RNF-1 | La búsqueda responde en menos de 300 ms con un catálogo de 5.000 juegos. | Prueba de rendimiento con datos de demostración. |
| RNF-2 | Si dos operadores prestan la última unidad al mismo tiempo, uno completa la operación y el otro recibe `409`. | Prueba automatizada de concurrencia. |
| RNF-3 | Las contraseñas no se guardan en texto claro ni aparecen en respuestas o registros. | Revisión de almacenamiento, respuestas y registros. |
| RNF-4 | Todos los errores HTTP usan una estructura de respuesta uniforme. | Pruebas del contrato de la API. |
| RNF-5 | Los contenedores y la base de datos arrancan con un solo comando. | Prueba de arranque según las instrucciones de `infra`. |

## 6. Entregables verificables

El proyecto incluye frontend en React, backend en Laravel, sitio público estático, contenedores con base de datos, sembrador de datos que usa la API y pruebas automatizadas.

El proyecto debe contener al menos 15 archivos de pruebas automatizadas y cubrir las 10 historias, las 6 reglas de negocio, los 5 requisitos no funcionales, los endpoints equivalentes del contrato y las 23 tareas del plan.