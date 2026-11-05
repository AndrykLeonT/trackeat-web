# Levantar TrackEat con Docker (una sola PC)

Guía para levantar la Práctica 5 completa en una computadora y comprobar qué nodo atiende cada petición. Para repartirlo en tres PCs, ver [cambiar a 3 pc.md](cambiar%20a%203%20pc.md). El informe de la práctica está en [docs/informe-practica-5.md](docs/informe-practica-5.md).

## Qué se levanta

Nueve contenedores en una misma red de Docker:

| Contenedor | Qué es | Puerto en tu PC |
|---|---|---|
| `trackeat-balanceador` | Nginx: la única puerta de entrada | **8080** |
| `trackeat-nodo1`, `trackeat-nodo2` | La aplicación, dos copias | 8001, 8002 |
| `trackeat-reverb1`, `trackeat-reverb2` | WebSockets (pantalla de cocina), uno por nodo | 8091, 8092 |
| `trackeat-cola1`, `trackeat-cola2` | Procesan la cola de cada nodo | — |
| `trackeat-db-docker` | PostgreSQL, base única | 5433 |
| `trackeat-redis` | Canal compartido entre los dos Reverb | 6380 |

Los usuarios siempre entran por el **8080**. Los puertos 8001 y 8002 sirven para probar un nodo directo, sin balanceador.

## Requisitos

- Docker Desktop encendido (en Windows usa WSL2).
- El archivo `backend/.env` con una `APP_KEY` (ya lo tienes si ejecutaste el proyecto antes).
- El frontend compilado en `backend/public/build` (ya lo tienes si ejecutaste `pnpm build`). Si no existe: `cd frontend` y luego `pnpm install` y `pnpm build`.
- El puerto 8080 libre. Si `presentar.bat` está abierto, ciérralo.

## 1. Levantar todo

Abre PowerShell en la raíz del proyecto (`C:\web\trackeat-web`):

```powershell
docker compose -f docker/compose.yml build
docker compose -f docker/compose.yml up -d
docker compose -f docker/compose.yml exec nodo1 php artisan migrate --seed --force
docker compose -f docker/compose.yml ps
```

- El `build` tarda unos 4 minutos la primera vez; después usa caché.
- El `migrate --seed` es solo para la primera vez. Repetirlo no hace daño.
- `ps` debe mostrar los 9 contenedores en `Up`. Si `cola1`, `cola2` o los `reverb` se reinician al inicio, es normal antes de migrar.

Después entra a **http://127.0.0.1:8080/**.

## 2. Saber en qué nodo estás

### Con `/salud` (la más simple)

Abre `http://127.0.0.1:8080/salud` y recarga varias veces. El JSON cambia entre los dos nodos:

```json
{"nodo":"1","bd":"ok","ip_cliente":"172.18.0.1"}
{"nodo":"2","bd":"ok","ip_cliente":"172.18.0.1"}
```

### En el navegador (F12 > Red)

Haz clic en cualquier petición y mira las cabeceras de respuesta:

| Cabecera | Quién la pone | Qué dice |
|---|---|---|
| `X-Servidor` | La aplicación | `1` o `2`: el número del nodo |
| `X-Nodo` | Nginx | La IP del nodo que atendió, por ejemplo `172.18.0.4:8000` |

Para saber qué IP corresponde a cada nodo:

```powershell
docker network inspect trackeat_default --format '{{range .Containers}}{{.Name}} {{.IPv4Address}}{{"\n"}}{{end}}'
```

Las IPs las asigna Docker y pueden cambiar si recreas los contenedores; consúltalas siempre con ese comando.

### Seis peticiones seguidas desde PowerShell

```powershell
1..6 | ForEach-Object { (Invoke-WebRequest http://127.0.0.1:8080/salud -UseBasicParsing).Content }
```

### Cada nodo directo, sin balanceador

- `http://127.0.0.1:8001/salud` es siempre el nodo 1.
- `http://127.0.0.1:8002/salud` es siempre el nodo 2.

