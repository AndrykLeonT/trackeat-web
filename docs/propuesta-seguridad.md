# Propuesta integral de seguridad de TrackEat

Aplicaciones Web II

Instituto Tecnológico de La Paz

Ingeniería en Sistemas Computacionales

Grupo 8 H

Equipo

Angélica Menchaca Rueda

Andryk Manuel León Tapia

Daniel Alexander Estrada Cosio

Carlos Omar Celis Calzada

Entrega del 25 de noviembre de 2026

Este documento explica las decisiones de seguridad de nuestro sistema de pedidos. Diferenciamos los controles implementados en catálogo y pedidos de las medidas de cuentas e infraestructura que el equipo todavía debe integrar o demostrar. La lista de verificación del repositorio contiene el estado de cada una de las catorce medidas.

<!-- page -->

## 1 Qué protegemos

TrackEat permite consultar un menú público, preparar un carrito y enviar pedidos a un restaurante. El personal consulta comandas, actualiza su preparación y registra operaciones de caja. El administrador mantiene el catálogo. Nuestra prioridad es que cada persona acceda a la información necesaria para su función y que el servidor conserve precios, estados e historial coherentes.

Clasificamos la información según el daño que causaría su exposición o modificación. El nombre, descripción, precio e imágenes de un producto publicado son información pública. Su integridad sigue siendo importante: cambiar un precio o una estación puede afectar el cobro o enviar una preparación al área equivocada. Que un dato sea público no significa que cualquiera pueda modificarlo.

Los pedidos, cantidades, notas, número de mesa e historial de consumo son información privada del cliente y de la operación. Las notas podrían contener información personal escrita libremente. Por eso no las copiamos a la bitácora de seguridad ni a los eventos generales de tiempo real. El cliente consulta sus pedidos; cocina, meseros, caja y administración conservan el acceso operativo acordado para este restaurante.

Los nombres, correos y teléfonos de las cuentas requieren protección adicional. Los hashes de contraseñas, cookies de sesión, tokens de recuperación, claves de aplicación y credenciales de conexión son datos restringidos. No deben aparecer en respuestas públicas, capturas de entrega ni registros de seguridad. Las contraseñas se verifican mediante hash; no se necesita recuperarlas como texto.

Los pagos y cortes contienen información financiera operativa. TrackEat no procesa pagos en línea ni necesita almacenar números de tarjetas o códigos de seguridad. El registro de un método de pago no justifica incorporar esos datos. Los cortes cerrados y los ítems históricos tienen protecciones de integridad existentes que conservamos en esta contribución.

También protegemos disponibilidad. Una galería excesiva puede llenar el almacenamiento y un pedido manipulado puede saturar la operación. Limitamos imágenes, cantidades y tamaños de texto. Los límites adicionales por mesa o por pedidos abiertos necesitan un acuerdo del equipo y no se presentan como implementados. La base local, los archivos privados y los respaldos requieren permisos y custodia fuera de Git.

<!-- page -->

## 2 De qué protegemos el sistema

Partimos de nuestro inventario de superficies: formularios de cuentas, API de pedidos, administración del catálogo, imágenes, canales privados, aplicaciones offline y configuración del servidor. Revisar las rutas reales evita proteger únicamente lo que se ve en una pantalla. Una solicitud directa a la API debe cumplir las mismas reglas que una acción desde el menú.

La amenaza principal de acceso es que un cliente cambie el número de un pedido y obtenga información ajena. La medida elegida es restringir la consulta por propietario antes de entregar datos. Un pedido ajeno responde 404, igual que uno inexistente. Para una función administrativa prohibida por rol usamos 403. No confiamos en la dificultad de adivinar un identificador ni en ocultar enlaces [1].

Otra amenaza es que alguien modifique el catálogo sin autorización o use texto que el navegador interprete como código. Los formularios administrativos requieren una cuenta administradora activa y validación del servidor. Los nombres, descripciones y notas se muestran como texto. Las consultas reciben parámetros enlazados; no concatenamos nombres enviados por el usuario en una sentencia SQL.

