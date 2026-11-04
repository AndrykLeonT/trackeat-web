# Mediciones con Apache Bench

Se midió con `ab -n 1000 -c 20 -l <url>` sobre el montaje de `docker/compose.yml`. El nodo solo se midió en el puerto 8001 y el balanceador en el 8080. En el último escenario, el nodo 2 se apagó con `docker stop trackeat-nodo2` a los 4 s de iniciada la corrida.

| Escenario | URL | -n / -c | Peticiones/s | p50 (ms) | p90 (ms) | p99 (ms) | Fallidas | No 2xx |
|---|---|---|---|---|---|---|---|---|
| Un nodo solo (sin balanceador) | http://127.0.0.1:8001/ | 1000 / 20 | 65.25 | 304 | 321 | 344 | 0 | 0 |
| Balanceador, turno rotatorio | http://127.0.0.1:8080/ | 1000 / 20 | 124.34 | 132 | 265 | 320 | 0 | 0 |
| Balanceador, least_conn | http://127.0.0.1:8080/ | 1000 / 20 | 125.88 | 155 | 189 | 216 | 0 | 0 |
| least_conn, nodo 2 apagado a la mitad | http://127.0.0.1:8080/ | 1000 / 20 | 69.87 | 151 | 299 | 2154 | 0 | 0 |
