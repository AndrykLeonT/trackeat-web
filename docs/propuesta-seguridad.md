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

Este documento explica las decisiones de seguridad de nuestro sistema de pedidos. Cubre los controles de catálogo, pedidos, caja y cuentas, y las medidas de servidor que se aplican en el despliegue con Docker y Nginx. La lista de verificación del repositorio contiene el estado de cada una de las catorce medidas: trece cumplen y una es parcial.

<!-- page -->

## 1 Qué protegemos

TrackEat permite consultar un menú público, preparar un carrito y enviar pedidos a un restaurante. El personal consulta comandas, actualiza su preparación y registra operaciones de caja. El administrador mantiene el catálogo. Nuestra prioridad es que cada persona acceda a la información necesaria para su función y que el servidor conserve precios, estados e historial coherentes.

Clasificamos la información según el daño que causaría su exposición o modificación. El nombre, descripción, precio e imágenes de un producto publicado son información pública. Su integridad sigue siendo importante: cambiar un precio o una estación puede afectar el cobro o enviar una preparación al área equivocada. Que un dato sea público no significa que cualquiera pueda modificarlo.

Los pedidos, cantidades, notas, número de mesa e historial de consumo son información privada del cliente y de la operación. Las notas podrían contener información personal escrita libremente. Por eso no las copiamos a la bitácora de seguridad ni a los eventos generales de tiempo real. El cliente consulta sus pedidos; cocina, meseros, caja y administración conservan el acceso operativo acordado para este restaurante.

Los nombres, correos y teléfonos de las cuentas requieren protección adicional. Los hashes de contraseñas, cookies de sesión, tokens de recuperación, claves de aplicación, llaves del certificado y credenciales de conexión son datos restringidos. No deben aparecer en respuestas públicas, capturas de entrega, registros de seguridad ni en Git. Las contraseñas se guardan con bcrypt; no se necesita recuperarlas como texto.

Los pagos y cortes contienen información financiera operativa. TrackEat no procesa pagos en línea ni necesita almacenar números de tarjetas o códigos de seguridad. El registro de un método de pago no justifica incorporar esos datos. Los cortes cerrados y los ítems históricos tienen protecciones de integridad que conservamos.

También protegemos disponibilidad. Una galería excesiva puede llenar el almacenamiento y un pedido manipulado puede saturar la operación. Limitamos imágenes, tamaños de texto y reglas de negocio en servidor: mesas válidas del 1 al 30, hasta 50 renglones y 100 piezas por renglón, tope de $5,000 MXN por pedido web ($20,000 MXN para personal) y máximo 3 pedidos abiertos por cliente. Los intentos de inicio de sesión y de registro también están limitados. La base local, los archivos privados y los respaldos requieren permisos y custodia fuera de Git.

<!-- page -->

## 2 De qué protegemos el sistema

Partimos de nuestro inventario de superficies: formularios de cuentas, API de pedidos, administración del catálogo, imágenes, canales privados, aplicaciones offline y configuración del servidor. Revisar las rutas reales evita proteger únicamente lo que se ve en una pantalla. Una solicitud directa a la API debe cumplir las mismas reglas que una acción desde el menú.

La amenaza principal de acceso es que un cliente cambie el número de un pedido y obtenga información ajena, o que alguien avance una comanda que no le corresponde. Restringimos la consulta por propietario antes de entregar datos: un pedido ajeno responde 404, igual que uno inexistente. Para funciones administrativas o de caja prohibidas por rol usamos 403, y al avanzar comandas PedidoPolicy valida el rol, la estación del cocinero (cocina, barra o general) y la sala de demostración. No confiamos en la dificultad de adivinar un identificador ni en ocultar enlaces [1].

Otra amenaza es que alguien modifique el catálogo sin autorización o use texto que el navegador interprete como código. Los formularios administrativos requieren una cuenta administradora activa y validación del servidor. Los nombres, descripciones y notas se muestran como texto. Las consultas reciben parámetros enlazados; no concatenamos nombres enviados por el usuario en una sentencia SQL.

Las imágenes añaden una superficie diferente. Un archivo llamado foto.jpg podría contener código o datos que no corresponden a una fotografía. Comprobamos contenido, formato, tamaño y dimensiones, y guardamos una imagen recodificada con nombre aleatorio. Rechazamos SVG y ejecutables. Esta combinación reduce la dependencia del nombre y del tipo declarado por el navegador [2].

