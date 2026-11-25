# Arquitectura de balanceo de TrackEat, explicada línea por línea

Este documento explica **qué hace cada pieza** del despliegue con balanceo (Práctica 5), **dónde está su configuración**, **por qué se configuró así** y **cómo se comporta** dentro del proyecto. Al final está la revisión del funcionamiento real (pruebas hechas el 5 de noviembre de 2026 con los 9 contenedores levantados) y los problemas encontrados.

Convención: `archivo:línea` señala la línea exacta del repositorio.

---

## Índice

1. [Panorama: "tres máquinas", nueve contenedores](#1-panorama-tres-máquinas-nueve-contenedores)
2. [El recorrido de una petición, de principio a fin](#2-el-recorrido-de-una-petición-de-principio-a-fin)
3. [La imagen: `docker/Dockerfile` y `.dockerignore`](#3-la-imagen-dockerdockerfile-y-dockerignore)
4. [Los archivos Compose](#4-los-archivos-compose)
5. [Nginx, el balanceador (dónde ocurre el redireccionamiento)](#5-nginx-el-balanceador)
6. [PostgreSQL](#6-postgresql)
7. [Redis](#7-redis)
8. [Reverb (WebSockets)](#8-reverb-websockets)
9. [Las colas](#9-las-colas)
10. [Nivel de nodo de aplicación](#10-nivel-de-nodo-de-aplicación)
11. [Nivel de nodo de infraestructura](#11-nivel-de-nodo-de-infraestructura)
12. [Redireccionamiento dentro de la aplicación (capas)](#12-redireccionamiento-dentro-de-la-aplicación)
13. [Modo una PC contra modo tres PCs](#13-modo-una-pc-contra-modo-tres-pcs)
14. [Revisión del funcionamiento: qué se probó y qué se encontró](#14-revisión-del-funcionamiento)
15. [Glosario](#15-glosario)

---

## 1. Panorama: "tres máquinas", nueve contenedores

La idea de la práctica se suele resumir como "tres contenedores: dos con la aplicación y uno con la base de datos y Redis". En el código, cada una de esas tres **partes** está formada por varios contenedores:

| Parte (máquina lógica) | Archivo | Contenedores | Papel |
|---|---|---|---|
| **Infraestructura** | `docker/infra/compose.yml` | `trackeat-db-docker` (PostgreSQL), `trackeat-redis` (Redis), `trackeat-balanceador` (Nginx) | Lo que se comparte: datos, canal de mensajes y la puerta de entrada |
| **Nodo 1** | `docker/nodo/compose.yml` | `trackeat-nodo1` (app), `trackeat-reverb1` (WebSocket), `trackeat-cola1` (worker de la cola) | Una copia completa de la aplicación |
| **Nodo 2** | `docker/nodo/compose.yml` | `trackeat-nodo2`, `trackeat-reverb2`, `trackeat-cola2` | La segunda copia, idéntica |

¿Por qué el balanceador vive con la base y Redis? Porque las tres son piezas **únicas** (no se duplican) y todo el tráfico pasa por ellas; los nodos, en cambio, son **intercambiables**: se puede apagar uno y el sistema sigue.

¿Por qué cada nodo tiene tres contenedores y no uno? Porque la aplicación Laravel hace tres trabajos distintos que son procesos de larga duración separados:

- `php artisan serve` atiende HTTP (páginas y la API).
- `php artisan reverb:start` mantiene abiertos los WebSockets de las pantallas.
- `php artisan queue:work` ejecuta trabajos en segundo plano (aquí: enviar los eventos en vivo).

Un contenedor de Docker ejecuta **un** proceso principal; por eso son tres contenedores que usan **la misma imagen** con distinto comando.

```
                              Navegador
                     http://localhost:8080 (o IP:8080)
                                  │
                   ┌──────────────▼───────────────┐
 INFRAESTRUCTURA   │  trackeat-balanceador (Nginx)│
                   │   /      → upstream trackeat │
                   │   /app   → upstream reverb   │
                   └───┬───────────┬──────────┬───┘
                       │           │          │
          ┌────────────┘           │          └─────────────┐
          ▼                        ▼                        ▼
 ┌─────── NODO 1 ───────┐ ┌─────── NODO 2 ───────┐
 │ nodo1  :8000 (HTTP)  │ │ nodo2  :8000 (HTTP)  │
 │ reverb1:8081 (WS)    │ │ reverb2:8081 (WS)    │
 │ cola1  (worker)      │ │ cola2  (worker)      │
 └──┬──────────┬────────┘ └──┬──────────┬────────┘
    │ SQL      │ pub/sub     │ SQL      │ pub/sub
    ▼          ▼             ▼          ▼
 ┌───────────────────┐   ┌──────────────────┐
 │ trackeat-db-docker│   │ trackeat-redis   │   INFRAESTRUCTURA
 │ PostgreSQL :5432  │   │ Redis :6379      │
 └───────────────────┘   └──────────────────┘
```

---

## 2. El recorrido de una petición, de principio a fin

Ejemplo real: un cliente confirma un pedido en `/menu/` y la cocina lo ve aparecer en `/comandas` sin recargar.

1. **Navegador → Nginx.** `POST http://localhost:8080/api/pedidos`. Nginx (`docker/infra/nginx.conf.template:47-48`) lo manda al `upstream trackeat`, que con `least_conn` elige el nodo con menos conexiones abiertas (p. ej. `nodo2`).
2. **Nginx → nodo2.** `php artisan serve` recibe la petición. Como `/api/pedidos` no es un archivo en `public/`, entra a Laravel (`index.php`).
3. **Laravel.** Middlewares: `TrustProxies` (cree las cabeceras `X-Forwarded-*` de Nginx), sesión (la lee **de PostgreSQL**, por eso no importa qué nodo atendió el login), `auth`, `ActiveTrackEatUser`. Llega a `TrackEatController::crear` (`backend/app/Http/Controllers/TrackEatController.php:55`).
4. **Transacción.** `PedidoService::crear` (`backend/app/Services/PedidoService.php:133-174`) inserta el pedido y, en la línea 171, dispara `event(PedidoActualizado::forOrder($pedido))`.
5. **Cola.** Como el evento implementa `ShouldBroadcast`, Laravel **no** lo envía en ese momento: lo guarda como un trabajo en la tabla `jobs` de PostgreSQL. Como implementa `ShouldDispatchAfterCommit`, lo guarda solo si la transacción se confirma.
6. **Worker.** `cola1` o `cola2` (cualquiera, comparten la tabla) toma el trabajo y hace una petición HTTP al Reverb **de su propio nodo** (`REVERB_HOST=reverb1` o `reverb2`).
7. **Reverb → Redis.** Con `REVERB_SCALING_ENABLED=true`, ese Reverb **no** entrega el mensaje directamente: lo publica en el canal `reverb` de Redis.
8. **Redis → todos los Reverb.** `reverb1` y `reverb2` están suscritos a ese canal; ambos reciben el mensaje y cada uno lo entrega a **sus** WebSockets suscritos a `private-trackeat.personal`.
9. **Navegador de la cocina.** `frontend/src/lib/trackeat.js:147` recibe `.pedido.actualizado`, llama a `sync()`, que pide `GET /api/pedidos` (otra vez por el balanceador, quizá a otro nodo) y redibuja la lista.

El evento solo lleva `id`, `numero_pedido`, `estado` y `updated_at` (`PedidoActualizado.php:41-44`): es un **aviso**, no los datos. La pantalla vuelve a pedir los datos por HTTP. Por eso, si se pierde un aviso, el sondeo cada 5 s (sección 8.6) lo corrige.

---

## 3. La imagen: `docker/Dockerfile` y `.dockerignore`

Una sola imagen (`trackeat-app`) para los seis contenedores de nodo.

| Línea | Código | Qué hace y por qué |
|---|---|---|
| 3 | `FROM php:8.3-cli-alpine` | PHP en versión **CLI** (sin Apache ni FPM) sobre Alpine (imagen pequeña). Es CLI porque los tres procesos se lanzan con `php artisan ...`. |
| 5 | `apk add ... postgresql-dev libzip-dev` | Bibliotecas de sistema necesarias para compilar las extensiones siguientes. |
| 6 | `docker-php-ext-install pdo_pgsql bcmath opcache pcntl zip` | `pdo_pgsql`: hablar con PostgreSQL. `bcmath`: aritmética exacta de dinero (`App\Support\Money`). `opcache`: caché de código compilado. `pcntl`: señales de proceso, la usan `queue:work` y `reverb:start` para apagarse limpiamente. `zip`: dependencia de Composer. **No** se instala `phpredis`: Laravel no usa Redis en este proyecto (ver sección 7.4). |
| 7 | `opcache.enable_cli=1`, `opcache.validate_timestamps=0`, `memory_limit=256M` | Activa opcache también en CLI (por defecto está apagado ahí) y no revisa si los archivos cambiaron, porque dentro de la imagen el código nunca cambia. Acelera cada petición. |
| 9 | `COPY --from=composer:2 ...` | Toma el ejecutable de Composer de su imagen oficial sin instalarlo a mano. |
| 10 | `WORKDIR /app` | Todo el código queda en `/app`. |
| 13-14 | Copia solo `composer.json`/`composer.lock` y `composer install --no-scripts --no-autoloader` | Truco de caché: si las dependencias no cambian, Docker reutiliza esta capa (la más lenta) aunque cambie el código. |
| 16 | `COPY backend/ ./` | Copia el backend **incluido `public/build`, `public/menu` y `public/personal`**, que son el frontend ya compilado. Si no se compiló antes (`pnpm build`), la imagen sale sin frontend o con uno viejo (ver hallazgo H5). |
| 17-18 | `composer dump-autoload --optimize` y `mkdir -p storage/...` | Genera el autoload optimizado y las carpetas que Laravel necesita para escribir (caché de vistas, logs). |
| 20-21 | `EXPOSE 8000` / `CMD php artisan serve --host=0.0.0.0 --port=8000 --no-reload` | Comando por defecto (el de `nodoN`). `0.0.0.0` para aceptar conexiones de fuera del contenedor. `--no-reload` evita que `serve` vigile el `.env` (no hay `.env` dentro de la imagen). |

**`.dockerignore`** (raíz): la línea 2 (`*`) excluye todo y la 3 (`!backend/`) vuelve a incluir solo el backend. Las líneas 4-15 quitan lo que no debe entrar: `backend/.env*` (secretos; las variables llegan por Compose), `vendor/` (se instala dentro), cachés y logs locales, y `public/hot` (que haría que Laravel buscara el servidor de desarrollo de Vite).

Consecuencia importante: **dentro del contenedor no existe `.env`**. Laravel lee la configuración de las variables de entorno que pone Compose.

---

## 4. Los archivos Compose

### 4.1 `docker/compose.yml` (modo una PC)

| Línea | Qué hace |
|---|---|
| 11 | `name: trackeat` — nombre del proyecto. De ahí sale el nombre de la red: `trackeat_default`. |
| 13-15 | `include:` los otros dos archivos. Al incluirlos en un mismo proyecto, **los nueve contenedores quedan en la misma red** y se encuentran por nombre de servicio (`db`, `redis`, `nodo1`, `reverb2`...). Docker tiene un DNS interno (`127.0.0.11`) que resuelve esos nombres. |

### 4.2 `docker/infra/compose.yml` (PC de infraestructura)

**Servicio `db`** (líneas 11-27)

| Línea | Qué hace y por qué |
|---|---|
| 12 | `postgres:16-alpine`: imagen oficial. |
| 14 | `restart: unless-stopped`: si se cae, Docker la reinicia; si tú la paras con `docker stop`, se queda parada (útil para las pruebas de fallo). |
| 16-18 | Crea la base `trackeat`, el usuario `trackeat` y su contraseña (`DB_PASSWORD`, por defecto `trackeat`). La imagen solo aplica esto la **primera** vez que el volumen está vacío. |
| 19-20 | Volumen `trackeat-docker-db`: los datos sobreviven a `docker compose down`. Solo `down -v` los borra. |
| 22 | `ports: "${ESCUCHA:-127.0.0.1}:${DB_PUERTO_PUBLICADO:-5433}:5432"`: publica el 5432 interno en el **5433** del anfitrión (para no chocar con un PostgreSQL local), y por defecto solo en `127.0.0.1`. En modo tres PCs `ESCUCHA=0.0.0.0` lo abre a la red para que lleguen los nodos de otras máquinas. |
| 23-27 | `healthcheck` con `pg_isready` cada 2 s: Docker marca el contenedor `healthy` cuando acepta conexiones (se ve en `docker compose ps`). |

**Servicio `redis`** (líneas 30-42): explicado en la sección 7.

**Servicio `balanceador`** (líneas 44-56)

| Línea | Qué hace y por qué |
|---|---|
| 45 | `nginx:1.27-alpine`. Se necesita ≥ 1.27.3 porque `resolve` dentro de `upstream` llegó a la versión gratuita de Nginx en esa versión. |
| 49-53 | Variables `NODO1_ADDR`, `NODO2_ADDR`, `REVERB1_ADDR`, `REVERB2_ADDR`. Por defecto son nombres de contenedor (`nodo1:8000`...); en tres PCs se cambian por `IP:puerto`. |
| 55 | Monta `nginx.conf.template` en `/etc/nginx/templates/`. La imagen oficial, al arrancar, ejecuta `envsubst` sobre cada `*.template`, sustituye `${NODO1_ADDR}` etc. y escribe `/etc/nginx/conf.d/default.conf`. Por eso **cambiar el algoritmo exige `restart`**, no `nginx -s reload`: la sustitución solo ocurre al arrancar. |
| 56 | `ports: ["8080:80"]`: **la única puerta** para los usuarios. Nótese que no lleva `${ESCUCHA}`: el 8080 siempre escucha en todas las interfaces. |

### 4.3 `docker/nodo/compose.yml` (PCs de los nodos)

**El ancla `x-app`** (líneas 13-46). Es un bloque YAML reutilizable (`&app`) que los seis servicios heredan con `<<: *app`:

| Línea | Qué hace y por qué |
|---|---|
| 14 | `image: trackeat-app`: todos usan la misma imagen → **misma versión del código en los dos nodos** (requisito de la práctica). |
| 16 | `build:` con contexto en la raíz del repo y el Dockerfile de `docker/`. |
| 17 | `env_file: ../../backend/.env`: carga el `.env` del backend **como variables de entorno**. De ahí sale sobre todo `APP_KEY` (debe ser idéntica en todos los nodos) y `PHP_CLI_SERVER_WORKERS=4`. |
| 19-46 | `environment:` — estas variables **tienen prioridad** sobre las de `env_file`. Así se ignora, por ejemplo, el `DB_CONNECTION=sqlite` del `.env` local. |
| 20 | `APP_URL` = la URL del balanceador (por defecto `http://127.0.0.1:8080`). |
| 21 | `LOG_CHANNEL: stderr`: los logs salen por `docker logs` en vez de a un archivo dentro del contenedor. |
| 22-28 | Conexión a PostgreSQL. `DB_HOST=db` en una PC; la IP de la infraestructura en tres. `PGCONNECT_TIMEOUT: "1"`: si la base no contesta, falla en 1 s en vez de colgarse; así `/salud` responde 503 rápido. |
| 29-31 | `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` = `database`: sesión, caché y cola **en PostgreSQL**, la pieza compartida. Esta es la decisión central para que la sesión sobreviva al cambio de nodo (sección 10.4). |
| 32-38 | Reverb: `BROADCAST_CONNECTION=reverb` (Laravel publica eventos por Reverb), credenciales de la aplicación Pusher (`APP_ID/KEY/SECRET`), puerto 8081, esquema `http`, `REVERB_ALLOWED_ORIGINS="*"` (acepta WebSockets desde cualquier origen; necesario porque el navegador puede entrar por `localhost`, `127.0.0.1` o una IP). |
| 40 | `REVERB_SCALING_ENABLED: "true"`: activa la sincronización por Redis. Sin esto, Redis no se usaría. |
| 41-43 | Dónde está Redis y su contraseña. |
| 44 | `TRACKEAT_SOCKET_URL`: la dirección de WebSocket **que se le da al navegador** (la del balanceador). |
| 46 | `TRUSTED_PROXIES: *`: el nodo cree las cabeceras `X-Forwarded-*` de cualquier proxy directo (sección 10.2). |

**Los servicios** (líneas 50-88). Cada nodo son tres servicios que solo difieren en:

| Servicio | `command` | `NODO` | `REVERB_HOST` | Puerto publicado |
|---|---|---|---|---|
| `nodo1` | (el del Dockerfile: `artisan serve`) | `1` | `reverb1` | `8001→8000` |
| `reverb1` | `reverb:start --host=0.0.0.0 --port=8081` | `1` | `reverb1` | `8091→8081` |
| `cola1` | `queue:work --sleep=1 --tries=3` | `1` | `reverb1` | ninguno |
| `nodo2`/`reverb2`/`cola2` | ídem | `2` | `reverb2` | `8002`, `8092` |

- `environment: { <<: *app-env, NODO: "1", REVERB_HOST: reverb1 }` mezcla las variables comunes con las propias del nodo.
- `REVERB_HOST` significa "el Reverb **de este nodo**": la app y la cola del nodo 1 publican en `reverb1`.
- Los puertos 8001/8002/8091/8092 sirven para dos cosas: probar un nodo directo sin balanceador y, en modo tres PCs, para que el Nginx de otra máquina llegue a ellos.
- `cola1`/`cola2` no publican puertos: no reciben conexiones, solo consultan la base.

### 4.4 `docker/.env.example`

Plantilla de las variables que cambian entre una y tres PCs. En una PC no hace falta. En tres PCs se copia como `docker/.env`, **idéntico en las tres**, y se pasa con `--env-file docker/.env`. Los grupos: `ESCUCHA` (abrir puertos a la red), `DB_*`/`REDIS_*` (dónde está la infraestructura, con los puertos **publicados** 5433/6380), `NODO*_ADDR`/`REVERB*_ADDR` (dónde ve Nginx a los nodos), `URL_PUBLICA` (dirección del balanceador), `TRUSTED_PROXIES` y contraseñas.

---

## 5. Nginx, el balanceador

Archivo: **`docker/infra/nginx.conf.template`**. Es el único lugar donde se decide **a qué contenedor va cada petición**.

### 5.1 Línea por línea

| Línea | Código | Qué hace y por qué |
|---|---|---|
| 7 | `resolver 127.0.0.11 valid=5s ipv6=off;` | `127.0.0.11` es el DNS interno de Docker. Nginx volverá a preguntar las IPs de `nodo1`, `nodo2`... cada 5 s. Sin esto, Nginx resolvería los nombres una sola vez al arrancar: si un contenedor se recrea con otra IP, Nginx seguiría mandando a la vieja. |
| 9 | `upstream trackeat {` | Grupo de servidores para HTTP normal. |
| 10 | `zone trackeat 64k;` | Memoria **compartida** entre los procesos *worker* de Nginx. Sin zona, cada worker llevaría su propia cuenta de conexiones y fallos, y `least_conn` decidiría con información parcial. También es obligatoria para usar `resolve`. |
| 11 | `least_conn;` | **Algoritmo**: menos conexiones activas. Si hay empate (peticiones cortas y secuenciales) se comporta como turno rotatorio. Alternativas indicadas en el comentario: borrar la línea = turno rotatorio; `ip_hash;` = afinidad por IP. Justificación en `docs/balanceo.md`, caso E. |
| 12-13 | `server ${NODO1_ADDR} resolve max_fails=2 fail_timeout=10s;` | `resolve`: aplica el `resolver` a este nombre. `max_fails=2 fail_timeout=10s`: si en 10 s hay 2 fallos con ese nodo, se considera caído durante 10 s (después se le vuelve a probar). Son **comprobaciones pasivas**: Nginx gratuito no consulta `/salud`; deduce que un nodo cayó cuando una petición real falla. |
| 17-22 | `upstream reverb { ... least_conn; ... }` | Lo mismo para los dos Reverb. `least_conn` es especialmente útil aquí: los WebSockets duran todo el turno, así que conviene mandar cada pantalla nueva al Reverb con menos conexiones abiertas. |
| 24-27 | `map $http_upgrade $connection_upgrade { default upgrade; '' close; }` | Si la petición trae `Upgrade: websocket`, la variable vale `upgrade`; si no, `close`. Se usa en la cabecera `Connection` del bloque `/app`. Es el patrón estándar para que un proxy deje pasar WebSockets. |
| 30 | `listen 80;` | Dentro del contenedor escucha el 80; Compose lo publica como 8080. |
| 33 | `location = /favicon.ico { return 204; }` | El navegador pide el icono en cada carga; responder aquí evita que esa petición "gaste un turno" del reparto y confunda al contar qué nodo atendió. |
| 36 | `location /app {` | **Primer punto de redireccionamiento**: todo lo que empiece por `/app` va a los Reverb. El cliente Pusher abre `ws://HOST:8080/app/<APP_KEY>?protocol=7...`. |
| 37 | `proxy_pass http://reverb;` | Al upstream de los Reverb. |
| 38-40 | `proxy_http_version 1.1`, `Upgrade`, `Connection` | Sin HTTP/1.1 y estas dos cabeceras el *handshake* WebSocket no pasa por el proxy. |
| 42 | `proxy_next_upstream error timeout;` | Si no logra conectar con un Reverb, prueba el otro. |
| 43 | `proxy_connect_timeout 2s;` | Espera máxima para conectar. Sin esto serían 60 s. |
| 44 | `proxy_read_timeout 3600s;` | Un WebSocket puede pasar mucho tiempo sin datos; con el valor por defecto (60 s) Nginx lo cortaría. Reverb además manda un *ping* cada 60 s (`config/reverb.php:86`). |
| 47-48 | `location / { proxy_pass http://trackeat; }` | **Segundo punto de redireccionamiento**: todo lo demás (páginas, API, archivos estáticos, login, `/broadcasting/auth`, `/salud`) va a los nodos. |
| 50 | `proxy_set_header Host $http_host;` | Pasa el `Host` **con puerto** (`localhost:8080`). Con `$host` se perdería el `:8080` y Laravel generaría URLs absolutas sin el puerto (redirecciones rotas). |
| 51-53 | `X-Real-IP`, `X-Forwarded-For`, `X-Forwarded-Proto` | Le dicen al nodo la IP real del cliente y el esquema original. Laravel solo las cree si el proxy es de confianza (sección 10.2). |
| 55 | `proxy_next_upstream error timeout http_502 http_503 http_504;` | Si un nodo no conecta, tarda demasiado o responde 502/503/504, Nginx reintenta la **misma** petición en el otro nodo. Por defecto Nginx **no** reintenta peticiones no idempotentes (POST, PATCH) una vez enviadas, lo que evita pedidos duplicados. |
| 57 | `proxy_connect_timeout 2s;` | Un nodo apagado deja de responder; en 2 s Nginx desiste y prueba el otro. |
| 60 | `add_header X-Nodo $upstream_addr always;` | Cabecera de respuesta con la IP:puerto del nodo que atendió. `always` la añade también en respuestas de error. |

### 5.2 Qué cabecera dice qué

| Cabecera | Quién la pone | Valor | Aparece en |
|---|---|---|---|
| `X-Nodo` | Nginx | `172.18.0.x:8000` (o `IP-PC:8001` en tres PCs) | Todas las respuestas que pasan por el balanceador |
| `X-Servidor` | Laravel (`IdentificarNodo`) | `1` o `2` | Solo las respuestas que generó Laravel, **no** los archivos estáticos (sección 12) |

---

## 6. PostgreSQL

**Qué es:** base de datos relacional. **Configuración:** servicio `db` en `docker/infra/compose.yml:11-27`; conexión en `docker/nodo/compose.yml:22-28` y `backend/config/database.php`.

**Qué guarda en este proyecto** (todo compartido por los dos nodos):

| Tabla | Para qué | Por qué importa para el balanceo |
|---|---|---|
| `users`, `pedidos`, `productos`... | Los datos del negocio | Un pedido creado en el nodo 1 se ve desde el nodo 2 |
| `sessions` | Sesiones de usuario (`SESSION_DRIVER=database`) | Cualquier nodo lee la sesión creada por el otro |
| `cache`, `cache_locks` | Caché de Laravel (`CACHE_STORE=database`) | Límites de peticiones (`throttle`) compartidos entre nodos |
| `jobs` | La cola (`QUEUE_CONNECTION=database`) | Los dos workers sacan trabajos de la misma tabla |
| `failed_jobs` | Trabajos que agotaron sus intentos | Diagnóstico |

**Por qué una sola base:** la práctica exige que los nodos compartan datos. Es un punto único de falla: si cae, cae la aplicación (comprobado: `/salud` responde 503 con `"bd":"caida"`).

**Detalle de concurrencia:** como dos nodos pueden escribir a la vez, `PedidoService` usa bloqueos de PostgreSQL: `pg_advisory_xact_lock` (`PedidoService.php:136`) para que dos reintentos del mismo pedido (mismo `client_request_id`) no creen dos pedidos aunque lleguen a nodos distintos, y `lockForUpdate()` (`:189-191`) para que dos cocineros no avancen la misma estación a la vez. Estos bloqueos viven en la base, así que funcionan **entre nodos**; un bloqueo en memoria de PHP no serviría.

---

## 7. Redis

### 7.1 Qué es

Redis es un servidor de datos **en memoria**, muy rápido. Se puede usar como caché, almacén de sesiones, cola o, como aquí, como **canal de mensajes publicar/suscribir** (*pub/sub*): un proceso hace `PUBLISH canal mensaje` y Redis lo reenvía al instante a todos los procesos que hicieron `SUBSCRIBE canal`. Redis no guarda esos mensajes: si nadie escucha, se pierden.

### 7.2 Para qué se usa en TrackEat (y para qué NO)

**Solo** para que los dos Reverb se pasen los eventos entre sí. Problema que resuelve: cada Reverb conoce únicamente los WebSockets conectados a él. Si la cocina está conectada a `reverb2` y el evento lo recibe `reverb1`, sin Redis la cocina nunca se enteraría.

**No** se usa para sesión, caché ni cola (están en PostgreSQL, sección 6). Por eso la imagen no instala la extensión `phpredis` (`Dockerfile:6`) y no hace falta: Reverb usa su propio cliente Redis asíncrono (de ReactPHP), no el de Laravel.

### 7.3 Dónde está su configuración

**Servidor** — `docker/infra/compose.yml:30-42`:

| Línea | Código | Por qué |
|---|---|---|
| 31 | `redis:7-alpine` | Imagen oficial ligera. |
| 34 | `--requirepass ${REDIS_PASSWORD:-trackeat-redis}` | Exige contraseña. En modo tres PCs el puerto queda abierto a la red; sin contraseña cualquiera podría conectarse. |
| 34 | `--save ""` | Desactiva las instantáneas a disco (RDB). |
| 34 | `--appendonly no` | Desactiva el registro de escrituras a disco (AOF). Ambos se apagan porque **no hay nada que guardar**: los mensajes pub/sub nunca se almacenan. Persistir solo gastaría disco. Por eso Redis tampoco tiene volumen. |
| 35-36 | `REDIS_PASSWORD` como variable | Solo para que el `healthcheck` pueda usarla. |
| 37 | `ports: ${ESCUCHA}:6380:6379` | Publicado en el 6380 para no chocar con un Redis local; en tres PCs los Reverb de otras máquinas llegan por aquí. |
| 39 | `redis-cli -a "$$REDIS_PASSWORD" ping \| grep -q PONG` | Comprobación de salud. `$$` evita que Compose sustituya la variable; la sustituye el shell del contenedor. |

**Cliente (Reverb)** — `backend/config/reverb.php:40-52`:

```php
'scaling' => [
    'enabled' => env('REVERB_SCALING_ENABLED', false),   // true en docker/nodo/compose.yml:40
    'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),// nombre del canal pub/sub
    'server' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),        // redis, o IP de la infraestructura
        'port' => env('REDIS_PORT', '6379'),             // 6379, o 6380 en tres PCs
        'password' => env('REDIS_PASSWORD'),
        ...
```

### 7.4 Comportamiento comprobado

Con el sistema levantado, Redis muestra exactamente lo esperado:

```
PUBSUB CHANNELS *      → reverb
PUBSUB NUMSUB reverb   → reverb 2        (los dos Reverb suscritos)
CLIENT LIST            → 2 conexiones desde reverb1 y 2 desde reverb2
                          (una para suscribirse y otra para publicar, por cada Reverb)
```

Código que decide el camino, dentro de Reverb (`vendor/laravel/reverb/src/Protocols/Pusher/EventDispatcher.php`):

```php
if ($server->shouldNotPublishEvents()) {      // scaling apagado
    static::dispatchSynchronously(...);       // entrega directo a sus clientes
    return;
}
app(PubSubProvider::class)->publish($data);   // scaling encendido: SOLO publica en Redis
```

Con el escalado activo, un Reverb **no entrega nada directamente**, ni siquiera a sus propios clientes: publica en Redis y entrega cuando le llega su propio mensaje de vuelta por la suscripción. Así todos los Reverb entregan por el mismo camino.

**Consecuencia (comprobada):** si Redis cae, **ninguna** pantalla recibe eventos en vivo, ni siquiera las conectadas al Reverb que recibió el evento. Las pantallas siguen al día gracias al sondeo de 5 s. Esto **corrige** lo que dicen `docs/balanceo.md` e `informe-practica-5.md` ("una pantalla solo vería lo publicado en su propio Reverb"); ver hallazgo H2. Al volver Redis, los Reverb se reconectan solos y los eventos vuelven a llegar (comprobado: 5 de 5).

### 7.5 Por qué uno solo y no uno por nodo

Si cada nodo tuviera su Redis, `reverb1` publicaría en uno y `reverb2` escucharía otro: volvería el problema original. El canal tiene que ser común, por eso vive en la infraestructura.

---

## 8. Reverb (WebSockets)

### 8.1 Qué es

Laravel Reverb es un servidor de **WebSockets** escrito en PHP que implementa el protocolo de **Pusher**. Un WebSocket es una conexión que el navegador abre una vez y deja abierta, para que el servidor le envíe mensajes cuando quiera (sin que el navegador pregunte). Se usa para que la pantalla de cocina reciba las comandas al instante.

Reverb tiene dos "caras":

- **WebSocket** (`/app/{key}`): donde se conectan los navegadores.
- **API HTTP** (`/apps/{app_id}/events`): donde Laravel (la app o la cola) le pide "envía este evento a este canal".

### 8.2 Configuración

**`backend/config/reverb.php`** (lo usa el proceso `reverb:start`):

| Líneas | Qué configura |
|---|---|
| 31-34 | Host/puerto donde escucha. En Docker se sobrescriben con `--host=0.0.0.0 --port=8081` en el `command` (`docker/nodo/compose.yml:60,80`). |
| 40-52 | Escalado por Redis (sección 7.3). |
| 76-78 | `key`, `secret`, `app_id` de la "aplicación" Pusher. El navegador solo conoce la `key`; el `secret` sirve para firmar. |
| 85 | `allowed_origins` (`*` en Docker). |
| 86-87 | `ping_interval` 60 s y `activity_timeout` 30 s: mantener viva la conexión y detectar clientes muertos. |
| 90 | `accept_client_events_from => 'none'`: los navegadores **no** pueden emitir eventos a otros clientes; solo escuchan. Medida de seguridad. |

**`backend/config/broadcasting.php`** (lo usan `nodoN` y `colaN` para **publicar**):

| Línea | Qué hace |
|---|---|
| 4 | `default => BROADCAST_CONNECTION` (`reverb` en Docker; `log` en el `.env` local, que solo escribe en el log). |
| 10 | `host => REVERB_HOST` (`reverb1`/`reverb2`), `port => REVERB_PORT` (8081): a qué Reverb hacer la petición HTTP. |
| 13 | `timeout 5`, `connect_timeout 2`: si el Reverb no responde, el intento falla rápido y la cola lo reintenta. |

**`backend/config/trackeat.php:6`** — `socket_url`: la dirección que se entrega **al navegador**. Comentario de la línea 5: "Laravel publica directo al Reverb local". Por eso hay dos direcciones distintas:

| Quién | Usa | Valor en Docker | Pasa por Nginx |
|---|---|---|---|
| Laravel (publicar) | `REVERB_HOST:REVERB_PORT` | `reverb1:8081` | No, directo dentro de la red |
| Navegador (escuchar) | `TRACKEAT_SOCKET_URL` | `http://127.0.0.1:8080` | Sí, `location /app` |

### 8.3 El evento: `backend/app/Events/PedidoActualizado.php`

| Línea | Código | Qué hace |
|---|---|---|
| 10 | `implements ShouldBroadcast, ShouldDispatchAfterCommit` | `ShouldBroadcast`: se envía por la **cola** (no en la petición HTTP). `ShouldDispatchAfterCommit`: se encola solo cuando la transacción de la base se confirma; si la transacción falla, no se anuncia un pedido que no existe. |
| 12 | `public int $tries = 5;` | Intentos máximos. **Tiene prioridad** sobre `--tries=3` del worker (Laravel lo copia al trabajo en `BroadcastEvent.php:68`). |
| 14 | `public array $backoff = [1, 2, 5, 10];` | Espera entre intentos: 1 s, 2 s, 5 s, 10 s. |
| 18-24 | `forOrder()` | Construye el evento con lo mínimo del pedido. |
| 26-34 | `broadcastOn()` | Tres canales **privados**: `trackeat.personal` (todo el personal), `trackeat.usuario.{id}` (el cliente dueño), `trackeat.demo.{room}` (salón de demostración). |
| 36-39 | `broadcastAs()` | Nombre del evento: `pedido.actualizado`. |
| 41-44 | `broadcastWith()` | Solo `id`, `numero_pedido`, `estado`, `updated_at`: nunca nombres, correos ni notas. |

Se dispara en `PedidoService.php:171` (crear), `:200` (avanzar estación) y `:228` (cancelar).

### 8.4 Autorización de canales privados: `backend/routes/channels.php`

Un canal privado exige permiso. Flujo:

1. El navegador abre el WebSocket y Reverb le da un `socket_id`.
2. El navegador hace `POST /broadcasting/auth` **por el balanceador** (va a cualquier nodo, `location /`) con su cookie de sesión.
3. Laravel ejecuta la regla del canal:
   - Línea 6: `$active` = usuario no eliminado y, si es de demo, que la demo no haya expirado.
   - Línea 7: `trackeat.personal` solo para `administrador`, `cocinero`, `mesero`, `cajero`.
   - Línea 8: `trackeat.usuario.{id}` solo para el propio usuario.
   - Línea 9: `trackeat.demo.{room}` solo para quien está en ese salón.
4. Si se permite, Laravel firma `socket_id:canal` con el `REVERB_APP_SECRET` y el navegador envía esa firma a Reverb, que la verifica con el mismo secreto.

Por eso `REVERB_APP_SECRET` debe ser igual en todos los nodos y Reverb (lo es: está fijo en `docker/nodo/compose.yml:35`). Se registra solo gracias a `channels:` en `backend/bootstrap/app.php:14`.

### 8.5 El cliente en el navegador: `frontend/src/lib/trackeat.js`

| Líneas | Qué hace |
|---|---|
| 8-15 | `api()`: todas las llamadas son relativas (`/api/...`), así que van al mismo origen, es decir, al balanceador. |
| 16-19 | Si responde 419 (token CSRF caducado), renueva la sesión y reintenta una vez. |
| 35-39 | `session()`: `GET /api/sesion` devuelve usuario, token CSRF y `socket: {key, url}` (`TrackEatController.php:41`). |
| 57-59 | Elige canales según el rol. |
| 81-89 | `schedulePoll()`: **sondeo cada 5 s**. Siempre está activo; es la red de seguridad si se pierde un evento o cae el WebSocket. |
| 130-143 | Crea el cliente Pusher con `wsHost`/`wsPort` sacados de `socket.url` (127.0.0.1:8080) y la ruta de autorización `/broadcasting/auth`. |
| 145-158 | Se suscribe a cada canal; al recibir `.pedido.actualizado` llama a `sync()`, que espera 100-250 ms al azar y recarga los datos (el azar evita que todas las pantallas golpeen el servidor en el mismo milisegundo). |
| 159-163 | Si la conexión deja de estar `connected`, muestra "Actualización automática · cada 5 s". `pusher-js` se reconecta solo; al reconectar, Nginx lo manda al Reverb que esté vivo. |

### 8.6 Un Reverb por nodo: comportamiento comprobado

- Con `reverb1` apagado, un WebSocket nuevo entra por el balanceador y termina en `reverb2`.
- **En navegador real** (Chrome con Playwright, pantalla `/comandas`): al apagar el Reverb al que estaba conectada, la página abrió un segundo WebSocket (Nginx lo mandó al otro Reverb) y siguió recibiendo eventos (5 de 5). Esto estaba pendiente en el informe y ya queda verificado.
- Matiz: los eventos que tome `cola1` con `reverb1` caído fallan en ese intento; ver sección 9.4 para saber cómo se recuperan.

---

## 9. Las colas

### 9.1 Qué es una cola

Una cola es una lista de **trabajos pendientes** que un proceso aparte (*worker*) ejecuta en segundo plano. Sirve para que la petición del usuario responda rápido y para reintentar automáticamente lo que falle. Aquí el único trabajo es enviar `PedidoActualizado` a Reverb: si Reverb tarda o está caído, el usuario que creó el pedido no espera ni recibe un error.

### 9.2 Configuración

- **`backend/config/queue.php:16`** — `default => QUEUE_CONNECTION` (`database`).
- **`:37-43`** — conexión `database`: tabla `jobs`, cola `default`, `retry_after` 90 s (si un worker toma un trabajo y muere, a los 90 s otro lo puede retomar).
- **`:106-110`** — los trabajos fallidos van a `failed_jobs`.
- **Tablas:** `backend/database/migrations/0001_01_01_000002_create_jobs_table.php`.
- **Worker:** `docker/nodo/compose.yml:67,87` → `php artisan queue:work --sleep=1 --tries=3`.
  - `--sleep=1`: si la cola está vacía, espera 1 s antes de volver a preguntar. Es la latencia máxima añadida a un evento (en la práctica se vieron unos milisegundos).
  - `--tries=3`: valor por defecto, pero el evento lo sobrescribe con 5 (sección 8.3).

### 9.3 Por qué en la base y no en Redis

Laravel puede usar Redis como cola, pero se eligió `database` para no hacer depender la operación de Redis: si Redis cae, los pedidos siguen creándose y los trabajos siguen procesándose (solo se pierde el reparto en vivo). La tabla además es compartida y transaccional.

### 9.4 Dos workers, una tabla

Ambos workers leen la misma tabla `jobs`. Laravel toma cada trabajo con `SELECT ... FOR UPDATE SKIP LOCKED` (`vendor/laravel/framework/src/Illuminate/Queue/DatabaseQueue.php:357`): el trabajo que uno bloquea, el otro lo salta. Nunca se procesa dos veces.

**Consecuencia:** un evento generado en el **nodo 2** puede procesarlo **`cola1`** y publicarse en **`reverb1`**. La frase "cada nodo publica en su propio Reverb" es exacta para la petición HTTP, pero el envío real lo hace el worker que tome el trabajo. No importa, porque Redis lo reparte a todos (comprobado en los logs: `cola1` y `cola2` procesan eventos de ambos nodos).

**Recuperación comprobada con `reverb1` apagado:**

```
19:51:19 cola1  PedidoActualizado  RUNNING
19:51:21 cola1  PedidoActualizado  2s FAIL     (no conecta con reverb1)
19:51:22 cola1  PedidoActualizado  RUNNING     (reintento tras 1 s)
19:51:24 cola1  PedidoActualizado  2s FAIL
19:51:26 cola2  PedidoActualizado  DONE        (el siguiente reintento lo tomó cola2 → reverb2)
```

El reintento vuelve a la tabla compartida y lo puede tomar el otro worker, cuyo Reverb sí está vivo. El evento llegó unos 7 s tarde, pero llegó, y `failed_jobs` quedó en 0. Esto mejora lo que dice el informe ("se reintentan 3 veces y quedan como fallidos").

**Matiz con Redis caído:** el trabajo **no** falla (Reverb acepta la petición HTTP y la publicación en Redis falla dentro de Reverb), así que no se reintenta y el aviso se pierde; lo recupera el sondeo de 5 s.

---

## 10. Nivel de nodo de aplicación

Lo que vive **dentro** de cada `nodoN` y hace que dos copias se comporten como una sola aplicación.

### 10.1 Servidor HTTP: `php artisan serve`

Servidor integrado de PHP. Con `PHP_CLI_SERVER_WORKERS=4` (del `backend/.env`, vía `env_file`) atiende 4 peticiones a la vez. Es un servidor de desarrollo: suficiente para la práctica, no para producción (ahí iría PHP-FPM o Octane). Su comportamiento con archivos se explica en la sección 12.

### 10.2 Confianza en el proxy: `backend/bootstrap/app.php:25-28`

```php
$proxies = trim((string) env('TRUSTED_PROXIES', '127.0.0.1,::1'));
$middleware->trustProxies(at: in_array($proxies, ['*', '**'], true)
    ? $proxies
    : array_values(array_filter(array_map('trim', explode(',', $proxies)))));
```

Laravel ignora por defecto `X-Forwarded-For/Proto/Host` (cualquiera podría falsificarlas). Esta línea le dice de qué proxies creerlas: `*` (Docker, el valor por defecto) o una lista de IPs separadas por comas. Efectos: `request()->ip()` devuelve la IP del cliente y no la de Nginx, y las URLs que genera Laravel usan el esquema y host originales.

### 10.3 Identificación del nodo

- `backend/config/trackeat.php:8` — `'nodo' => env('NODO', gethostname())`: `NODO` viene de Compose (`"1"`, `"2"`).
- `backend/app/Http/Middleware/IdentificarNodo.php:14-15` — tras generar la respuesta añade `X-Servidor: <nodo>`. Se registra para **todas** las rutas con `$middleware->append(...)` en `bootstrap/app.php:29`.
- `backend/routes/salud.php` — `GET /salud`: ejecuta `select 1` en la base; responde `{"nodo","bd":"ok","ip_cliente"}` (200) o `{"nodo","bd":"caida"}` (503). Se carga en `bootstrap/app.php:17-19` con `Route::middleware([])`, es decir, **sin** el grupo `web`: no crea sesiones ni cookies en cada consulta.

### 10.4 Sesión compartida

- `SESSION_DRIVER=database` → tabla `sessions` en PostgreSQL.
- `APP_KEY` idéntica en todos los nodos (`env_file` del mismo `backend/.env`): con ella se cifra la cookie de sesión y se firma el token CSRF. Con claves distintas, el nodo 2 no podría descifrar la cookie creada por el nodo 1 y el usuario "perdería" la sesión.
- Comprobado: sesión iniciada por el balanceador; la misma cookie contra `:8001` y `:8002` devuelve "Cocinero Demo" en ambos.

### 10.5 Los tres procesos del nodo

| Contenedor | Proceso | Necesita | Si cae |
|---|---|---|---|
| `nodoN` | `artisan serve` | PostgreSQL | Nginx lo saca del reparto |
| `reverbN` | `reverb:start` | Redis | Las pantallas se reconectan al otro Reverb |
| `colaN` | `queue:work` | PostgreSQL, `reverbN` | El otro worker procesa todo |

No hay `depends_on` entre nodo e infraestructura (no puede haberlo en modo tres PCs, porque están en archivos y máquinas distintas). Lo cubre `restart: unless-stopped`: `cola1`/`cola2` se reinician solos hasta que existe la tabla `jobs` (por eso se les ve en `Restarting` antes del `migrate`).

---

## 11. Nivel de nodo de infraestructura

La PC (o el grupo de contenedores) con las piezas únicas:

| Pieza | Qué aporta | Es punto único de falla | Si cae (comprobado) |
|---|---|---|---|
| Nginx | Puerta de entrada y reparto | Sí | Nadie entra |
| PostgreSQL | Datos, sesión, caché, cola | Sí | `/salud` → 503; la aplicación no funciona |
| Redis | Sincronía entre Reverb | Sí, solo para el tiempo real | Ningún evento en vivo; las pantallas se actualizan cada 5 s |

Por qué juntas: comparten el papel de "lo que todos los nodos necesitan" y así hay **una** IP que configurar en los nodos (`DB_HOST`, `REDIS_HOST`) y **una** IP que darle a los usuarios (`URL_PUBLICA`). Lo que **no** necesita esta PC: ni el frontend compilado ni el `backend/.env`, porque no ejecuta Laravel.

---

## 12. Redireccionamiento dentro de la aplicación

Una petición a `localhost:8080` atraviesa **cuatro capas** que deciden a dónde va:

```
1. Nginx           /app...        → Reverb
                   /favicon.ico   → 204 sin tocar los nodos
                   todo lo demás  → nodo1 | nodo2 (least_conn)
2. PHP serve       ¿existe el archivo/carpeta en public/?  → lo sirve tal cual (sin Laravel)
   (server.php)    si no                                   → public/index.php (Laravel)
3. Laravel         backend/routes/web.php, auth.php, salud.php, /broadcasting/auth
4. Inertia/Svelte  la ruta indica qué página .svelte se dibuja
```

**Capa 2** — `vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php:12-14`:

```php
if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;   // el servidor de PHP sirve el archivo directamente
}
```

Por eso `/build/assets/*.js`, `/logo.svg`, `/menu/` y `/personal/` **no pasan por Laravel**: llevan `X-Nodo` (de Nginx) pero no `X-Servidor`. Y `/menu` (sin barra) tampoco llega a la ruta de `web.php:30`: como la carpeta `public/menu` existe, PHP sirve directamente su `index.html`.

**Capa 3** — rutas (`backend/routes/web.php`) y resultado medido por el balanceador:

| Ruta | Línea | Qué hace | Resultado por `:8080` |
|---|---|---|---|
| `/` | 10-12 | Inertia `public/landing` | 200 |
| `/dashboard` | 14-19 | Inertia `public/dashboard` | 200 |
| `/comandas` | 21-23 | Inertia `public/comandas` (pantalla de cocina) | 200 |
| `/comandas/desglose` | 25-27 | Inertia `public/desglose-comandas` | 200 |
| `/menu`, `/menu/` | 30 | Redirige a `/menu/`; en la práctica lo sirve PHP como estático | 200 |
| `/personal`, `/personal/` | 45 | Igual, catálogo de personal sin conexión | 200 |
| `/recetas`, `/metricas` | 47-59 | Reutilizan `public/dashboard` | 200 |
| `/login`, `/register` | `auth.php:19,14` | Inertia `auth/login`, `auth/register` | 200 |
| `GET /api/catalogo` | 34 | Productos y categorías (JSON) | 200 |
| `GET /api/sesion` | 35 | Usuario, CSRF y datos del socket | 200 |
| `GET /api/pedidos` | 37 | Pedidos (requiere sesión) | Sin sesión: 302 → `/login` (HTML) o 401 (JSON) |
| `POST /api/pedidos` | 38 | Crear pedido, 60 por minuto | 200 con sesión de cliente |
| `PATCH /api/pedidos/{id}/estacion` | 39 | Avanzar estación | 200 con sesión de personal |
| `GET /api/tablero` | 40 | Conteo por estado | Requiere sesión |
| `/salud` | `salud.php` | Estado del nodo y la base | 200 / 503 |
| `/up` | `bootstrap/app.php:16` | Salud nativa de Laravel | 200 |
| `POST /broadcasting/auth` | automática | Firma la suscripción a canales privados | 200 con sesión |

**Redirecciones de la aplicación:**

- `auth` sin sesión → `/login` (middleware de Laravel).
- Tras el login → `redirect()->intended(route('home'))`, es decir, la página que se intentaba abrir o `/` (`AuthenticatedSessionController.php:36`). Se usa `absolute: false`, así que la redirección es relativa y conserva `:8080`.
- Logout → `/`.
- `/menu` → `/menu/` y `/personal` → `/personal/` (solo si la carpeta compilada no existiera).

---

## 13. Modo una PC contra modo tres PCs

| Concepto | Una PC | Tres PCs |
|---|---|---|
| Archivo | `docker/compose.yml` | `infra/compose.yml` en A; `nodo/compose.yml` en B y C (nombrando los servicios) |
| Cómo se encuentran | Nombres de Docker (`db`, `nodo1`) por el DNS `127.0.0.11` | `IP:puerto publicado` desde `docker/.env` |
| `DB_HOST` / `DB_PORT` | `db` / `5432` | IP de A / `5433` |
| `NODO1_ADDR` | `nodo1:8000` | `IP-B:8001` |
| `ESCUCHA` | `127.0.0.1` | `0.0.0.0` |
| Qué debe ser idéntico | — | `backend/.env` (`APP_KEY`) y `docker/.env` en las tres |

Con IPs fijas, `resolve` en Nginx no aporta nada (una IP no "se resuelve"), pero tampoco estorba. El modo tres PCs sigue **sin probarse entre máquinas reales**.

---

## 14. Revisión del funcionamiento

### 14.1 Qué se hizo

En este equipo (`C:\Tareas\track-eat`, rama `andryk`): `pnpm build`, construcción de la imagen, `up -d` de los 9 contenedores, `migrate --seed` y estas pruebas, todas a través de `http://localhost:8080`:

| Prueba | Resultado |
|---|---|
| `/salud` repetido | Alterna nodo 1 y nodo 2 ✅ |
| Las 20 rutas de la tabla de la sección 12 | Todas responden como se espera ✅ |
| Recursos de Vite de las páginas Inertia | 200 ✅ (tras recompilar, ver H5) |
| Sesión iniciada por el balanceador y usada en `:8001` y `:8002` | Mismo usuario en ambos ✅ |
| 4 pedidos de un cliente | Se repartieron nodo 1, 2, 1, 2; todos 200 ✅ |
| Cocinero: `GET /api/pedidos` y `PATCH .../estacion` | 200 ✅ |
| WebSocket por `:8080/app` + `/broadcasting/auth` + canal `private-trackeat.personal` | 5 de 5 eventos ✅ |
| Prueba de navegador del proyecto (`frontend/tests/realtime-browser.mjs`) contra `:8080` | Pasa en modo WebSocket y en modo respaldo sin socket ✅ |
| `reverb1` apagado | 4 eventos al instante y el 5.º ~7 s después por reintento en `cola2` ✅ |
| Navegador real con cada Reverb apagado por turnos | Se reconecta al otro y sigue recibiendo ✅ |
| Redis apagado | 0 eventos en vivo; al volver, 5 de 5 ⚠️ (ver H2) |
| `nodo2` apagado | El sitio sigue, pero aparece **un 504** ⚠️ (ver H1) |

**Conclusión:** la aplicación funciona y el direccionamiento desde `localhost:8080` hacia menú, pedidos, comandas, API, login y WebSockets es correcto. Los hallazgos de abajo son problemas puntuales y diferencias con la documentación, no fallas de diseño.

### 14.2 Hallazgos

**H1 · Un 504 al apagar un nodo (`resolve` en Nginx). Severidad media.**
Con `nodo2` apagado y peticiones cada 0.5 s, aparece **un 504 tras 2 s** en cada apagado (reproducido 2 de 2 veces). En el log de Nginx coincide con `nodo2 could not be resolved (3: Host not found)`: al detenerse el contenedor, su nombre desaparece del DNS de Docker, y la petición que estaba en curso hacia la IP vieja no se reintentó en `nodo1`. Prueba de control: un Nginx temporal idéntico **pero sin `resolve`** dio 0 errores en 32 peticiones con el mismo apagado. No se investigó la causa interna de Nginx; la relación con `resolve` es lo que se observó. Contradice el "0 fallidas" del informe (con `ab` probablemente no se coincidió con esa ventana).
*Opciones:* quitar `resolve` y `zone` en el modo tres PCs (allí las direcciones son IPs fijas y no lo necesita); o probar `proxy_next_upstream_tries 2;` y volver a medir; o documentar que puede haber un error aislado durante el apagado.

**H2 · Con Redis caído no llega ningún evento en vivo. Documentación incorrecta.**
`docs/balanceo.md` (tabla de fallos) e `informe-practica-5.md` §5 dicen que una pantalla seguiría viendo lo publicado en su propio Reverb. Con escalado activo, Reverb solo publica en Redis (sección 7.4), así que no llega nada a nadie. Las pantallas se mantienen al día con el sondeo de 5 s. *Corregir el texto.*

**H3 · Los reintentos son 5, no 3, y se recuperan solos.**
El informe dice "3 veces seguidas". El evento define `$tries = 5` y `$backoff = [1,2,5,10]`, que tienen prioridad sobre `--tries=3`. Además, el reintento lo puede tomar el otro worker (sección 9.4), así que con un solo Reverb caído el evento termina llegando. *Actualizar el texto.*

**H4 · `docker compose build` falla de forma intermitente.**
Seis servicios declaran `build:` con la misma etiqueta `trackeat-app`; Compose los construye en paralelo y uno falla con `image "docker.io/library/trackeat-app:latest": already exists`. Pasó en este equipo. *Solución inmediata:* `docker compose -f docker/compose.yml build nodo1` (los seis usan esa imagen). *Solución en el archivo:* dejar `build:` solo en un servicio y `pull_policy: never` en el resto, o documentar el comando.

**H5 · En este checkout el frontend compilado era antiguo.**
`backend/public/build/manifest.json` era de septiembre y tenía entradas `.tsx` (versión React); faltaban `public/menu` y `public/personal`. Como el Dockerfile copia `backend/` tal cual, una imagen construida así daría error de Vite en las páginas Inertia y no tendría menú. Se corrigió ejecutando `pnpm build` (archivos ignorados por git). *Recomendación:* en las guías, poner `pnpm build` como paso obligatorio antes de `docker compose build` en cada PC de nodo. Si faltara `public/menu`, la ruta `/menu` redirigiría a `/menu/` y Laravel la volvería a tratar como `/menu`: un bucle de redirecciones (deducido del código, no probado).

**H6 · `location /app` captura más de lo debido. Menor.**
Es un prefijo sin barra: `/apple`, `/applications`, etc. también irían a Reverb (comprobado: `/apple` → 404 de Reverb). Hoy no hay rutas así. *Mejor:* `location /app/`.

**H7 · El comentario de `routes/salud.php:6` es engañoso. Menor.**
Dice "el balanceador la consulta seguido", pero Nginx gratuito no hace comprobaciones activas: solo detecta fallos cuando una petición real falla (`max_fails`). `/salud` la consultan las personas y las pruebas. Además, al responder 503 con la base caída, `proxy_next_upstream ... http_503` hace que Nginx reintente en el otro nodo (que también dará 503).

**H8 · Seguridad en modo tres PCs. A tener en cuenta.**
Con `ESCUCHA=0.0.0.0`, PostgreSQL (5433) y Redis (6380) quedan abiertos a la red con contraseñas por defecto (`trackeat`, `trackeat-redis`), y el `.env` tiene `APP_DEBUG=true` (en un error muestra trazas). Para la demo en el salón está bien; fuera de ella, cambiar contraseñas y apagar el modo depuración.

**H9 · `vendor/` local sin `laravel/reverb`. Solo fuera de Docker.**
`composer.json` exige `laravel/reverb`, pero el `backend/vendor` de este equipo no lo tiene (la imagen sí, porque instala desde `composer.lock`). Para ejecutar Reverb fuera de Docker hace falta `composer install`.

---

## 15. Glosario

| Término | Significado |
|---|---|
| **Balanceador** | Programa que recibe todas las peticiones y las reparte entre varios servidores iguales. Aquí, Nginx. |
| **Upstream** | En Nginx, el grupo de servidores entre los que se reparte. |
| **least_conn** | Algoritmo: al servidor con menos conexiones abiertas. |
| **Nodo** | Una copia completa de la aplicación (app + Reverb + cola). |
| **Proxy inverso** | Servidor que recibe peticiones en nombre de otros y se las reenvía (Nginx respecto a los nodos). |
| **WebSocket** | Conexión permanente navegador↔servidor por la que el servidor puede enviar mensajes en cualquier momento. |
| **Reverb** | Servidor de WebSockets de Laravel, compatible con el protocolo Pusher. |
| **Broadcast** | En Laravel, enviar un evento del servidor a los navegadores por WebSocket. |
| **Canal privado** | Canal de WebSocket que exige autorización de Laravel (`/broadcasting/auth`). |
| **Pub/sub** | Publicar/suscribir: un emisor publica en un canal y todos los suscritos lo reciben. Lo que hace Redis aquí. |
| **Cola / worker** | Lista de trabajos pendientes / proceso que los ejecuta en segundo plano. |
| **SKIP LOCKED** | Instrucción de PostgreSQL que salta las filas bloqueadas por otro proceso; evita que dos workers tomen el mismo trabajo. |
| **Sesión compartida** | La sesión se guarda en un almacén común (PostgreSQL) y no en la memoria de un servidor. |
| **APP_KEY** | Clave con la que Laravel cifra cookies y firma datos; debe ser igual en todos los nodos. |
| **Healthcheck** | Comando que Docker ejecuta periódicamente para saber si un contenedor está sano. |
| **Punto único de falla** | Pieza sin copia cuya caída detiene el servicio (Nginx, PostgreSQL, y Redis para el tiempo real). |