Las imágenes añaden una superficie diferente. Un archivo llamado foto.jpg podría contener código o datos que no corresponden a una fotografía. Comprobamos contenido, formato, tamaño y dimensiones, y guardamos una imagen recodificada con nombre aleatorio. Rechazamos SVG y ejecutables. Esta combinación reduce la dependencia del nombre y del tipo declarado por el navegador [2].

En pedidos consideramos el cambio de precio entre la consulta y el envío, y el reintento tras perder una respuesta. El servidor usa su precio vigente y compara el que el cliente revisó. Un cambio devuelve 409 y exige revisar el carrito. El UUID de solicitud permite recuperar un pedido ya confirmado sin crearlo dos veces.

Quedan amenazas que esta contribución no resuelve por completo: creación automatizada de cuentas sin CAPTCHA, transporte o cookies mal configurados, scripts permitidos por una CSP ausente y secretos de demostración reutilizados en un despliegue. Son pendientes explícitos de integración. La comprobación de HTTPS temporal o la aprobación de pruebas locales no demuestra por sí solo que todo el entorno esté preparado para exposición pública.

<!-- page -->

## 3 El recorrido de una nota de pedido

Elegimos la nota de un producto porque es texto libre y puede revelar preferencias o datos personales. Aunque se escriba para cocina, no debe tratarse como información pública. Su recorrido permite revisar entrada, transporte, almacenamiento, autorización y salida sin suponer que un formulario protege todo el sistema.

La persona consulta el catálogo público y agrega un producto. El navegador conserva el carrito en IndexedDB para sobrevivir a una recarga. Esto incluye la nota pendiente. Es una decisión de persistencia local: alguien con acceso al mismo perfil del navegador podría verla. El equipo debe evitar usar equipos compartidos con notas reales y considerar una opción de vaciado explícito en una ampliación futura.

Cuando hay conexión y una cuenta cliente activa, el menú envía productos, cantidades, notas, tipo de entrega, precio revisado y un UUID. No envía un propietario elegible ni un total que el servidor deba aceptar. En el entorno final este trayecto requiere HTTPS y una cookie de sesión correctamente configurada. Esa comprobación de infraestructura permanece separada de la validación funcional local.

Laravel verifica sesión y cuenta activa, valida tipos y límites y obtiene el dueño desde la sesión. El servicio consulta productos y categorías bajo bloqueos, comprueba disponibilidad y compara precios. Si falta un dato o cambió el catálogo, la operación no debe guardar un pedido parcial. Si el mismo UUID ya produjo un pedido del cliente, se devuelve el resultado existente.

Dentro de la transacción se conservan precio unitario, estación y nota de cada ítem. Cambiar después la categoría o el precio del producto no reescribe esos valores históricos. Tras confirmar la transacción, el evento de tiempo real anuncia únicamente datos mínimos del pedido; no distribuye notas, nombres ni correos a todos los suscriptores.

Al consultar el detalle, la autorización limita al cliente a su pedido. El personal permitido obtiene la información operativa y Svelte representa la nota como texto escapado. La respuesta privada no entra en la caché pública del menú. La bitácora de seguridad conserva el tipo de operación y el resultado, sin copiar la nota ni el cuerpo enviado. De este modo, cada copia adicional del dato tiene una justificación y un límite [3].

<!-- page -->

## 4 Medidas y comprobaciones

La práctica solicita catorce medidas. Nuestra lista distingue implementación y evidencia: una configuración propuesta no equivale a una prueba exitosa. Las comprobaciones de servidor utilizan PostgreSQL aislado y los recorridos de navegador emplean cuentas, fotografías y pedidos ficticios. El resultado completo se conserva en el repositorio, junto con los pasos para repetirlo.

