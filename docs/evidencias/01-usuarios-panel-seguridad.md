# Punto 1 · Usuarios, panel y seguridad de aplicación

Verificación del 25 de noviembre de 2026 en la rama `omar`. Se usaron PostgreSQL 16 aislado, datos de `DemoSeeder`, la compilación de Vite y Chrome controlado por Playwright.

## Implementación

- La portada enlaza al menú y al registro. El login dirige a cada rol a su pantalla. Los clientes van a `/menu/`, cocina a `/comandas`, caja a `/caja`, meseros a `/mesero/pedidos` y administradores a `/admin`. El menú estático recibe una navegación completa para cargar bien sus recursos.
- `/perfil` usa la cuenta de la sesión. El cambio de correo y de contraseña requiere la contraseña actual. Un cambio invalida el enlace de recuperación previo. Al cambiar la contraseña, Laravel renueva la sesión actual y rechaza las demás sesiones vinculadas a la contraseña anterior.
- `/admin/usuarios` permite buscar, filtrar, dar de alta, editar y desactivar clientes y personal. Los administradores pueden asignar otro administrador. El servidor protege al último activo contra bajas y cambios de rol, incluso con dos solicitudes simultáneas. La baja es lógica y conserva el historial relacionado. Las cuentas de enlaces temporales de demo quedan fuera de estas operaciones. El rechazo de acceso en navegador conserva el estado HTTP 403.
- `/admin` muestra cuatro gráficos de Chart.js y sus tablas. La ventana de 30 días incluye hoy en la zona `America/Mazatlan`. Pedidos y productos excluyen pedidos cancelados, bajas y pedidos de salas de demostración. Los productos se ordenan por unidades. Las ventas suman pagos vigentes por fecha de cobro y excluyen propinas y reembolsos. El tiempo promedio usa la fecha de recepción de la estación y la transición a listo de `historial_estados`. [Documentación de Chart.js](https://www.chartjs.org/docs/latest/getting-started/integration.html)
- La recuperación responde con un mensaje uniforme, limita solicitudes y solo emite enlaces para cuentas activas y permanentes. Laravel genera un token temporal de un uso. El enlace parte de `APP_URL` y lleva el token sin el correo. La pantalla pide el correo y entrega `Referrer-Policy: no-referrer` para evitar propagar la URL de recuperación.

## Comprobaciones de Práctica 6

| Medida | Comprobación y evidencia |
| --- | --- |
| M1 · Contraseñas derivadas | Se verificó hashing en registro, gestión y cambio de contraseña con `SecurityIntegrationReviewTest` y `UserAdministrationTest`. El modelo conserva el cast `hashed` y Laravel usa bcrypt por defecto. |
| M2 · Error genérico de login | `SecurityIntegrationReviewTest` comprueba que una dirección inexistente y una contraseña incorrecta reciben el mismo error. |
| M3 · Captcha en registro | Se conservó Turnstile. `CaptchaTest` usa respuestas controladas para comprobar token ausente, rechazado y aceptado. La sesión de navegador descrita abajo aisló el captcha para probar el flujo de cuentas. |
| M4 · Límite de intentos | `SecurityIntegrationReviewTest` y `RegistroSeguroTest` comprueban el bloqueo al sexto intento. La recuperación y el cambio de contraseña también tienen límites por ruta. |
| M5 · Cookies | PHPUnit comprobó `Secure`, `HttpOnly` y `SameSite=Lax` en la cookie de sesión con `Secure` activado. Se comprobó la activación predeterminada cuando `APP_URL` usa HTTPS. Falta observar la cookie en el Nginx de la demo cuando se habilite TLS. |
| M6 · Consultas parametrizadas | Eloquent y Query Builder enlazan filtros y valores. Las expresiones SQL de agregación son fijas y el parámetro de zona horaria está enlazado. Pasó la prueba de persistencia de metacaracteres como datos de `CatalogoSecurityTest`. |
| M7 · Validación y salida codificada | Laravel valida campos, roles y estación. En Chrome se guardó un apellido ficticio que contiene signos de marcado y se mostró literalmente como texto en el listado. |
| M8 · RBAC y control por recurso | Se comprobó el 403 para un cliente que intenta acceder a la administración, incluyendo solicitudes por ID. El perfil ignora IDs, roles y estado enviados en el formulario. Dos procesos independientes intentaron dar de baja al mismo tiempo a los únicos dos administradores, cada uno a sí mismo. Una baja se aceptó, la otra se rechazó y quedó un administrador activo. |
| M9 · Subida segura | `CatalogoSecurityTest` comprobó rechazo de archivos disfrazados, SVG y tamaños fuera del límite, y recodificación a WebP con almacenamiento privado. Las pantallas nuevas no reciben archivos. |
| M10 · Bitácora | Se comprobó la lista de campos de los eventos de autenticación, perfil y administración. Registra rol, resultado, motivo, plantilla de ruta, método, estado y UUID aleatorio de solicitud. Los eventos revisados no contienen contraseñas, correos, tokens, IDs de usuarios ni cuerpos de petición. |

**Resultado automatizado.** `php artisan test --compact` pasó 186 pruebas y 1337 aserciones. `pnpm build` compiló la aplicación, el menú y el catálogo de personal. Chrome dibujó los cuatro gráficos y completó alta, búsqueda, edición, perfil, cambio de contraseña, acceso denegado y baja de un usuario con sesión abierta. También se recibió el mensaje por SMTP en Mailpit, se abrió el enlace, se cambió la contraseña y se inició sesión con la nueva.

## Reproducción en la laptop Windows

1. Instalar dependencias con `composer install` en `backend` y `pnpm install --frozen-lockfile` en `frontend`. Compilar con `pnpm build` desde `frontend`.
2. Preparar la base local dedicada a la demostración con el flujo documentado por el equipo. El seeder da datos al panel y cuentas para cada rol. [Guía de arranque local y cuentas de ejemplo](../equipo.md)
3. Abrir `/admin` con el administrador de ejemplo. Revisar los cuatro gráficos y sus tablas. En **Usuarios**, crear un cliente de prueba, editarlo y desactivarlo con una segunda ventana abierta en su perfil. Intentar desactivar o cambiar el rol del último administrador activo y comprobar que la operación se rechaza.
4. Para verificar la suite, preparar una base PostgreSQL dedicada que termine en `_test`, configurar la conexión de pruebas y ejecutar `php artisan test --compact` desde `backend`.

### Correo real con Gmail

La opción elegida es una cuenta Gmail dedicada al equipo y habilitada para contraseñas de aplicación. El titular activa la verificación en dos pasos y genera la contraseña de aplicación. Configurar `backend/.env` localmente, conservando las credenciales fuera de Git. [SMTP de Google](https://knowledge.workspace.google.com/admin/gmail/send-email-from-a-printer-scanner-or-app?hl=es-419) · [Contraseñas de aplicación](https://support.google.com/accounts/answer/185833?hl=es)

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=<direccion-gmail-del-equipo>
MAIL_PASSWORD=<contrasena-de-aplicacion>
MAIL_FROM_ADDRESS=<misma-direccion-gmail-del-equipo>
MAIL_FROM_NAME="TrackEat"
```

Definir `APP_URL` con la dirección que el navegador de la demo puede abrir. En Compose, `URL_PUBLICA` determina ese valor para ambos nodos. Quitar una caché de configuración obsoleta del entorno de demo con `php artisan config:clear` y, si se usa Compose, recrear los nodos para cargar el entorno. Dar de alta una cuenta con un buzón receptor de prueba, solicitar el enlace en `/forgot-password` y comprobar su recepción y uso.

**Evidencia pendiente.** El envío y el recorrido del enlace se verificaron con un receptor SMTP local. Para acreditar la entrega externa falta configurar una cuenta Gmail en la laptop y verificar la recepción en un buzón real. La comprobación en Nginx de la cookie `Secure` y las medidas de servidor M11–M14 siguen el orden de aprobaciones de la entrega.

## Regresión de identidad en Menú Digital y Personal

Menú Digital y Personal consultan `/api/sesion` para obtener la identidad del usuario. El encabezado, los permisos del menú lateral y la tarjeta de cuenta usan ese mismo usuario. Las vistas estáticas usan el token CSRF de la sesión para enviar el cierre de sesión a Laravel. Al abrirlas sin conexión, el menú lateral muestra la identidad neutra y la comprueba de nuevo cuando vuelve la red.

Se verificó en Chrome con PostgreSQL aislado que el cliente conserva nombre, rol y enlaces al pasar por Menú Digital, Personal y Mi perfil, y que `/admin` devuelve 403 para ese rol. También se comprobó la navegación de administrador en ambas vistas, el funcionamiento sin conexión y que cerrar sesión desde Personal invalida la sesión del servidor. La prueba se puede repetir desde `frontend` con `TRACKEAT_TEST_URL=http://127.0.0.1:8005 node tests/static-session-browser.mjs`, usando una instancia aislada con datos de `DemoSeeder` y Turnstile desactivado solo para esa prueba.