En pedidos consideramos el cambio de precio entre la consulta y el envío, y el reintento tras perder una respuesta. El servidor usa su precio vigente y compara el que el cliente revisó. Un cambio devuelve 409 y exige revisar el carrito. El UUID de solicitud permite recuperar un pedido ya confirmado sin crearlo dos veces.

Contra el abuso de cuentas usamos Cloudflare Turnstile, un campo trampa oculto y límite de intentos en login y registro. Contra la intercepción y la inyección de scripts, Nginx redirige HTTP a HTTPS con 301, solo acepta TLS 1.2 y 1.3 y envía cinco cabeceras: CSP, HSTS, X-Content-Type-Options, Referrer-Policy y Permissions-Policy [4]. La CSP no permite scripts en línea, así que un script inyectado no se ejecuta [5]. Para exponer el sistema en público faltan llaves reales de Turnstile, un certificado emitido por una autoridad reconocida y el entorno de producción en Docker.

<!-- page -->

## 3 El recorrido de una nota de pedido

Elegimos la nota de un producto porque es texto libre y puede revelar preferencias o datos personales. Aunque se escriba para cocina, no debe tratarse como información pública. Su recorrido permite revisar entrada, transporte, almacenamiento, autorización y salida sin suponer que un formulario protege todo el sistema.

La persona consulta el catálogo público y agrega un producto. El navegador conserva el carrito en IndexedDB para sobrevivir a una recarga. Esto incluye la nota pendiente. Es una decisión de persistencia local: alguien con acceso al mismo perfil del navegador podría verla. Por eso el panel del carrito tiene un botón para vaciarlo, y recomendamos no usar equipos compartidos con notas reales.

Cuando hay conexión y una cuenta cliente activa, el menú envía productos, cantidades, notas, tipo de entrega, precio revisado y un UUID. No envía un propietario elegible ni un total que el servidor deba aceptar. El trayecto viaja por HTTPS a través de Nginx, y la cookie de sesión lleva Secure, HttpOnly y SameSite=Lax, de modo que no se envía por HTTP ni la lee JavaScript.

Laravel verifica sesión y cuenta activa, valida tipos y límites de negocio (mesa, renglones, cantidad, tope de total y pedidos abiertos) y obtiene el dueño desde la sesión. El servicio consulta productos y categorías bajo bloqueos, comprueba disponibilidad, compara precios y asigna un folio diario atómico. Si falta un dato o cambió el catálogo, la operación no guarda un pedido parcial. Si el mismo UUID ya produjo un pedido del cliente, se devuelve el resultado existente.

Dentro de la transacción se conservan precio unitario, estación y nota de cada ítem. Cambiar después la categoría o el precio del producto no reescribe esos valores históricos. Tras confirmar la transacción, el evento de tiempo real anuncia únicamente el identificador, el folio y el estado del pedido; no distribuye notas, nombres ni correos a los suscriptores.

Al consultar el detalle, la autorización limita al cliente a su pedido. El personal permitido obtiene la información operativa y Svelte representa la nota como texto escapado. La respuesta privada no entra en la caché pública del menú. La bitácora de seguridad conserva el tipo de operación y el resultado, sin copiar la nota ni el cuerpo enviado. De este modo, cada copia adicional del dato tiene una justificación y un límite [3].

<!-- page -->

## 4 Medidas y comprobaciones