| Medidas | Ubicación y comprobación | Estado |
| --- | --- | --- |
| 1 Hash y 2 error genérico | Registro y LoginRequest; comprobar algoritmo y mismo error ante correo inexistente o contraseña incorrecta | Verificadas para login |
| 3 CAPTCHA y 4 intentos | Sexto fallo de login limitado; falta desafío y límite específico del registro | Parciales |
| 5 Cookies | Cookie de sesión con HttpOnly, Secure y SameSite lax en el túnel de prueba | Verificada en ese entorno |
| 6 Consultas y 7 validación y salida | Eloquent, Form Requests y Svelte; valores manipulados rechazados y texto de ataque sin ejecución | Verificadas en flujos revisados |
| 8 Acceso por recurso | OrderAccess y CatalogAdmin; dos clientes, pedido propio 200, ajeno 404 y administración 403 | Verificada |
| 9 Archivos seguros | Límites, decodificación, WebP y UUID; rechazar archivo disfrazado, SVG y exceso de imágenes | Verificada |
| 10 Bitácora reducida | SecurityAudit y canal JSON privado; entradas ficticias sensibles ausentes del registro | Verificada en ese canal |
| 11 HTTPS y 12 cabeceras | Túnel HTTPS y redirección 301 comprobados; cabeceras ausentes en HTML | HTTPS verificado y cabeceras pendientes |
| 13 CSP | Política que permita lo necesario y rechace un script de prueba | Pendiente |
| 14 Depuración y secretos | Presentación sin debug; revisar variables, Docker e historial de secretos | Parcial |

El acceso se comprueba desde la API y desde la página. La carga de archivos se prueba también por solicitud directa para evitar depender del selector del navegador. En la bitácora usamos una lista cerrada de campos; no escribimos excepciones o formularios completos en el canal de seguridad. Los logs generales y del proxy requieren su propia revisión.

El menú indica cuándo terminó la descarga de imágenes. Si falta espacio, avisa que las galerías están incompletas. Una prueba sin conexión debe abrir directamente un detalle, recargar y mostrar la fotografía guardada. La confirmación de pedidos sigue bloqueada sin red. Los reportes distinguen estos controles de la disponibilidad del servidor.

<!-- page -->

## 5 Decisiones que descartamos

Decidimos no usar el número de pedido como mecanismo de autorización. Cambiarlo por un UUID podría dificultar la enumeración, pero no define quién tiene permiso. Elegimos una comprobación de propietario o rol en el servidor. El costo es mantener una regla compartida entre páginas y API y volver a probarla cuando cambie la operación del restaurante.

No implementamos asignación de comandas por empleado ni por sucursal. El modelo actual no registra ese alcance y nuestro acuerdo permite al personal operativo consultar pedidos del restaurante. Inventar una restricción incompleta habría producido una pantalla aparentemente segura con reglas difíciles de cumplir. Si el sistema crece a varias sucursales, esa decisión debe revisarse antes de compartir datos entre ellas.

No guardamos usuarios, direcciones IP, notas o cuerpos de solicitudes en la bitácora de seguridad. Registramos rol, acción, resultado y contexto técnico mínimo. El costo es menor capacidad para atribuir un evento a una cuenta concreta. Aceptamos esa limitación para este alcance y conservamos separado el historial operativo de estados. La retención de treinta días naturales reduce la acumulación, pero la rotación no sustituye una política de respaldo y eliminación.

Descartamos HTML enriquecido y SVG subido en productos. Para esta entrega, texto plano y fotografías cubren el menú sin añadir interpretación de contenido activo. Tampoco aceptamos enlaces externos para las galerías: los archivos locales permiten aplicar las mismas reglas y comprobar la consulta offline. La recodificación requiere GD y espacio de almacenamiento, por lo que documentamos esas dependencias.

No enviamos pedidos automáticamente cuando vuelve internet. El usuario debe revisar disponibilidad y precio y confirmar. El costo es un paso adicional; la ventaja es evitar pedidos sorpresa con un total que cambió mientras no había conexión. Tampoco garantizamos que una caché antigua refleje el estado del negocio: mostramos la fecha y revalidamos en el servidor.

No presentamos antivirus, autenticación multifactor, monitoreo centralizado o restauración distribuida como funciones terminadas. Son ampliaciones posibles según el uso real. Igualmente, no cambiamos silenciosamente medidas de cuentas o servidor que corresponden a otros integrantes. Se documentan como pendientes verificables. Separar lo elegido, lo implementado y lo que falta permite defender la propuesta sin atribuirle protecciones inexistentes.

<!-- page -->

## 6 Qué haríamos ante una brecha

