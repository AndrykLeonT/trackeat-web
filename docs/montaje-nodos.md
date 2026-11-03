# Montaje de la Práctica 5 en cada computadora (guía para asistentes de IA)

Este archivo es para que el asistente de IA de cada integrante (Claude Code, Copilot, Cursor, etc.) deje lista su computadora. Un humano también puede seguirlo paso a paso.

## Reglas para la IA (leer antes de hacer nada)

1. **No regeneres `APP_KEY`.** Nunca corras `php artisan key:generate`. El `backend/.env` se copia de la PC del balanceador; si las llaves son distintas, ningún nodo puede leer la sesión del otro.
2. **No corras `php artisan migrate` ni `db:seed` en los nodos.** La base es una sola y vive en la PC del balanceador; solo esa PC migra.
3. **No cambies `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` ni `BROADCAST_CONNECTION`.** Deben quedar como los escribe el balanceador (`database` y `reverb`).
4. **No subas al repositorio** `backend/.env`, `balanceo/db.json` ni `balanceo/estado.json`. Ya están en `.gitignore`.
5. **Pregunta las IP al usuario.** No las inventes ni las adivines. Se ven con `ipconfig` (línea "Dirección IPv4" del adaptador Wi‑Fi o Ethernet).
6. **No uses `npm run dev` ni `pnpm dev` detrás del balanceador**; si existe `backend/public/hot`, bórralo.
7. **Usa `scripts/balanceo.ps1`** para arrancar y detener. No levantes procesos a mano salvo para diagnosticar.
8. Todo se hace en Windows con PowerShell, desde la raíz del repositorio.

## Cómo queda el montaje

| PC | Rol | Qué corre | Puertos que deben estar abiertos |
|---|---|---|---|
| A | Balanceador | Nginx (8080), PostgreSQL en Docker (5432), Reverb (8081, solo local), cola de Laravel | 8080 y 5432 |
| B | Nodo 1 | `php artisan serve --host=0.0.0.0 --port=8000` con `NODO=1` | 8000 |
| C | Nodo 2 | Lo mismo con `NODO=2` | 8000 |

- Las tres PCs deben estar en la misma red.
- El sitio se abre en `http://IP-DE-A:8080/`.
- Los nodos se conectan a la base de A.
- Los navegadores se conectan al WebSocket a través de Nginx (`/app`).

## Requisitos en cada PC (verificar antes)

| Herramienta | Comando para verificar | Esperado | Dónde |
|---|---|---|---|
| Git | `git --version` | cualquier versión | todas |
| PHP 8.2+ | `php -v` | `PHP 8.2` o mayor | todas (Laragon trae PHP en `C:\laragon\bin\php`) |
| Extensión pgsql | `Test-Path "$(Split-Path (Get-Command php).Source)\ext\php_pdo_pgsql.dll"` | `True` (el script la carga aunque no esté en php.ini) | todas |
| Composer | `composer --version` | cualquier versión | todas |
| Node.js 20+ y pnpm | `node -v` y `pnpm -v` | versión impresa | B y C |
| Docker Desktop | `docker info` | sin error ("Server Version") | solo A |
| Nginx | `Test-Path C:\laragon\bin\nginx` | `True` (o `nginx.exe` en el PATH) | solo A |
| Apache Bench | `Get-ChildItem C:\laragon\bin\apache\*\bin\ab.exe` | encuentra el archivo | solo A (para medir) |

Si falta algo, instálalo antes de seguir. Laragon trae PHP, Nginx y `ab.exe`. pnpm se instala con `npm install -g pnpm`.

## Paso 1. Las tres PCs: mismo código

```powershell
git clone https://github.com/<usuario>/trackeat-web.git   # o git pull si ya existe
cd trackeat-web
git checkout Daniel          # la rama que use el equipo
git pull
git log -1 --format=%h       # anota el commit: debe ser EL MISMO en A, B y C
```

## Paso 2. PC A (balanceador)

1. Abre Docker Desktop y espera a que diga "Engine running".
2. Anota la IP de A con `ipconfig`, por ejemplo `192.168.1.10`, y pide al usuario las IP de B y C.
3. Si `backend/.env` no existe, créalo y genera la llave **solo esta vez**:

   ```powershell
   Copy-Item backend\.env.example backend\.env
   cd backend; php artisan key:generate; cd ..
   ```

4. Arranca el balanceador:

   ```powershell
   .\balanceo.bat -Rol Balanceador -IpBalanceador 192.168.1.10 -Nodos 192.168.1.20,192.168.1.21
   ```

   La primera vez el script hace esto:
   - Descarga `postgres:16-alpine`.
   - Crea la base `trackeat` y escribe `DB_*`, `REVERB_*`, `APP_URL` y `TRACKEAT_SOCKET_URL` en `backend/.env`. Antes guarda una copia en `backend/.env.antes-balanceo`.
   - Migra y siembra los datos.
   - Arranca Reverb, la cola y Nginx.

   Debe terminar con: `Balanceador listo en http://192.168.1.10:8080/`.
5. Abre el firewall. Esto se corre en PowerShell **como administrador** y el usuario lo hace a mano:

   ```powershell
   New-NetFirewallRule -DisplayName "TrackEat balanceador" -Direction Inbound -Protocol TCP -LocalPort 8080 -Action Allow
   New-NetFirewallRule -DisplayName "TrackEat PostgreSQL" -Direction Inbound -Protocol TCP -LocalPort 5432 -Action Allow
   ```

