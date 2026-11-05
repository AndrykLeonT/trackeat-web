# Mediciones con Apache Bench

Se midió con `ab -n 1000 -c 20 -l <url>` sobre el montaje de `docker/compose.yml` (modo una PC; ver [balanceo](balanceo.md#despliegue)). El nodo solo se midió en el puerto 8001 y el balanceador en el 8080. El análisis y la interpretación están en el [informe](informe-practica-5.md#4-mediciones-con-apache-bench).

## Ronda 2 · 5 de noviembre de 2026 (montaje actual)

Con la infraestructura separada (`infra` y `nodo`), Redis y un Reverb por nodo. Cada nodo se calentó antes con unas peticiones. La corrida con nodo apagado usa 5000 peticiones: con los nodos actuales 1000 peticiones terminan en unos 2.5 s y el apagado a los 4 s caía después de que `ab` ya había terminado. El nodo 2 se apagó con `docker stop trackeat-nodo2` a los 6 s de iniciada esa corrida, y se incluye una corrida de control de 5000 peticiones sin apagar nada.

| Escenario | URL | -n / -c | Peticiones/s | p50 (ms) | p90 (ms) | p99 (ms) | Fallidas | No 2xx |
|---|---|---|---|---|---|---|---|---|
| Un nodo solo (sin balanceador) | http://127.0.0.1:8001/ | 1000 / 20 | 276.03 | 71 | 75 | 99 | 0 | 0 |
| Balanceador, turno rotatorio | http://127.0.0.1:8080/ | 1000 / 20 | 397.44 | 52 | 68 | 84 | 0 | 0 |
| Balanceador, least_conn | http://127.0.0.1:8080/ | 1000 / 20 | 391.27 | 54 | 66 | 78 | 0 | 0 |
| least_conn, sin apagar nada (control) | http://127.0.0.1:8080/ | 5000 / 20 | 365.32 | 57 | 74 | 86 | 0 | 0 |
| least_conn, nodo 2 apagado a los 6 s | http://127.0.0.1:8080/ | 5000 / 20 | 300.81 | 73 | 84 | 94 | 0 | 0 |

## Ronda 1 · primer montaje (historial)

Medida antes de repartir el compose y de añadir Redis y un Reverb por nodo; el tráfico HTTP medido no pasa por ellos. En el último escenario, el nodo 2 se apagó con `docker stop trackeat-nodo2` a los 4 s de iniciada la corrida. Las cifras son más bajas que las de la ronda 2; no se investigó por qué (posiblemente otra carga en la computadora en ese momento), así que solo son comparables los escenarios dentro de una misma ronda.

| Escenario | URL | -n / -c | Peticiones/s | p50 (ms) | p90 (ms) | p99 (ms) | Fallidas | No 2xx |
|---|---|---|---|---|---|---|---|---|
| Un nodo solo (sin balanceador) | http://127.0.0.1:8001/ | 1000 / 20 | 65.25 | 304 | 321 | 344 | 0 | 0 |
| Balanceador, turno rotatorio | http://127.0.0.1:8080/ | 1000 / 20 | 124.34 | 132 | 265 | 320 | 0 | 0 |
| Balanceador, least_conn | http://127.0.0.1:8080/ | 1000 / 20 | 125.88 | 155 | 189 | 216 | 0 | 0 |
| least_conn, nodo 2 apagado a la mitad | http://127.0.0.1:8080/ | 1000 / 20 | 69.87 | 151 | 299 | 2154 | 0 | 0 |
