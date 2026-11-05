# Balanceo de carga — TrackEat

Aplicaciones Web II · Unidad 3 · Sesión 15
Instituto Tecnológico de La Paz · Grupo 8-H

Equipo: Daniel Alexander Estrada Cosio, Angelica Menchaca Rueda, Andryk Manuel Leon Tapia, Carlos Omar Celis Calzada.

El informe de la práctica (opción de nodos, diagrama, mediciones y pruebas) está en [informe-practica-5.md](informe-practica-5.md).

## Tabla de casos

| Caso | Algoritmo | Por qué | Qué pasaría con turno rotatorio |
|---|---|---|---|
| A · Catálogo público | Turno rotatorio (round robin) | Las peticiones son cortas y se parecen entre sí, y los servidores son iguales, así que repartir en orden deja la carga distribuida de forma equitativa. | Es el caso ideal del turno rotatorio, por lo que funcionaría bien. |
| B · Reportes de ventas | Menos conexiones (least connections) | Si un reporte mantiene la conexión abierta mucho tiempo, el balanceador ve que ese servidor está ocupado y manda las siguientes peticiones a otros. Aunque no haya recibido más conexiones nuevas, toma en cuenta las que siguen activas. | Mientras un servidor sigue procesando un reporte largo, le pueden seguir tocando peticiones por turno; las peticiones cortas esperarían a que se libere y el servidor se iría saturando poco a poco. |
| C · Máquinas desiguales | Turno rotatorio ponderado (weighted) | Como un servidor tiene menos capacidad que los otros, se le asigna menos peso para que cada servidor reciba carga de acuerdo con lo que puede manejar. | La carga se repartiría pareja aunque las capacidades no lo son, y el servidor con menos recursos se saturaría poco a poco. |
| D · Pantalla de cocina | Menos conexiones (least connections) | El WebSocket se mantiene abierto mientras dure la conexión, así que lo ideal es mandar cada pantalla nueva al servidor que tenga menos conexiones activas. | Solo repartiría las conexiones nuevas sin considerar cuáles siguen abiertas. Las conexiones quedarían disparejas y algunos servidores acumularían varias sesiones largas mientras otros siguen disponibles. |
| E · TrackEat | Menos conexiones (least connections) | TrackEat combina peticiones cortas (menú, pedidos, cobros) con conexiones largas (pantallas de cocina y barra por WebSocket) y consultas más pesadas (gráficos y cortes de caja). Menos conexiones toma en cuenta las conexiones que siguen activas, y con peticiones cortas se comporta prácticamente igual que el turno rotatorio, así que cubre todos los perfiles. La sesión se resuelve con almacén compartido (ver abajo). | Funcionaría bien para el menú, pero las pantallas de cocina y los reportes se repartirían sin considerar las conexiones abiertas, y algunos servidores se saturarían mientras otros quedan libres. |

## Detalle del caso E

### Perfiles de tráfico

| Perfil | Quién lo usa | Tipo de petición |
|---|---|---|
| Menú público (catálogo, búsqueda, filtros) | Visitantes y clientes | Corta y parecida. Es la de mayor volumen. |
| Operación (crear pedido, actualizar estado, registrar pago) | Clientes, meseros, cocineros y cajero | Corta, con escritura en base de datos. |
| Tiempo real (comandas y estado del pedido) | Cocineros, meseros y clientes | WebSocket que se queda conectado todo el turno. |
| Panel administrativo (gráficos, cortes de caja) | Administrador y cajero | Más pesada que el resto por las consultas de agregación. |

### Algoritmo

Se usa menos conexiones para todo el sistema. Al haber conexiones de duración muy distinta, es el algoritmo que mejor evita que un servidor se sature mientras otros están libres.

### Sesión

Se resuelve con **almacén compartido**: la sesión se guarda en la base de datos PostgreSQL que ya usa el proyecto (`SESSION_DRIVER=database`), así cualquier servidor puede leerla sin importar a cuál llegó la petición anterior.

Se descartan las otras dos opciones:

- **Afinidad (ip hash):** en un restaurante o food truck, el personal y muchos clientes salen por la misma red, así que caerían todos en un solo servidor.
- **Testigo firmado:** es difícil invalidarlo antes de que caduque; si el administrador desactiva a un empleado, su sesión seguiría siendo válida.

### Tiempo real

Los WebSockets los atiende **Laravel Reverb**, y hay **un Reverb por nodo de aplicación**. Si hubiera uno solo, su caída dejaría a todas las pantallas sin eventos en vivo.

Con dos Reverb aparece un problema: cada uno solo conoce a los clientes que tiene conectados. Una comanda publicada en el Reverb del nodo 1 no llegaría a una pantalla de cocina conectada al Reverb del nodo 2. Se resuelve con **Redis como canal compartido** (`REVERB_SCALING_ENABLED=true`): cada Reverb publica en Redis lo que recibe y escucha lo que publican los demás, así que una comanda llega a todas las pantallas sin importar a qué Reverb esté conectada cada una.

El flujo de una comanda es:

1. El navegador abre su WebSocket al balanceador (`/app`), y Nginx lo manda al Reverb con menos conexiones.
2. Cuando se crea o cambia un pedido, el nodo (o el worker de su cola) lo publica en **su propio** Reverb.
3. Ese Reverb lo difunde por Redis y todos los Reverb lo entregan a sus clientes.

