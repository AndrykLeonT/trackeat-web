# Informe · Práctica 5: balanceo de carga en TrackEat

Aplicaciones Web II · DAM-2306 · Unidad 3 · Práctica 5
Instituto Tecnológico de La Paz · Grupo 8-H

Equipo: Daniel Alexander Estrada Cosio, Angelica Menchaca Rueda, Andryk Manuel Leon Tapia, Carlos Omar Celis Calzada.

Las pruebas de este informe se ejecutaron el **5 de noviembre de 2026** en una sola computadora con Windows 11, Docker Desktop (WSL2) y Docker Compose v5. Los números completos de Apache Bench están en [mediciones](mediciones.md); el análisis de casos A–E y la tabla de fallos, en [balanceo](balanceo.md).

## 1. Opción de nodos elegida: contenedores Docker

La práctica permite tres computadoras, máquinas virtuales o contenedores Docker. El equipo eligió **Docker**, por estas razones:

- **Es la opción más accesible.** Cualquier integrante levanta todo igual con un comando, sin instalar Nginx ni PostgreSQL ni configurar IPs a mano.
- **Se presta al comportamiento que se quiere estudiar.** Cada contenedor es un proceso aislado con su propia dirección, que se puede apagar y volver a encender, como si fuera un servidor distinto.
- **Se adapta a lo que el cliente necesite.** Los mismos archivos permiten levantar todo en una sola PC, repartirlo en dos servidores o, lo ideal, en tres. Solo cambia un archivo de variables (`docker/.env`), no el código.
- **Es una práctica habitual** para desplegar aplicaciones web, por lo que lo aprendido aquí se traslada a un entorno real.

