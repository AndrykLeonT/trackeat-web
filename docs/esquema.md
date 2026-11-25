# Diseño de datos de TrackEat

## PostgreSQL

Las migraciones de `backend/database/migrations/` son la definición ejecutable del esquema. Se usan claves foráneas restrictivas, importes `decimal(12,2)`, restricciones CHECK, marcas de baja lógica y fechas con zona horaria. No se reinicia una base existente para actualizarla: `php artisan migrate` aplica los cambios pendientes.

| Tabla | Relación y propósito |
| --- | --- |
| `users` | Identidad, datos de contacto y rol: cliente, cocinero, mesero, cajero o administrador. `estacion` (`general`, `cocina` o `barra`) la asigna el administrador y decide qué comandas ve y avanza un cocinero; todo cocinero tiene una (`general` por omisión, regla en un trigger y un CHECK de PostgreSQL). |
| `categorias` | Clasifica productos y asigna la estación que los prepara: `cocina` o `barra` (CHECK). `general` ya no es estación de producto: es la de un cocinero que atiende ambas. |
| `productos` | Pertenece a categoría; nombre, descripción, precio y disponibilidad. |
| `imagenes_producto` | Fotografías por producto, ordenadas y con URL. |
| `pedidos` | `id_usuario` (obligatorio) es quien levantó el pedido: el cliente en la web o el mesero, cajero o administrador que lo captura. Folio único `TE-AAAAMMDD-NNN`, entrega, mesa, total, estado global y `motivo_cancelacion`. |
| `folios_diarios` | Una fila por día con el último número de folio asignado; es el contador del folio consecutivo diario. |
| `items_pedido` | Relaciona pedido y producto; conserva cantidad, precio y estación al ordenar, además de notas. |
| `pedidos_estacion` | Una fila única por pedido/estación; avance independiente de cada área. |
| `historial_estados` | Registra pedido, estación, actor, estado anterior/nuevo y fecha. |
| `cortes` | Apertura/cierre de caja; un corte abierto por cajero (no abre otro sin cerrar el suyo). |
| `pagos` | Relaciona pedido, cajero y corte; método, importe, propina y reembolso. |

El avance de estación es `recibido → preparacion → listo → entregado`. El estado global sigue la estación más atrasada. `PedidoService` valida disponibilidad, calcula los precios y conserva historial dentro de una transacción. Los pedidos cancelados no avanzan.

### Reglas del módulo de pedidos

- **Folio.** Se guarda como `TE-AAAAMMDD-NNN` y se muestra como `#NNN`. El número es consecutivo y **reinicia cada día**, según la zona del restaurante (`TRACKEAT_ZONA_FOLIO`, por omisión `America/Mazatlan`; no es `APP_TIMEZONE`). Se toma de `folios_diarios` con un `INSERT … ON CONFLICT DO UPDATE` dentro de la transacción: la fila del día queda bloqueada hasta el commit, así que dos nodos nunca repiten un folio y una creación revertida no deja huecos. Los folios anteriores (`TE-` más un código largo, `DEMO-001`) se conservan y se muestran tal cual.
- **Dueño del pedido.** Siempre es quien lo levanta. No existen pedidos sin cuenta ni se vincula un cliente aparte. El 24 de noviembre `id_usuario` se hizo nullable (`2026_11_24_000001`) y ese mismo día se revirtió (`2026_11_24_000005`): el efecto neto es **NOT NULL**. La migración de reversión falla si hubiera filas nulas, en vez de borrar o inventar datos.
- **Estaciones.** Una comanda llega a `cocina` o `barra` según la categoría de cada producto. El cocinero con estación `cocina` o `barra` solo ve y avanza la suya; con `general` atiende ambas (y las comandas `general` de pedidos anteriores). Nadie entrega una estación suelta: **la entrega es por pedido completo**, y solo cuando todas sus estaciones están en `listo`. Entregan mesero, cajero y administrador (`PedidoPolicy`).
- **Cancelación.** El cliente cancela mientras todas las estaciones estén en `recibido`. El mesero, el cajero y el administrador, hasta antes de `entregado`, eligiendo un motivo de una lista (`config/trackeat.php`), que se guarda en `pedidos.motivo_cancelacion`. No se cancela con pagos activos ni con pagos en un corte cerrado: primero se corrigen o reembolsan.
- **Límites al crear (superficie A08).** Los aplica `PedidoService` con los valores de `config/trackeat.php`: hasta 100 piezas por renglón, 50 renglones, mesas de 1 a 30, total máximo de $5,000 para clientes y $20,000 para el personal, y 3 pedidos en curso por cliente (el personal no tiene este último). Las cifras se cambian con variables de entorno `TRACKEAT_*`.
- **Día de un pedido.** El historial ubica un pedido en el día de la **apertura del primer corte** donde tiene un pago activo que no sea reembolso (corte → pagos → pedidos). Sin pago, cuenta el día en que se creó. Lo usan `/mis-pedidos` (por día, hoy por omisión) y `/admin/pedidos` (además filtra por estado, mesa, entrega, cliente y corte; solo administrador, sin pedidos de salas de demostración y sin exportación).

