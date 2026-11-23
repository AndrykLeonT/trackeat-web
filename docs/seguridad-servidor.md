# Configuración del servidor y demostración HTTPS

Este archivo comenta la configuración existente y separa los cambios requeridos al equipo. No instala un servidor nuevo ni afirma que la Práctica 6 completa esté resuelta.

## Entorno de presentación existente

`scripts/presentacion.ps1` inicia la aplicación, Reverb y la cola con variables propias del proceso. Usa puertos de loopback y fuerza `APP_DEBUG=false`. Con `-Tunnel` abre túneles Cloudflare para el sitio y el socket. Con `-TestDatabase` utiliza la base ficticia `trackeat_demo_test`.

```powershell
# Preparar primero la base aislada y compilar el frontend.
./scripts/verificar.ps1 -PrepareDemo
./scripts/presentacion.ps1 -TestDatabase -SkipBuild -Tunnel -WebPort 8005 -SocketPort 8085 -RunSeconds 1800
```

El túnel es temporal y su dirección puede cambiar. No fijar el dominio anterior en la aplicación. Probar el sitio usando la dirección HTTPS y repetir la solicitud con HTTP para comprobar la redirección. La autorización sigue siendo necesaria aunque el enlace sea difícil de adivinar.

`bootstrap/app.php` confía en proxies de loopback por defecto. La infraestructura Docker permite ampliar `TRUSTED_PROXIES`; un comodín solo es aceptable si la red impide llegar directamente al nodo. El proxy debe sobrescribir las cabeceras reenviadas. No copiar esa confianza a una máquina expuesta sin revisar su red.

## Variables para el entorno HTTPS que debe revisar el equipo

```dotenv
# Propuesta para el entorno HTTPS. No sobrescribir el entorno local HTTP.
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
# APP_URL y TRACKEAT_SOCKET_URL deben contener los dominios actuales de HTTPS/WSS.
# APP_KEY, contraseñas y secreto de Reverb se suministran fuera de Git.
```

`HttpOnly` evita lectura de la cookie de sesión desde JavaScript; `Secure` exige HTTPS; `SameSite` restringe envíos entre sitios. La cookie XSRF puede ser legible por JavaScript por diseño: no confundirla con la cookie de sesión al demostrar HttpOnly.

## Cabeceras y CSP pendientes

Como el mensaje proporcionado no incluye los nombres de las cinco cabeceras del PDF de la práctica, contrastar la lista exacta antes de declararla completa. Conjunto propuesto para revisar: `Strict-Transport-Security`, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` y `Permissions-Policy`. CSP se comprueba aparte. HSTS corresponde al acceso HTTPS; evitar habilitar `includeSubDomains` o preload sobre dominios que el equipo no controle.

La configuración del proxy deberá aplicar las cabeceras tanto a Laravel como a `/menu/`, `/personal/` y sus recursos estáticos. Las imágenes nuevas ya tienen `X-Content-Type-Options: nosniff`; eso no cubre todas las respuestas del sitio.

La CSP deberá permitir recursos propios y solo los orígenes de fuentes y sockets que se utilicen. Ziggy actualmente produce un script inline en `app.blade.php`: resolverlo con nonce o externalización antes de endurecer `script-src`. No usar `unsafe-inline` como prueba de bloqueo. Verificar service workers, galería, navegación y Reverb después del cambio.

## Comprobación reproducible

```powershell
# Sustituir DOMINIO por el túnel actual; no incluir credenciales.
curl.exe -I https://DOMINIO/menu/
curl.exe -I http://DOMINIO/menu/
curl.exe -I https://DOMINIO/login
```

En DevTools revisar cabeceras, cookie de sesión y consola CSP. Adjuntar una captura de un script de prueba rechazado, sin permitir su ejecución para producir la evidencia. Los errores de validación de la aplicación y los bloqueos de CSP son controles diferentes.

## Resultado observado en el túnel de prueba

El túnel de esta revisión respondió 200 por HTTPS y 301 desde HTTP hacia HTTPS. La cookie de sesión llegó con HttpOnly, Secure y SameSite=lax. No se encontraron las cinco cabeceras propuestas ni Content-Security-Policy en `/menu/` o `/login`. El JSON de evidencia conserva únicamente atributos, nunca valores de cookies o tokens. Repetir las comprobaciones al cambiar de dominio o despliegue.

## Archivos y registros

Las imágenes se almacenan fuera de `public`, se sirven como WebP recodificado y no se ejecutan como PHP. En despliegues con varios nodos hay que compartir ese almacenamiento o usar un disco común; el balanceador existente no sincroniza los archivos ni los logs. Esta entrega verifica el entorno local elegido, no la persistencia distribuida.

`security` es un archivo fuera del directorio público. Linux utiliza modo `0600`; Windows requiere permisos NTFS que limiten lectura a la cuenta del servicio y administración. La rotación y la limpieza por fecha conservan treinta días naturales al escribir. El equipo debe revisar además logs de Nginx, PHP, Cloudflare y Laravel: pueden incluir direcciones, rutas o detalles que el canal reducido excluye.

Los valores predeterminados de demostración de Docker no son secretos de producción. Cambiarlos antes de exponer el sistema, impedir acceso público a la base y Redis y comprobar APP_DEBUG en cada nodo. No subir un `.env` real para compartir la configuración.
