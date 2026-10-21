# Diseño de datos de TrackEat

## PostgreSQL

Las migraciones de `backend/database/migrations/` son la definición ejecutable del esquema. Se usan claves foráneas restrictivas, importes `decimal(12,2)`, restricciones CHECK, marcas de baja lógica y fechas con zona horaria. No se reinicia una base existente para actualizarla: `php artisan migrate` aplica los cambios pendientes.

| Tabla | Relación y propósito |
| --- | --- |
| `users` | Identidad, datos de contacto y rol: cliente, cocinero, mesero, cajero o administrador. |
| `categorias` | Clasifica productos y asigna estación: general, cocina o barra. |
| `productos` | Pertenece a categoría; nombre, descripción, precio y disponibilidad. |
| `imagenes_producto` | Fotografías por producto, ordenadas y con URL. |
| `pedidos` | Pertenece a cliente; folio único, entrega, mesa, total y estado global. |
| `items_pedido` | Relaciona pedido y producto; conserva cantidad, precio y estación al ordenar, además de notas. |
| `pedidos_estacion` | Una fila única por pedido/estación; avance independiente de cada área. |
| `historial_estados` | Registra pedido, estación, actor, estado anterior/nuevo y fecha. |
| `cortes` | Apertura/cierre de caja; únicamente un corte abierto. |
| `pagos` | Relaciona pedido, cajero y corte; método, importe, propina y reembolso. |

El avance de estación es `recibido → preparacion → listo → entregado`. El estado global sigue la estación más atrasada. `PedidoService` valida disponibilidad, calcula los precios y conserva historial dentro de una transacción. Los pedidos cancelados no avanzan.

La migración del 20 de octubre añade `users.demo_room` (UUID), `users.demo_expires_at` (fecha), `pedidos.demo_room` y `pedidos.client_request_id` (UUID). Son campos anulables: las cuentas y pedidos anteriores permanecen válidos. Un índice único por `(id_usuario, client_request_id)` y un bloqueo transaccional evitan duplicados incluso con reintentos simultáneos. `demo_room` identifica el tablero compartido de cada enlace de clase.

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
