# Mediciones con Apache Bench

| Escenario | URL | -n / -c | Peticiones/s | p50 (ms) | p90 (ms) | p99 (ms) | Fallidas | No 2xx |
|---|---|---|---|---|---|---|---|---|
| Un nodo solo (sin balanceador) | http://127.0.0.1:8001/ | 1000 / 20 | 19.41 | 1023 | 1128 | 1209 | 0 | 0 |
| Balanceador, turno rotatorio | http://127.0.0.1:8080/ | 1000 / 20 | 47.07 | 418 | 484 | 536 | 0 | 0 |
| Balanceador, least_conn | http://127.0.0.1:8080/ | 1000 / 20 | 34.16 | 591 | 662 | 751 | 0 | 0 |
| Balanceador, least_conn, nodo 2 apagado a la mitad | http://127.0.0.1:8080/ | 1000 / 20 | 22.33 | 734 | 1265 | 1612 | 0 | 0 |
