# Pasar de una PC a tres PCs

Qué cambiar, qué copiar y qué ejecutar para repartir TrackEat en tres computadoras de la misma red, en lugar de levantar todo en una. Para el modo de una PC, ver [levantar-compose.md](levantar-compose.md).

> **Estado:** este modo está preparado y su configuración se validó, pero **no se ha probado todavía entre computadoras distintas**. Si algo no conecta, casi siempre es el firewall de Windows o una IP mal escrita; ver el apartado «Si algo falla» al final.

## El reparto

Ejemplo con tres PCs (cambia las IPs por las tuyas; se ven con `ipconfig`):

| PC | IP de ejemplo | Qué corre | Archivo |
|---|---|---|---|
| **A** · infraestructura | `192.168.1.10` | PostgreSQL, Redis y el balanceador Nginx | `docker/infra/compose.yml` |
| **B** · nodo 1 | `192.168.1.11` | `nodo1`, `reverb1`, `cola1` | `docker/nodo/compose.yml` |
| **C** · nodo 2 | `192.168.1.12` | `nodo2`, `reverb2`, `cola2` | `docker/nodo/compose.yml` |

Los usuarios entran siempre por la **PC A**: `http://192.168.1.10:8080`. Las tres PCs deben estar en la **misma red** y verse entre sí.

## Qué cambia respecto a una PC

| | Una PC | Tres PCs |
|---|---|---|
| Archivo que se levanta | `docker/compose.yml` (incluye todo) | `docker/infra/compose.yml` en A y `docker/nodo/compose.yml` en B y C |
| Cómo se encuentran los contenedores | Por nombre (`db`, `redis`, `nodo1`...) dentro de una red de Docker | Por **IP de la PC** y puerto publicado |
| `docker/.env` | No hace falta | **Obligatorio**, con las IPs reales, en las tres PCs |
| Comando | `docker compose -f ...` | `docker compose --env-file docker/.env -f ...` |
| Firewall | No aplica | Abrir puertos en cada PC |

El código y los archivos de Docker son los mismos. Solo cambian el `docker/.env` y qué parte se levanta en cada PC.

## 1. Preparar cada PC (las tres)

### 1.1 Conseguir el código

La opción más limpia es **clonar el repositorio** en cada PC y cambiar a la rama con estos cambios:

```powershell
git clone https://github.com/AndrykLeonT/trackeat-web.git
cd trackeat-web
git checkout andryk        # o main, cuando la rama ya esté integrada
```

Con Docker Desktop encendido en cada PC.

### 1.2 Lo que NO viene en git y hay que conseguir

Dos cosas están ignoradas por git y no llegan con el `git clone`:

**a) `backend/.env`**: debe ser **el mismo archivo en las tres PCs**.
Copia el `backend/.env` de la PC donde ya funciona el proyecto (por USB, red compartida o mensaje cifrado) y pégalo en `backend/.env` de las otras dos. Es obligatorio que la `APP_KEY` sea idéntica: con claves distintas, un nodo no puede leer la sesión que creó el otro y el usuario aparece sin sesión al cambiar de nodo. No generes una `APP_KEY` nueva en cada PC.

**b) El frontend compilado** (`backend/public/build` y `backend/public/personal`): se genera, no se copia desde git. Dos formas:

- Compilarlo en cada PC que corra un nodo (B y C): `cd frontend`, luego `pnpm install` y `pnpm build`.
- O copiar las carpetas `backend/public/build` y `backend/public/personal` desde la PC donde ya está compilado.

La PC A (infraestructura) **no necesita** ni el frontend ni el `backend/.env` para correr, porque solo levanta PostgreSQL, Redis y Nginx.

### 1.3 Crear `docker/.env`

Copia `docker/.env.example` como `docker/.env` y escribe las IPs reales. **El mismo archivo, con los mismos valores, va en las tres PCs.** Con las IPs de ejemplo de arriba:

```
ESCUCHA=0.0.0.0

# Dónde está la infraestructura (IP de la PC A)
DB_HOST=192.168.1.10
DB_PORT=5433
REDIS_HOST=192.168.1.10
REDIS_PORT=6380

# Dónde están los nodos, tal como los ve el balanceador (IP de cada PC : puerto publicado)
NODO1_ADDR=192.168.1.11:8001
NODO2_ADDR=192.168.1.12:8002
REVERB1_ADDR=192.168.1.11:8091
REVERB2_ADDR=192.168.1.12:8092

# Dirección con la que el navegador entra (la del balanceador, PC A)
URL_PUBLICA=http://192.168.1.10:8080
```

Qué hace cada grupo:

- `ESCUCHA=0.0.0.0` hace que los contenedores acepten conexiones de otras PCs. Sin esto solo escuchan en `127.0.0.1` y las otras PCs no llegan.
- `DB_*` y `REDIS_*` los usan los nodos y los Reverb para llegar a la PC A. Los puertos son los **publicados** (5433 y 6380), no los internos de los contenedores.
- `NODO*_ADDR` y `REVERB*_ADDR` los usa Nginx (en la PC A) para llegar a los nodos.
- `URL_PUBLICA` se usa como `APP_URL` y como dirección del WebSocket que recibe el navegador. Debe ser la del balanceador; si dejas `127.0.0.1`, el WebSocket intentará conectar al equipo del propio visitante.

Opcional (ya tienen valor por omisión, solo cámbialos si quieres):

```
DB_PASSWORD=trackeat
REDIS_PASSWORD=trackeat-redis
TRUSTED_PROXIES=*
```

`TRUSTED_PROXIES=*` (el valor por omisión) acepta cualquier proxy directo y funciona siempre. Si quieres restringirlo a la PC del balanceador, pon su IP, pero si el tráfico llega con otra dirección de origen (por ejemplo por la red de WSL) puede dar problemas con las cabeceras `X-Forwarded-*`; ante la duda, déjalo en `*`.

### 1.4 Abrir el firewall de Windows

En cada PC, PowerShell **como administrador**, con los puertos que esa PC publica:

```powershell
# PC A (infraestructura)
New-NetFirewallRule -DisplayName "TrackEat infra" -Direction Inbound -Protocol TCP -LocalPort 8080,5433,6380 -Action Allow -Profile Any

# PC B (nodo 1)
New-NetFirewallRule -DisplayName "TrackEat nodo1" -Direction Inbound -Protocol TCP -LocalPort 8001,8091 -Action Allow -Profile Any

# PC C (nodo 2)
New-NetFirewallRule -DisplayName "TrackEat nodo2" -Direction Inbound -Protocol TCP -LocalPort 8002,8092 -Action Allow -Profile Any
```

Para quitarlas después: `Remove-NetFirewallRule -DisplayName "TrackEat infra"` (y lo mismo con los otros nombres).

## 2. Levantar (en este orden)

El orden no es estricto (los contenedores se reintentan solos), pero así es más limpio.

**PC A, infraestructura:**

```powershell
docker compose --env-file docker/.env -f docker/infra/compose.yml up -d
```

**PC B, nodo 1:**

```powershell
docker compose --env-file docker/.env -f docker/nodo/compose.yml up -d --build nodo1 reverb1 cola1
```

**PC C, nodo 2:**

```powershell
docker compose --env-file docker/.env -f docker/nodo/compose.yml up -d --build nodo2 reverb2 cola2
```

Los nombres al final (`nodo1 reverb1 cola1`) son importantes: el archivo `docker/nodo/compose.yml` define los dos nodos y esos nombres le dicen a Docker cuál levantar en esa PC. Si los omites, intentará levantar los seis.

**Una sola vez**, desde la PC B (o la C), crear las tablas y los datos de ejemplo:

```powershell
docker compose --env-file docker/.env -f docker/nodo/compose.yml exec nodo1 php artisan migrate --seed --force
```

## 3. Comprobar que funciona

Desde **cualquier PC** (o un celular en la misma red), abre el balanceador:

- `http://192.168.1.10:8080/salud` y recarga varias veces: el campo `nodo` cambia entre `"1"` y `"2"`.
- En F12 > Red, la cabecera `X-Nodo` ahora muestra la **IP de la PC** que atendió (`192.168.1.11:8001` o `192.168.1.12:8002`), en lugar de una IP interna de Docker.
- `ip_cliente` en `/salud` debería mostrar la IP de quien consulta. Docker Desktop en Windows a veces oculta la IP original y muestra la de la puerta de enlace de Docker (`172.x.0.1`); es un detalle de la red de Docker Desktop, no un fallo del balanceo.

Verificaciones de conexión, si algo falla (desde PowerShell en la PC que corresponda):

```powershell
Test-NetConnection 192.168.1.10 -Port 5433   # ¿llega a PostgreSQL?  (desde B y C)
Test-NetConnection 192.168.1.10 -Port 6380   # ¿llega a Redis?       (desde B y C)
Test-NetConnection 192.168.1.11 -Port 8001   # ¿llega al nodo 1?     (desde A)
Test-NetConnection 192.168.1.12 -Port 8002   # ¿llega al nodo 2?     (desde A)
```

Cada una debe decir `TcpTestSucceeded : True`.

## 4. Probar la tolerancia a fallos

Cada comando se ejecuta en la PC donde corre ese contenedor, con `/salud` recargándose desde otra:

```powershell
docker stop trackeat-nodo2        # (en la PC C) el sitio sigue; solo responde el nodo 1
docker start trackeat-nodo2
docker stop trackeat-reverb1      # (en la PC B) el otro Reverb atiende los WebSockets
docker start trackeat-reverb1
docker stop trackeat-db-docker    # (en la PC A) /salud da 503 con "bd":"caida"
docker start trackeat-db-docker
```

Y la más ilustrativa: **apagar una PC entera** (la B o la C). El balanceador la saca del reparto y el sitio sigue con la otra.

## 5. Apagar

En cada PC, con el mismo archivo con que se levantó:

```powershell
docker compose --env-file docker/.env -f docker/infra/compose.yml down   # PC A
docker compose --env-file docker/.env -f docker/nodo/compose.yml down    # PC B y C
```

## Variante: solo dos PCs

Si no hay tres computadoras, la PC A puede correr también un nodo: levanta en ella `infra` y además `nodo1 reverb1 cola1` (con el mismo `docker/.env`, poniendo `NODO1_ADDR=192.168.1.10:8001` y `REVERB1_ADDR=192.168.1.10:8091`), y la segunda PC corre el nodo 2. Hay que abrir también los puertos 8001 y 8091 en la PC A. Es el mismo principio, con una PC menos (esta variante tampoco se ha probado).

## Si algo falla

| Síntoma | Causa probable |
|---|---|
| Los nodos no conectan a la base (`/salud` da 503 o `connection refused`) | Firewall de la PC A sin los puertos 5433 y 6380, o `DB_HOST`/`DB_PORT` mal escritos. Probar con `Test-NetConnection`. |
| El balanceador da 502 | No llega a un nodo: `NODO1_ADDR`/`NODO2_ADDR` con la IP o el puerto mal, o firewall de la PC del nodo sin el 8001/8002. |
| Al cambiar de nodo, el usuario pierde la sesión | `backend/.env` distinto en cada PC: la `APP_KEY` debe ser idéntica. |
| La página carga pero el tiempo real no conecta | `URL_PUBLICA` con una IP equivocada (o `127.0.0.1`), o puertos 8091/8092 cerrados, o Redis inalcanzable desde los nodos. |
| `up` en una PC de nodo levanta contenedores de más | Faltó poner los nombres de los servicios al final del comando (`nodo1 reverb1 cola1`). |
| `docker compose` no toma el `.env` | Faltó `--env-file docker/.env`, o se ejecutó desde otra carpeta; corre siempre desde la raíz del proyecto. |
| Una PC dejó de tener la misma IP | La IP de la LAN cambió (DHCP): actualiza `docker/.env` en las tres PCs y vuelve a levantar. Conviene fijar las IPs en el router. |