La práctica solicita catorce medidas. Nuestra lista distingue implementación y evidencia: una configuración propuesta no equivale a una prueba exitosa. Las comprobaciones se hicieron el 25 de noviembre contra TrackEat en Docker (https://localhost:8443), con cuentas, fotografías y pedidos ficticios. El resultado completo y los pasos para repetirlo están en el repositorio.

| Medidas | Ubicación y comprobación | Estado |
| --- | --- | --- |
| 1 Hash y 2 error genérico | User con cast hashed y bcrypt de 12 rondas; LoginRequest y registro responden igual ante correo inexistente, contraseña incorrecta o correo repetido | Verificadas |
| 3 CAPTCHA y 4 intentos | Turnstile y campo trampa; 5 fallos por correo e IP en login y 5 por minuto por IP en registro (CaptchaTest y RegistroSeguroTest) | Verificadas (llaves de prueba) |
| 5 Cookies | Cookie de sesión con Secure, HttpOnly y SameSite=Lax en el despliegue HTTPS | Verificada |
| 6 Consultas y 7 validación y salida | Eloquent, Form Requests, límites de PedidoService y Svelte; inyección SQL sin efecto y HTML mostrado como texto | Verificadas |
| 8 Acceso por recurso | RequireRole, OrderAccess, CatalogAdmin y PedidoPolicy; pedido ajeno 404, rol no permitido 403 y estación de cocinero | Verificada |
| 9 Archivos seguros | Límites, decodificación, WebP y UUID; rechazar archivo disfrazado, SVG y exceso de imágenes | Verificada |
| 10 Bitácora reducida | SecurityAudit en auth, catálogo, pedidos, tablero, caja y purga; sin correos, IP ni tokens en el canal JSON | Verificada |
| 11 HTTPS y 12 cabeceras | nginx.conf.template: redirección 301, TLS 1.3 y las cinco cabeceras en HTML y errores | Verificadas |
| 13 CSP | Sin 'unsafe-inline'; un script inyectado queda bloqueado (csp-browser.mjs) | Verificada |
| 14 Depuración y secretos | APP_DEBUG=false en los nodos; .env y llaves .pem fuera de Git; falta APP_ENV=production | Parcial |

El acceso se comprueba desde la API y desde la página. La carga de archivos se prueba también por solicitud directa para no depender del selector del navegador. En la bitácora usamos una lista cerrada de campos; no escribimos excepciones ni formularios completos en el canal de seguridad. Los logs generales y del proxy requieren su propia revisión.

Las pruebas de seguridad del backend pasan. La suite completa da 201 pruebas correctas y una fallida del listado administrativo de pedidos, que no está relacionada con estas medidas. Sin conexión, el menú abre un detalle guardado con su fotografía, pero la confirmación de pedidos sigue bloqueada hasta que vuelve la red.

<!-- page -->

## 5 Decisiones que descartamos

Decidimos no usar el número de pedido como mecanismo de autorización. Cambiarlo por un UUID podría dificultar la enumeración, pero no define quién tiene permiso. Elegimos una comprobación de propietario o rol en el servidor. El costo es mantener una regla compartida entre páginas y API y volver a probarla cuando cambie la operación del restaurante.

Separamos el avance de comandas por estación (cocina, barra o general) y sala de demostración mediante PedidoPolicy, pero descartamos asignar pedidos a un empleado individual o dividir el sistema en varias sucursales. El modelo actual atiende un solo restaurante y permite al personal operativo consultar los pedidos necesarios. Si el sistema crece a varias sucursales, esa decisión deberá revisarse antes de compartir datos entre ellas.

No guardamos usuarios, direcciones IP, notas o cuerpos de solicitudes en la bitácora de seguridad. Registramos rol, acción, resultado y contexto técnico mínimo. El costo es menor capacidad para atribuir un evento a una cuenta concreta. Aceptamos esa limitación para este alcance y conservamos separado el historial operativo de estados. La retención de treinta días reduce la acumulación, pero la rotación no sustituye una política de respaldo y eliminación.

Descartamos HTML enriquecido y SVG subido en productos, y también 'unsafe-inline' en la CSP. Texto plano, fotografías y scripts servidos desde el propio origen cubren el menú sin interpretar contenido activo. El costo es que ningún componente puede usar scripts ni estilos en línea, y que Turnstile y las fuentes externas deben declararse uno por uno. Tampoco aceptamos enlaces externos para las galerías: los archivos locales permiten aplicar las mismas reglas y consultarlos sin conexión.

Dejamos HSTS con una vigencia de cinco minutos. Con una vigencia larga, un navegador que visitó la demostración con un certificado local seguiría forzando HTTPS en localhost durante meses. En un dominio público con certificado reconocido la vigencia debe subir a un año.

No enviamos pedidos automáticamente cuando vuelve internet. El usuario debe revisar disponibilidad y precio y confirmar. El costo es un paso adicional; la ventaja es evitar pedidos sorpresa con un total que cambió mientras no había conexión.

No presentamos antivirus, autenticación multifactor, monitoreo centralizado o restauración distribuida como funciones terminadas. Son ampliaciones posibles según el uso real. Separar lo elegido, lo implementado y lo que falta permite defender la propuesta sin atribuirle protecciones inexistentes.

<!-- page -->

## 6 Qué haríamos ante una brecha

Si observamos acceso indebido, archivos inesperados o actividad que comprometa cuentas, primero limitaríamos el daño. La persona que detecte el incidente avisaría al equipo responsable de la aplicación y de infraestructura. Se asignaría una coordinación del incidente y se registraría una cronología con hora, síntomas y acciones, sin difundir datos de clientes en un chat abierto.

La contención dependería de la superficie afectada. Ante una fuga de pedidos, se deshabilitaría temporalmente la ruta o la función vulnerable. Ante credenciales expuestas, se retirarían del entorno y se rotarían los secretos correspondientes, incluida la llave del certificado si se filtró. Si se sospecha robo de sesiones, se invalidarían las sesiones afectadas o todas si no se puede delimitar el alcance. Un túnel temporal se cerraría cuando contribuya a la exposición.

Antes de limpiar, preservaríamos la evidencia necesaria en un lugar privado: versión del código, configuración pertinente sin secretos en claro, eventos del canal de seguridad y registros autorizados de infraestructura. Conservaríamos una copia del estado afectado con acceso restringido. La bitácora mínima ayuda a ubicar tipos de rechazo, pero no permite atribuir por sí sola todas las acciones a una persona; esa limitación debe reconocerse durante el análisis.

Después determinaríamos qué información pudo consultarse o modificarse y durante cuánto tiempo. Revisaríamos pedidos, precios, roles, archivos y cortes según el incidente. No asumiríamos que la ausencia de un evento demuestra ausencia de acceso. También comprobaríamos los demás nodos y copias, porque corregir el código de una máquina no actualiza automáticamente un despliegue distribuido.

La recuperación incluiría corregir la causa y agregar una prueba que reproduzca el fallo. Se desplegaría una versión identificable, se verificarían permisos y configuración y se restaurarían datos únicamente desde un respaldo confiable cuando resulte necesario. Antes de reabrir se repetirían las pruebas afectadas y se confirmaría que el arreglo no expone información mediante respuestas o logs.

La comunicación explicaría hechos confirmados, alcance conocido y acciones tomadas, distinguiendo lo que todavía se investiga. El equipo y la institución determinarían las notificaciones que correspondan con asesoría pertinente. Al terminar, documentaríamos la causa, tiempos y mejoras, actualizaríamos superficies y evidencias y practicaríamos nuevamente el procedimiento. Este apartado es un protocolo propuesto; no afirma que ya exista un simulacro de recuperación o un monitoreo continuo.

<!-- page -->

## Referencias y evidencias

[1] OWASP. Authorization Cheat Sheet. Criterios de autorización por recurso y comprobación en cada acceso. https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html

[2] OWASP. File Upload Cheat Sheet. Validación de archivos, nombres generados y almacenamiento separado. https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html

[3] OWASP. Logging Cheat Sheet. Selección de eventos y exclusión de información sensible en registros. https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

[4] OWASP. HTTP Headers Cheat Sheet. Cabeceras de respuesta recomendadas y sus valores. https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Headers_Cheat_Sheet.html

[5] OWASP. Content Security Policy Cheat Sheet. Directivas de la CSP y por qué evitar 'unsafe-inline'. https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html

[6] Laravel. Validation y HTTP Session, documentación de la versión 12 utilizada por el proyecto. https://laravel.com/docs/12.x/validation y https://laravel.com/docs/12.x/session

Fuentes del sistema revisadas: rutas de catálogo, pedidos, caja, autenticación y canales; modelos; servicios de pedidos, catálogo y caja; middleware y políticas; aplicaciones Svelte; configuración de sesión y logs; docker/infra/nginx.conf.template y docker/nodo/compose.yml.

Evidencia del repositorio: docs/superficies.md, docs/evidencias/lista-verificacion-practica-6.md y docs/evidencias/Evidencias-Practica-6.pdf. Las pruebas reproducibles están en CatalogoSecurityTest, SecurityIntegrationReviewTest, CaptchaTest, RegistroSeguroTest, PedidosModuloTest, PedidosConcurrencyTest, CajaModuloTest, NavegacionPorRolTest, frontend/tests/catalogo-browser.mjs y frontend/tests/csp-browser.mjs. Los resultados de navegador y capturas usan datos ficticios.

Alcance de la entrega: las catorce medidas integradas con los módulos del equipo; trece verificadas y una parcial (entorno de producción). Este documento describe la propuesta y la implementación en el despliegue local con Docker; no acredita una auditoría de producción.

Declaración de uso de IA: se utilizó Codex en la iteración inicial para análisis del código, implementación, pruebas y borradores; Antigravity para auditar el estado tras los merges del equipo, ampliar la bitácora, corregir pruebas y sincronizar la documentación; y Claude Code el 25 de noviembre para contrastar el documento con el código final y actualizar el estado de las medidas de servidor. Las decisiones funcionales fueron respondidas por la responsable del módulo. Los integrantes deben revisar el documento y ensayar la demostración antes de presentarla.