6. Pasa `backend/.env` a B y C por USB o por un mensaje privado. **Nunca** lo subas a GitHub: tiene la contraseña de la base y la `APP_KEY`.

## Paso 3. PCs B y C (nodos)

1. Coloca el `.env` recibido en `backend/.env`, reemplazando el que hubiera.
2. Verifica que es el correcto:

   ```powershell
   Select-String -Path backend\.env -Pattern '^DB_CONNECTION=pgsql','^DB_HOST=','^APP_KEY=base64:'
   ```

   Deben salir las tres líneas, y `DB_HOST` debe ser la IP de A.
3. Comprueba que la base de A se alcanza:

   ```powershell
   Test-NetConnection 192.168.1.10 -Port 5432
   ```

   Debe decir `TcpTestSucceeded : True`.
4. Abre el puerto del nodo. Esto también va **como administrador**:

   ```powershell
   New-NetFirewallRule -DisplayName "TrackEat nodo" -Direction Inbound -Protocol TCP -LocalPort 8000 -Action Allow
   ```

5. Arranca el nodo. Usa `-Numero 1` en B y `-Numero 2` en C:

   ```powershell
   .\balanceo.bat -Rol Nodo -Numero 1 -IpBalanceador 192.168.1.10
   ```

   El script hace esto:
   - `composer install` si faltan dependencias.
   - `pnpm install` / `pnpm build` si falta el build.
   - Borra `public/hot`.
   - Arranca `php artisan serve --host=0.0.0.0 --port=8000 --no-reload` con `NODO` y `TRUSTED_PROXIES` puestos.

   La consola se queda quieta; **Ctrl+C apaga el nodo** y así se hace la prueba de caída. Si `pnpm build` falla porque no hay dependencias, corre `cd frontend; pnpm install; cd ..` y repite.

## Paso 4. Verificación final

| Desde | Comando | Esperado |
|---|---|---|
| B | `Invoke-RestMethod http://127.0.0.1:8000/salud` | `nodo: 1`, `bd: ok` |
| C | `Invoke-RestMethod http://127.0.0.1:8000/salud` | `nodo: 2`, `bd: ok` |
| A | `Invoke-RestMethod http://192.168.1.20:8000/salud` | `nodo: 1`, `ip_cliente` con la IP de A |
| A | `1..6 \| % { (Invoke-WebRequest http://192.168.1.10:8080/salud -UseBasicParsing).Headers['X-Nodo'] }` | Alterna entre las dos IP de los nodos |
| Navegador | `http://192.168.1.10:8080/` → F12 › Red → clic en la petición → cabeceras de respuesta | `X-Nodo` (lo pone Nginx) y `X-Servidor` (lo pone Laravel) |

## Comandos del día a día (PC A)

```powershell
.\balanceo.bat -Algoritmo rotatorio -Recargar     # cambia a turno rotatorio y recarga Nginx
.\balanceo.bat -Algoritmo least_conn -Recargar    # vuelve a menos conexiones
.\balanceo.bat -Algoritmo ip_hash -Recargar       # para mostrar que todo cae en un nodo
.\balanceo.bat -Detener                           # apaga Nginx, Reverb, cola y la base (los datos se quedan)

# Mediciones: agregan una fila a balanceo/mediciones.md
powershell -ExecutionPolicy Bypass -File scripts\medir.ps1 -Escenario "Un nodo solo" -Url http://192.168.1.20:8000/
powershell -ExecutionPolicy Bypass -File scripts\medir.ps1 -Escenario "Balanceador least_conn" -Url http://192.168.1.10:8080/
# Nodo apagado a la mitad: corre la medición y a los ~15 s alguien da Ctrl+C en el nodo 2
```

En `medir.ps1` usa siempre la IP o `127.0.0.1`, nunca `localhost`, y no olvides la diagonal final.

## Ensayo en una sola laptop

Para probar todo sin las otras PCs:

```powershell
.\balanceo.bat -Rol Ensayo        # nodos en 127.0.0.1:8001 y 8002, balanceador en 8080
.\balanceo.bat -ApagarNodo 2      # simula la caída
.\balanceo.bat -PrenderNodo 2
.\balanceo.bat -Detener
```

## Problemas comunes

| Síntoma | Causa | Arreglo |
|---|---|---|
| **502** al abrir el sitio recién arrancado | Los nodos tardan unos segundos | Espera y recarga |
| **502** permanente | Firewall del nodo o el nodo no escucha en `0.0.0.0` | Regla de firewall del paso 3.4; arrancar con el script |
| El nodo no se conecta a la base (`SQLSTATE[08006]`) | Firewall de A en 5432, o Docker apagado | Regla del paso 2.5; `docker ps` debe mostrar `trackeat-db` |
| El login funciona en un nodo y en el otro pide entrar de nuevo | `APP_KEY` distinta o `SESSION_DRIVER` no es `database` | Volver a copiar el `.env` de A |
| Después del login redirige a `http://IP/...` sin `:8080` | Nginx usa `$host` | La plantilla usa `$http_host`; no la edites a mano |
| La página carga sin estilos o pide `localhost:5173` | Existe `backend/public/hot` | Bórralo; no uses `pnpm dev` |
| `ab` da números raros o falla | Usaste `localhost` o te faltó la diagonal | `http://IP:8080/` |
| La pantalla de comandas no se actualiza sola | Reverb o la cola no corren en A | `.\balanceo.bat -Detener` y volver a arrancar |
