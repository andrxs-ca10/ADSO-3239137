# Constitución del proyecto Board Games

**Identificador del proyecto:** `board-games`

Esta constitución establece los principios que deben cumplir la documentación y el producto. Las reglas de negocio detalladas se especifican en `spec.md`.

## Artículo 1. El alcance se basa en la especificación

El sistema implementa las historias, reglas, requisitos, endpoints y tareas definidos para este plan. Toda funcionalidad debe tener respaldo en la especificación.

**Verificación:** cada funcionalidad se puede relacionar con un requisito o una tarea.

## Artículo 2. Se respeta el dominio de la ludoteca

El catálogo administra juegos identificados por EAN y utiliza las cinco categorías definidas para el proyecto: estrategia, familiares, cartas, rol e infantiles.

**Verificación:** la especificación y el sembrador presentan esas categorías y usan EAN para identificar juegos.

## Artículo 3. El idioma del código y de la interfaz es consistente

Los identificadores del código, las rutas y los nombres de campos se escriben en inglés. Los mensajes, etiquetas e instrucciones que ve el usuario se presentan en español.

**Verificación:** la revisión del código y de la interfaz confirma ambas convenciones.

## Artículo 4. Las reglas de negocio se validan en el backend

El backend aplica las reglas de préstamos, devoluciones, disponibilidad y bloqueo de socios. La interfaz puede ayudar a prevenir errores, pero no es la única que aplica estas reglas.

**Verificación:** las pruebas del backend comprueban las reglas aunque la solicitud no provenga de la interfaz.

## Artículo 5. El inventario debe permanecer consistente

Las operaciones de préstamo actualizan la disponibilidad de forma segura. Si dos operadores intentan prestar la última unidad, solo uno completa la operación y el otro recibe una respuesta HTTP `409`.

**Verificación:** una prueba automatizada de concurrencia demuestra ambos resultados.

## Artículo 6. Se conserva el historial de operaciones

Los préstamos y las devoluciones no se borran. El catálogo permite dar de baja los juegos de forma lógica. Los datos históricos de una operación conservan los valores que correspondían al momento en que ocurrió.

**Verificación:** las pruebas confirman que una baja del catálogo no elimina operaciones ni altera sus datos históricos.

## Artículo 7. Las fechas se manejan en UTC

Las fechas almacenadas y transmitidas por el sistema usan UTC. Los mensajes dirigidos al usuario están en español.

**Verificación:** la especificación de la API define el formato y las pruebas revisan las fechas devueltas.

## Artículo 8. Los secretos y dependencias generadas no se suben

No se versionan archivos `.env` ni carpetas `node_modules`. Las credenciales reales se mantienen fuera del repositorio.

**Verificación:** la revisión del repositorio confirma que no contiene esos elementos.

## Artículo 9. El trabajo se demuestra con evidencia

Las tareas se consideran terminadas cuando existe evidencia verificable. El proyecto incluye al menos 15 archivos de pruebas automatizadas, según el plan de mejoramiento.

**Verificación:** las pruebas están versionadas y se pueden ejecutar.

## Artículo 10. No se agregan funciones fuera del alcance

Las funciones nuevas deben estar justificadas por la especificación del plan de mejoramiento.

**Verificación:** cada función implementada tiene correspondencia con un requisito o tarea.