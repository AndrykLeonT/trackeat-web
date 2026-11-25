# Manual de instalación e implementación de TrackEat

Aplicaciones Web II · Instituto Tecnológico de La Paz · Grupo 8-H
Versión 1.0 · 25 de noviembre de 2026

Equipo: Angélica Menchaca Rueda, Andryk Manuel León Tapia, Daniel Alexander Estrada Cosio y Carlos Omar Celis Calzada.

**Plataforma:** este manual está escrito para equipos con **Windows 10 u 11 y Docker Desktop**. Todos los comandos son de **PowerShell** y se ejecutan desde la raíz del repositorio. Docker Desktop debe estar **abierto y con el motor en ejecución** antes de cualquier comando `docker`.

Este manual explica cómo instalar y poner en marcha TrackEat desde cero. La instalación base (secciones 1 a 7) deja todo el sistema funcionando en **una sola computadora**. El **Apéndice A** describe la otra arquitectura para la que está diseñado el sistema: **tres servidores**. Los apéndices B a D reúnen las tablas de consulta.

## Contenido

1. [Elegir la arquitectura y el docker compose que corresponde](#1-elegir-la-arquitectura-y-el-docker-compose-que-corresponde)
2. [Requisitos](#2-requisitos)
3. [Instalación en una sola PC](#3-instalación-en-una-sola-pc)
4. [Verificar la instalación](#4-verificar-la-instalación)
5. [Operación diaria](#5-operación-diaria)
6. [Solución de problemas](#6-solución-de-problemas)
7. [Registro de prueba en otra computadora](#7-registro-de-prueba-en-otra-computadora)
- [Apéndice A. Arquitectura de tres servidores](#apéndice-a-arquitectura-de-tres-servidores)
- [Apéndice B. Variables de `docker/.env`](#apéndice-b-variables-de-dockerenv)
- [Apéndice C. Puertos y firewall](#apéndice-c-puertos-y-firewall)
- [Apéndice D. Estructura de archivos de despliegue](#apéndice-d-estructura-de-archivos-de-despliegue)

---

## 1. Elegir la arquitectura y el docker compose que corresponde

TrackEat se despliega con Docker. Los mismos archivos sirven para dos arquitecturas; lo que cambia es **qué archivo de Compose se ejecuta** y el contenido de `docker/.env`.

| | **Arquitectura base: una PC** | **Apéndice A: tres servidores** |
|---|---|---|
| Para qué sirve | Instalación local, demostración, pruebas | Reparto de la carga y tolerancia a la caída de un nodo |
| Equipos | 1 | 3 (PC A: infraestructura, PC B: nodo 1, PC C: nodo 2) |
| Qué corre en cada equipo | Los 9 contenedores juntos | A: PostgreSQL, Redis y Nginx. B y C: app, Reverb y cola de un nodo |
| Cómo se encuentran los contenedores | Por nombre dentro de una red de Docker | Por la IP de cada equipo y el puerto publicado |
| `docker/.env` | Solo las 3 credenciales | Credenciales **y** las IPs reales, igual en los tres equipos |
| Estado de la prueba | Probado el 25/11/2026 (ver [sección 7](#7-registro-de-prueba-en-otra-computadora)) | Configuración validada; **pendiente de probar entre equipos distintos** |

### Archivo de Compose según la arquitectura

El parámetro `--env-file docker/.env` va **siempre**. Todos los comandos se ejecutan desde la **raíz del repositorio**.

| Arquitectura | Equipo | Comando para levantar |
|---|---|---|
| Una PC | La única | `docker compose --env-file docker/.env -f docker/compose.yml up -d --build` |
| Tres servidores | PC A (infraestructura) | `docker compose --env-file docker/.env -f docker/infra/compose.yml up -d` |
| Tres servidores | PC B (nodo 1) | `docker compose --env-file docker/.env -f docker/nodo/compose.yml up -d --build nodo1 reverb1 cola1` |
| Tres servidores | PC C (nodo 2) | `docker compose --env-file docker/.env -f docker/nodo/compose.yml up -d --build nodo2 reverb2 cola2` |

Para consultar (`ps`, `logs`), reiniciar o apagar (`down`) se usa **el mismo `-f`** con el que se levantó.

> **Reglas que evitan los errores más comunes**
>
> - Con tres servidores **no se usa `docker/compose.yml`**: ese archivo incluye todo y levantaría una segunda base, otro Redis y otro balanceador en el equipo equivocado.
> - Con una sola PC **no se levantan `infra` y `nodo` por separado**: cada uno crea su propia red de Docker y los nombres `db`, `redis` o `nodo1` dejan de resolverse entre ellos.
> - En las PC B y C **hay que escribir los nombres de los servicios** al final (`nodo1 reverb1 cola1`). Sin ellos, Compose intenta levantar los seis.

---

## 2. Requisitos

### 2.1 Equipo

| Concepto | Requisito |
|---|---|
| Sistema operativo | **Windows 10 (22H2 o posterior) u 11**, de 64 bits. Los comandos de este manual son de **PowerShell** |
| Contenedores | **Docker Desktop** con el motor WSL2 (es la opción predeterminada al instalarlo) |
| Memoria | 8 GB de RAM recomendados para Docker Desktop con los 9 contenedores |
| Disco | 10 GB libres recomendados (imágenes de Docker, volúmenes y dependencias) |
| Virtualización | Habilitada en el BIOS/UEFI (Docker Desktop usa WSL2) |
| Internet | Necesario para la instalación y también en uso: el login y el registro cargan el captcha de Cloudflare Turnstile y la tipografía desde sus servidores. Sin conexión esas pantallas no funcionan por completo |

### 2.2 Software que se instala en el equipo

PHP, Composer y PostgreSQL **no** se instalan: van dentro de las imágenes de Docker. En el equipo se necesita:

| Programa | Para qué se usa | Instalación con `winget` |
|---|---|---|
| Git | Descargar el repositorio | `winget install Git.Git` |
| Docker Desktop (con WSL2) | Construir y ejecutar los contenedores | `winget install Docker.DockerDesktop` |
| Node.js 20 LTS o superior | Compilar el frontend | `winget install OpenJS.NodeJS.LTS` |
| pnpm | Gestor de paquetes del frontend | `npm install -g pnpm` |
| mkcert | Crear el certificado HTTPS local | `winget install FiloSottile.mkcert` |

Después de instalar Docker Desktop hay que **reiniciar el equipo**, abrir Docker Desktop, aceptar sus términos y esperar a que indique que el motor está en ejecución (abajo a la izquierda, «Engine running»). Cierra y vuelve a abrir PowerShell tras instalar los programas para que reconozca los comandos nuevos (`winget` modifica el `PATH` y el cambio no llega a las consolas ya abiertas).

Comprobación:

```powershell
git --version
docker --version
docker compose version
docker info --format '{{.ServerVersion}} {{.OperatingSystem}}'
node --version
pnpm --version
mkcert -version
```

Cada comando debe mostrar una versión. `docker info` debe responder con la versión del motor y `Docker Desktop`; si dice que no puede conectar con el motor, Docker Desktop no está abierto o aún está arrancando. Si algún comando no se reconoce, la instalación de ese programa no terminó o PowerShell se abrió antes de instalarlo.

### 2.3 Puertos libres

En la instalación de una PC deben estar libres, en el equipo, los puertos **8080, 8443, 8001, 8002, 8091, 8092, 5433 y 6380**. Si `presentar.bat` o un PostgreSQL local usan alguno, ciérralos antes de continuar. Para ver cuáles están ocupados:

```powershell
Get-NetTCPConnection -State Listen -LocalPort 8080,8443,8001,8002,8091,8092,5433,6380 -ErrorAction SilentlyContinue | Select-Object LocalPort,OwningProcess
```

Si el que ocupa el puerto es `com.docker.backend`, lo está publicando **otro contenedor de Docker** (`docker ps` muestra cuál). Si no quieres detener ese contenedor, cambia solo el puerto HTTP de TrackEat agregando `HTTP_PUERTO_PUBLICADO=8081` a `docker/.env` (paso 3). El sitio sigue entrando por el 8443.

---

## 3. Instalación en una sola PC

Es la arquitectura base. Se levantan nueve contenedores en una misma red de Docker:

```
                       Navegador  ──►  https://localhost:8443
                                              │
                                   ┌──────────▼──────────┐
                                   │ trackeat-balanceador│  Nginx (HTTPS, 301 desde :8080)
                                   └───┬────────────┬────┘
                          peticiones   │            │   WebSockets (/app)
                       ┌───────────────┤            ├───────────────┐
                       ▼               ▼            ▼               ▼
                 trackeat-nodo1  trackeat-nodo2  trackeat-reverb1  trackeat-reverb2
                       │               │            │               │
                 trackeat-cola1  trackeat-cola2     │               │
                       └───────┬───────┘            └───────┬───────┘
                               ▼                            ▼
                     trackeat-db-docker             trackeat-redis
                     PostgreSQL 16                  Redis 7
```

| Contenedor | Función | Puerto en la PC |
|---|---|---|
| `trackeat-balanceador` | Nginx: única puerta de entrada, termina HTTPS | 8080 (redirige) y **8443** |
| `trackeat-nodo1`, `trackeat-nodo2` | La aplicación Laravel, dos copias | 8001, 8002 |
| `trackeat-reverb1`, `trackeat-reverb2` | WebSockets para el tiempo real | 8091, 8092 |
| `trackeat-cola1`, `trackeat-cola2` | Procesan la cola de trabajos de cada nodo | no publica |
| `trackeat-db-docker` | PostgreSQL, base de datos única | 5433 |
| `trackeat-redis` | Canal compartido entre los dos Reverb | 6380 |

Los usuarios entran siempre por **https://localhost:8443**. Los puertos 8001 y 8002 solo sirven para diagnosticar un nodo sin pasar por el balanceador.

### Paso 1. Obtener el código

```powershell
git clone https://github.com/AndrykLeonT/trackeat-web.git
cd trackeat-web
git checkout main
```

Todos los comandos siguientes se ejecutan desde esta carpeta (la raíz del repositorio).

### Paso 2. Crear `backend/.env`

Este archivo contiene la clave de la aplicación (`APP_KEY`) y la configuración de correo. No se sube a Git, así que hay que crearlo. Este bloque lo copia desde la plantilla y genera una `APP_KEY` nueva sin necesitar PHP:

```powershell
if (Test-Path backend/.env) { throw 'Ya existe backend/.env. Conserva su APP_KEY.' }
Copy-Item backend/.env.example backend/.env
$bytes = New-Object byte[] 32
$rng = [Security.Cryptography.RandomNumberGenerator]::Create()
$rng.GetBytes($bytes); $rng.Dispose()
$key = 'base64:' + [Convert]::ToBase64String($bytes)
(Get-Content backend/.env) -replace '^APP_KEY=.*$', "APP_KEY=$key" | Set-Content -Encoding ascii backend/.env
Select-String -Path backend/.env -Pattern '^APP_KEY='
```

La última línea debe mostrar `APP_KEY=base64:` seguido de 44 caracteres.

Notas sobre este archivo:

- **No cambies `APP_ENV=local`.** Los datos de ejemplo (paso 8) solo se cargan con `local` o `testing`.
- Docker sobrescribe la conexión a la base, Redis, Reverb, `APP_URL` y `APP_DEBUG=false`. Lo único que el contenedor toma de este archivo es la `APP_KEY`, el correo y el resto de variables generales.
- Para que la recuperación de contraseña envíe correos reales, configura `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` y `MAIL_FROM_ADDRESS`. Con `MAIL_MAILER=array` (el valor de la plantilla) la aplicación funciona, pero los correos no se envían.

### Paso 3. Crear `docker/.env` con las credenciales

Docker exige tres valores privados distintos entre sí: `DB_PASSWORD`, `REDIS_PASSWORD` y `REVERB_APP_SECRET`. Este bloque los genera aleatorios:

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

Para una sola PC este archivo no necesita nada más. Es privado y no se sube a Git; no lo compartas por canales públicos.

### Paso 4. Crear el certificado HTTPS

Nginx publica el sitio por HTTPS. Se genera un certificado con una entidad certificadora local (CA) creada por mkcert:

```powershell
New-Item -ItemType Directory -Force docker/infra/tls | Out-Null
mkcert -install
mkcert -cert-file docker/infra/tls/server.pem -key-file docker/infra/tls/server-key.pem localhost 127.0.0.1 ::1
```

- `mkcert -install` agrega la CA al almacén de confianza de Windows y muestra una **advertencia de seguridad de Windows: haz clic en «Sí»**. Si se cancela o se cierra, mkcert lo indica («the local CA is not installed in the system trust store»), el certificado se crea de todos modos y el navegador mostrará «La conexión no es privada»; repite `mkcert -install` y acepta. Después **cierra y vuelve a abrir Edge o Chrome**.
- Los archivos `server.pem` y `server-key.pem` deben quedar exactamente en `docker/infra/tls/`. Git los ignora. Si la carpeta no existe, Docker se niega a levantar el balanceador.
- La clave de la CA (`rootCA-key.pem`, en la carpeta que muestra `mkcert -CAROOT`) **no se comparte ni se copia** a otros equipos.

### Paso 5. Compilar el frontend

La imagen de Docker incluye el frontend ya compilado, por lo que este paso va **antes** de construir las imágenes:

```powershell
pnpm --dir frontend install
pnpm --dir frontend build
```

Tarda entre uno y dos minutos con descarga de dependencias (unos 20 segundos si ya están descargadas). Al terminar existen las carpetas `backend/public/build`, `backend/public/personal` y `backend/public/menu`. Si faltan, el sitio carga sin estilos ni pantallas.

> Si `pnpm install` pide aprobar scripts de compilación (esbuild), acéptalo: el repositorio ya lo permite en `frontend/pnpm-workspace.yaml`.

### Paso 6. Validar la configuración (opcional, recomendado)

```powershell
docker compose --env-file docker/.env -f docker/compose.yml config --quiet
```

No imprime nada si todo está correcto. Si falla con «Define DB_PASSWORD…», revisa el paso 3.

### Paso 7. Construir y levantar

```powershell
docker compose --env-file docker/.env -f docker/compose.yml up -d --build
docker compose --env-file docker/.env -f docker/compose.yml ps
```

- La primera vez la construcción tarda unos 4 minutos (223 s medidos); las siguientes reutilizan la caché.
- Antes de crear las tablas (paso 8), `cola1`, `cola2`, `reverb1` y `reverb2` pueden reiniciarse varias veces. Es normal.

### Paso 8. Crear las tablas y los datos de ejemplo

**Solo la primera vez:**

```powershell
docker compose --env-file docker/.env -f docker/compose.yml exec -T nodo1 php artisan migrate --seed --force
```

Crea las tablas y carga 6 usuarios de ejemplo, 3 categorías, 4 productos con fotografía y 4 pedidos. Repetir el comando no duplica los datos. Una vez migrada la base, los contenedores que se reiniciaban se estabilizan.

**Cuentas de ejemplo** (contraseña de todas: `TrackEatDemo!2026`):

| Rol | Correo |
|---|---|
| Administrador | `administrador@example.test` |
| Cajero | `cajero@example.test` |
| Mesero | `mesero@example.test` |
| Cocinero | `cocinero@example.test` |
| Cliente | `cliente1@example.test`, `cliente2@example.test` |

Son datos ficticios solo para demostración. En una operación real no se cargan ejemplos: se crean las cuentas desde el panel de administración y se cambian estas contraseñas.

---

## 4. Verificar la instalación

Haz las cinco comprobaciones en orden.

**1. Contenedores.** `docker compose --env-file docker/.env -f docker/compose.yml ps` debe mostrar los **9 contenedores en estado `Up`**.

**2. Salud del sistema:**

```powershell
curl.exe -fsS https://localhost:8443/salud
```

Debe responder `{"nodo":"1","bd":"ok",...}`. Repite el comando varias veces: el campo `nodo` alterna entre `"1"` y `"2"`, lo que comprueba que el balanceador reparte la carga.

Si `curl.exe` dice `SEC_E_UNTRUSTED_ROOT`, la CA de mkcert no está instalada (repite el paso 4). Si dice `schannel: the revocation status is unknown`, agrega `--ssl-no-revoke`: los certificados de mkcert no tienen servicio de revocación.

**3. Redirección HTTP a HTTPS** (cambia 8080 por 8081 si pusiste `HTTP_PUERTO_PUBLICADO=8081`):

```powershell
curl.exe -sSI http://localhost:8080/login
```

Debe mostrar `HTTP/1.1 301` y `Location: https://localhost:8443/login`.

**4. En el navegador.** Abre `https://localhost:8443/`. Debe verse la página principal con el candado de HTTPS sin advertencias. Entra a `/login` con `administrador@example.test`: el captcha debe cargar y, tras iniciar sesión, debe abrirse el panel de administración.

**5. Tiempo real.** Con dos ventanas (una como cliente haciendo un pedido, otra como cocinero en Comandas), el pedido debe aparecer en la pantalla del cocinero sin recargar.

Si algo falla, revisa la [sección 6](#6-solución-de-problemas).

---

## 5. Operación diaria

Todos los comandos con `--env-file docker/.env -f docker/compose.yml` (arquitectura de una PC).

| Tarea | Comando |
|---|---|
| Ver el estado | `docker compose --env-file docker/.env -f docker/compose.yml ps` |
| Ver la actividad del balanceador | `docker compose --env-file docker/.env -f docker/compose.yml logs -f balanceador` |
| Ver los últimos mensajes de un contenedor | `docker logs --tail 50 trackeat-nodo1` |
| Apagar (conserva los datos) | `docker compose --env-file docker/.env -f docker/compose.yml down` |
| Volver a encender | `docker compose --env-file docker/.env -f docker/compose.yml up -d` |
| Apagar y **borrar todos los datos** | `docker compose --env-file docker/.env -f docker/compose.yml down -v` |

`down` conserva la base de datos y las fotos del catálogo (volúmenes `trackeat-docker-db` y `trackeat-catalogo`). `down -v` los borra; después hay que repetir el paso 8.

### Actualizar a una versión nueva

```powershell
git pull
pnpm --dir frontend install
pnpm --dir frontend build
docker compose --env-file docker/.env -f docker/compose.yml up -d --build
docker compose --env-file docker/.env -f docker/compose.yml exec -T nodo1 php artisan migrate --force
```

### Respaldo y restauración de la base de datos

```powershell
# Respaldo
docker exec trackeat-db-docker pg_dump -U trackeat -d trackeat -f /tmp/trackeat.sql
docker cp trackeat-db-docker:/tmp/trackeat.sql .\trackeat.sql

# Restauración (sobre una base vacía o recién migrada)
docker cp .\trackeat.sql trackeat-db-docker:/tmp/trackeat.sql
docker exec trackeat-db-docker psql -U trackeat -d trackeat -f /tmp/trackeat.sql
```

Las fotografías del catálogo viven en el volumen `trackeat-catalogo` y no forman parte de este respaldo.

### Probar la tolerancia a fallos

Con `/salud` recargándose en el navegador:

```powershell
docker stop trackeat-nodo2       # el sitio sigue; solo responde el nodo 1
docker start trackeat-nodo2      # tarda unos 10 s en volver al reparto
docker stop trackeat-reverb1     # el otro Reverb atiende los WebSockets
docker start trackeat-reverb1
docker stop trackeat-db-docker   # /salud responde 503 con "bd":"caida"
docker start trackeat-db-docker  # vuelve a 200 sin reiniciar nada
```

---

## 6. Solución de problemas

| Síntoma | Causa probable y solución |
|---|---|
| `required variable DB_PASSWORD is missing a value` | Falta `docker/.env` o el comando no lleva `--env-file docker/.env`. Revisa el paso 3 y ejecuta desde la raíz del repositorio. |
| `bind source path does not exist` o el balanceador no arranca | No existe `docker/infra/tls` con `server.pem` y `server-key.pem`. Repite el paso 4. |
| `port is already allocated` | Otro programa o contenedor usa 8080, 8443, 8001, 8002, 8091, 8092, 5433 o 6380. Ciérralo (por ejemplo `presentar.bat`) o identifícalo con `Get-NetTCPConnection` y `docker ps`. Para el 8080 se puede evitar el choque con `HTTP_PUERTO_PUBLICADO=8081` en `docker/.env`. |
| `curl.exe`: `SEC_E_UNTRUSTED_ROOT` o `revocation status is unknown` | La CA de mkcert no está instalada, o hay que agregar `--ssl-no-revoke` (ver Verificación 2). |
| `docker` dice que no puede conectar con el motor | Docker Desktop no está abierto o aún arranca. Ábrelo y espera a «Engine running». |
| `pull access denied for trackeat-app` | Falta construir la imagen. Ejecuta el paso 7 con `--build`. |
| El navegador muestra «La conexión no es privada» | La CA de mkcert no está instalada en este equipo. Ejecuta `mkcert -install` y reinicia el navegador. |
| El sitio carga sin estilos o con pantallas en blanco | Falta compilar el frontend (paso 5) y reconstruir la imagen con `up -d --build`. |
| Error 502 al abrir el sitio | Los nodos aún arrancan. Espera unos segundos y recarga. Si persiste: `docker logs trackeat-nodo1`. |
| Un contenedor se reinicia sin parar | Antes de migrar es normal. Si continúa tras el paso 8, revisa `docker logs trackeat-<nombre>`: suele ser la base de datos o `backend/.env`. |
| `/salud` responde 503 | La base no responde. Revisa que `trackeat-db-docker` esté `Up` en `ps`. |
| `migrate --seed` falla con «Los ejemplos solo se cargan en local/testing» | `APP_ENV` en `backend/.env` no es `local`. Corrígelo y repite el comando. |
| `migrate --seed` falla con «identificadores de ejemplo ocupados» | La base ya tiene datos que chocan con los de ejemplo. Para empezar de cero: `down -v` y repite los pasos 7 y 8. |
| No cargan las pantallas en vivo | Revisa que `trackeat-redis`, `trackeat-reverb1` y `trackeat-reverb2` estén `Up`. La aplicación sigue funcionando y consulta los cambios cada 5 s como respaldo. |
| El login o el registro no muestran el captcha | El equipo no tiene salida a internet hacia Cloudflare. Comprueba la conexión. |
| Docker Desktop no arranca | Habilita la virtualización en el BIOS/UEFI y verifica que WSL2 esté instalado (`wsl --status`). |

---

## 7. Registro de prueba en otra computadora

### 7.1 Prueba ya realizada (25 de noviembre de 2026)

Se siguieron los pasos de este manual en un equipo de desarrollo con Windows 11, Docker Desktop (motor 29.7.2, Compose v5.5.1), Node.js 24.15 y pnpm 12.3.4. **No fue una instalación limpia**: Git, Docker, Node y pnpm ya estaban instalados, y ya existía un `backend/.env` (se reutilizó; el bloque del paso 2 se probó aparte sobre una copia). mkcert sí se instaló con `winget` durante la prueba.

| Paso o verificación | Resultado |
|---|---|
| mkcert instalado con `winget`; certificado creado | Correcto. `mkcert -install` quedó **sin confirmar** (la ventana de Windows se canceló), por lo que el navegador mostraría advertencia hasta aceptarla |
| Paso 3. `docker/.env` | Correcto (con `HTTP_PUERTO_PUBLICADO=8081`, porque el 8080 lo usaba otro contenedor) |
| Paso 5. Frontend compilado | Correcto, 19 s con dependencias ya descargadas |
| Paso 7. Construcción y arranque | Correcto, 223 s. 9 contenedores `Up` (tras el paso 8, `cola2` dejó de reiniciarse) |
| Paso 8. `migrate --seed` | Correcto: 15 migraciones y los seeders. Repetirlo no duplica datos (4 pedidos de ejemplo) |
| Verificación 2. `/salud` | Correcto: 8 de 8 respuestas con `"bd":"ok"`, alternando nodo 1 y 2 |
| Verificación 3. Redirección | Correcto: `301` con `Location: https://localhost:8443/login` |
| Cabeceras y cookie de `/login` | Correcto: CSP, HSTS, Referrer-Policy; cookie con `secure`, `httponly`, `samesite=lax` |
| Verificación 4 (por script) | Login de cocinero y cliente: redirección a `/comandas` y `/menu/`. El cliente recibe 403 en `/admin` |
| Verificación 5. Tiempo real | Correcto: conexión `wss://` por el balanceador, canal privado autorizado y el cocinero recibió el evento de un pedido nuevo en 202 ms |
| Tolerancia a fallos | Con `nodo2` apagado, 6 de 6 respuestas del nodo 1 (200); al encenderlo vuelve al reparto. Con la base apagada, `/salud` da 503 con `"bd":"caida"` y se recupera sola |
| Persistencia | `down` y `up -d` conservaron los datos (5 pedidos antes y después) |

**No se probó:** la vista en el navegador con la CA confiable, el widget del captcha de Turnstile (el script usó el token de prueba, que el servidor acepta) ni la actualización con `git pull`.

### 7.2 Prueba en un equipo limpio

Antes de entregar, la instalación debe repetirse en un equipo donde nunca se haya ejecutado el proyecto. Se debe usar este manual tal cual, sin ayuda de memoria, y anotar el resultado.

| Dato | Valor |
|---|---|
| Fecha de la prueba | |
| Quién la realizó | |
| Equipo (modelo, RAM) y sistema operativo | |
| Versión de Docker Desktop / Node.js / pnpm | |
| Tiempo total de instalación | |

| Paso | ¿Funcionó sin ayuda? (Sí/No) | Observaciones o corrección necesaria al manual |
|---|---|---|
| 2.2 Software instalado y comprobado | | |
| Paso 1. Código obtenido | | |
| Paso 2. `backend/.env` creado | | |
| Paso 3. `docker/.env` creado | | |
| Paso 4. Certificado HTTPS | | |
| Paso 5. Frontend compilado | | |
| Paso 7. Contenedores levantados (9 en `Up`) | | |
| Paso 8. Tablas y datos de ejemplo | | |
| Verificación 2. `/salud` alterna entre nodos | | |
| Verificación 3. Redirección 301 | | |
| Verificación 4. Login de administrador | | |
| Verificación 5. Tiempo real | | |

---
---

# Apéndice A. Arquitectura de tres servidores

Esta es la arquitectura para la que está diseñado el sistema en producción: la infraestructura compartida en un servidor y cada nodo de la aplicación en su propio servidor. Si un nodo falla o se apaga, el balanceador lo retira y el otro sigue atendiendo.

> **Estado:** la configuración está preparada y se validó que los archivos de Compose se resuelven, pero **este modo todavía no se ha probado entre computadoras distintas**. Si algo no conecta, casi siempre es el firewall de Windows o una IP mal escrita (ver A.8). Al probarlo, anota los resultados y las correcciones.

## A.1 Reparto

Ejemplo con IPs de una red local (cámbialas por las reales; se consultan con `ipconfig`):

```
                          Navegador / celular de la misma red
                          https://192.168.1.10:8443
                                      │
              ┌───────────────────────▼───────────────────────┐
              │ PC A · infraestructura · 192.168.1.10         │
              │  Nginx (balanceador) · PostgreSQL · Redis     │
              └───────┬───────────────────────────────┬───────┘
          :8001 / :8091│                               │:8002 / :8092
        ┌──────────────▼─────────────┐   ┌─────────────▼──────────────┐
        │ PC B · nodo 1 · .1.11      │   │ PC C · nodo 2 · .1.12      │
        │  nodo1 · reverb1 · cola1   │   │  nodo2 · reverb2 · cola2   │
        └──────────────┬─────────────┘   └─────────────┬──────────────┘
                       └────► PostgreSQL :5433 y Redis :6380 (PC A) ◄────┘
```

| PC | IP de ejemplo | Qué corre | Archivo de Compose |
|---|---|---|---|
| **A** · infraestructura | `192.168.1.10` | PostgreSQL, Redis y el balanceador Nginx | `docker/infra/compose.yml` |
| **B** · nodo 1 | `192.168.1.11` | `nodo1`, `reverb1`, `cola1` | `docker/nodo/compose.yml` |
| **C** · nodo 2 | `192.168.1.12` | `nodo2`, `reverb2`, `cola2` | `docker/nodo/compose.yml` |

Los usuarios entran **siempre por la PC A**: `https://192.168.1.10:8443`. Las tres PC deben estar en la misma red y verse entre sí. Conviene **fijar las IPs** (reserva en el router): si el DHCP las cambia, hay que actualizar `docker/.env` en las tres.

## A.2 Qué cambia respecto a una PC

| | Una PC | Tres servidores |
|---|---|---|
| Archivo que se levanta | `docker/compose.yml` | `docker/infra/compose.yml` en A; `docker/nodo/compose.yml` en B y C |
| Cómo se encuentran | Por nombre dentro de la red de Docker | Por IP y puerto publicado |
| `docker/.env` | Solo credenciales | Credenciales **más las IPs**, idéntico en las tres PC |
| Certificado HTTPS | Para `localhost` | Debe incluir la **IP de la PC A** |
| Firewall | No aplica | Abrir puertos en cada PC |

El código y los archivos de Docker son los mismos. Solo cambian `docker/.env` y qué parte se levanta en cada PC.

## A.3 Qué necesita cada PC

Las **tres PC** deben cumplir los requisitos de la [sección 2](#2-requisitos): Windows 10/11 con Docker Desktop abierto y el motor en ejecución, y PowerShell para los comandos. Instala solo lo que indica la columna de cada PC:

| Elemento | PC A | PC B y C |
|---|---|---|
| Docker Desktop encendido | Sí | Sí |
| Git y repositorio clonado | Sí (usa la carpeta `docker/`) | Sí |
| `docker/.env` con las IPs | Sí | Sí, **el mismo** |
| `docker/infra/tls` (certificado) | **Sí** | No |
| `backend/.env` | No | **Sí, idéntico** en B y C |
| Frontend compilado (`pnpm build`) | No | **Sí** |
| Node.js y pnpm | No | Sí (o copiar las carpetas compiladas) |
| mkcert | Sí (genera el certificado) | No |

## A.4 Preparar cada PC

### A.4.1 Código (las tres PC)

```powershell
git clone https://github.com/AndrykLeonT/trackeat-web.git
cd trackeat-web
git checkout main
```

### A.4.2 `backend/.env` (solo PC B y C)

Debe ser **el mismo archivo en las dos**. Genéralo en la PC B siguiendo el [Paso 2](#paso-2-crear-backendenv) y **cópialo a la PC C** (memoria USB, carpeta compartida o un canal cifrado). La `APP_KEY` debe ser idéntica: con claves distintas, un nodo no puede leer la sesión que creó el otro y el usuario aparece sin sesión al cambiar de nodo. **No generes una `APP_KEY` nueva en cada PC.**

### A.4.3 Frontend compilado (solo PC B y C)

En cada una: `pnpm --dir frontend install` y `pnpm --dir frontend build` (como en el [Paso 5](#paso-5-compilar-el-frontend)). Como alternativa, copia las carpetas `backend/public/build`, `backend/public/personal` y `backend/public/menu` desde un equipo donde ya estén compiladas.

### A.4.4 `docker/.env` (las tres PC, idéntico)

Genera las credenciales una sola vez con el [Paso 3](#paso-3-crear-dockerenv-con-las-credenciales) en la PC A, y luego **añade al final las IPs reales**. Cópialo tal cual a las PC B y C. Con las IPs de ejemplo:

```
DB_PASSWORD=<valor generado>
REDIS_PASSWORD=<valor generado>
REVERB_APP_SECRET=<valor generado>

# Aceptar conexiones de otras PC (por omisión solo escuchan en 127.0.0.1)
ESCUCHA=0.0.0.0

# Dónde está la infraestructura (IP de la PC A y puertos PUBLICADOS)
DB_HOST=192.168.1.10
DB_PORT=5433
REDIS_HOST=192.168.1.10
REDIS_PORT=6380

# Dónde están los nodos, como los ve el balanceador (IP de cada PC : puerto publicado)
NODO1_ADDR=192.168.1.11:8001
NODO2_ADDR=192.168.1.12:8002
REVERB1_ADDR=192.168.1.11:8091
REVERB2_ADDR=192.168.1.12:8092

# Dirección con la que el navegador entra: el balanceador (PC A)
URL_PUBLICA=https://192.168.1.10:8443

# Dirección con la que los nodos ven llegar al balanceador (ver nota)
TRUSTED_PROXIES=192.168.1.10
```

| Grupo | Para qué sirve |
|---|---|
| `ESCUCHA=0.0.0.0` | Sin él los contenedores solo aceptan conexiones locales y las otras PC no llegan. |
| `DB_*` y `REDIS_*` | Los usan los nodos, los Reverb y las colas para llegar a la PC A. Son los puertos **publicados** (5433 y 6380), no los internos. |
| `NODO*_ADDR` y `REVERB*_ADDR` | Los usa Nginx (en la PC A) para llegar a los nodos. |
| `URL_PUBLICA` | Es el `APP_URL`, la dirección HTTPS de la cookie de sesión y del WebSocket. Debe coincidir con la dirección que se escribe en el navegador y figurar en el certificado. |
| `TRUSTED_PROXIES` | Hace que los nodos solo crean en las cabeceras `X-Forwarded-*` del balanceador. |

> **Nota sobre `TRUSTED_PROXIES`.** El valor correcto es la dirección **desde la que el nodo ve llegar al balanceador**. Con Docker Desktop en Windows esa dirección puede aparecer como la puerta de enlace de Docker (`172.x.0.1`) y no como la IP de la PC A. Si al instalar el sitio redirige a `http://` en vez de `https://` o la cookie de sesión no lleva `Secure`, los nodos no están confiando en el balanceador: verifica con qué dirección llegan las peticiones y ajusta este valor. Si se omite la variable, los nodos aceptan cualquier proxy directo (`*`), lo que es menos estricto.

### A.4.5 Certificado HTTPS con la IP (solo PC A)

El certificado debe incluir la dirección con la que se entra al sitio. En la PC A:

```powershell
New-Item -ItemType Directory -Force docker/infra/tls | Out-Null
mkcert -install
mkcert -cert-file docker/infra/tls/server.pem -key-file docker/infra/tls/server-key.pem localhost 127.0.0.1 192.168.1.10
```

**Cada dispositivo que abra el sitio** (otras PC, celulares) debe confiar en la CA que firmó el certificado, o el navegador mostrará una advertencia:

1. En la PC A: `mkcert -CAROOT` muestra la carpeta; copia **solo** `rootCA.pem` (nunca `rootCA-key.pem`).
2. En una PC cliente, en PowerShell **como administrador**: `certutil -addstore -f "ROOT" rootCA.pem`, y reinicia el navegador.
3. En un celular, instala `rootCA.pem` como certificado de CA desde los ajustes de seguridad del dispositivo.

Si cambia la IP de la PC A, hay que regenerar el certificado y actualizar `URL_PUBLICA`.

### A.4.6 Firewall de Windows (las tres PC)

PowerShell **como administrador**, con los puertos que publica cada PC. Se limita el origen para no exponer la base de datos ni Redis más de lo necesario:

```powershell
# PC A · infraestructura
New-NetFirewallRule -DisplayName "TrackEat web" -Direction Inbound -Protocol TCP -LocalPort 8080,8443 -RemoteAddress LocalSubnet -Action Allow -Profile Any
New-NetFirewallRule -DisplayName "TrackEat datos" -Direction Inbound -Protocol TCP -LocalPort 5433,6380 -RemoteAddress 192.168.1.11,192.168.1.12 -Action Allow -Profile Any

# PC B · nodo 1
New-NetFirewallRule -DisplayName "TrackEat nodo1" -Direction Inbound -Protocol TCP -LocalPort 8001,8091 -RemoteAddress 192.168.1.10 -Action Allow -Profile Any

# PC C · nodo 2
New-NetFirewallRule -DisplayName "TrackEat nodo2" -Direction Inbound -Protocol TCP -LocalPort 8002,8092 -RemoteAddress 192.168.1.10 -Action Allow -Profile Any
```

Para quitarlas: `Remove-NetFirewallRule -DisplayName "TrackEat web"` (y lo mismo con los demás nombres).

## A.5 Levantar (en este orden)

El orden no es estricto (los contenedores se reintentan solos), pero así es más limpio. Todos los comandos, desde la raíz del repositorio de cada PC.

**1. PC A, infraestructura:**

```powershell
docker compose --env-file docker/.env -f docker/infra/compose.yml up -d
```

**2. PC B, nodo 1:**

```powershell
docker compose --env-file docker/.env -f docker/nodo/compose.yml up -d --build nodo1 reverb1 cola1
```

**3. PC C, nodo 2:**

```powershell
docker compose --env-file docker/.env -f docker/nodo/compose.yml up -d --build nodo2 reverb2 cola2
```

**4. Crear las tablas y los datos de ejemplo, una sola vez, desde la PC B:**

```powershell
docker compose --env-file docker/.env -f docker/nodo/compose.yml exec -T nodo1 php artisan migrate --seed --force
```

**5. Cargar las fotografías de ejemplo en la PC C.** Cada PC tiene su propio volumen de fotos (ver A.9). El comando anterior las dejó en la PC B; en la PC C se cargan con:

```powershell
docker compose --env-file docker/.env -f docker/nodo/compose.yml exec -T nodo2 php artisan db:seed --class=CatalogoImagenesSeeder --force
```

> El orden y los nombres importan: los nombres al final (`nodo1 reverb1 cola1`) indican a Docker cuál de los dos nodos del archivo levantar en esa PC.

## A.6 Verificar

Desde **cualquier PC** o un celular de la misma red:

| Comprobación | Resultado esperado |
|---|---|
| Abrir `https://192.168.1.10:8443/salud` y recargar varias veces | `"bd":"ok"` y el campo `nodo` alterna entre `"1"` y `"2"` |
| F12 → Red → cabecera `X-Nodo` | Muestra la **IP de la PC** que atendió (`192.168.1.11:8001` o `192.168.1.12:8002`) |
| `http://192.168.1.10:8080/` | Redirige con 301 a `https://192.168.1.10:8443/` |
| Login con una cuenta de ejemplo | Entra sin advertencias de certificado |
| Cambiar de nodo con la sesión abierta | La sesión se mantiene (misma `APP_KEY`) |
| Pedido en una ventana, Comandas en otra | El pedido aparece sin recargar |

`ip_cliente` en `/salud` debe mostrar la IP de quien consulta. Docker Desktop en Windows a veces muestra la puerta de enlace de Docker (`172.x.0.1`); es un detalle de su red, no un fallo del balanceo.

Si algo no conecta, prueba cada enlace desde PowerShell. Todos deben decir `TcpTestSucceeded : True`:

```powershell
Test-NetConnection 192.168.1.10 -Port 5433   # desde B y C: PostgreSQL
Test-NetConnection 192.168.1.10 -Port 6380   # desde B y C: Redis
Test-NetConnection 192.168.1.11 -Port 8001   # desde A: nodo 1
Test-NetConnection 192.168.1.12 -Port 8002   # desde A: nodo 2
Test-NetConnection 192.168.1.11 -Port 8091   # desde A: Reverb 1
Test-NetConnection 192.168.1.12 -Port 8092   # desde A: Reverb 2
```

## A.7 Operación, tolerancia a fallos y apagado

Cada comando se ejecuta en la PC donde corre ese contenedor, con `/salud` recargándose desde otra:

```powershell
docker stop trackeat-nodo2        # (PC C) el sitio sigue; solo responde el nodo 1
docker start trackeat-nodo2
docker stop trackeat-reverb1      # (PC B) el otro Reverb atiende los WebSockets
docker start trackeat-reverb1
docker stop trackeat-db-docker    # (PC A) /salud responde 503 con "bd":"caida"
docker start trackeat-db-docker
```

La prueba más ilustrativa es **apagar una PC entera** (la B o la C): el balanceador la retira del reparto y el sitio sigue con la otra.

Para apagar, en cada PC y con el mismo archivo con que se levantó:

```powershell
docker compose --env-file docker/.env -f docker/infra/compose.yml down   # PC A
docker compose --env-file docker/.env -f docker/nodo/compose.yml down    # PC B y C
```

Para actualizar a una versión nueva: `git pull` en las tres PC; en B y C, `pnpm --dir frontend build`; y repetir el comando `up -d` de cada PC (en B y C, con `--build` y los nombres de sus servicios). Las migraciones se ejecutan una sola vez, desde la PC B:

```powershell
docker compose --env-file docker/.env -f docker/nodo/compose.yml exec -T nodo1 php artisan migrate --force
```

## A.8 Solución de problemas de esta arquitectura

| Síntoma | Causa probable |
|---|---|
| Los nodos no conectan a la base (`/salud` da 503 o `connection refused`) | Firewall de la PC A sin los puertos 5433 y 6380, o `DB_HOST`/`DB_PORT` mal escritos. Probar con `Test-NetConnection`. |
| El balanceador da 502 | No llega a un nodo: `NODO1_ADDR`/`NODO2_ADDR` con IP o puerto mal, o firewall de la PC del nodo sin el 8001/8002. |
| Al cambiar de nodo el usuario pierde la sesión | `backend/.env` distinto entre B y C: la `APP_KEY` debe ser idéntica. |
| La página carga pero el tiempo real no conecta | `URL_PUBLICA` con una IP equivocada (o `127.0.0.1`), puertos 8091/8092 cerrados o Redis inalcanzable desde los nodos. |
| El navegador advierte del certificado | El certificado no incluye la IP de la PC A, o el dispositivo no tiene instalada la CA (A.4.5). |
| Redirige a `http://` o la cookie no lleva `Secure` | `TRUSTED_PROXIES` no corresponde a la dirección desde la que los nodos ven al balanceador (nota de A.4.4). |
| `up` en una PC de nodo levanta contenedores de más | Faltaron los nombres de los servicios al final del comando. |
| `docker compose` no toma el `.env` | Faltó `--env-file docker/.env` o se ejecutó fuera de la raíz del repositorio. |
| Una PC cambió de IP | La IP de la red cambió (DHCP): actualiza `docker/.env` en las tres PC, regenera el certificado si era la PC A y vuelve a levantar. |

## A.9 Limitaciones conocidas de esta arquitectura

- **Fotografías del catálogo por PC.** Cada PC de nodo tiene su propio volumen `trackeat-catalogo`. Las fotos de ejemplo salen idénticas en ambas (paso A.5.5), pero **una foto que el administrador sube desde el panel solo queda en el nodo que atendió esa petición**: el otro nodo no la tendría y se vería rota cuando el balanceador lo elija. Resolverlo exige almacenamiento compartido entre nodos (carpeta de red o almacenamiento de objetos), que queda fuera del alcance actual.
- **Puntos únicos de falla.** PostgreSQL, Redis y Nginx corren juntos en la PC A. Si la PC A cae, el sistema completo queda fuera; replicarlos exigiría replicación de PostgreSQL, Redis Sentinel y un segundo balanceador. Los detalles de qué ocurre con cada falla están en `docs/balanceo.md`.

---

# Apéndice B. Variables de `docker/.env`

Plantilla completa: `docker/.env.example`. Todas, salvo las tres credenciales, son opcionales.

| Variable | Valor por omisión | Descripción | Una PC | Tres servidores |
|---|---|---|---|---|
| `DB_PASSWORD` | — (obligatoria) | Contraseña de PostgreSQL | Sí | Sí, idéntica |
| `REDIS_PASSWORD` | — (obligatoria) | Contraseña de Redis | Sí | Sí, idéntica |
| `REVERB_APP_SECRET` | — (obligatoria) | Secreto de Reverb | Sí | Sí, idéntico |
| `ESCUCHA` | `127.0.0.1` | Interfaz en la que se publican los puertos. `0.0.0.0` acepta otras PC | No | `0.0.0.0` |
| `DB_HOST` / `DB_PORT` | `db` / `5432` | Dónde está PostgreSQL para los nodos | No | IP de la PC A / `5433` |
| `REDIS_HOST` / `REDIS_PORT` | `redis` / `6379` | Dónde está Redis para los nodos | No | IP de la PC A / `6380` |
| `NODO1_ADDR`, `NODO2_ADDR` | `nodo1:8000`, `nodo2:8000` | Dónde ve Nginx cada nodo | No | IP de B : `8001`, IP de C : `8002` |
| `REVERB1_ADDR`, `REVERB2_ADDR` | `reverb1:8081`, `reverb2:8081` | Dónde ve Nginx cada Reverb | No | IP de B : `8091`, IP de C : `8092` |
| `URL_PUBLICA` | `https://localhost:8443` | Origen HTTPS con el que se entra, sin diagonal final | Opcional | `https://IP-de-A:8443` |
| `TRUSTED_PROXIES` | `*` | Dirección del balanceador vista por los nodos | No | IP del balanceador (ver nota A.4.4) |
| `HTTP_PUERTO_PUBLICADO` / `HTTPS_PUERTO_PUBLICADO` | `8080` / `8443` | Puertos del balanceador | Opcional | Opcional |
| `TLS_DIR` | `./tls` | Carpeta con `server.pem` y `server-key.pem`, relativa a `docker/infra` | Opcional | Solo PC A |
| `DB_PUERTO_PUBLICADO` / `REDIS_PUERTO_PUBLICADO` | `5433` / `6380` | Puertos con que se publican la base y Redis | Opcional | Opcional |
| `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY` | Llaves de prueba de Cloudflare | Captcha del registro y el login. En producción, llaves reales del panel de Cloudflare | Opcional | Opcional |

Si cambias `HTTPS_PUERTO_PUBLICADO` o `URL_PUBLICA`, mantén ambos consistentes y que el nombre o la IP figure en el certificado.

# Apéndice C. Puertos y firewall

| Puerto | Servicio | Una PC | Tres servidores: dónde se publica | Quién debe poder llegar |
|---|---|---|---|---|
| 8080 | Nginx, HTTP (redirige a HTTPS) | Local | PC A | Los usuarios |
| **8443** | Nginx, HTTPS (entrada al sitio) | Local | PC A | Los usuarios |
| 5433 | PostgreSQL | Local | PC A | PC B y PC C |
| 6380 | Redis | Local | PC A | PC B y PC C |
| 8001 | Nodo 1 | Local (diagnóstico) | PC B | PC A |
| 8091 | Reverb 1 | Local | PC B | PC A |
| 8002 | Nodo 2 | Local (diagnóstico) | PC C | PC A |
| 8092 | Reverb 2 | Local | PC C | PC A |

En la instalación de una PC no hace falta abrir nada en el firewall: todo se publica solo en `127.0.0.1`. Si se quiere que otros dispositivos entren a una instalación de una sola PC, hay que poner `ESCUCHA=0.0.0.0`, `URL_PUBLICA=https://IP-de-la-PC:8443`, incluir esa IP en el certificado y abrir 8080 y 8443.

# Apéndice D. Estructura de archivos de despliegue

| Archivo | Función |
|---|---|
| `docker/compose.yml` | Modo una PC: incluye los dos siguientes |
| `docker/infra/compose.yml` | PostgreSQL 16, Redis 7 y el balanceador Nginx 1.27 |
| `docker/infra/nginx.conf.template` | Configuración del balanceador (HTTPS, cabeceras de seguridad, reparto por menos conexiones, proxy de WebSocket). Docker la convierte en `/etc/nginx/conf.d/default.conf` al iniciar |
| `docker/infra/tls/` | Certificado y clave del balanceador (los crea mkcert; ignorados por Git) |
| `docker/nodo/compose.yml` | Los dos paquetes de nodo: `nodoN` (app), `reverbN` (WebSocket) y `colaN` (worker de la cola) |
| `docker/Dockerfile` | Imagen única de la aplicación (PHP 8.3 sobre Alpine); la usan app, Reverb y cola |
| `docker/.env` | Credenciales y direcciones del despliegue (privado; ignorado por Git) |
| `docker/.env.example` | Plantilla documentada de `docker/.env` |
| `backend/.env` | `APP_KEY`, correo y variables generales de Laravel (privado; ignorado por Git) |
| `backend/.env.example` | Plantilla de `backend/.env` |

Tras cambiar `nginx.conf.template`, reinicia el balanceador (no basta recargarlo, porque Docker renderiza la plantilla al arrancar) y valida la sintaxis:

```powershell
docker compose --env-file docker/.env -f docker/compose.yml restart balanceador
docker compose --env-file docker/.env -f docker/compose.yml exec -T balanceador nginx -t
```