La migración del 20 de octubre añade `users.demo_room` (UUID), `users.demo_expires_at` (fecha), `pedidos.demo_room` y `pedidos.client_request_id` (UUID). Son campos anulables: las cuentas y pedidos anteriores permanecen válidos. Un índice único por `(id_usuario, client_request_id)` y un bloqueo transaccional evitan duplicados incluso con reintentos simultáneos. `demo_room` identifica el tablero compartido de cada enlace de clase.

Migraciones del 23 al 25 de noviembre (módulo de pedidos y comandas):

| Migración | Efecto |
| --- | --- |
| `2026_11_24_000001_pedidos_sin_cuenta_y_motivo_cancelacion` | Añade `pedidos.motivo_cancelacion` (`string(50)`, anulable). También volvió anulable `id_usuario`; lo revierte la `000005`. |
| `2026_11_24_000002_users_estacion` | Añade `users.estacion` con CHECK (`general`, `cocina`, `barra`); anulable para quien no es cocinero. |
| `2026_11_24_000003_categorias_solo_cocina_y_barra` | Las categorías `general` pasan a `barra` (si su nombre es de bebidas) o a `cocina`, y el CHECK deja solo esas dos. No toca `pedidos_estacion`, `items_pedido` ni `historial_estados`: conservan filas históricas con `general`. |
| `2026_11_24_000004_folios_diarios` | Crea el contador del folio diario. |
| `2026_11_24_000005_pedidos_usuario_obligatorio` | `pedidos.id_usuario` vuelve a ser NOT NULL. |
| `2026_11_25_000001_cocinero_estacion_general` | Trigger y CHECK: un cocinero nunca queda sin estación (`general` por omisión); los existentes pasan a `general`. |

Los eventos se publican después del commit mediante la cola de base de datos. Los mensajes sólo contienen ID, folio, estado y fecha; el detalle se consulta por HTTP con autorización. Las tablas `jobs` y `failed_jobs` permiten procesar y diagnosticar fallos sin deshacer pedidos confirmados.

## IndexedDB del menú

Base `trackeat-menu`, versión **1**, independiente de Personal y perteneciente al origen/perfil del navegador.

| Almacén | Clave | Datos e índices |
| --- | --- | --- |
| `productos` | `id` del servidor | Nombre, descripción, precio decimal como texto, categoría, imagen y `nombreBusqueda`. Índices `porCategoria` y `porNombre`. |
| `categorias` | `id` del servidor | Nombre y estación. |
| `carrito` | `id = actual` | Productos elegidos, cantidades, notas, modalidad, mesa y UUID de solicitud. |
| `metadatos` | `id = actualizado` | Fecha de la última descarga completa. |

La sustitución de productos/categorías y su fecha es atómica. Una descarga fallida conserva el catálogo anterior. El nombre normalizado permite buscar por prefijo sin acentos mediante `.index('porNombre')`; el otro índice consulta una categoría.

El carrito persiste antes de enviar. Si se pierde la respuesta, permanece bloqueado para edición y permite reintentar la misma solicitud. No hay envío automático al reconectar. Los precios visibles son estimados: PostgreSQL determina los definitivos.

El service worker de `/menu/` precarga sólo el HTML público y sus recursos compilados locales. Usa cache-first con revisión por contenido. No almacena sesiones, tokens CSRF, respuestas privadas ni pedidos. Las imágenes externas pueden usar el logotipo incluido si no están disponibles offline. El aviso offline requiere tanto la caché instalada como una descarga del catálogo.

## IndexedDB de Personal

Base `trackeat-personal`, versión **2**. Su esquema, índices, fotografía Blob, migración y caché están descritos en [el LEEME de la Práctica 4](../frontend/personal/LEEME.md). La caché está limitada a `/personal/`: actualizar un módulo no borra la caché ni la base del otro.
