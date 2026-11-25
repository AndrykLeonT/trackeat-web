# TrackEat en la laptop Windows de la demostración

Esta guía prepara una instalación de TrackEat en una sola laptop. Docker Desktop ejecuta Nginx, Laravel, PostgreSQL, Redis y Reverb. Abre **https://localhost:8443** en la misma laptop que ejecuta Docker.

## 1. Instalar herramientas y bajar el proyecto

- Instala **Docker Desktop** con el motor WSL 2 y ábrelo antes de ejecutar los comandos. [Instalación de Docker Desktop en Windows](https://docs.docker.com/desktop/setup/install/windows-install/)
- Instala **Git, Node.js y pnpm**. El repositorio se ha compilado con Node.js 24 y pnpm 12. [Git para Windows](https://git-scm.com/download/win) · [Node.js](https://nodejs.org/en/download) · [Instalación de pnpm](https://pnpm.io/installation)
- Instala **mkcert** y deja `mkcert.exe` en el `PATH`. Ten **Microsoft Edge** disponible para la demostración de CSP. [Instalación de mkcert en Windows](https://github.com/FiloSottile/mkcert#windows)

En **PowerShell**, sitúate en la carpeta donde guardarás el proyecto y ejecuta el bloque siguiente. Si ya tienes el repositorio, abre su carpeta y actualiza la rama `omar`. Para un repositorio privado, usa una cuenta de GitHub con acceso.

```powershell
git clone --branch omar https://github.com/AndrykLeonT/trackeat-web.git
Set-Location trackeat-web
git branch --show-current
docker info --format '{{.OSType}}'
docker compose version
node --version
pnpm --version
mkcert -version
```

Docker debe mostrar `linux` y la rama debe ser `omar`. Ejecuta el resto de los comandos en la **raíz del repositorio**, donde están las carpetas `backend`, `docker` y `frontend`.

## 2. Preparar la configuración local

Los dos archivos `.env` cumplen funciones distintas. `backend/.env` contiene la clave de Laravel y los ajustes de correo. `docker/.env` contiene las contraseñas de PostgreSQL y Redis y la clave privada de Reverb. Git excluye ambos archivos.

En una instalación nueva, ejecuta este bloque para crearlos con valores propios de la laptop. Si la laptop ya tiene un volumen `trackeat-docker-db` con datos, reutiliza su `docker/.env` con la contraseña correspondiente. Para actualizar una base anterior al cambio de credenciales, consulta la [guía de migración de credenciales](evidencias/05-cierre-practica-6.md#credenciales-y-arranque).

```powershell
$rng = [Security.Cryptography.RandomNumberGenerator]::Create()
$utf8 = [Text.UTF8Encoding]::new($false)
try {
    if (-not (Test-Path backend/.env)) {
        $key = New-Object byte[] 32
        $rng.GetBytes($key)
        $template = Get-Content backend/.env.example -Raw
        $content = $template -replace '(?m)^APP_KEY=(?=\r?$)', ('APP_KEY=base64:' + [Convert]::ToBase64String($key))
        [IO.File]::WriteAllText((Join-Path (Get-Location).Path 'backend/.env'), $content, $utf8)
    }
    if (-not (Test-Path docker/.env)) {
        $values = foreach ($name in 'DB_PASSWORD', 'REDIS_PASSWORD', 'REVERB_APP_SECRET') {
            $bytes = New-Object byte[] 24
            $rng.GetBytes($bytes)
            "$name=$([BitConverter]::ToString($bytes).Replace('-', '').ToLowerInvariant())"
        }
        [IO.File]::WriteAllLines((Join-Path (Get-Location).Path 'docker/.env'), [string[]]$values, $utf8)
    }
} finally {
    $rng.Dispose()
}

docker compose --env-file docker/.env -f docker/compose.yml config --quiet
```

En una instalación nueva, PostgreSQL y Redis se configuran con esos valores al arrancar. Si algún archivo ya existe, el bloque conserva su contenido. En ese caso, confirma que `APP_KEY` y las tres credenciales de Docker estén configuradas.

## 3. Generar el certificado HTTPS en la laptop

```powershell
New-Item -ItemType Directory -Force docker/infra/tls | Out-Null
mkcert -install
mkcert -cert-file docker/infra/tls/server.pem -key-file docker/infra/tls/server-key.pem localhost 127.0.0.1 ::1
```

Acepta la instalación de la CA local en Windows y vuelve a abrir Edge si ya estaba abierto. Nginx lee los dos archivos PEM desde `docker/infra/tls`. La ruta que devuelve `mkcert -CAROOT` contiene la CA local. Conserva su clave privada en la laptop donde se generó. [Detalles del certificado de TrackEat](evidencias/02-nginx-https.md)

## 4. Preparar Ziggy y compilar el frontend

Vite lee el JavaScript de Ziggy desde `backend/vendor/tightenco/ziggy/dist/index.js`. En un clon nuevo esa ruta se prepara con la imagen del proyecto, que instala PHP y las dependencias de Composer dentro de Docker.

```powershell
docker compose --env-file docker/.env -f docker/compose.yml build nodo1
if ($LASTEXITCODE -ne 0) { throw 'Falló la construcción inicial de TrackEat.' }

$ziggyContainer = docker create trackeat-app
if ($LASTEXITCODE -ne 0) { throw 'No se pudo preparar el contenedor temporal de Ziggy.' }
try {
    New-Item -ItemType Directory -Force backend/vendor/tightenco/ziggy/dist | Out-Null
    docker cp "${ziggyContainer}:/app/vendor/tightenco/ziggy/dist/index.js" backend/vendor/tightenco/ziggy/dist/index.js
    if ($LASTEXITCODE -ne 0) { throw 'Falló la copia de Ziggy.' }
} finally {
    docker rm $ziggyContainer | Out-Null
}

pnpm --dir frontend install --frozen-lockfile
if ($LASTEXITCODE -ne 0) { throw 'Falló la instalación del frontend.' }
pnpm --dir frontend build
if ($LASTEXITCODE -ne 0) { throw 'Falló la compilación del frontend.' }
```

La compilación deja el panel, el menú y el catálogo de personal en `backend/public`. El siguiente bloque vuelve a construir la imagen para incluir esos archivos.

## 5. Levantar el sistema y cargar los datos de ejemplo

```powershell
docker compose --env-file docker/.env -f docker/compose.yml up -d --wait db redis
if ($LASTEXITCODE -ne 0) { throw 'PostgreSQL o Redis no están disponibles.' }
docker compose --env-file docker/.env -f docker/compose.yml up -d --build
if ($LASTEXITCODE -ne 0) { throw 'Falló el arranque de TrackEat.' }
```

En una **base nueva de demostración**, aplica las migraciones y carga las cuentas y los pedidos ficticios. La plantilla de Laravel usa `APP_ENV=local`, que permite cargar los ejemplos.

```powershell
docker compose --env-file docker/.env -f docker/compose.yml exec -T nodo1 php artisan migrate --seed --force
```

En una **base ya preparada**, usa `php artisan migrate --force` dentro de `nodo1` si hay migraciones pendientes. Los datos de Docker se guardan en el volumen `trackeat-docker-db`.

## 6. Comprobar la demo antes de presentar

```powershell
docker compose --env-file docker/.env -f docker/compose.yml ps
docker compose --env-file docker/.env -f docker/compose.yml exec -T balanceador nginx -t
curl.exe -fsS "https://localhost:8443/salud"
curl.exe -sSI "http://127.0.0.1:8080/menu/?categoria=bebidas"
```

- `ps` muestra los servicios en marcha y `nginx -t` valida la configuración.
- `/salud` devuelve JSON con `"bd":"ok"`. El acceso por HTTP devuelve **301** con destino a `https://localhost:8443/menu/?categoria=bebidas`.
- En Edge, abre `https://localhost:8443/login`. Debe cargar con el certificado confiable. Usa una cuenta de [los datos ficticios del equipo](equipo.md). Comprueba que el administrador entre a `/admin` y que esa misma ruta devuelva **403** con la cuenta de cliente. El login y el registro necesitan acceso a Cloudflare para cargar Turnstile.
- En Edge, abre **F12 → Red**, recarga `/login` y selecciona el documento. Revisa las cinco cabeceras y los atributos de la cookie de sesión. [Comprobación de cabeceras](evidencias/03-cabeceras-nginx.md)

Para comprobar el bloqueo CSP y dejar visible el resultado, ejecuta:

```powershell
pnpm --dir frontend verify:csp --visible
```

El script externo debe aparecer como **Ejecutado** y el inline como **Sin ejecutar**. Cierra la pestaña de prueba para terminar. [Detalles de la prueba](evidencias/04-prueba-csp.md)

### Correo de recuperación, si se va a demostrar

La instalación base usa `MAIL_MAILER=array`. Para enviar un mensaje real, configura el SMTP del equipo en `backend/.env` siguiendo la [guía de correo](evidencias/01-usuarios-panel-seguridad.md#correo-real-con-gmail). Después aplica el cambio con:

```powershell
docker compose --env-file docker/.env -f docker/compose.yml up -d --force-recreate nodo1 nodo2
```

Usa una cuenta de prueba con un buzón accesible y comprueba la recepción antes de presentar. Los enlaces que apuntan a `localhost:8443` se abren desde la laptop de la demo.

## Problemas comunes y próximos arranques

| Síntoma | Qué revisar |
| --- | --- |
| `Define DB_PASSWORD en docker/.env` o error similar | Revisa que el archivo tenga valores para las tres variables y que el comando incluya `--env-file docker/.env`. |
| Vite no encuentra Ziggy | Repite el paso 4 y verifica que exista `backend/vendor/tightenco/ziggy/dist/index.js`. |
| Error `port is already allocated` | Identifica qué proceso ocupa el puerto y libera el que usa TrackEat. En esta demo, la URL HTTPS y la prueba de CSP usan el puerto 8443. |
| Edge o curl rechazan el certificado | Comprueba que ejecutaste `mkcert -install` en esta laptop con la cuenta que abrirá el navegador y que estás usando `https://localhost:8443`. |
| `/salud` indica un problema de base de datos | Revisa el estado de `db` y que `DB_PASSWORD` coincida con la contraseña del volumen existente. |
| Login devuelve error o el panel aparece vacío en la primera instalación | Revisa que la migración y la carga de ejemplos del paso 5 hayan terminado. |
| El captcha no carga | Comprueba Internet y el acceso a `challenges.cloudflare.com`. |

Para detener la demo y conservar sus datos, usa `docker compose --env-file docker/.env -f docker/compose.yml stop`. Para retomarla, usa `docker compose --env-file docker/.env -f docker/compose.yml up -d`. Si cambió el código, vuelve a compilar el frontend y levanta con `up -d --build`.
