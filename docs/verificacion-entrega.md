# Verificación de la entrega

Ejecutada el **20 de octubre de 2026**, en Windows, con PostgreSQL local y Chrome. Las pruebas PHP usan `trackeat_test`; navegador y carga usan `trackeat_demo_test`, sin reiniciar la base normal.

## Backend y compilación

- PHPUnit: **63 pruebas, 239 verificaciones, todas aprobadas**. Incluye permisos, cuentas eliminadas, expiración y desactivación de demo, aislamiento de salas, validaciones, precios del servidor, reintentos, flujo por estación y eventos después del commit/rollback.
- `pnpm build`: genera TrackEat, Personal y Menú; compilación correcta. Permanecen dos avisos anteriores de Svelte en `reset-password.svelte` sobre valores iniciales.
- ESLint y Prettier de los archivos añadidos/modificados: correctos. Laravel Pint y `git diff --check`: correctos. Los scripts PowerShell pasan el análisis de sintaxis.

## Navegador

El recorrido reproducible está en `frontend/tests/entrega.mjs` y se ejecuta con `pnpm verify:entrega`.

- Dos perfiles reciben identidades demo distintas y suscriben sus canales privados.
- El menú recarga sin red, busca por índice y conserva cantidades, notas y mesa. No permite confirmar sin conexión.
- Un pedido confirmado aparece en otra sesión sin recargar; personal lo prepara, marca listo y entrega. Reconectar recupera el estado perdido.
- El gráfico usa cifras del servidor y tiene tabla alternativa. El menú no desborda a 390 px.
- Personal abre offline, guarda fotografía Blob, consulta el índice, demuestra migración v1 → v2 con bytes idénticos y permite editar/eliminar con confirmación.

El recorrido completo también pasó mediante **dos túneles públicos Cloudflare
con HTTP/2**, usando HTTPS para la aplicación y WSS para Reverb. Se verificaron
sesión firmada, autorización de canales, envío, cambios de estado, gráfico y
reconexión sin errores JavaScript. Se corrigió la conservación de la barra final
en la redirección a `/menu/`, necesaria para cargar sus recursos relativos.

## Carga local

| Medición | Resultado |
| --- | --- |
| Conexiones distintas | **50** |
| Duración con todas conectadas | **600 segundos** |
| Pedidos de carga persistidos | **50** |
| Concurrencia de creación | **10 solicitudes** |
| Reintentos | Cada solicitud se reenvió y devolvió el mismo pedido |
| Pedidos duplicados | **0** |
| Entregas exigidas | **2,500**, cada pedido recibido una vez por cada conexión |
| Eventos recibidos en total | **2,700**, incluidos 200 de un recorrido funcional simultáneo |
| Latencia p95 | **1,959 ms** |
| Latencia máxima observada | **1,981 ms** |
| Errores o conexiones perdidas | **0** |

La latencia incluye cola y se calcula desde `updated_at` del servidor hasta la recepción WebSocket. La marca del servidor tiene resolución de un segundo: la medición puede incluir hasta un segundo adicional de redondeo. La prueba mantuvo los 50 sockets y verificó persistencia e aislamiento por cuenta.

El script de carga filtra ahora los eventos por los pedidos de su propia ejecución para que las interacciones de otros ensayos no aumenten su contador.

## Pendientes humanos

- Probar desde un celular con **datos móviles**, fuera del Wi-Fi. Las pruebas automatizadas de esta computadora no acreditan esa condición.
- Confirmar el usuario de GitHub del docente y verificar que tiene acceso como colaborador.
- Cada integrante debe poder explicar el código y registrar sus aportaciones reales. No se generaron commits con autorías simuladas.

Las capturas y reportes JSON completos quedan en `output/playwright/`, excluido de Git. Para repetirlos y presentar en clase, consultar [Avance 2](avance-2.md).
