# Punto 2 · HTTPS y redirección 301 en Nginx

Verificación del 25 de noviembre de 2026 en la rama `omar`. El despliegue de la demo en una sola laptop publica HTTP en `127.0.0.1:8080` y HTTPS en `127.0.0.1:8443`. La URL de entrada es `https://localhost:8443`.

## Configuración

- Nginx responde con HTTP 301 a las peticiones del puerto 8080. El destino usa el origen configurado en `URL_PUBLICA` y agrega la ruta y la consulta originales.
- En el puerto 8443, Nginx presenta un certificado firmado por una CA local de mkcert y admite TLS 1.2 y TLS 1.3. Docker monta el certificado y la clave del servidor en modo de solo lectura. La clave de la CA se queda fuera del directorio montado. Los archivos PEM están excluidos de Git.
- Nginx termina TLS y comunica el esquema original a Laravel mediante `X-Forwarded-Proto`. El tráfico entre Nginx, Laravel y Reverb va por la red interna de Docker. Los nodos conocen el origen HTTPS para generar enlaces, emitir la cookie de sesión segura y anunciar el endpoint de WebSockets.

[Configuración HTTPS de Nginx](https://nginx.org/en/docs/http/configuring_https_servers.html) · [mkcert](https://github.com/FiloSottile/mkcert#readme)

## Reproducir en la laptop Windows

1. Instalar mkcert por uno de sus métodos oficiales para Windows. Si se usa el ejecutable publicado por el proyecto, colocarlo en el `PATH` como `mkcert.exe`. [Instalación en Windows](https://github.com/FiloSottile/mkcert#windows) · [Ejecutables oficiales](https://github.com/FiloSottile/mkcert/releases)
2. Desde la raíz del repositorio, ejecutar en PowerShell:

   ```powershell
   New-Item -ItemType Directory -Force docker/infra/tls | Out-Null
   mkcert -install
   mkcert -cert-file docker/infra/tls/server.pem -key-file docker/infra/tls/server-key.pem localhost 127.0.0.1 ::1
   ```

   `mkcert -install` agrega su propia CA al almacén de confianza de Windows. Ejecutarlo en la laptop que abrirá TrackEat y reiniciar Edge o Chrome si ya estaba abierto. La raíz pública se puede localizar con `mkcert -CAROOT`. La clave `rootCA-key.pem` debe permanecer en el equipo donde se generó. [Almacenes de confianza de mkcert](https://github.com/FiloSottile/mkcert#supported-root-stores)
3. Con `backend/.env`, las [credenciales de Docker](05-cierre-practica-6.md#credenciales-y-arranque) y las dependencias del frontend preparados, ejecutar:

   ```powershell
   pnpm --dir frontend build
   docker compose -f docker/compose.yml up -d --build
   docker compose -f docker/compose.yml exec -T balanceador nginx -t
   curl.exe -sSI "http://localhost:8080/menu/?categoria=bebidas"
   curl.exe -fsS "https://localhost:8443/salud"
   ```

   En una instalación local con una base nueva, ejecutar una vez `docker compose -f docker/compose.yml exec -T nodo1 php artisan migrate --seed --force` para cargar los datos de la demo. El primer `curl.exe` debe mostrar `301` con `Location: https://localhost:8443/menu/?categoria=bebidas`. El segundo debe responder con `"bd":"ok"`. Para mostrar la demo, escribir la URL HTTP en el navegador y comprobar que abre el sitio HTTPS con el certificado válido.

Si ya existe una configuración de varias PCs, usar la misma `URL_PUBLICA=https://nombre-o-ip:8443` para el balanceador y todos los nodos, con ese nombre o IP en el certificado. Si cambia el puerto publicado, ajustar también `HTTPS_PUERTO_PUBLICADO`. Las opciones están en `docker/.env.example`.

## Evidencia ejecutada

| Verificación | Resultado observado en Docker Desktop para macOS |
| --- | --- |
| Sintaxis y carga de claves | `nginx -t` pasó en el contenedor activo. OpenSSL verificó el certificado para `localhost` contra su CA. |
| Redirección 301 | `http://127.0.0.1:8080/menu/?categoria=bebidas&buscar=agua%20fria` devolvió `301` hacia el mismo recurso en `https://localhost:8443`. |
| Certificado y rutas | Clientes que confiaron explícitamente en la CA validaron `localhost` y `127.0.0.1` sin omitir la verificación. La portada, el login, Menú Digital, Personal y `/salud` devolvieron 200 por HTTPS. |
| Proxy y sesión | `/admin` sin sesión redirigió a `https://localhost:8443/login`. Los scripts del login se sirvieron por HTTPS y la cookie de sesión incluyó `Secure`, `HttpOnly` y `SameSite=Lax`. |
| Tiempo real | Se abrió la conexión `wss://localhost:8443/app/…` con verificación TLS. Reverb devolvió el evento `pusher:connection_established`. |
| Persistencia | Los dos nodos y Nginx se recrearon y PostgreSQL siguió usando el mismo contenedor y volumen. |

**Por verificar en la laptop de la demo.** Confirmar la confianza en el almacén de Windows y repetir la navegación del guion. En macOS se comprobó la confianza pasando la CA explícitamente a los clientes de prueba.
