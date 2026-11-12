# Arquitectura — Board Games

El sistema se organiza en una interfaz de operación, una API backend, una base de datos, un sitio público estático y una herramienta de sembrado. La API Laravel centraliza las reglas de negocio.

## 1. Componentes

| Componente | Responsabilidad |
|---|---|
| `app` | Interfaz de operación desarrollada en React. Consume la API y muestra mensajes en español. |
| `api` | Backend en PHP con Laravel. Gestiona autenticación, permisos, validaciones, reglas de negocio y acceso a datos. |
| Base de datos | PostgreSQL 16. Guarda juegos, categorías, socios, usuarios, préstamos y líneas de préstamo. |
| `page` | Sitio público estático que presenta el producto y no depende de la API. |
| `tool` | Sembrador de datos de demostración que crea registros mediante la API. |
| `infra` | Configuración de contenedores para iniciar el sistema y la base de datos con un solo comando. |

## 2. Estructura de una solicitud

La interfaz React envía solicitudes HTTP JSON a las rutas definidas en `api-contract.md`. Laravel autentica al usuario, revisa su rol, valida los datos y ejecuta la operación correspondiente. Si la operación es válida, Laravel consulta o actualiza PostgreSQL y devuelve la respuesta definida por el contrato.

Los controladores reciben y responden solicitudes. La lógica de negocio se mantiene en servicios de aplicación y modelos. Las migraciones de Laravel definen y actualizan el esquema de la base de datos.

## 3. Módulos del backend

| Módulo | Funciones |
|---|---|
| Autenticación y usuarios | Inicio de sesión, gestión de operadores y control de roles. |
| Catálogo | Juegos, categorías, unidades, búsqueda, portada y baja lógica. |
| Socios | Registro, consulta y búsqueda de socios. |
| Préstamos | Creación de préstamos, líneas, vencimientos, estado y devoluciones. |
| Reportes | Consulta de juegos más solicitados en un rango de fechas. |

## 4. Flujo para registrar un préstamo

1. Un operador autenticado envía el socio y los juegos solicitados.
2. Laravel valida la cantidad, que no haya juegos repetidos, el límite del socio y sus vencimientos.
3. Laravel inicia una transacción y comprueba la disponibilidad de cada juego.
4. El sistema registra el préstamo y conserva en cada línea los datos requeridos del juego en ese momento.
5. El sistema actualiza las unidades disponibles y confirma la transacción.
6. Si otro operador ya tomó la última unidad, la operación se revierte y responde `409`.

El mecanismo concreto de control de concurrencia se define en un ADR y se refleja en `data-model.md` y en las pruebas.

## 5. Flujo para devolver un juego

Laravel comprueba que la línea del préstamo exista y no haya sido devuelta. Dentro de una transacción registra la fecha de devolución y restablece una unidad disponible. Una segunda solicitud de devolución no debe modificar el inventario y responde `409`.

## 6. Sitio público y sembrador

El sitio `page` presenta la ludoteca como producto público. No requiere que la API esté disponible para mostrar su contenido.

El sembrador `tool` utiliza las rutas de la API. No escribe directamente en la base de datos.

## 7. Ejecución local

Docker Compose coordina los contenedores requeridos por el sistema. Las instrucciones de `infra` describen el comando único de inicio y la configuración necesaria para ejecutar la aplicación.