**Para la presentación** se muestra el montaje en **una sola PC**, con todos los contenedores en la misma red de Docker. El **modo de tres PCs** (infraestructura en una, un nodo en cada una de las otras dos) está preparado y documentado en [balanceo](balanceo.md#despliegue), pero **no se ha probado todavía entre computadoras distintas**; se mostrará solo si el tiempo y la red del salón lo permiten.

## 2. Diagrama

Nombres, direcciones y puertos tomados de `docker network inspect trackeat_default` con el sistema levantado. La red es `172.18.0.0/16` (puerta de enlace `172.18.0.1`).

```
                          Navegador del usuario
                       http://127.0.0.1:8080  (IP del balanceador)
                                   │
                                   ▼
                    ┌─────────────────────────────┐
                    │ trackeat-balanceador        │
                    │ Nginx · 172.18.0.5 · :80    │   publicado en el anfitrión: 8080
                    └──────┬───────────────┬──────┘
          /  (least_conn)  │               │  /app  (WebSocket, least_conn)
              ┌────────────┴───┐       ┌───┴──────────────┐
              ▼                ▼       ▼                  ▼
   ┌────────────────┐ ┌────────────────┐ ┌─────────────────┐ ┌─────────────────┐
   │ trackeat-nodo1 │ │ trackeat-nodo2 │ │ trackeat-reverb1│ │ trackeat-reverb2│
   │ 172.18.0.4     │ │ 172.18.0.2     │ │ 172.18.0.7      │ │ 172.18.0.8      │
   │ :8000 (→8001)  │ │ :8000 (→8002)  │ │ :8081 (→8091)   │ │ :8081 (→8092)   │
   └───────┬────────┘ └───────┬────────┘ └────────┬────────┘ └────────┬────────┘
           │  trackeat-cola1  │  trackeat-cola2   │                   │
           │  172.18.0.3      │  172.18.0.9       │                   │
           └────────┬─────────┴───────────────────┼───────────────────┘
                    ▼                             ▼
         ┌────────────────────┐         ┌────────────────────┐
         │ trackeat-db-docker │         │ trackeat-redis     │
         │ PostgreSQL         │         │ 172.18.0.6 :6379   │
         │ 172.18.0.10 :5432  │         │ (→6380)            │
         │ (→5433)            │         └────────────────────┘
         └────────────────────┘
```

| Contenedor | Función | IP | Puerto interno | Publicado en el anfitrión |
|---|---|---|---|---|
| `trackeat-balanceador` | Nginx: reparte peticiones y WebSockets | 172.18.0.5 | 80 | **8080** |
| `trackeat-nodo1` | TrackEat (nodo 1) | 172.18.0.4 | 8000 | 8001 |
| `trackeat-nodo2` | TrackEat (nodo 2) | 172.18.0.2 | 8000 | 8002 |
| `trackeat-reverb1` / `reverb2` | WebSockets de cada nodo | 172.18.0.7 / .8 | 8081 | 8091 / 8092 |
| `trackeat-cola1` / `cola2` | Worker de la cola de cada nodo | 172.18.0.3 / .9 | — | — |
| `trackeat-db-docker` | PostgreSQL, base única compartida | 172.18.0.10 | 5432 | 5433 |
| `trackeat-redis` | Canal compartido de los Reverb | 172.18.0.6 | 6379 | 6380 |

Las IPs las asigna Docker al crear los contenedores y pueden cambiar si se recrean. Por eso los nodos y el balanceador se encuentran por **nombre** (`nodo1`, `db`, `redis`...), y para el informe se consultan con:

```
docker network inspect trackeat_default --format '{{range .Containers}}{{.Name}} {{.IPv4Address}}{{"\n"}}{{end}}'
```

## 3. Requisitos de la práctica

| Requisito | Cómo se cumple | Evidencia |
|---|---|---|
| Misma versión en los dos nodos | Los dos usan la misma imagen `trackeat-app`; solo cambia la variable `NODO` | `docker/nodo/compose.yml` |
| Una sola base compartida | Un PostgreSQL (`trackeat-db-docker`); los dos nodos apuntan a `DB_HOST=db` | Los pedidos creados en un nodo se ven en el otro |
| Cada nodo se identifica | Cabecera `X-Servidor` de la app, `X-Nodo` del balanceador (IP del nodo que atendió) y campo `nodo` en `/salud` | `curl -i http://127.0.0.1:8001/` → `X-Servidor: 1`; el puerto 8002 → `X-Servidor: 2` |
| `/salud` revisa la base de verdad | Ejecuta `select 1`; si falla responde 503 | Ver abajo |
| La sesión sobrevive al cambio de nodo | **Almacén compartido**: `SESSION_DRIVER=database`, misma `APP_KEY` en los dos nodos | Ver abajo |
| `trustProxies` | `bootstrap/app.php` acepta una lista de IPs o `*`; en Docker se usa `*` | `docker/nodo/compose.yml` |
| `$http_host`, favicon, `proxy_next_upstream`, `X-Nodo` | Están en la plantilla del balanceador | `docker/infra/nginx.conf.template` |
| Probar al menos dos algoritmos | Turno rotatorio y `least_conn` | Sección 4 |
| Apache Bench: solo, 2 algoritmos y nodo apagado | Cuatro corridas | Sección 4 |

**Prueba de `/salud`.** Con la base encendida y luego apagada (`docker stop trackeat-db-docker`):

```
{"nodo":"1","bd":"ok","ip_cliente":"172.18.0.1"}  → 200
{"nodo":"1","bd":"caida"}                         → 503
```

Al volver a encender la base, `/salud` regresa a 200 sin reiniciar el nodo. El campo `ip_cliente` muestra `172.18.0.1` porque Docker Desktop en Windows hace NAT y todas las conexiones desde el anfitrión llegan con la IP de la puerta de enlace; desde otra computadora mostraría la IP real del cliente.

**Prueba de sesión.** Se inició sesión como cocinero **a través del balanceador** y se usó la misma cookie directamente contra cada nodo:

```
puerto 8001 (nodo 1) → Cocinero Demo, rol cocinero
puerto 8002 (nodo 2) → Cocinero Demo, rol cocinero
sin cookie           → sin sesión
```

La sesión se guardó en la tabla `sessions` de PostgreSQL, por lo que cualquier nodo la lee. Se descartaron la afinidad por IP (en un restaurante muchos clientes salen por la misma IP y caerían en un solo nodo) y el testigo firmado (no se puede invalidar antes de que caduque); el razonamiento completo está en [balanceo](balanceo.md#sesión).

## 4. Mediciones con Apache Bench

Se usó `ab -n 1000 -c 20 -l` contra `127.0.0.1`, con las mismas opciones en todos los escenarios y un calentamiento previo de cada nodo. La corrida con nodo apagado usa **5000 peticiones** en lugar de 1000, porque con los nodos actuales 1000 peticiones terminan en unos 2.5 s y el apagado a los 4 s caía después de que `ab` ya había terminado.

| Escenario | URL | -n / -c | Peticiones/s | p50 (ms) | p90 (ms) | p99 (ms) | Fallidas | No 2xx |
|---|---|---|---|---|---|---|---|---|
| Un nodo solo (sin balanceador) | http://127.0.0.1:8001/ | 1000 / 20 | 276.03 | 71 | 75 | 99 | 0 | 0 |
| Balanceador, turno rotatorio | http://127.0.0.1:8080/ | 1000 / 20 | 397.44 | 52 | 68 | 84 | 0 | 0 |
| Balanceador, `least_conn` | http://127.0.0.1:8080/ | 1000 / 20 | 391.27 | 54 | 66 | 78 | 0 | 0 |
| `least_conn`, sin apagar nada (control) | http://127.0.0.1:8080/ | 5000 / 20 | 365.32 | 57 | 74 | 86 | 0 | 0 |
| `least_conn`, nodo 2 apagado a los 6 s | http://127.0.0.1:8080/ | 5000 / 20 | 300.81 | 73 | 84 | 94 | 0 | 0 |

### Qué se observa

- **El balanceador mejora, pero no duplica.** Pasar de un nodo a dos sube las peticiones por segundo de 276 a unas 395 (≈1.4 veces) y baja la mediana de 71 a 52 ms. No llega a 2 veces porque todo corre en la misma computadora: `ab`, Nginx, los dos nodos y PostgreSQL se reparten el mismo procesador, así que el segundo nodo no suma capacidad nueva, solo aprovecha mejor la que había. En computadoras separadas la mejora sería mayor.
- **Turno rotatorio y `least_conn` quedan empatados.** La diferencia (397 contra 391 peticiones por segundo) está dentro de la variación normal entre corridas. Es lo esperable: esta prueba pide siempre la misma página, con peticiones cortas y parecidas, que es justo el caso donde los dos algoritmos se comportan igual. La ventaja de `least_conn` aparece cuando hay peticiones de duración desigual (reportes) o conexiones largas (WebSockets), que esta prueba no genera; por eso se eligió en el [caso E](balanceo.md#detalle-del-caso-e).
- **Con un nodo apagado a la mitad no se perdió ninguna petición** (0 fallidas, 0 respuestas distintas de 2xx sobre 5000). `proxy_next_upstream` reintenta en el otro nodo las peticiones que no alcanzaron a conectarse. El rendimiento baja de 365 a 301 peticiones por segundo y la mediana sube de 57 a 73 ms, porque el nodo restante atiende solo a partir de ese momento.
- **Un nodo que regresa tarda en volver al reparto.** Tras `docker start trackeat-nodo2`, el balanceador siguió enviando todo al nodo 1 durante unos segundos y después volvió a repartir 50/50. Se debe al `fail_timeout=10s` (el nodo se considera caído durante 10 s tras los fallos) más el arranque de `php artisan serve`.
- **Comparación con la ronda anterior.** Una primera ronda, antes de reorganizar los composes, dio cifras más bajas (65 peticiones por segundo con un nodo, 125 con el balanceador) y un p99 de 2154 ms al apagar el nodo (`mediciones.md`, ronda 1). Ese pico es probablemente el tiempo de `proxy_connect_timeout` de 2 s que esperaron las peticiones hacia el nodo detenido; en esta ronda no se repitió (p99 de 94 ms). No se investigó la causa con más detalle, así que se deja como hipótesis. No se investigó por qué las cifras de la primera ronda son más bajas (posiblemente otra carga en la computadora); lo que cuenta, como pide el PDF, es comparar los escenarios dentro de una misma ronda.
- **Sobre `php artisan serve`.** El PDF advierte que en Windows atiende una petición a la vez y da números bajos. Aquí corre dentro de contenedores Linux con `PHP_CLI_SERVER_WORKERS=4`, por lo que no tiene esa limitación; aun así sigue siendo un servidor de desarrollo, y los números no representan un servidor de producción.

### Comandos para repetir las pruebas

```
ab -n 1000 -c 20 -l http://127.0.0.1:8001/            # un nodo solo
ab -n 1000 -c 20 -l http://127.0.0.1:8080/            # a través del balanceador
docker compose -f docker/compose.yml stop nodo2       # tirar un nodo con la prueba corriendo
docker compose -f docker/compose.yml start nodo2      # reincorporarlo
```

Para cambiar de algoritmo se edita la línea `least_conn;` del `upstream trackeat` en `docker/infra/nginx.conf.template` (comentarla da turno rotatorio; `ip_hash;` da afinidad) y se reinicia el balanceador con `docker compose -f docker/compose.yml restart balanceador`. Se reinicia en lugar de recargar porque el archivo es una plantilla que Docker convierte en configuración al arrancar.

## 5. Mejora propia: tiempo real con alta disponibilidad

Esta sección va más allá de lo que pide la práctica.

**Motivo.** Al revisar el proyecto se vio un punto débil: con dos nodos de aplicación pero un solo servidor de WebSockets (Reverb), si ese Reverb caía las pantallas de cocina dejaban de recibir comandas en vivo aunque los nodos siguieran sanos. Pasar a un Reverb por nodo no implicaba un cambio fuerte (variables de entorno y dos contenedores más) y mejora la disponibilidad y el reparto de las conexiones largas, así que se integró.

**Qué se agregó.**

- Un **Reverb por nodo** (`reverb1`, `reverb2`), con su propia **cola** (`cola1`, `cola2`). Cada nodo publica sus eventos en su propio Reverb.
- **Redis** como canal compartido entre los dos Reverb (`REVERB_SCALING_ENABLED=true`). Sin él, cada Reverb solo conocería a sus clientes y una comanda publicada en uno no llegaría a las pantallas conectadas al otro. Es un solo Redis, no uno por nodo: con uno por nodo los Reverb no se sincronizarían.
- En Nginx, un `upstream reverb` con `least_conn` para repartir las pantallas entre los dos Reverb.
- Los composes se separaron en `infra` (PostgreSQL, Redis, Nginx) y `nodo` (app, Reverb y cola), lo que habilita el despliegue en varias computadoras.

**Pruebas hechas** (con un cliente WebSocket propio suscrito a un canal público):

| Prueba | Resultado |
|---|---|
| Cliente conectado al Reverb 2; el nodo 1 publica en el Reverb 1 | El cliente recibió el evento (lo sincroniza Redis) |
| Cliente conectado por el balanceador; el nodo 2 publica en el Reverb 2 | Recibido |
| `docker stop trackeat-reverb1`; 3 clientes nuevos por el balanceador | Los 3 se conectaron (al Reverb 2); un evento publicado por el nodo 2 llegó |
| Además se apaga `nodo2` | 6 de 6 peticiones al balanceador respondieron 200 |
| Se vuelven a encender `reverb1` y `nodo2` | El balanceador reparte de nuevo; el nodo 2 había cambiado de IP y Nginx la siguió solo |

Estas pruebas se hicieron con un cliente WebSocket propio, no con la pantalla de cocina en un navegador. Por diseño, el frontend se reconecta solo (pusher-js) y además consulta el estado cada 5 s como respaldo (`frontend/src/lib/trackeat.js`), pero esa reconexión no se ha verificado todavía con la aplicación real. Cuando un Reverb está caído, los eventos que su nodo intenta publicar se reintentan 3 veces seguidas desde la cola; si no vuelve a tiempo, el evento queda como trabajo fallido y la pantalla se pone al día con el sondeo.

**Límites conocidos.** PostgreSQL, Redis y el balanceador siguen siendo instancias únicas, y viven en la misma computadora. Si cae Redis la aplicación sigue funcionando, pero los eventos en vivo dejan de cruzar de un Reverb a otro. Si caen PostgreSQL o Nginx, la aplicación completa deja de estar disponible. La tabla completa de qué falla y qué sigue funcionando está en [balanceo](balanceo.md#qué-falla-y-qué-sigue-funcionando).

## 6. Lo que queda pendiente

- **Probar el modo de tres computadoras.** Está preparado (`docker/.env.example`) pero solo se validó la configuración, no la comunicación real entre equipos (firewall de Windows, IPs de la red del salón).
- **Dominio y HTTPS.** Hoy se entra por la IP del balanceador (`http://IP:8080`). Para usar un dominio hace falta un registro DNS en el proveedor del dominio y ajustar `URL_PUBLICA`; con HTTPS, además, certificados en Nginx o un túnel.
- **Reducir los puntos únicos de falla** (réplica de PostgreSQL, Redis Sentinel, segundo balanceador), fuera del alcance de esta práctica.

## 7. Guion de la demostración

1. `docker compose -f docker/compose.yml up -d` y, la primera vez, `docker compose -f docker/compose.yml exec nodo1 php artisan migrate --seed --force`.
2. Abrir `http://127.0.0.1:8080/salud` y recargar varias veces: cambia el campo `nodo` entre 1 y 2. En F12 > Red se ve `X-Nodo` con la IP del nodo que atendió.
3. Iniciar sesión y recargar: la sesión se mantiene aunque cambie de nodo.
4. Mientras se recarga, `docker stop trackeat-nodo2`: el sitio sigue respondiendo desde el nodo 1. `docker start trackeat-nodo2` lo reincorpora.
5. `docker stop trackeat-db-docker`: `/salud` responde 503 con `"bd":"caida"`; al encenderla regresa a 200.
6. Con la pantalla de cocina abierta, `docker stop trackeat-reverb1`: debe reconectarse al otro Reverb y las comandas siguen llegando (o, como mínimo, aparecen por el sondeo de 5 s). Conviene ensayarlo antes de la presentación: con el cliente WebSocket propio funcionó, pero con la pantalla real en navegador no se ha probado.
7. Para mostrar las IPs y puertos: `docker network inspect trackeat_default` y `docker compose -f docker/compose.yml ps`.