Sirve para comparar. La página del menú carga sus datos por `/api/...`, así que en F12 verás `X-Servidor` en muchas peticiones y puede cambiar de una a otra: eso es el reparto funcionando.

## 3. Probar fallos

Deja `/salud` recargándose en el navegador y, en PowerShell:

```powershell
docker stop trackeat-nodo2        # /salud ya solo dice "nodo":"1"; el sitio no se cae
docker start trackeat-nodo2       # tarda unos 10 s en volver al reparto

docker stop trackeat-db-docker    # /salud responde 503 con "bd":"caida"
docker start trackeat-db-docker   # vuelve a 200 sin reiniciar nada

docker stop trackeat-reverb1      # el otro Reverb atiende los WebSockets
docker start trackeat-reverb1
```

- Al apagar un nodo, una recarga puede tardar hasta unos 2 segundos mientras Nginx descubre que cayó, pero no debe dar error.
- Un nodo que regresa tarda unos 10 segundos en recibir tráfico otra vez (`fail_timeout=10s` más el arranque de la aplicación).

## 4. Cambiar el algoritmo de balanceo

Edita `docker/infra/nginx.conf.template`, en el bloque `upstream trackeat`:

- `least_conn;` es menos conexiones (el que se usa).
- Comentar esa línea da turno rotatorio.
- `ip_hash;` da afinidad por IP.

Luego **reinicia** el balanceador (no basta recargarlo, porque el archivo es una plantilla que Docker convierte en configuración al arrancar):

```powershell
docker compose -f docker/compose.yml restart balanceador
```

## 5. Medir con Apache Bench

`ab.exe` viene con Laragon, en `C:\laragon\bin\apache\httpd-...\bin\`. Usa `127.0.0.1` y no olvides la diagonal final:

```powershell
ab -n 1000 -c 20 -l http://127.0.0.1:8001/    # un nodo solo
ab -n 1000 -c 20 -l http://127.0.0.1:8080/    # por el balanceador
```

Para la prueba con un nodo apagado a la mitad, usa más peticiones (`-n 5000`) y apaga el nodo a los pocos segundos con `docker stop trackeat-nodo2`; con 1000 la corrida termina antes de que alcances a apagarlo. Los resultados de referencia están en [docs/mediciones.md](docs/mediciones.md).

## 6. Ver qué pasa por dentro

```powershell
docker compose -f docker/compose.yml logs -f balanceador   # cada petición que entra
docker logs -f trackeat-reverb2                            # actividad del Reverb 2
docker logs --tail 50 trackeat-nodo1                       # últimos mensajes de un nodo
```

## 7. Apagar

```powershell
docker compose -f docker/compose.yml down
```

Apaga y borra los contenedores, pero **conserva los datos de la base** (volumen `trackeat-docker-db`). Para borrar también los datos y empezar de cero: `docker compose -f docker/compose.yml down -v` (después hay que volver a correr el `migrate --seed`).

## Si algo falla

| Síntoma | Qué revisar |
|---|---|
| `Bind for 0.0.0.0:8080 failed: port is already allocated` | Otro programa usa el 8080 (por ejemplo `presentar.bat`). Ciérralo y repite `up -d`. |
| `pull access denied for trackeat-app` | Faltó el `build`. Ejecuta `docker compose -f docker/compose.yml build` y repite `up -d`. |
| El sitio da 502 al inicio | Los nodos tardan unos segundos en arrancar. Espera y recarga. |
| Un contenedor se reinicia sin parar | `docker logs trackeat-<nombre>`. Antes de migrar es normal; si persiste después, suele ser un error de base de datos o de `backend/.env`. |
| `/salud` responde 503 | La base no contesta: `docker compose -f docker/compose.yml ps` y revisa `trackeat-db-docker`. |
| La página carga pero no hay pantallas en vivo | Revisa que `trackeat-redis` y los dos `reverb` estén `Up`. La app sigue funcionando y se pone al día por sondeo cada 5 s. |
