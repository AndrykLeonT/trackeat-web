# Avance 2: preparación y demostración

Entrega: **miércoles 21 de octubre de 2026, al inicio de clase**. El código debe estar en `main` antes de esa hora. Ambos ejercicios se presentan juntos en diez minutos.

## Arranque en Windows

1. Instalar las dependencias de backend y frontend indicadas en el README, incluido Reverb en Composer y Echo en pnpm. Habilitar las extensiones PHP requeridas. `scripts/php.ps1` carga las DLL disponibles cuando PHP de Windows no tiene php.ini.
2. Ejecutar `iniciar-base-datos.bat`. Conserva los datos existentes y carga ejemplos locales si corresponde.
3. Instalar el túnel una vez: `npm install -g cloudflared`.
4. Ejecutar **`presentar.bat`** desde el repositorio. Compila los tres paquetes, aplica migraciones pendientes, inicia Laravel en 8000, Reverb en 8080, la cola y dos túneles con HTTP/2. No utiliza Vite en la presentación.
5. Copiar las direcciones que imprime: aplicación HTTPS, socket WSS y enlace firmado del salón. El enlace dura dos horas y crea un cliente distinto por navegador. El personal usa su acceso normal. Ctrl+C cierra los procesos de esa presentación y desactiva sus enlaces.

Si Docker u otro proceso ocupa 8080, ejecutar `presentar.bat -SocketPort 8081`. El script comprueba los puertos y nunca detiene procesos ajenos.

Para ensayo local sin túneles:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/presentacion.ps1 -SocketPort 8081
```

El modo presentación habilita la demo sólo en sus procesos. Genera un secreto Reverb aleatorio y no lo imprime ni modifica `.env`. El servidor publica en Reverb por loopback; el navegador recibe la URL pública desde `/api/sesion`. Los cambios de túnel no requieren recompilar. Los orígenes admitidos incluyen localhost y el dominio HTTPS de la aplicación.

Con túneles iniciados manualmente, pasar `-WebUrl https://DOMINIO-WEB.trycloudflare.com -SocketUrl https://DOMINIO-SOCKET.trycloudflare.com`. Ambos procesos cloudflared deben usar `--protocol http2` y apuntar al puerto correspondiente.

## Prueba externa pendiente de confirmación humana

Desde un celular con Wi-Fi apagado y datos encendidos: abrir el enlace del salón, esperar «Tiempo real conectado», enviar un pedido y comprobar que aparece en la computadora. Cambiar su estado desde Comandas y comprobarlo en el celular. No basta con que abra el HTML: deben funcionar sesión, POST, autorización del canal y WSS.

Cada ejecución de cloudflared cambia los dominios. Repetir la prueba con las direcciones que se llevarán a clase y levantar los túneles al llegar. No usar `ws://192.168…` como dirección del salón.

## Guion de diez minutos

| Tiempo | Acción y evidencia |
| --- | --- |
| 0:00–0:40 | Abrir Personal por localhost; F12 a la vista; service worker `activated`; IndexedDB v2 y foto Blob. |
| 0:40–2:30 | Elegir Offline, recargar, registrar una persona con foto y buscarla por índice. Mostrar la línea `.index(...)` y el gráfico actualizado. |
| 2:30–4:00 | Editar, cancelar una baja y confirmar otra; mostrar conservación v1 → v2 y explicar `onupgradeneeded`. Reactivar la red. |
| 4:00–5:00 | Abrir el Menú Digital ya preparado, apagar red y recargar. Buscar un producto; mostrar `trackeat-menu` y su esquema documentado. Reactivar red. |
| 5:00–7:00 | Proyectar enlace web y WSS; el salón entra y confirma pedidos. Mostrar comandas y gráfico cambiando sin recargar. |
| 7:00–9:00 | Personal inicia preparación y marca listo; cada cliente ve el cambio. Interrumpir y recuperar una conexión para mostrar resincronización. |
| 9:00–10:00 | Mostrar diseño PostgreSQL y reporte de carga; responder migración y explicar las aportaciones reales del equipo en Git. |

Los ejemplos locales incluyen `administrador@example.test`, `cocinero@example.test` y `mesero@example.test`, con contraseña **TrackEatDemo!2026**. Son datos de desarrollo del seeder; no deben reutilizarse en una instalación con datos reales.

## Repetir las verificaciones

```powershell
# Desde la raíz: pruebas PHP sobre trackeat_test
powershell -ExecutionPolicy Bypass -File scripts/verificar.ps1

# Preparar otra base para navegador/carga, sin reiniciar la base normal
powershell -ExecutionPolicy Bypass -File scripts/verificar.ps1 -PrepareDemo
powershell -ExecutionPolicy Bypass -File scripts/presentacion.ps1 -TestDatabase -SocketPort 8081

# En otra terminal, dentro de frontend; usar el enlace recién generado
$env:TRACKEAT_DEMO_URL='ENLACE_FIRMADO'
pnpm verify:entrega
pnpm verify:carga
```

Las pruebas de navegador usan Chrome instalado y perfiles temporales. La carga crea 50 cuentas/pedidos reales en la base seleccionada: ejecutar sobre `-TestDatabase`. Por defecto mantiene 50 sockets diez minutos y compara entrega, persistencia y reintentos. Los reportes y capturas se guardan en `output/playwright/`. El tiempo de eventos se mide desde `updated_at` del servidor, con precisión de un segundo, por lo que la cifra incluye hasta un segundo adicional de redondeo.

## Diagnóstico

- **No conecta:** comprobar que ambos túneles siguen activos y el socket usa WSS al abrir el sitio por HTTPS. Revisar `backend/storage/logs/presentacion-*.log` y `tunnel-*.log`.
- **Canal rechazado:** iniciar sesión; comprobar origen permitido, caducidad y activación de demo. Una cuenta cliente no accede al canal de personal.
- **Pedido guardado pero no aparece en otro equipo:** revisar trabajador de cola y `failed_jobs`; después de corregir Reverb, ejecutar `php artisan queue:retry all`. Al reconectar, la pantalla consulta el estado actual.
- **Carrito en «Reintentar envío»:** reenviar la misma solicitud. Su UUID evita que un timeout produzca dos pedidos.
- **Menú local vacío:** completar una primera visita con red. Los datos no se comparten entre localhost, 127.0.0.1, otro puerto o un dominio nuevo del túnel.
- **PHP sin extensiones:** usar `scripts/php.ps1` desde PowerShell o configurar php.ini. Composer requiere también ZIP para instalar paquetes.

## Entrega en Git

Comprobar en GitHub que `main` contiene el commit verificado, que el docente conserva acceso como colaborador y que cada integrante registra su propia aportación real. El script no agrega colaboradores ni inventa autorías. El usuario de GitHub del docente debe confirmarse con el equipo si no consta en el repositorio.

## Uso de IA

OpenAI Codex asistió en integración de API, IndexedDB, interfaz, WebSockets, scripts, documentación y pruebas. El equipo debe revisar y poder explicar las decisiones y el código presentado.
