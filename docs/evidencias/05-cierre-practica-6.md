# Punto 5 · Lista de verificación de la Práctica 6

Verificación del 25 de noviembre de 2026 en Docker Desktop para macOS. El profesor elegirá dos medidas para demostrar en vivo. La tabla indica cómo mostrar cualquiera de ellas y qué evidencia se obtuvo.

## Lista de las 14 medidas

| Medida | Cómo verificarla para la demostración | Evidencia registrada |
| --- | --- | --- |
| **M1 · Contraseñas derivadas** | Ejecutar `SecurityIntegrationReviewTest` y `UserAdministrationTest` en PostgreSQL de pruebas. Ver el resultado de la comprobación de hash. | Laravel usa bcrypt por defecto. La suite verifica el hash y el cambio de contraseña. |
| **M2 · Error genérico de login** | Comparar el error de una cuenta ficticia inexistente con el de una cuenta ficticia que usa una contraseña incorrecta. | `SecurityIntegrationReviewTest` comprueba que ambos son iguales. |
| **M3 · Captcha en registro** | Abrir `/register`, intentar enviar el formulario sin completar el reto y después completarlo. Para mostrar el rechazo del servidor, ejecutar `CaptchaTest`. | Se observó la carga de Turnstile en Firefox. La suite cubre token ausente, rechazado, válido y error del proveedor. El entorno conserva la configuración de Turnstile acordada en el Punto 1. |
| **M4 · Límite de intentos** | Ejecutar `SecurityIntegrationReviewTest` y `RegistroSeguroTest`, que realizan seis intentos controlados y comprueban el bloqueo. | Laravel limita el login por correo e IP y el registro por IP. La configuración de caché es compartida entre nodos. |
| **M5 · Cookies de sesión** | Abrir `https://localhost:8443/login`. En **F12 → Red → login → Encabezados**, mostrar los atributos de la cookie de sesión y ocultar su valor. | Se verificaron `Secure`, `HttpOnly` y `SameSite=Lax` en la respuesta HTTPS real del Punto 2. |
| **M6 · Consultas parametrizadas** | Mostrar una consulta Eloquent de catálogo o los enlaces de parámetros de `DashboardController`. Ejecutar `CatalogoSecurityTest`. | PostgreSQL trata los metacaracteres del caso de prueba como datos y la búsqueda sigue funcionando. |
| **M7 · Validación y salida codificada** | En un entorno con datos ficticios, guardar texto con signos de marcado en el nombre y observarlo en Usuarios. Mostrar también el rechazo de un dato inválido. | Chrome mostró el texto literalmente. Laravel validó campos, roles, estación y reglas de pedidos. [Punto 1](01-usuarios-panel-seguridad.md) |
| **M8 · RBAC y control por objeto** | Iniciar sesión como cliente de prueba e intentar abrir `/admin`. En la suite, comparar el acceso al pedido propio con el de otro cliente de prueba. | Se observó 403 para el cliente que abre administración y 404 para el pedido ajeno. `UserAdministrationTest` protege al último administrador. |
| **M9 · Subida segura** | Como administrador de prueba, cargar una imagen válida y probar un formato rechazado. Ejecutar `CatalogoSecurityTest` para repetir los casos de archivo, tamaño y galería. | El catálogo valida contenido, tamaño y dimensiones, recodifica a WebP y guarda con UUID en almacenamiento privado. |
| **M10 · Bitácora sin datos personales** | Provocar un rechazo con una cuenta de prueba. Mostrar el registro de Laravel por plantilla y la línea de acceso de Nginx por estado. | Laravel comprobó su lista de campos permitidos. Una consulta, cookie y referencia sintéticas quedaron fuera del nuevo formato de acceso de Nginx. |
| **M11 · HTTPS con redirección** | Ejecutar `curl.exe -sSI "http://127.0.0.1:8080/login"` y abrir el destino HTTPS usando la CA del equipo. | Nginx devolvió 301. Se validaron el certificado, PostgreSQL detrás del proxy y el saludo WSS. [Punto 2](02-nginx-https.md) |
| **M12 · Cinco cabeceras** | Mostrar en F12 las cabeceras de la respuesta HTTPS a `/login`. | Se verificaron CSP, HSTS, `nosniff`, Referrer-Policy y Permissions-Policy. También se comprobaron en 204, 302, 403 y recursos estáticos. [Punto 3](03-cabeceras-nginx.md) |
| **M13 · Bloqueo por CSP** | Ejecutar `pnpm --dir frontend verify:csp --visible` en Windows con la CA instalada. Mostrar la página protegida y la salida de la terminal. | Firefox ejecutó el script externo permitido y registró `script-src-elem bloqueó inline` para el otro script. La prueba también pasó tras el despliegue del Punto 5. [Punto 4](04-prueba-csp.md) |
| **M14 · Debug desactivado y secretos locales** | Mostrar la directiva `APP_DEBUG` de Compose y comprobar `config('app.debug')` desde el contenedor. Ejecutar un escaneo local de Git con salida redactada. | Ambos nodos generaron 500 genéricos en HTML y JSON con una excepción sintética. Git excluye los `.env` y las claves TLS. La imagen tampoco contiene el `.env` ni la configuración de PostgreSQL local. Se rotaron las credenciales de infraestructura. |

