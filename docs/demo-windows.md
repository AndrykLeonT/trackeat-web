# TrackEat en Windows · Cambios de los seis puntos

Usa la rama `omar` y abre PowerShell en la raíz del repositorio. Para preparar una laptop desde cero, consulta los requisitos y la compilación en la [guía de Docker del equipo](../levantar-compose.md). Aplica los ajustes de los puntos 2 y 5 de esta página antes de arrancar Compose.

## 1. Usuarios, perfil y panel

Con TrackEat abierto en `https://localhost:8443`, usa las [cuentas ficticias del equipo](equipo.md).

- **Administrador → Panel de administración** (`/admin`). Revisa los cuatro gráficos de los últimos 30 días y sus tablas. La base de demostración debe tener los datos de ejemplo.
- **Administrador → Usuarios** (`/admin/usuarios`). Puedes crear, editar y desactivar clientes y personal. Si intentas desactivar o cambiar de rol al último administrador activo, el servidor rechaza el cambio.
- **Cuenta → Mi perfil** (`/perfil`). Cada usuario gestiona sus datos. Cambiar correo o contraseña requiere la contraseña actual.
- Con una cuenta **Cliente**, abre **Menú Digital** y **Personal**. Ambos deben conservar el nombre y el menú lateral del cliente. Si abres `/admin` con esa cuenta, debe aparecer **403**.

Para demostrar la recuperación con correo real, configura el Gmail del equipo y su contraseña de aplicación en `backend/.env` según las instrucciones enlazadas. Si Docker ya está corriendo, recrea ambos nodos con `docker compose --env-file docker/.env -f docker/compose.yml up -d --force-recreate nodo1 nodo2`. Solicita el enlace en `/forgot-password` para una cuenta de prueba con buzón accesible y comprueba la recepción en esa laptop. [Evidencia del Punto 1 y configuración SMTP](evidencias/01-usuarios-panel-seguridad.md#correo-real-con-gmail)

## 2. Certificado HTTPS y redirección

Instala [mkcert para Windows](https://github.com/FiloSottile/mkcert#windows) y ejecuta estos comandos **en la laptop de la demostración**. Acepta que Windows confíe en la CA local y vuelve a abrir Edge si estaba abierto.

```powershell
New-Item -ItemType Directory -Force docker/infra/tls | Out-Null
mkcert -install
mkcert -cert-file docker/infra/tls/server.pem -key-file docker/infra/tls/server-key.pem localhost 127.0.0.1 ::1
```

Con Compose levantado según el punto 5, comprueba ambas respuestas. La primera debe devolver **301** y un encabezado `Location` con `https://localhost:8443/login`. La segunda debe devolver JSON con `"bd":"ok"` mediante HTTPS confiable. [Evidencia del Punto 2](evidencias/02-nginx-https.md)

```powershell
curl.exe -sSI "http://127.0.0.1:8080/login"
curl.exe -fsS "https://localhost:8443/salud"
```

## 3. Cinco cabeceras de seguridad

En Edge, abre `https://localhost:8443/login`, entra a **F12 → Red**, recarga la página y selecciona la solicitud del documento. En los encabezados de respuesta deben aparecer `Content-Security-Policy`, `Strict-Transport-Security`, `X-Content-Type-Options`, `Referrer-Policy` y `Permissions-Policy`. Para mostrar la medida de cookies del Punto 1, revisa también `HttpOnly`, `Secure` y `SameSite=Lax` en la cookie de sesión sin mostrar su valor. [Evidencia del Punto 3](evidencias/03-cabeceras-nginx.md)

## 4. Bloqueo de un script por CSP

Con Edge disponible, las dependencias del frontend instaladas, la CA local confiable y Compose en marcha, ejecuta:

```powershell
pnpm --dir frontend verify:csp --visible
```

En la pestaña de prueba protegida, el script externo debe mostrar **Ejecutado** y el script inline **Sin ejecutar**. La terminal también muestra la comparación con el caso de control. Cierra la pestaña al terminar. [Evidencia del Punto 4](evidencias/04-prueba-csp.md)

## 5. Secretos locales y evidencias de la Práctica 6

Conserva la `APP_KEY` local en `backend/.env`. En `docker/.env` configura `DB_PASSWORD`, `REDIS_PASSWORD` y `REVERB_APP_SECRET` con valores propios. Si el volumen de PostgreSQL ya existe, conserva su contraseña o aplica el procedimiento de migración antes de arrancar. Compose fija `APP_DEBUG=false`. [Generación de secretos en PowerShell y migración de credenciales](evidencias/05-cierre-practica-6.md#credenciales-y-arranque)

Una vez compilado el frontend y preparados el certificado y los dos archivos `.env`, ejecuta:

```powershell
docker compose --env-file docker/.env -f docker/compose.yml config --quiet
docker compose --env-file docker/.env -f docker/compose.yml up -d --build
docker compose --env-file docker/.env -f docker/compose.yml exec -T balanceador nginx -t
```

Prepara los datos ficticios si la base es nueva, como indica la [guía de Docker](../levantar-compose.md). Para la presentación, ten a mano el [checklist de las 14 medidas](evidencias/05-cierre-practica-6.md#lista-de-las-14-medidas), la [plantilla comentada de Nginx](../docker/infra/nginx.conf.template), el [mapa de superficies](superficies.md) y la [declaración de IA en el README](../README.md#declaración-de-uso-de-ia).

## 6. PDF de la Tarea 4

Abre el [informe de firma, certificados y HTTPS](Tarea_4_firma_certificados_HTTPS.pdf). Es el entregable en PDF con texto negro. Incluye la explicación de firmas y certificados, e.firma y NOM-151, el procedimiento para una CA pública y la decisión de TLS para TrackEat. La demo de esta laptop usa el certificado local del Punto 2.