Si observamos acceso indebido, archivos inesperados o actividad que comprometa cuentas, primero limitaríamos el daño. La persona que detecte el incidente avisaría al equipo responsable de la aplicación y de infraestructura. Se asignaría una coordinación del incidente y se registraría una cronología con hora, síntomas y acciones, sin difundir datos de clientes en un chat abierto.

La contención dependería de la superficie afectada. Ante una fuga de pedidos, se deshabilitaría temporalmente la ruta o la función vulnerable. Ante credenciales expuestas, se retirarían del entorno y se rotarían los secretos correspondientes. Si se sospecha robo de sesiones, se invalidarían las sesiones afectadas o todas si no se puede delimitar el alcance. Un túnel temporal se cerraría cuando contribuya a la exposición.

Antes de limpiar, preservaríamos la evidencia necesaria en un lugar privado: versión del código, configuración pertinente sin secretos en claro, eventos del canal de seguridad y registros autorizados de infraestructura. Conservaríamos una copia del estado afectado con acceso restringido. La bitácora mínima ayuda a ubicar tipos de rechazo, pero no permite atribuir por sí sola todas las acciones a una persona; esa limitación debe reconocerse durante el análisis.

Después determinaríamos qué información pudo consultarse o modificarse y durante cuánto tiempo. Revisaríamos pedidos, precios, roles, archivos y cortes según el incidente. No asumiríamos que la ausencia de un evento demuestra ausencia de acceso. También comprobaríamos otros nodos y copias, porque corregir el código de una máquina no actualiza automáticamente un despliegue distribuido.

La recuperación incluiría corregir la causa y agregar una prueba que reproduzca el fallo. Se desplegaría una versión identificable, se verificarían permisos y configuración y se restaurarían datos únicamente desde un respaldo confiable cuando resulte necesario. Antes de reabrir se repetirían las pruebas afectadas y se confirmaría que el arreglo no expone información mediante respuestas o logs.

La comunicación explicaría hechos confirmados, alcance conocido y acciones tomadas, distinguiendo lo que todavía se investiga. El equipo y la institución determinarían las notificaciones que correspondan con asesoría pertinente. Al terminar, documentaríamos la causa, tiempos y mejoras, actualizaríamos superficies y evidencias y practicaríamos nuevamente el procedimiento. Este apartado es un protocolo propuesto; no afirma que ya exista un simulacro de recuperación o un monitoreo continuo.

<!-- page -->

## Referencias y evidencias

[1] OWASP. Authorization Cheat Sheet. Criterios de autorización por recurso y comprobación en cada acceso. https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html

[2] OWASP. File Upload Cheat Sheet. Validación de archivos, nombres generados y almacenamiento separado. https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html

[3] OWASP. Logging Cheat Sheet. Selección de eventos y exclusión de información sensible en registros. https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

[4] Laravel. Validation y HTTP Session, documentación de la versión 12 utilizada por el proyecto. https://laravel.com/docs/12.x/validation y https://laravel.com/docs/12.x/session

Fuentes del sistema revisadas: rutas de catálogo, pedidos, autenticación y canales; modelos; servicios de pedidos y caja; migraciones; aplicaciones Svelte; configuración de sesión, logs, presentación e infraestructura Docker.

Evidencia del repositorio: docs/superficies.md, docs/seguridad-verificacion.md, docs/seguridad-servidor.md, docs/catalogo-menu.md y docs/validacion-catalogo.md. Las pruebas reproducibles están en CatalogoSecurityTest, SecurityIntegrationReviewTest y frontend/tests/catalogo-browser.mjs. Los resultados de navegador y capturas usan datos ficticios.

Alcance de la entrega: catálogo completo y medidas asignadas de pedidos, validación y bitácora. Las medidas restantes se revisan para integración y se señalan cuando requieren trabajo del equipo. Este documento describe la propuesta y la implementación local; no acredita una auditoría de producción ni la aprobación de la práctica completa.

Declaración de uso de IA: se utilizó Codex para análisis del código, implementación, pruebas y preparación de borradores. Las decisiones funcionales fueron respondidas por la responsable del módulo. Los integrantes deben revisar el documento y ensayar la demostración antes de presentarla. No se afirma una revisión humana o institucional que no se haya realizado.