Si un Reverb cae, el cliente se reconecta y Nginx lo manda al otro. Si un nodo completo cae, el balanceador deja de enviarle tráfico y el otro nodo sigue atendiendo.

**Un solo Redis, no uno por nodo.** Con un Redis por nodo cada Reverb publicaría en uno distinto y nunca se enterarían de los eventos del otro. Por eso Redis es compartido y vive junto a la base de datos.

### Qué falla y qué sigue funcionando

| Si cae... | Qué pasa |
|---|---|
| Un nodo de la app | Nginx lo saca del reparto y el otro atiende todo. La sesión no se pierde (está en PostgreSQL). |
| Un Reverb | Las conexiones nuevas se van al otro Reverb (comprobado con un cliente WebSocket). El frontend está diseñado para reconectarse solo y consulta el estado cada 5 s como respaldo, pero no se ha probado en navegador. Los eventos que el nodo del Reverb caído intente publicar se reintentan 3 veces seguidas desde la cola; si no vuelve a tiempo, quedan como trabajo fallido y el sondeo del frontend los recupera. |
| Una cola | La otra cola sigue procesando; ambas leen de la misma tabla de trabajos sin duplicarlos. |
| **Redis** | La app sigue funcionando, pero los eventos en vivo dejan de cruzar de un Reverb a otro (una pantalla solo vería lo publicado en su propio Reverb). |
| **PostgreSQL** | Cae la aplicación completa. Es la única base de la práctica. |
| **Nginx** | Nadie puede entrar. Es una sola instancia. |

Los tres últimos son puntos únicos de falla y viven en la misma PC (la de infraestructura). Reducirlos exigiría replicación de PostgreSQL, Redis Sentinel y un segundo balanceador, lo cual queda fuera del alcance de la práctica.

## Despliegue

Los contenedores se reparten en dos archivos, según el papel que cumplen:

| Archivo | Contenedores | Se levanta en |
|---|---|---|
| `docker/infra/compose.yml` | PostgreSQL, Redis y el balanceador Nginx | La PC de infraestructura |
| `docker/nodo/compose.yml` | Por nodo: la app, su Reverb y su cola (`nodo1`, `reverb1`, `cola1` y `nodo2`, `reverb2`, `cola2`) | Una PC por nodo |
| `docker/compose.yml` | Incluye los dos anteriores | Una sola PC, para todo junto |

Los mismos archivos sirven para los dos modos. Lo único que cambia es `docker/.env`, que dice dónde está cada pieza (ver `docker/.env.example`).

### Modo una PC

No necesita configuración. Los contenedores se encuentran por nombre (`db`, `redis`, `nodo1`, `reverb2`...) dentro de la red del proyecto.

```
docker compose -f docker/compose.yml build
docker compose -f docker/compose.yml up -d
docker compose -f docker/compose.yml exec nodo1 php artisan migrate --seed --force   # solo la primera vez
```

La aplicación queda en http://127.0.0.1:8080. Los nodos también responden directo en el 8001 y el 8002.

### Modo tres PCs

Ejemplo: PC A con la infraestructura (`192.168.1.10`), PC B con el nodo 1 (`192.168.1.11`) y PC C con el nodo 2 (`192.168.1.12`).

1. En las tres, clonar el repositorio, compilar el frontend y **copiar el mismo `backend/.env`**. La `APP_KEY` debe ser idéntica; con claves distintas un nodo no puede leer la sesión que creó el otro.
2. En las tres, crear `docker/.env` a partir de `docker/.env.example` con las IPs reales (`ESCUCHA=0.0.0.0`, `DB_HOST`, `REDIS_HOST`, `NODO1_ADDR`, `NODO2_ADDR`, `REVERB1_ADDR`, `REVERB2_ADDR`, `URL_PUBLICA` y `TRUSTED_PROXIES`).
3. Abrir en el firewall de Windows los puertos que cada PC publica: 8080, 5433 y 6380 en la PC A; 8001 y 8091 en la B; 8002 y 8092 en la C.
4. Levantar cada parte, empezando por la infraestructura:

```
# PC A
docker compose --env-file docker/.env -f docker/infra/compose.yml up -d
# PC B
docker compose --env-file docker/.env -f docker/nodo/compose.yml up -d --build nodo1 reverb1 cola1
# PC C
docker compose --env-file docker/.env -f docker/nodo/compose.yml up -d --build nodo2 reverb2 cola2
# Una sola vez, desde cualquier nodo
docker compose --env-file docker/.env -f docker/nodo/compose.yml exec nodo1 php artisan migrate --seed --force
```

El orden de arranque no es estricto: los contenedores se reinician solos (`restart: unless-stopped`) hasta que encuentran la base y Redis. El navegador entra siempre por `URL_PUBLICA`, la dirección del balanceador.

### Cómo probar la tolerancia a fallos

```
docker stop trackeat-nodo2       # el balanceador sigue respondiendo con el nodo 1
docker stop trackeat-reverb1     # las pantallas se reconectan al Reverb 2
docker start trackeat-nodo2 trackeat-reverb1
```

Nginx vuelve a resolver los nombres de los contenedores cada pocos segundos (`resolve` en el `upstream`), así que un nodo que se levanta de nuevo, aunque cambie de IP, vuelve a recibir tráfico sin reiniciar el balanceador. Con cada petición, la cabecera `X-Nodo` (F12 > Red) indica qué nodo atendió.