La suite completa pasó 186 pruebas con 1337 aserciones. Para repetirla, usar una base PostgreSQL aislada llamada `trackeat_test` y ejecutar `php artisan test --compact` desde `backend`. También se puede filtrar por nombre de clase con `--filter`. La preparación de cuentas ficticias está en la [guía de arranque local](../equipo.md).

## Plantilla comentada de Nginx

Cada directiva y cada cierre de bloque está explicado en [la plantilla del balanceador](../../docker/infra/nginx.conf.template). Docker la convierte al iniciar en `/etc/nginx/conf.d/default.conf`. Después de cambiar la plantilla, reiniciar el balanceador y ejecutar `nginx -t` en el contenedor. El formato de acceso en ambos puertos registra hora, ID de petición, método, estado y duración. [Formato de acceso de Nginx](https://nginx.org/en/docs/http/ngx_http_log_module.html#log_format)

```powershell
docker compose --env-file docker/.env -f docker/compose.yml restart balanceador
docker compose --env-file docker/.env -f docker/compose.yml exec -T balanceador nginx -t
```

## Credenciales y arranque

Docker requiere valores privados distintos para `DB_PASSWORD`, `REDIS_PASSWORD` y `REVERB_APP_SECRET`. `docker/.env.example` enumera las variables. `backend/.env` conserva la clave de Laravel y la configuración SMTP. Usa el mismo conjunto de valores en los nodos de un despliegue y mantén los archivos fuera de Git. El modo Docker fija `APP_DEBUG=false`. [Variables de Docker Compose](https://docs.docker.com/compose/how-tos/environment-variables/variable-interpolation/) · [Modo debug de Laravel](https://laravel.com/docs/12.x/configuration#debug-mode)

En la laptop Windows, para generar `docker/.env` en una instalación nueva desde PowerShell:

```powershell
if (Test-Path docker/.env) { throw 'Ya existe docker/.env. Conserva su configuración.' }
$rng = [Security.Cryptography.RandomNumberGenerator]::Create()
try {
    $values = foreach ($name in 'DB_PASSWORD', 'REDIS_PASSWORD', 'REVERB_APP_SECRET') {
        $bytes = New-Object byte[] 24
        $rng.GetBytes($bytes)
        "$name=$([BitConverter]::ToString($bytes).Replace('-', '').ToLowerInvariant())"
    }
    $values | Set-Content -Encoding ascii docker/.env
} finally {
    $rng.Dispose()
}
```

Si PostgreSQL ya tiene datos en el volumen de Docker, respáldalo antes de migrar la configuración. Con el contenedor de PostgreSQL todavía activo, ejecuta `docker exec -it trackeat-db-docker psql -U trackeat -d trackeat -c '\password trackeat'` e introduce por el prompt el mismo valor guardado en `DB_PASSWORD`. El valor de `POSTGRES_PASSWORD` se aplica al inicializar un volumen nuevo. El cambio en una base existente lo hace `\password`.

Después, con la CA y el certificado del [Punto 2](02-nginx-https.md) preparados, inicia y verifica:

```powershell
docker compose --env-file docker/.env -f docker/compose.yml config --quiet
docker compose --env-file docker/.env -f docker/compose.yml up -d --build
docker compose --env-file docker/.env -f docker/compose.yml ps
curl.exe -fsS "https://localhost:8443/salud"
```

En el despliegue local se conservaron el volumen `trackeat-docker-db`, sus cuatro pedidos y las bitácoras de ambos nodos. Redis respondió `PONG`. `git check-ignore` confirmó la exclusión de ambos archivos `.env` y de las claves TLS. Gitleaks examinó 47 commits accesibles con `--all` y el conjunto de archivos versionables del árbol de trabajo. En ambos escaneos reportó cero coincidencias. Los valores iniciales conocidos de infraestructura dejaron de usarse después de la rotación.

Queda repetir las comprobaciones seleccionadas en la laptop Windows. La entrega de recuperación a un buzón real sigue pendiente de validación con la contraseña de aplicación vigente. La [evidencia del Punto 1](01-usuarios-panel-seguridad.md#correo-real-con-gmail) registra el recorrido verificado por SMTP local.
