# Cómo leer y explicar TrackEat con Docker

Guía para entender, servicio por servicio, cómo está armada la Práctica 5: qué hace cada pieza, dónde vive en el código (archivo y línea), por qué se configuró así y qué pasaría si se cambiara. Está pensada para poder explicarla en voz alta frente al profesor.

Documentos relacionados: [levantar-compose.md](levantar-compose.md) (cómo ejecutarlo), [cambiar a 3 pc.md](cambiar%20a%203%20pc.md) (repartirlo en tres PCs) y [docs/informe-practica-5.md](docs/informe-practica-5.md) (el informe).

> **Sobre los números de línea.** Son los del repositorio en el commit actual de la rama `andryk`. Si alguien edita los archivos, las líneas se corren unas posiciones; los nombres de variable y de bloque siguen siendo la referencia segura.
>
> **Sobre lo que se probó.** Cada vez que digo qué pasa al cambiar algo, lo marco como **(comprobado)** si lo ejecuté en esta práctica, o **(esperado, no probado)** si lo deduzco del funcionamiento de la herramienta. No conviene presentar lo segundo como un hecho comprobado.

## Índice

1. [El mapa completo](#1-el-mapa-completo)
2. [Cómo se lee cada servicio](#2-cómo-se-lee-cada-servicio) (la plantilla que se repite)
3. [Redis](#3-redis)
4. [Reverb](#4-reverb)
5. [La cola (queue worker)](#5-la-cola-queue-worker)
6. [La base de datos (PostgreSQL)](#6-la-base-de-datos-postgresql)
7. [El nodo de aplicación](#7-el-nodo-de-aplicación-nodo1-y-nodo2)
8. [El balanceador (Nginx)](#8-el-balanceador-nginx)
9. [El compose, archivo por archivo](#9-el-compose-archivo-por-archivo)
10. [Cómo se relacionan la PC, Docker y la aplicación](#10-cómo-se-relacionan-la-pc-docker-y-la-aplicación)
11. [El navegador y el frontend](#11-el-navegador-y-el-frontend)
12. [Qué pasa si... (tabla de experimentos)](#12-qué-pasa-si-tabla-de-experimentos)
13. [Problemas reales que aparecieron y cómo se resolvieron](#13-problemas-reales-que-aparecieron-y-cómo-se-resolvieron)
14. [Preguntas que puede hacer el profesor](#14-preguntas-que-puede-hacer-el-profesor)

---

## 1. El mapa completo

### Las piezas

Hay **9 contenedores**. Los dividimos en tres papeles:

| Papel | Contenedores | Se repite |
|---|---|---|
| **Entrada** | `trackeat-balanceador` (Nginx) | 1 |
| **Aplicación** (un "paquete" por nodo) | `nodo1`/`nodo2` (la app), `reverb1`/`reverb2` (WebSockets), `cola1`/`cola2` (trabajos en segundo plano) | 2 |
| **Datos compartidos** | `trackeat-db-docker` (PostgreSQL), `trackeat-redis` | 1 |

### El dibujo

```
 Tu PC (Windows)
 ┌──────────────────────────────────────────────────────────────────────────────┐
 │  Navegador  ──►  127.0.0.1:8080                                              │
 │                      │  (puerto publicado por Docker)                        │
 │  ┌───────────────────▼───────────────────  red de Docker "trackeat_default" ─┐│
 │  │  balanceador (Nginx :80)                                                  ││
 │  │     │ "/"            │ "/app" (WebSocket)                                 ││
 │  │     ▼                ▼                                                    ││
 │  │  nodo1 ◄─┐        reverb1 ◄─┐        ┌─► reverb2        ┌─► nodo2        ││
 │  │  cola1   │        │         │        │    │             │   cola2        ││
 │  │     │    │        │      ┌──┴────────┴────┴──┐          │     │          ││
 │  │     │    │        └────► │      redis        │ ◄────────┘     │          ││
 │  │     │    │               └───────────────────┘                │          ││
 │  │     └────┴───────────────────►  PostgreSQL  ◄──────────────────┴──────────┘│
 │  └───────────────────────────────────────────────────────────────────────────┘│
 └──────────────────────────────────────────────────────────────────────────────┘
```

### Qué ocurre cuando alguien abre una página (petición normal)

1. El navegador pide `http://127.0.0.1:8080/dashboard`. Docker entrega esa conexión al puerto 80 del contenedor del balanceador.
2. Nginx elige uno de los dos nodos con el algoritmo `least_conn` (el que tenga menos conexiones abiertas) y le reenvía la petición.
3. El nodo (Laravel) lee la sesión de PostgreSQL, arma la página y responde. Agrega la cabecera `X-Servidor: 1` o `2`.
4. Nginx devuelve la respuesta y le agrega `X-Nodo: <IP del nodo>`.

### Qué ocurre cuando un cliente confirma un pedido (tiempo real)

Este es el recorrido más importante, porque pasa por **todas** las piezas. Lo comprobamos de punta a punta con un script y con el navegador.

| Paso | Qué pasa | Dónde está en el código |
|---|---|---|
| 1 | El navegador del cliente envía `POST /api/pedidos` | `backend/routes/web.php:38` |
| 2 | Nginx lo manda a un nodo, por ejemplo `nodo2` | `docker/infra/nginx.conf.template:54-68` |
| 3 | El nodo guarda el pedido en PostgreSQL dentro de una transacción | `backend/app/Services/PedidoService.php` (método `crear`) |
| 4 | Al terminar la transacción lanza el evento `PedidoActualizado` | `PedidoService.php:80` y `app/Events/PedidoActualizado.php:10` |
| 5 | Como el evento es `ShouldBroadcast`, **no se envía en el momento**: se guarda como un trabajo en la tabla `jobs` de PostgreSQL | `PedidoActualizado.php:10` |
| 6 | Un worker de cola (`cola1` o `cola2`) toma el trabajo y lo publica en **su propio Reverb** (`reverb1` o `reverb2`) | `docker/nodo/compose.yml:54,68,74,88` (`REVERB_HOST`) y `backend/config/broadcasting.php:10` |
| 7 | Ese Reverb publica el mensaje en **Redis** | `backend/config/reverb.php:40-51` |
| 8 | **Los dos** Reverb reciben el mensaje de Redis y lo entregan a las pantallas que tengan conectadas | `reverb.php:41` (`REVERB_SCALING_ENABLED`) |
| 9 | La pantalla del cocinero, conectada por `/app` a cualquiera de los dos Reverb, recibe el aviso y pide los datos nuevos | `frontend/src/lib/trackeat.js:131-147` |

La idea de fondo: **el pedido se guarda en un lugar (PostgreSQL), y el aviso viaja por otro (cola → Reverb → Redis → navegador).** Si el aviso falla, los datos no se pierden.

---

## 2. Cómo se lee cada servicio

Para cada servicio se repiten los mismos apartados, así puedes explicar cualquiera con la misma estructura:

1. **Qué es y qué hace normalmente** (en cualquier proyecto, no solo en este).
2. **Dónde vive aquí** (contenedor, archivo, línea).
3. **Su configuración, línea por línea.**
4. **Por qué se hizo así.**
5. **Qué pasa si se cambia** (con la marca comprobado / esperado).
6. **Cómo verlo funcionando.**

---

## 3. Redis

### 3.1 Qué es y qué hace normalmente

Redis es una base de datos **en memoria** muy rápida, de tipo clave-valor. Normalmente se usa para:

- **Caché** (guardar resultados costosos para no recalcularlos).
- **Sesiones** de usuario.
- **Colas** de trabajos.
- **Mensajería publicar/suscribir (pub/sub):** un programa "publica" un mensaje en un canal y todos los que estén "suscritos" a ese canal lo reciben al instante. Los mensajes no se guardan: si nadie escucha en ese momento, se pierden.

### 3.2 Dónde vive aquí

| Qué | Dónde |
|---|---|
| Contenedor `trackeat-redis` | `docker/infra/compose.yml:30-42` |
| Quién se conecta | `reverb1` y `reverb2` (y, por tener las mismas variables, también los nodos y las colas, aunque no lo usan) |
| Variables de conexión | `docker/nodo/compose.yml:40-43` |
| Cómo las lee Laravel | `backend/config/reverb.php:40-51` |

**En este proyecto Redis hace una sola cosa: ser el canal de mensajes entre los dos Reverb.** No guarda sesiones (están en PostgreSQL), ni caché (PostgreSQL), ni la cola (PostgreSQL). Eso se comprueba en las líneas `SESSION_DRIVER`, `CACHE_STORE` y `QUEUE_CONNECTION` de `docker/nodo/compose.yml:29-31`, que valen `database`.

### 3.3 Configuración, línea por línea

`docker/infra/compose.yml`:

| Línea | Contenido | Qué hace |
|---|---|---|
| 30 | `redis:` | Nombre del servicio. **También es su nombre en la red de Docker**: por eso los demás contenedores lo encuentran con `REDIS_HOST=redis`. |
| 31 | `image: redis:7-alpine` | Imagen oficial de Redis 7 sobre Alpine Linux (pequeña). |
| 32 | `container_name: trackeat-redis` | Nombre visible en `docker ps` y en los comandos `docker stop`. |
| 33 | `restart: unless-stopped` | Si el contenedor se cae o Docker se reinicia, vuelve a levantarse solo; solo se queda apagado si tú lo apagaste. |
| 34 | `command: ["redis-server", "--requirepass", "${REDIS_PASSWORD}", "--save", "", "--appendonly", "no"]` | Arranca Redis con tres opciones, explicadas abajo. |
| 35-36 | `environment: REDIS_PASSWORD: ...` | Pasa la contraseña al contenedor para que el *healthcheck* la pueda usar. |
| 37 | `ports: ["${ESCUCHA:-127.0.0.1}:${REDIS_PUERTO_PUBLICADO:-6380}:6379"]` | Publica el puerto 6379 del contenedor como el 6380 de tu PC, solo en `127.0.0.1` salvo que `ESCUCHA=0.0.0.0`. |
| 38-42 | `healthcheck` | Cada 2 s ejecuta `redis-cli ping` y espera `PONG`. Docker marca el contenedor como `healthy` o no. |

Las tres opciones del `command` (línea 34):

- `--requirepass <clave>`: Redis exige contraseña. Sin esto, cualquier equipo de la red podría escribir en él.
- `--save ""`: **no** guarda instantáneas en disco.
- `--appendonly no`: **no** registra cada escritura en disco.

Las dos últimas significan que Redis no recuerda nada al reiniciar. Es intencional, ver 3.4.

Cómo lo usa Laravel, `backend/config/reverb.php`:

| Línea | Contenido | Qué hace |
|---|---|---|
| 40-41 | `'scaling' => ['enabled' => env('REVERB_SCALING_ENABLED', false)` | **El interruptor.** Con `true`, cada Reverb publica y escucha en Redis. Por omisión viene apagado; lo encendemos en `docker/nodo/compose.yml:40`. |
| 42 | `'channel' => env('REVERB_SCALING_CHANNEL', 'reverb')` | Nombre del canal de Redis donde se intercambian los mensajes. |
| 45-49 | `host`, `port`, `password`, `database` desde `REDIS_*` | A dónde y con qué clave conectarse. |

### 3.4 Por qué se hizo así

- **Por qué hace falta Redis:** cada Reverb solo conoce a las pantallas que están conectadas a **él**. Sin Redis, una comanda publicada en `reverb1` jamás llegaría a una pantalla conectada a `reverb2`. Redis es el "altavoz" que repite cada mensaje a todos los Reverb.
- **Por qué uno solo y no uno por nodo:** si cada nodo tuviera su Redis, `reverb1` publicaría en uno y `reverb2` escucharía en otro, y tampoco se enterarían. Tiene que ser el mismo para los dos.
- **Por qué sin guardar en disco:** el pub/sub no necesita memoria. Los mensajes se entregan y se olvidan; guardar algo solo gastaría disco.
- **Por qué contraseña:** en el modo de tres PCs Redis queda expuesto en la red.
- **Por qué no se instaló la extensión `phpredis` en la imagen:** Reverb usa su propio cliente de Redis hecho en PHP. Lo comprobamos: `extension_loaded('redis')` da `false` y el tiempo real funciona **(comprobado)**.

### 3.5 Qué pasa si se cambia

| Cambio | Efecto |
|---|---|
| `REVERB_SCALING_ENABLED: "false"` | Cada Reverb queda aislado; solo entrega a sus propios clientes. Las pantallas conectadas al otro Reverb no verían los pedidos en vivo, aunque el sondeo cada 5 s las pondría al día **(esperado, no probado)**. |
| Apagar Redis (`docker stop trackeat-redis`) | La app sigue funcionando, pero los eventos dejan de cruzar de un Reverb a otro **(esperado, no probado)**. |
| Cambiar `REDIS_PASSWORD` solo en un lado | Los Reverb no pueden autenticarse en Redis y fallan al arrancar o al publicar **(esperado, no probado)**. Debe cambiarse igual en infra y nodos; por eso ambos leen `${REDIS_PASSWORD}`. |
| Quitar `--requirepass` | Funciona igual, pero cualquiera con acceso al puerto puede usar Redis **(esperado)**. |
| Quitar `--save "" --appendonly no` | Funciona igual; solo habría escrituras a disco innecesarias **(esperado)**. |
| `ESCUCHA=0.0.0.0` | Redis queda accesible desde otras PCs (necesario para el modo de tres PCs). |

### 3.6 Cómo verlo funcionando

```powershell
docker exec trackeat-redis sh -c 'REDISCLI_AUTH="$REDIS_PASSWORD" redis-cli ping'          # responde PONG
docker exec trackeat-redis sh -c 'REDISCLI_AUTH="$REDIS_PASSWORD" redis-cli pubsub channels'   # canales activos
```

El segundo lista el canal `reverb` mientras los Reverb están encendidos **(comprobado)**: es la prueba de que los Reverb están suscritos a Redis. Además, un cliente conectado al `reverb2` recibió un evento publicado en `reverb1` **(comprobado)**.

---

## 4. Reverb

### 4.1 Qué es y qué hace normalmente

Laravel Reverb es el **servidor de WebSockets** oficial de Laravel. Un WebSocket es una conexión que se queda abierta entre el navegador y el servidor, por la que el servidor puede **empujar** mensajes sin que el navegador pregunte. Se usa para chats, notificaciones, tableros en vivo y, aquí, la pantalla de cocina.

Habla el protocolo de **Pusher**, por eso en el navegador se usan las librerías `pusher-js` y `laravel-echo`.

Conceptos que aparecen:

- **Canal:** una "sala" a la que se suscribe el navegador. Aquí son canales **privados**: para entrar, el navegador debe pedir autorización al servidor Laravel (`/broadcasting/auth`).
- **Evento:** el mensaje que se emite en un canal (aquí `pedido.actualizado`).
- **App key / secret:** identificadores de la "aplicación" dentro de Reverb. La *key* es pública (la recibe el navegador); el *secret* es privado y firma las publicaciones.

### 4.2 Dónde vive aquí

| Qué | Dónde |
|---|---|
| Contenedores `reverb1`, `reverb2` | `docker/nodo/compose.yml:57-62` y `77-82` |
| Configuración del servidor | `backend/config/reverb.php` |
| Configuración de **publicar** desde la app | `backend/config/broadcasting.php:6-14` |
| Qué canales existen y quién puede entrar | `backend/routes/channels.php:7-9` |
| Qué evento se emite | `backend/app/Events/PedidoActualizado.php` |
| Cliente del navegador | `frontend/src/lib/trackeat.js:131-147` |
| Ruta de entrada por Nginx | `docker/infra/nginx.conf.template:43-52` (`location /app`) |

### 4.3 Configuración, línea por línea

**El contenedor** (`docker/nodo/compose.yml`):

| Línea | Contenido | Qué hace |
|---|---|---|
| 57 | `reverb1:` | Nombre del servicio y nombre DNS dentro de la red de Docker. |
| 58 | `<<: *app` | Hereda toda la configuración común del bloque `x-app` (imagen, variables, reinicio). Ver sección 9. |
| 60 | `command: ["php", "artisan", "reverb:start", "--host=0.0.0.0", "--port=8081"]` | Reemplaza el comando de la imagen (que levanta la web) por el servidor Reverb. `--host=0.0.0.0` acepta conexiones de otros contenedores (con `127.0.0.1` solo escucharía dentro de sí mismo). |
| 61 | `environment: { <<: *app-env, NODO: "1", REVERB_HOST: reverb1 }` | Reutiliza las variables comunes y fija a qué nodo pertenece y su nombre. |
| 62 | `ports: ["${ESCUCHA:-127.0.0.1}:8091:8081"]` | Publica el 8081 interno como el 8091 de tu PC (el 8081 de la PC puede estar ocupado por `presentar.bat`). |

**Las variables comunes** (`docker/nodo/compose.yml`, bloque `x-app`):

| Línea | Variable | Para qué sirve |
|---|---|---|
| 32 | `BROADCAST_CONNECTION: reverb` | Le dice a Laravel que, al emitir un evento, use el driver `reverb`. |
| 33 | `REVERB_APP_ID: trackeat` | Identificador de la aplicación en Reverb. |
| 34 | `REVERB_APP_KEY: trackeat-balanceo` | Llave **pública**. Laravel se la entrega al navegador por `/api/sesion`. |
| 35 | `REVERB_APP_SECRET: ${REVERB_APP_SECRET}` | Llave **privada** para firmar. Nunca llega al navegador. Se configura en `docker/.env`. |
| 36 | `REVERB_PORT: "8081"` | Puerto al que la app publica sus eventos. |
| 37 | `REVERB_SCHEME: http` | Protocolo (sin cifrar; en producción sería `https`). |
| 38 | `REVERB_ALLOWED_ORIGINS: "*"` | Desde qué sitios web se aceptan conexiones. `*` acepta cualquiera. |
| 40 | `REVERB_SCALING_ENABLED: "true"` | Activa la coordinación entre Reverb mediante Redis (ver sección 3). |
| 44 | `TRACKEAT_SOCKET_URL: ${URL_PUBLICA:-http://127.0.0.1:8080}` | Dirección que se le da al **navegador** para conectar el WebSocket. Es la del balanceador, no la de un Reverb. |
| 54, 61, 68 | `REVERB_HOST: reverb1` (y `reverb2` en el nodo 2) | **A qué Reverb publica este nodo.** Cada nodo, su cola y su Reverb apuntan al Reverb de su propio nodo. |

**El servidor** (`backend/config/reverb.php`):

| Línea | Contenido | Qué hace |
|---|---|---|
| 32-33 | `host`, `port` | Dónde escucha el servidor (se sobreescriben con las opciones del `command`). |
| 35 | `'hostname' => env('REVERB_HOST')` | Nombre con el que se identifica este servidor. |
| 40-51 | `'scaling' => [...]` | Coordinación por Redis (sección 3). |
| 76-78 | `key`, `secret`, `app_id` | Las credenciales de la aplicación, tomadas de las variables de arriba. |
| 85 | `allowed_origins` | Lista de orígenes permitidos, desde `REVERB_ALLOWED_ORIGINS`. |
| 86-87 | `ping_interval` (60) y `activity_timeout` (30) | Cada cuánto el servidor verifica que el cliente siga vivo. |

**Cómo se publica desde la app** (`backend/config/broadcasting.php`):

| Línea | Contenido | Qué hace |
|---|---|---|
| 10 | `'host' => env('REVERB_HOST'...), 'port' => env('REVERB_PORT'...)` | Laravel publica hacia `reverb1:8081`. Es una llamada HTTP interna. |
| 13 | `'client_options' => ['timeout' => 5, 'connect_timeout' => 2]` | Si el Reverb no contesta en 2 s (conexión) o 5 s (total), la publicación falla y se reintenta. |

**Las reglas de acceso** (`backend/routes/channels.php`):

| Línea | Canal | Quién puede entrar |
|---|---|---|
| 7 | `trackeat.personal` | Solo cuentas activas con rol administrador, cocinero, mesero o cajero. |
| 8 | `trackeat.usuario.{id}` | Solo el propio usuario, para ver **sus** pedidos. |
| 9 | `trackeat.demo.{room}` | Usuarios de una sala de demostración. |

### 4.4 Por qué se hizo así

- **Un Reverb por nodo (en vez de uno solo):** si hay un único Reverb y cae, todas las pantallas pierden el tiempo real. Con dos, las pantallas se reconectan al sobreviviente.
- **Cada nodo publica en su propio Reverb (en vez de en cualquiera):** es lo más simple y evita un salto de red. Si ese Reverb está caído, ese nodo no puede publicar durante un momento; la cola reintenta (sección 5).
- **Las pantallas entran por Nginx (`/app`), no directo a un Reverb:** el navegador solo conoce una dirección; Nginx decide a cuál Reverb lo manda y deja de usar uno caído.
- **Canales privados con autorización:** un cliente solo recibe sus pedidos; el personal recibe todos.

### 4.5 Qué pasa si se cambia

| Cambio | Efecto |
|---|---|
| `REVERB_APP_KEY` distinto entre nodos o entre Reverb | El navegador recibe una llave que el Reverb no reconoce y no conecta **(esperado)**. Debe ser igual en todos. |
| `REVERB_APP_SECRET` distinto entre la app y el Reverb | La publicación es rechazada por firma inválida **(esperado)**. |
| `REVERB_HOST` de `nodo1` apuntando a `reverb2` | Funciona igual: Redis reparte el mensaje a ambos **(esperado)**. Se hizo local para no depender del otro nodo. |
| `--host=127.0.0.1` en el `command` | Nadie fuera del contenedor puede conectar: ni Nginx ni los nodos **(esperado)**. |
| `TRACKEAT_SOCKET_URL=http://127.0.0.1:8080` usado desde **otra PC** | El navegador de esa PC intentaría conectar a **su propio** `127.0.0.1` y fallaría **(esperado)**. Por eso en tres PCs se usa `URL_PUBLICA` con la IP real. |
| Apagar `reverb1` | Las conexiones nuevas van a `reverb2`; un evento publicado por `nodo2` llegó a un cliente nuevo **(comprobado con un cliente WebSocket propio)**. |
| Apagar `reverb1` con la pantalla real abierta | Debería reconectarse sola (`pusher-js`) o ponerse al día por el sondeo de 5 s **(esperado, no probado en el navegador)**. |
| Apagar `reverb1` y publicar desde `nodo1` | Esa publicación falla y se reintenta (sección 5) **(esperado, no probado)**. |

### 4.6 Cómo verlo funcionando

```powershell
docker logs trackeat-reverb1              # arranque del servidor
docker stop trackeat-reverb1              # las conexiones nuevas van al otro
docker start trackeat-reverb1
```

En el navegador, F12 > Red > filtro `WS`: aparece una conexión a `ws://127.0.0.1:8080/app/trackeat-balanceo`. En el Dashboard o en Comandas hay un punto verde cuando el tiempo real está conectado.

---

## 5. La cola (queue worker)

### 5.1 Qué es y qué hace normalmente

Una **cola de trabajos** es una lista de tareas pendientes. La aplicación web, en vez de hacer una tarea lenta o que puede fallar durante la petición del usuario, **la anota** en la cola y responde enseguida. Un proceso aparte, el **worker**, va tomando tareas de la lista y las ejecuta.

Se usa para enviar correos, procesar imágenes, generar reportes o, aquí, **avisar al Reverb**.

Ventajas: la respuesta al usuario es rápida; si la tarea falla se puede **reintentar** sin que el usuario se entere.

### 5.2 Dónde vive aquí

| Qué | Dónde |
|---|---|
| Contenedores `cola1`, `cola2` | `docker/nodo/compose.yml:64-68` y `84-88` |
| Dónde se guarda la lista de tareas | Tabla `jobs` de PostgreSQL (migración `0001_01_01_000002_create_jobs_table.php`) |
| Tareas que fallan definitivamente | Tabla `failed_jobs` (`backend/config/queue.php:106-110`) |
| Qué tarea se encola | `PedidoActualizado` (`backend/app/Events/PedidoActualizado.php:10`) |
| Dónde se lanza el evento | `backend/app/Services/PedidoService.php:80, 109, 137` (crear, avanzar y cancelar un pedido) |

### 5.3 Configuración, línea por línea

`docker/nodo/compose.yml`:

| Línea | Contenido | Qué hace |
|---|---|---|
| 31 | `QUEUE_CONNECTION: database` | La cola vive en PostgreSQL (tabla `jobs`), no en Redis. |
| 67 | `command: ["php", "artisan", "queue:work", "--sleep=1", "--tries=3"]` | Arranca el worker. |

Las opciones del `command`:

- `queue:work`: se queda corriendo en bucle, tomando tareas.
- `--sleep=1`: cuando no hay tareas, espera 1 s antes de volver a preguntar a la base.
- `--tries=3`: intento máximo **por omisión** para tareas que no declaran el suyo.

`backend/app/Events/PedidoActualizado.php`:

| Línea | Contenido | Qué hace |
|---|---|---|
| 10 | `implements ShouldBroadcast, ShouldDispatchAfterCommit` | `ShouldBroadcast` = "no emitas ahora, **encola** la emisión". `ShouldDispatchAfterCommit` = "espera a que la transacción de la base termine bien antes de encolar". |
| 12 | `public int $tries = 5;` | Esta tarea se intenta hasta **5 veces**. Prevalece sobre el `--tries=3` del worker. |
| 14 | `public array $backoff = [1, 2, 5, 10];` | Esperas entre intentos: 1, 2, 5 y 10 segundos (unos 18 s en total). |
| 26 | `broadcastOn()` | A qué canales se emite: `trackeat.personal` y `trackeat.usuario.{id}`. |
| 36 | `broadcastAs()` | Nombre del evento: `pedido.actualizado`. |

`backend/config/queue.php`:

| Línea | Contenido | Qué hace |
|---|---|---|
| 16 | `'default' => env('QUEUE_CONNECTION', 'database')` | Cola por omisión. |
| 37-42 | `'database' => [... 'table' => 'jobs', 'retry_after' => 90]` | Usa la tabla `jobs`. Si un worker toma una tarea y no termina en 90 s, se considera perdida y otro worker puede tomarla. |

### 5.4 Por qué se hizo así

- **Por qué una cola para los avisos:** publicar en Reverb es una llamada de red que puede tardar o fallar. Si se hiciera dentro de la petición, un Reverb lento haría lento el pedido del cliente, y un Reverb caído lo haría fallar. Con la cola, **el pedido siempre se guarda y el aviso se reintenta aparte**.
- **Por qué `ShouldDispatchAfterCommit`:** evita avisar de un pedido que luego se deshace por un error.
- **Por qué cola en la base de datos y no en Redis:** ya existe PostgreSQL y es transaccional; añadir Redis a ese papel no aportaba nada y habría hecho a Redis más crítico (si Redis cayera, se perdería la cola, y no solo los avisos).
- **Por qué una cola por nodo:** así el trabajo de avisar sigue aunque caiga el otro nodo. Dos workers sobre la misma tabla son seguros: cada tarea la reserva un solo worker.

### 5.5 Qué pasa si se cambia

| Cambio | Efecto |
|---|---|
| Apagar `cola1` y `cola2` | Los pedidos se siguen guardando, pero los avisos se quedan acumulados en `jobs`; las pantallas se enteran solo por el sondeo de 5 s **(esperado, no probado)**. Al volver las colas, se envían de golpe. |
| Quitar `--sleep=1` | El worker usa 100% de un núcleo preguntando sin parar a la base **(esperado)**. |
| `$tries = 1` | Un solo intento; si el Reverb está caído en ese instante, el aviso se pierde **(esperado)**. |
| `QUEUE_CONNECTION=sync` | La publicación se haría dentro de la petición del usuario: más lenta y sensible a fallos **(esperado)**. |
| Levantar la app sin haber corrido `migrate` | Los workers y Reverb **se reinician en bucle** porque consultan la tabla `cache` (el comando `queue:restart` la usa) y no existe. **Lo vimos en el primer arranque (comprobado)** y se resuelve corriendo `migrate --seed`. |

### 5.6 Cómo verlo funcionando

```powershell
docker logs trackeat-cola1
docker exec trackeat-db-docker psql -U trackeat -d trackeat -c "select count(*) from jobs"          # tareas pendientes
docker exec trackeat-db-docker psql -U trackeat -d trackeat -c "select count(*) from failed_jobs"   # tareas fallidas
```

En condiciones normales `jobs` está casi siempre en 0, porque los workers las consumen en menos de un segundo.

---

## 6. La base de datos (PostgreSQL)

### 6.1 Qué es y qué hace normalmente

PostgreSQL es una base de datos **relacional**: guarda la información en tablas con filas y columnas, con transacciones (o se guarda todo el cambio, o nada) y consultas SQL.

### 6.2 Dónde vive aquí

| Qué | Dónde |
|---|---|
| Contenedor `trackeat-db-docker` | `docker/infra/compose.yml:11-27` |
| Volumen donde se guardan los datos | `trackeat-docker-db` (`docker/infra/compose.yml:19-20` y `58-60`) |
| Cómo se conectan los nodos | `docker/nodo/compose.yml:22-28` |
| Tablas de la aplicación | `backend/database/migrations/` |
| Datos de ejemplo | `backend/database/seeders/DemoSeeder.php` |

### 6.3 Configuración, línea por línea

`docker/infra/compose.yml`:

| Línea | Contenido | Qué hace |
|---|---|---|
| 12 | `image: postgres:16-alpine` | PostgreSQL 16 sobre Alpine. |
| 15-18 | `POSTGRES_DB / USER / PASSWORD` | **Solo la primera vez** que se crea el volumen, Postgres crea esa base, ese usuario y esa contraseña. |
| 19-20 | `volumes: - trackeat-docker-db:/var/lib/postgresql/data` | Guarda los datos fuera del contenedor, en un volumen de Docker. Así sobreviven si el contenedor se borra. |
| 22 | `ports: ["${ESCUCHA:-127.0.0.1}:${DB_PUERTO_PUBLICADO:-5433}:5432"]` | Se publica en el **5433** de tu PC (no el 5432) para no chocar con un PostgreSQL local. |
| 23-27 | `healthcheck: pg_isready ...` | Cada 2 s comprueba que la base acepte conexiones. |

`docker/nodo/compose.yml`:

| Línea | Contenido | Qué hace |
|---|---|---|
| 22 | `DB_CONNECTION: pgsql` | Laravel usa el driver de PostgreSQL. |
| 23-24 | `DB_HOST: ${DB_HOST:-db}`, `DB_PORT: ${DB_PORT:-5432}` | En una PC, `db` es el nombre del contenedor y 5432 su puerto interno. En tres PCs se sobreescriben con la IP y el puerto publicado (5433). |
| 28 | `PGCONNECT_TIMEOUT: "1"` | Si la base no contesta, la conexión se rinde en 1 s. La intención es que un nodo con la base caída falle rápido (y `/salud` responda 503) en vez de quedarse colgado esperando. |
| 29-31 | `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` = `database` | La sesión, la caché y la cola **también** viven en PostgreSQL. |

### 6.4 Qué hay dentro

PostgreSQL cumple **cuatro** funciones en este proyecto:

| Función | Tabla | Por qué importa para el balanceo |
|---|---|---|
| Datos del negocio | `users`, `pedidos`, `items_pedido`, `pedidos_estacion`, `productos`, `categorias`, `pagos`, `cortes`, `historial_estados`, `imagenes_producto` (`create_trackeat_tables`) | Una sola fuente de verdad: lo que guarda un nodo lo ve el otro. |
| **Sesiones** | `sessions` | Un usuario puede cambiar de nodo entre peticiones sin perder la sesión. |
| Caché | `cache` | Compartida entre nodos. |
| Cola de trabajos | `jobs`, `failed_jobs` | Cualquier nodo puede encolar y cualquier worker consumir. |

### 6.5 Por qué se hizo así

- **Una sola base compartida:** lo exige la práctica. Si cada nodo tuviera la suya, un pedido guardado en un nodo no existiría en el otro.
- **La sesión en la base:** es la respuesta a la pregunta de la práctica «¿cómo sobrevive la sesión al cambio de nodo?». Es el **almacén compartido**. Se descartó `ip_hash` (muchos clientes de un restaurante salen por la misma IP y caerían en un solo nodo) y el testigo firmado (no se puede invalidar antes de que caduque).
- **Todo en PostgreSQL y no en Redis:** menos piezas críticas y todo transaccional.

### 6.6 Qué pasa si se cambia

| Cambio | Efecto |
|---|---|
| Cambiar `DB_PASSWORD` después de que ya existe el volumen | **Trampa:** Postgres solo lee `POSTGRES_PASSWORD` al crear el volumen. Los nodos usarían la clave nueva y la base seguiría con la vieja: no conectan **(esperado)**. Solución: borrar el volumen (`down -v`, pierde datos) o cambiar la clave dentro de Postgres. |
| `docker compose down -v` | Borra también el volumen: se pierden todos los datos y hay que volver a correr `migrate --seed` **(esperado)**. |
| `docker compose down` (sin `-v`) | Conserva los datos **(comprobado)**: tras apagar y volver a levantar, los pedidos siguen. |
| Apagar la base (`docker stop trackeat-db-docker`) | `/salud` responde 503 con `"bd":"caida"`; al encenderla vuelve a 200 sin tocar los nodos **(comprobado)**. |
| `SESSION_DRIVER=file` en los nodos | Cada nodo guardaría la sesión en su propio disco: al cambiar de nodo, el usuario aparecería sin sesión **(esperado; es el caso que advierte el PDF)**. |
| `APP_KEY` distinta en cada nodo | Un nodo no puede descifrar la cookie que creó el otro: el usuario pierde la sesión al cambiar **(esperado; lo advierte el PDF)**. |

### 6.7 Cómo verlo funcionando

```powershell
docker exec trackeat-db-docker psql -U trackeat -d trackeat -c "\dt"                          # lista las tablas
docker exec trackeat-db-docker psql -U trackeat -d trackeat -c "select numero_pedido, estado from pedidos order by id desc limit 5"
docker exec trackeat-db-docker psql -U trackeat -d trackeat -c "select count(*) from sessions"
```

---

## 7. El nodo de aplicación (`nodo1` y `nodo2`)

### 7.1 Qué es y qué hace normalmente

Es la aplicación Laravel en sí (PHP): recibe peticiones HTTP, consulta la base, aplica las reglas del negocio y responde con una página o con datos en JSON. "Nodo" significa una copia de esa aplicación. Tener dos copias idénticas permite repartir la carga y seguir funcionando si una cae.

### 7.2 Dónde vive aquí

| Qué | Dónde |
|---|---|
| Contenedores `nodo1`, `nodo2` | `docker/nodo/compose.yml:50-55` y `71-75` |
| Cómo se construye la imagen | `docker/Dockerfile` |
| Rutas de la aplicación | `backend/routes/web.php`, `auth.php`, `salud.php` |
| Identificación del nodo | `backend/app/Http/Middleware/IdentificarNodo.php`, `backend/config/trackeat.php:8` |
| Confianza en el balanceador | `backend/bootstrap/app.php:22-28` |

### 7.3 El Dockerfile, línea por línea

| Línea | Contenido | Qué hace |
|---|---|---|
| 3 | `FROM php:8.3-cli-alpine` | Parte de PHP 8.3 en su versión de línea de comandos, sobre Alpine. |
| 5-7 | `RUN apk add ... docker-php-ext-install pdo_pgsql bcmath opcache pcntl zip` | Instala las extensiones de PHP que necesita: `pdo_pgsql` (hablar con PostgreSQL), `pcntl` (manejo de procesos, lo usan el worker y Reverb), `opcache` y otras. Además escribe una configuración de PHP (línea 7), explicada abajo. |
| 9 | `COPY --from=composer:2 ...` | Copia Composer (el gestor de paquetes de PHP). |
| 13-14 | `COPY backend/composer.json composer.lock ./` y `RUN composer install ...` | **Primero solo la lista de dependencias.** Docker guarda cada paso en caché: mientras `composer.lock` no cambie, no vuelve a descargar los paquetes. Por eso el segundo `build` tarda segundos. |
| 16 | `COPY backend/ ./` | Copia el código de la aplicación **dentro de la imagen**, incluyendo `public/build` (el frontend ya compilado). |
| 17-18 | `composer dump-autoload` y `mkdir ...` | Genera el cargador de clases y crea carpetas de trabajo. |
| 21 | `CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]` | El comando por omisión: el servidor web de desarrollo de Laravel. `--host=0.0.0.0` es **obligatorio** (lo dice el PDF): sin él solo atendería dentro de su propio contenedor y Nginx no lo alcanzaría. |

La línea 7 deja en `trackeat.ini`: `opcache.validate_timestamps=0`. **Esto tiene una consecuencia práctica importante:** PHP no vuelve a revisar si los archivos cambiaron, y además el código está *dentro* de la imagen. **Cualquier cambio en el código o en el frontend requiere reconstruir la imagen** (`docker compose -f docker/compose.yml build` y luego `up -d`). Ese fue el origen del error del Dashboard (sección 13).

### 7.4 Cómo reciben su configuración (dos fuentes)

En `docker/nodo/compose.yml`:

- Línea 17: `env_file: ../../backend/.env`. Carga el archivo `backend/.env` (que **no** está en git ni dentro de la imagen) al arrancar el contenedor. De ahí salen `APP_KEY`, `PHP_CLI_SERVER_WORKERS=4` y otros.
- Líneas 19-46: `environment:`. Variables fijadas por el compose.

**Si una variable está en las dos, gana `environment`.** Por eso aunque `backend/.env` diga `DB_HOST=127.0.0.1`, el contenedor usa `db`.

`PHP_CLI_SERVER_WORKERS=4` hace que `artisan serve` atienda hasta 4 peticiones a la vez (el PDF avisa que en Windows atiende una a la vez; en estos contenedores Linux no).

### 7.5 Lo que hace distinto a un nodo

| Qué | Dónde | Detalle |
|---|---|---|
| Su número | `docker/nodo/compose.yml:54` (`NODO: "1"`) y `:74` (`NODO: "2"`) | Es **lo único** que cambia entre los dos nodos, además de a qué Reverb apuntan. |
| Se expone como configuración | `backend/config/trackeat.php:8` (`'nodo' => env('NODO', gethostname())`) | Lee la variable. |
| Se muestra en cada respuesta | `backend/app/Http/Middleware/IdentificarNodo.php` + `bootstrap/app.php:29` | Añade la cabecera `X-Servidor` a **todas** las respuestas. |
| Se muestra en `/salud` | `backend/routes/salud.php:17` | Campo `nodo`. |

### 7.6 `/salud`: la comprobación de verdad

`backend/routes/salud.php`:

| Línea | Qué hace |
|---|---|
| 11 | `DB::select('select 1')`: una consulta real. Si la base no contesta, lanza una excepción. |
| 12-14 | Si falla, responde **503** con `{"nodo":..., "bd":"caida"}`. |
| 16-20 | Si va bien, responde 200 con el nodo, `"bd":"ok"` y la IP del cliente. |

Está registrada **fuera** del grupo `web` (`bootstrap/app.php:17-19`) para que no inicie sesión ni cree cookies cada vez que algo la consulte. El PDF pide justamente que revise algo real y no responda 200 siempre.

### 7.7 Confiar en el balanceador (`trustProxies`)

`backend/bootstrap/app.php:22-28`. Detrás de Nginx, la conexión que ve el nodo viene de Nginx, no del usuario. `trustProxies` le dice a Laravel que **crea** en las cabeceras `X-Forwarded-For`, `X-Forwarded-Proto` y `X-Forwarded-Host` que Nginx agrega. Sin eso, `request()->ip()` devolvería la IP del balanceador y los enlaces se armarían con el host equivocado.

El valor viene de `TRUSTED_PROXIES` (`docker/nodo/compose.yml:46`, por omisión `*`). Las líneas 25-28 aceptan `*` o una lista de IPs separadas por comas.

### 7.8 Qué pasa si se cambia

| Cambio | Efecto |
|---|---|
| Editar el código y solo hacer `up -d` | **No se refleja**: el código está en la imagen. Hay que hacer `build` primero **(comprobado: así quedó el frontend viejo)**. |
| Quitar `--host=0.0.0.0` | Nginx no alcanza al nodo: 502 **(esperado)**. |
| `NODO: "1"` en los dos nodos | Los dos se identificarían igual y no se podría comprobar el reparto **(esperado)**. |
| `TRUSTED_PROXIES` con una IP equivocada | `ip_cliente` mostraría la IP del balanceador y los enlaces podrían salir con el host incorrecto **(esperado)**. |
| Apagar `nodo2` | El sitio sigue desde el nodo 1; 0 peticiones fallidas sobre 5000 **(comprobado)**. |
| Poner un `APP_KEY` distinto en cada PC | Se pierde la sesión al cambiar de nodo **(esperado, lo advierte el PDF)**. |

---

## 8. El balanceador (Nginx)

### 8.1 Qué es y qué hace normalmente

Nginx es un servidor web muy usado como **proxy inverso**: recibe las peticiones de los usuarios y las reenvía a otros servidores. Cuando reparte entre varios, actúa como **balanceador de carga**: decide a cuál mandar cada petición y deja de usar los que fallan.

### 8.2 Dónde vive aquí

| Qué | Dónde |
|---|---|
| Contenedor `trackeat-balanceador` | `docker/infra/compose.yml:44-56` |
| Su configuración | `docker/infra/nginx.conf.template` |

### 8.3 El contenedor

| Línea (`infra/compose.yml`) | Contenido | Qué hace |
|---|---|---|
| 45 | `image: nginx:1.27-alpine` | Nginx 1.27. El parámetro `resolve` dentro de `upstream` (ver abajo) solo existe en versiones recientes de Nginx; con la 1.27.5 que descargó Docker funciona **(comprobado)**. Si se fijara una versión más vieja, dejaría de funcionar. |
| 49-53 | `environment: NODO1_ADDR ...` | Direcciones de los nodos y de los Reverb. En una PC son nombres de contenedor; en tres PCs son `IP:puerto`. |
| 54-55 | `volumes: ./nginx.conf.template:/etc/nginx/templates/default.conf.template:ro` | Monta la plantilla en la carpeta especial `/etc/nginx/templates/`. Al arrancar, la imagen sustituye las variables `${...}` por su valor real y escribe `/etc/nginx/conf.d/default.conf`. |
| 56 | `ports: ["8080:80"]` | El puerto 8080 de tu PC entra al 80 del contenedor. **Es la única puerta pública.** |

Como el archivo es una **plantilla** y no la configuración final, `nginx -s reload` no basta para aplicar un cambio: hay que **reiniciar** el contenedor (`docker compose -f docker/compose.yml restart balanceador`) para que se vuelva a generar.

### 8.4 La configuración, bloque por bloque (`nginx.conf.template`)

| Líneas | Contenido | Qué hace |
|---|---|---|
| 7 | `resolver 127.0.0.11 valid=5s ipv6=off;` | `127.0.0.11` es el servidor DNS interno de Docker. Nginx vuelve a preguntar cada 5 s a qué IP corresponde cada nombre (`nodo1`...). |
| 9-14 | `upstream trackeat { ... }` | El **grupo de servidores** de la aplicación. |
| 10 | `zone trackeat 64k;` | Memoria compartida entre los procesos de Nginx. Necesaria para que `resolve` funcione. |
| 11 | `least_conn;` | **El algoritmo.** Cada petición va al nodo con **menos conexiones abiertas**. Para cambiarlo: comentarla (turno rotatorio) o `ip_hash;` (afinidad por IP). |
| 12-13 | `server ${NODO1_ADDR} resolve max_fails=2 fail_timeout=10s;` | Un nodo. `resolve`: sigue su nombre aunque cambie de IP. `max_fails=2`: tras 2 fallos... `fail_timeout=10s`: ...se le considera caído durante 10 s. |
| 17-22 | `upstream reverb { ... }` | Lo mismo para los dos Reverb. |
| 24-27 | `map $http_upgrade $connection_upgrade` | Si la petición pide pasar a WebSocket (`Upgrade`), se reenvía `Connection: upgrade`; si no, `close`. Es lo que permite que el WebSocket atraviese el proxy. |
| 33 | `location = /favicon.ico { return 204; }` | Responde vacío al icono. El navegador lo pide en cada recarga y se "comería" un turno del reparto (lo advierte el PDF). |
| 38-40 | `absolute_redirect off;` y `location = /menu` / `/personal` | Redirigen `/menu` a `/menu/` (y `/personal` a `/personal/`). Ver sección 13. |
| 43-52 | `location /app { proxy_pass http://reverb; ... }` | Las conexiones WebSocket van al grupo de Reverb. |
| 46-47 | `Upgrade` y `Connection` | Cabeceras necesarias para el WebSocket. |
| 49 | `proxy_next_upstream error timeout;` | Si un Reverb no contesta, probar con el otro. |
| 51 | `proxy_read_timeout 3600s;` | Una conexión WebSocket puede estar callada mucho tiempo; por omisión Nginx la cortaría a los 60 s. |
| 54-68 | `location / { proxy_pass http://trackeat; ... }` | Todo lo demás va al grupo de nodos. |
| 57 | `proxy_set_header Host $http_host;` | Conserva el host **con el puerto** (`127.0.0.1:8080`). El PDF avisa: con `$host` se pierde el puerto y la aplicación arma enlaces y redirecciones hacia `localhost` sin `:8080`, y el login los manda a una página que no existe. |
| 58-60 | `X-Real-IP`, `X-Forwarded-For`, `X-Forwarded-Proto` | Le pasan al nodo la IP real del usuario y el protocolo original. Las lee `trustProxies`. |
| 62 | `proxy_next_upstream error timeout http_502 http_503 http_504;` | Si un nodo falla o responde error de servidor, **reintenta la petición con el otro**: es lo que hizo que no hubiera peticiones fallidas al apagar un nodo. |
| 64 | `proxy_connect_timeout 2s;` | Espera máximo 2 s para conectar con un nodo antes de pasar al otro (por omisión serían 60 s). |
| 67 | `add_header X-Nodo $upstream_addr always;` | Añade a la respuesta la IP del nodo que atendió. Es la forma de comprobar el reparto en F12. |

### 8.5 Por qué se hizo así

- **`least_conn`:** TrackEat mezcla peticiones cortas, consultas pesadas y conexiones largas (WebSockets); el algoritmo que mejor reparte con perfiles mezclados es el de menos conexiones. Con peticiones cortas y parecidas se comporta casi igual que el turno rotatorio; lo vimos en las mediciones (391 contra 397 peticiones por segundo, empate técnico).
- **`resolve` + nombres en vez de IPs fijas:** antes los contenedores tenían IPs fijas (`172.28.0.x`). Al repartir en varias PCs no pueden existir IPs fijas de Docker, así que se usan nombres. Sin `resolve`, Nginx resuelve el nombre **una sola vez al arrancar** y, si el contenedor se recrea con otra IP, sigue mandándole tráfico a la dirección vieja. Con `resolve`, **lo comprobamos**: `nodo2` cambió de IP al recrearse y Nginx lo siguió solo **(comprobado)**.
- **Dos `upstream` separados (nodos y Reverb):** son servicios distintos con tipos de conexión distintos.

### 8.6 Qué pasa si se cambia

| Cambio | Efecto |
|---|---|
| Quitar `resolve` de los `server` | Si un contenedor aún no existe cuando Nginx arranca, Nginx **no arranca**; si cambia de IP, sigue apuntando a la vieja **(esperado)**. |
| `$host` en lugar de `$http_host` | Se pierde el `:8080` en enlaces y redirecciones; el login manda a una página inexistente **(lo advierte el PDF)**. |
| Quitar `proxy_next_upstream` | Al caer un nodo, algunas peticiones devolverían 502 al usuario en vez de reintentarse **(esperado)**. |
| Quitar `proxy_connect_timeout 2s` | Con un nodo caído, algunas peticiones esperarían hasta 60 s **(esperado)**. |
| Quitar `proxy_read_timeout 3600s` | Los WebSockets se cortarían tras 60 s de silencio; el cliente se reconectaría **(esperado)**. |
| Quitar `location = /menu` | `/menu` (sin diagonal) quedaría en blanco **(comprobado: era el error que vimos)**. |
| `ip_hash;` en lugar de `least_conn;` | Cada IP de cliente cae siempre en el mismo nodo. En esta demo, como todos entran desde la misma PC, **todo iría a un solo nodo** **(esperado)**. |
| Cambiar la plantilla y solo hacer `nginx -s reload` | No pasa nada: hay que reiniciar el contenedor **(comprobado en la práctica)**. |

---

## 9. El compose, archivo por archivo

Docker Compose describe, en archivos YAML, **qué contenedores levantar y cómo conectarlos**.

### 9.1 Los tres archivos

| Archivo | Contiene | Para qué |
|---|---|---|
| `docker/compose.yml` | Solo un `include` | Levantar **todo en una PC** con un comando |
| `docker/infra/compose.yml` | `db`, `redis`, `balanceador` | La parte compartida; va en la "PC de infraestructura" |
| `docker/nodo/compose.yml` | `nodo1/2`, `reverb1/2`, `cola1/2` | Los nodos; una PC por nodo |

`docker/compose.yml` completo:

```yaml
name: trackeat
include:
  - infra/compose.yml
  - nodo/compose.yml
```

- `name: trackeat` (línea 11): nombre del proyecto; Docker lo usa para nombrar la red (`trackeat_default`).
- `include:` (líneas 13-15): trae los servicios de los otros dos archivos como si fueran uno solo. Con un `docker compose -f docker/compose.yml up -d` se levanta todo.

### 9.2 Los conceptos de YAML y Compose que hay que reconocer

**Anclas y mezcla (`&app`, `*app`, `<<:`)**, en `docker/nodo/compose.yml:13-19`:

```yaml
x-app: &app            # "x-" = bloque propio que Compose ignora; "&app" le pone nombre
  image: trackeat-app
  ...
  environment: &app-env
    ...
services:
  nodo1:
    <<: *app           # "copia aquí todo lo que está en x-app"
    container_name: trackeat-nodo1
    environment: { <<: *app-env, NODO: "1", REVERB_HOST: reverb1 }   # copia las variables y añade las propias
```

Sirve para **no repetir** las ~30 variables en los seis servicios. Lo único que cada servicio define por sí mismo es lo que lo hace distinto: su nombre, su comando, su número de nodo y su puerto.

**Variables con valor por omisión (`${VAR:-valor}`)**, por ejemplo `docker/nodo/compose.yml:23`: `DB_HOST: ${DB_HOST:-db}`. Significa «usa la variable `DB_HOST` si existe (en `docker/.env` o en el entorno); si no, usa `db`». **Es el mecanismo que permite pasar de una PC a tres sin cambiar los archivos**: en una PC no se define nada y valen los nombres de contenedor; en tres PCs, `docker/.env` los reemplaza por IPs.

**Un solo `build` compartido**, `docker/nodo/compose.yml:14-16`:

```yaml
image: trackeat-app
build: { context: ../.., dockerfile: docker/Dockerfile }
```

Los seis servicios usan la misma imagen `trackeat-app`. Al estar el `build` en el bloque común, **todos** saben construirla. Esto resolvió el error `pull access denied for trackeat-app`: antes solo `nodo1` tenía `build`, y los demás intentaban descargar la imagen de internet, donde no existe. `context: ../..` es la raíz del proyecto (relativo a la carpeta del archivo), porque el Dockerfile copia `backend/`.

**`restart: unless-stopped`** (línea 18 de nodo y varias de infra): si el contenedor se cae, se reinicia solo. Es lo que hace que el orden de arranque no importe: si un worker arranca antes que la base, falla, se reinicia, y a la segunda va bien.

**`ports`**: `"HOST:CONTENEDOR"`. `"8080:80"` = «lo que llegue al puerto 8080 de mi PC, mándalo al 80 del contenedor». Con un prefijo de dirección (`127.0.0.1:8001:8000`) solo se acepta desde esa dirección.

**`container_name`**: nombre fijo (`trackeat-nodo1`) en lugar del generado. Hace que los comandos `docker stop trackeat-nodo2` sean predecibles.

**`healthcheck`**: una orden que Docker ejecuta periódicamente para saber si el servicio está sano (`pg_isready`, `redis-cli ping`). En infra solo informa; no se usa `depends_on` (ver 9.3).

**`volumes`**: almacenamiento persistente. `trackeat-docker-db` (declarado en `infra/compose.yml:58-60`) sobrevive a `down` y se borra solo con `down -v`.

**La red:** ningún archivo declara una red explícita. Compose crea una por proyecto (`trackeat_default`) y conecta ahí todos los servicios. Dentro de ella, **cada servicio se encuentra por su nombre** (`db`, `redis`, `nodo1`...). Por eso `DB_HOST: db` funciona.

### 9.3 Por qué no hay `depends_on`

`depends_on` permite decir «espera a que la base esté sana antes de arrancar la app». Pero requiere que ambos servicios estén en el **mismo** proyecto. En el modo de tres PCs, la base está en otra computadora y `depends_on` no puede apuntar a ella. Por eso se confía en `restart: unless-stopped` y en que cada servicio reintenta al fallar.

### 9.4 Cómo se pasa de una PC a tres

Con **los mismos archivos**. Solo cambia `docker/.env` (plantilla en `docker/.env.example`) y el comando. Ver [cambiar a 3 pc.md](cambiar%20a%203%20pc.md).

---

## 10. Cómo se relacionan la PC, Docker y la aplicación

### 10.1 Las capas

```
Windows (tu PC)
 └─ Docker Desktop
     └─ Máquina virtual de Linux (WSL2)
         ├─ Red de Docker "trackeat_default"  (172.18.0.0/16)
         │    ├─ nodo1  172.18.0.x    ├─ nodo2 ...   ├─ db ...   ├─ redis ...
         └─ Volumen "trackeat-docker-db" (disco de la VM)
```

Los contenedores **no** son procesos de Windows: viven dentro de una máquina virtual Linux que administra Docker Desktop.

### 10.2 Dos "mundos" de direcciones

| Desde dónde | Cómo llega a la aplicación | Ejemplo |
|---|---|---|
| **Tu navegador** (Windows) | Por los **puertos publicados** de tu PC | `127.0.0.1:8080`, `127.0.0.1:8001` |
| **Un contenedor** hacia otro | Por **nombre** dentro de la red de Docker | `db:5432`, `reverb1:8081` |

Por eso hay dos números de puerto distintos para lo mismo:

| Servicio | Dentro de Docker | En tu PC |
|---|---|---|
| Balanceador | 80 | **8080** |
| Nodo 1 / 2 | 8000 | 8001 / 8002 |
| Reverb 1 / 2 | 8081 | 8091 / 8092 |
| PostgreSQL | 5432 | 5433 |
| Redis | 6379 | 6380 |

Los nodos hablan con PostgreSQL por `db:5432` (dentro). Tú, desde Windows, entrarías por `127.0.0.1:5433`.

### 10.3 `127.0.0.1` significa cosas distintas

- En tu navegador, `127.0.0.1` es **tu PC**.
- Dentro de un contenedor, `127.0.0.1` es **ese mismo contenedor**.

Por eso `TRACKEAT_SOCKET_URL` debe ser la dirección que ve el **navegador** (`URL_PUBLICA`), mientras `REVERB_HOST` es la que ve la **aplicación** (`reverb1`). Son dos direcciones para el mismo servicio, vistas desde dos lugares distintos.

### 10.4 Qué está dentro de la imagen y qué no

| Cosa | ¿Dónde está? | Consecuencia |
|---|---|---|
| Código PHP de `backend/` | **Dentro** de la imagen (`Dockerfile:16`) | Cambiar código exige `build` |
| Frontend compilado (`backend/public/build`, `menu`, `personal`) | **Dentro** de la imagen, porque está en `backend/` | Hay que correr `pnpm build` **antes** del `docker compose build` |
| `backend/.env` | **Fuera**: se lee al arrancar (`env_file`). `.dockerignore` lo excluye de la imagen | Cambiar el `.env` solo necesita `up -d`, no `build` |
| Datos de PostgreSQL | En el **volumen** | Sobreviven a `down`; no a `down -v` |
| La plantilla de Nginx | **Montada** desde tu carpeta (`volumes`) | Cambiarla solo requiere `restart balanceador` |

### 10.5 Por qué el firewall de Windows solo importa en el modo de tres PCs

Con una PC, todo el tráfico es local (tu navegador → tu propio puerto 8080). Con tres, un nodo en otra PC debe llegar a tu puerto 5433, y Windows bloquea por omisión las conexiones entrantes: hay que abrir esos puertos (ver [cambiar a 3 pc.md](cambiar%20a%203%20pc.md)).

### 10.6 Por qué `ip_cliente` muestra `172.18.0.1`

Al entrar desde tu propio navegador, Docker Desktop hace NAT y la conexión llega al balanceador con la IP de la puerta de enlace de la red (`172.18.0.1`), no con la tuya. Es un detalle de Docker Desktop en Windows, no del balanceo.

---

## 11. El navegador y el frontend

El frontend es **Svelte** (con Inertia). Se compila en el host con `pnpm build` y el resultado queda en `backend/public`, de donde lo sirve cada nodo.

### 11.1 Tres "aplicaciones" distintas

| Ruta | Qué es | Dónde queda compilada |
|---|---|---|
| `/`, `/dashboard`, `/comandas`, `/recetas`, `/metricas`... | La aplicación principal (Inertia + Vite) | `backend/public/build/` |
| `/menu/` | Menú digital (página estática, con modo sin conexión) | `backend/public/menu/` |
| `/personal/` | Catálogo de personal (página estática, con modo sin conexión) | `backend/public/personal/` |

El `pnpm build` (`frontend/package.json:9`) ejecuta **tres** compilaciones: `vite build && vite build --config vite.personal.config.js && vite build --config vite.menu.config.js`.

### 11.2 Cómo se conecta el tiempo real (`frontend/src/lib/trackeat.js`)

| Líneas | Qué hace |
|---|---|
| 57-59 | Decide a qué canales suscribirse: siempre `trackeat.usuario.{id}`; si el rol es personal, también `trackeat.personal`. |
| 131-143 | Crea el cliente: `new Echo({ broadcaster: 'reverb', client: new Pusher(key, { wsHost, wsPort, ... }) })`. El host y el puerto salen de `data.socket.url`, que es **`TRACKEAT_SOCKET_URL`** (la del balanceador). La `key` sale de `REVERB_APP_KEY`. |
| 141 | `channelAuthorization: { endpoint: '/broadcasting/auth' ... }`: para los canales privados, pide permiso al servidor Laravel. |
| 146-147 | `echo.private(channel).listen('.pedido.actualizado', sync)`: al llegar el aviso, vuelve a pedir los datos. |
| 81-88 | `schedulePoll`: **cada 5 s** vuelve a consultar el estado aunque no llegue ningún aviso. Es la red de seguridad. |

Los datos de conexión (`key` y `url`) los entrega el servidor en `/api/sesion` (`backend/app/Http/Controllers/TrackEatController.php:41`).

### 11.3 Doble protección: aviso rápido + sondeo lento

Los avisos por WebSocket llegan en milisegundos pero pueden perderse (Reverb caído, red intermitente). El sondeo cada 5 s lo cubre. **Por eso la aplicación no depende de que el tiempo real funcione**: si falla, la pantalla se actualiza igual, solo que hasta 5 s más tarde.

### 11.4 Cómo se navega la aplicación

No existe un botón de «Iniciar sesión» en la portada. El camino real:

1. Portada `http://127.0.0.1:8080/` → botón **«Ir al Dashboard»** (arriba a la derecha; en celular, el botón «Dashboard»).
2. La **barra lateral** del Dashboard lleva a: Dashboard, Comandas, Personal, Menú Digital y Recetas. Abajo hay un enlace «Ver sitio público» que regresa a la portada.
3. Sin sesión, el Dashboard muestra «Inicia sesión como personal para consultar el panel» con un enlace **«Iniciar sesión»**, que lleva a `/login`.
4. También se puede escribir `/login` directamente.

---

## 12. Qué pasa si... (tabla de experimentos)

Para practicar la explicación o para demostrar el comportamiento. Todos se ejecutan en PowerShell desde la raíz del proyecto, con el sistema levantado, y se deshacen con el `start` correspondiente.

| Experimento | Comando | Qué observar | Estado |
|---|---|---|---|
| Apagar un nodo | `docker stop trackeat-nodo2` | `/salud` solo responde `"nodo":"1"`; el sitio no se cae | Comprobado |
| Reincorporarlo | `docker start trackeat-nodo2` | Tarda ~10 s en volver al reparto (`fail_timeout`) | Comprobado |
| Apagar la base | `docker stop trackeat-db-docker` | `/salud` → 503 `"bd":"caida"` | Comprobado |
| Apagar un Reverb | `docker stop trackeat-reverb1` | Conexiones nuevas van al otro | Comprobado (cliente propio) |
| Apagar Redis | `docker stop trackeat-redis` | La app sigue; los eventos no cruzan entre Reverb | No probado |
| Apagar las colas | `docker stop trackeat-cola1 trackeat-cola2` | Los avisos se acumulan en `jobs` | No probado |
| Ver el reparto | recargar `/salud` | El campo `nodo` alterna | Comprobado |
| Cambiar de algoritmo | Editar `least_conn;` (línea 11) y `restart balanceador` | Ver sección 8 | Comprobado |
| Ver la sesión compartida | Iniciar sesión por `:8080` y pedir `/api/sesion` en `:8001` y `:8002` | Ambos reconocen al usuario | Comprobado |
| Recorrido de punta a punta | Cliente crea pedido, cocinero lo recibe en vivo y lo avanza | Aviso en tiempo real y cambios de estado | Comprobado |

---

## 13. Problemas reales que aparecieron y cómo se resolvieron

Conviene conocerlos: son preguntas probables y muestran que se entiende el sistema.

### 13.1 `pull access denied for trackeat-app`

- **Síntoma:** al hacer `up`, Docker intentaba descargar `trackeat-app` de internet.
- **Causa:** la imagen es **local**; solo `nodo1` tenía la instrucción `build`, así que los otros servicios no sabían construirla.
- **Solución:** mover el `build` al bloque común `x-app` (`docker/nodo/compose.yml:14-16`).

### 13.2 `/dashboard`, `/comandas` y `/recetas` daban error 500

- **Síntoma:** pantalla de error en el navegador.
- **Mensaje:** `Unable to locate file in Vite manifest: src/pages/public/dashboard.svelte`.
- **Causa:** `backend/public/build` era un build **viejo** (del 21 de septiembre) anterior a esas páginas. Las páginas existían en el código pero no en el frontend compilado. Y como el código y el frontend van **dentro de la imagen**, no bastaba con compilar: había que reconstruir.
- **Solución:** `pnpm build` en `frontend/` y luego `docker compose -f docker/compose.yml build` y `up -d`.
- **Lección:** si algo del frontend no aparece, lo primero es recompilar y reconstruir. El directorio `public/build` no está en git, así que cada PC debe generarlo.

### 13.3 `/menu` entraba en bucle y `/menu` (sin diagonal) quedaba en blanco

- **Síntoma 1:** `/menu/` redirigía a sí mismo. **Causa:** faltaba la carpeta `backend/public/menu` (mismo problema de build viejo).
- **Síntoma 2:** con la carpeta ya presente, `127.0.0.1:8080/menu` (sin diagonal final) cargaba una **página en blanco**.
- **Causa:** el servidor `php artisan serve` responde `/menu` con el HTML directamente, pero el HTML pide sus recursos con rutas relativas (`./assets/...`). Sin la diagonal final, el navegador las resuelve contra la raíz (`/assets/...`), obtiene 404 y no pinta nada.
- **Solución:** en Nginx, `location = /menu { return 301 /menu/; }` y lo mismo para `/personal` (`nginx.conf.template:38-40`). `absolute_redirect off` hace que el redireccionamiento conserve el puerto con el que entró el usuario.
- **Limitación:** si se entra directo a un nodo (`:8001/menu`), sin pasar por el balanceador, el problema persiste. En el uso normal (por `:8080`) ya no ocurre.

### 13.4 Contenedores reiniciándose en el primer arranque

- **Síntoma:** `cola1`, `cola2`, `reverb1`, `reverb2` aparecen en `Restarting`.
- **Causa:** antes de correr `migrate`, la tabla `cache` no existe y los workers la consultan al arrancar.
- **Solución:** `docker compose -f docker/compose.yml exec nodo1 php artisan migrate --seed --force`. Se estabilizan solos.

### 13.5 Nginx no arrancaba con la plantilla nueva

- **Síntoma:** `unknown directive "﻿#"` en el log del balanceador.
- **Causa:** el archivo original traía una marca de orden de bytes (BOM) de Windows al inicio.
- **Solución:** quitarla. Si se edita la plantilla con un editor que añada BOM, volverá a pasar.

### 13.6 Una corrección de documentación

Se había escrito que los avisos fallidos se reintentan «3 veces seguidas». Eso era incorrecto: el evento declara `$tries = 5` y `$backoff = [1, 2, 5, 10]` (`PedidoActualizado.php:12-14`), es decir, hasta 5 intentos con esperas de 1, 2, 5 y 10 s. El `--tries=3` del worker solo vale para tareas que no declaran el suyo. Los documentos ya están corregidos.

---

## 14. Preguntas que puede hacer el profesor

**¿Por qué el balanceador no es también un punto de falla?**
Lo es. PostgreSQL, Redis y Nginx son instancias únicas y viven en la misma PC. Eliminar esos puntos únicos requeriría replicación de PostgreSQL, Redis Sentinel y un segundo balanceador, fuera del alcance de la práctica. Los nodos, los Reverb y las colas sí son redundantes.

**¿Qué pasa si se cae un nodo mientras el usuario está navegando?**
Nginx lo detecta (conexión rechazada o error de servidor), reintenta esa petición con el otro nodo (`proxy_next_upstream`) y lo deja fuera del reparto 10 s (`fail_timeout`). La sesión no se pierde porque está en PostgreSQL. En las pruebas, 0 peticiones fallidas sobre 5000.

**¿Por qué dos Reverb si uno bastaba?**
Con uno solo, su caída deja a todas las pantallas sin tiempo real. Con dos, una pantalla se reconecta al sobreviviente. Pero dos Reverb solos no se conocen entre sí, y por eso se añadió Redis: es el canal que los sincroniza.

**¿Para qué Redis si ya tienen PostgreSQL?**
PostgreSQL guarda datos; no está pensado para repartir mensajes efímeros e instantáneos entre procesos (pub/sub). Redis sí. Y solo se usa para eso.

**¿Por qué la cola está en la base de datos?**
Para no depender de otra pieza crítica y porque PostgreSQL es transaccional. Si Redis cayera, no se perdería la cola, solo la sincronización entre Reverb.

**¿Cómo demuestran que la sesión sobrevive al cambio de nodo?**
Se inicia sesión por el balanceador y se usa la misma cookie directo contra `:8001` y `:8002`: ambos reconocen al usuario, porque la sesión está en la tabla `sessions`. Además, las dos instancias comparten la misma `APP_KEY`.

**¿Por qué usaron Docker?**
Es la opción más accesible y funcional: cada contenedor es un proceso aislado con su propia dirección y se puede apagar o levantar a voluntad, como un servidor distinto. Los mismos archivos permiten levantar todo en una PC, o repartirlo en dos o tres según los recursos disponibles. Es una práctica habitual para desplegar aplicaciones.

**¿Qué falta o qué no probaron?**
El modo de tres computadoras está preparado pero no se ha probado entre equipos reales; la reconexión de la pantalla de cocina con un Reverb apagado se comprobó con un cliente propio, no con la pantalla real en el navegador; y apagar Redis o las colas no se ejecutó como experimento.

**¿Cómo agregarían un tercer nodo?**
Añadir `nodo3`, `reverb3` y `cola3` en `docker/nodo/compose.yml` (copiando el bloque del nodo 2 con `NODO: "3"` y puertos nuevos), y una línea `server ${NODO3_ADDR} resolve ...` en cada `upstream` de la plantilla de Nginx, con sus variables en `infra/compose.yml`. Redis ya distribuye a cualquier cantidad de Reverb.
