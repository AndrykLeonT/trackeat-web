# Módulo 2 · Pedidos y comandas — Requisitos, historias de usuario y backlog

> Documento de trabajo del módulo 2 de TrackEat. Responsable: Andryk Manuel León Tapia. Redactado el 2026-11-23.
> Reúne lo que hace falta para programar el módulo: requisitos que le corresponden, reglas de negocio vigentes, decisiones tomadas, historias de usuario con criterios de aceptación y orden de implementación.
> Fuentes: `docs/esquema.md` (esquema implementado), `docs/superficies.md` (seguridad), el reparto de módulos del 16 de octubre y la revisión del código en `main` (merge del 23 de noviembre).

## 1. Alcance del módulo

El módulo 2 es dueño de las entidades `pedidos`, `pedidos_estacion`, `items_pedido` e `historial_estados`, con sus modelos (`Pedido`, `PedidoEstacion`, `ItemPedido`, `HistorialEstado`), el servicio `PedidoService`, el evento `PedidoActualizado`, sus rutas, controladores, páginas y pruebas.

Reglas del reparto que aplican:

- Cada módulo declara sus rutas en su propio archivo (`routes/pedidos.php`) y `web.php` solo lo incluye.
- No se editan migraciones ya integradas: todo cambio de esquema es una migración nueva. Si toca una tabla de otro módulo, se le pide al dueño.
- Otros módulos leen pedidos pero no los escriben directamente: pasan por `PedidoService`.
- Las pruebas del módulo van en un archivo propio dentro de `backend/tests/Feature`.
- Rama del módulo: `feature/modulo-2-pedidos`, con pull request hacia `main` revisado por otra persona.

Dependencias con otros módulos:

| Módulo | Relación |
|---|---|
| 1 · Catálogo | Provee productos, precios y la estación de cada categoría (`categorias.estacion` decide a qué estación llega cada ítem). |
| 3 · Pagos y caja | Consulta pedidos para cobrar. Cobra cualquier pedido no cancelado con saldo, sin importar su estado (para llevar se cobra antes, comer aquí después). Solo el cajero registra pagos. |
| 4 · Usuarios | Dueño de `users`, del control de acceso por rol y del admin de usuarios. Debe agregar `users.estacion` (ver T-03). |

## 2. Requisitos funcionales que cubre

Tomados de `01-fundamentos` (Tarea 2). La numeración sigue el orden de esa lista.

| Req. | Requisito | Historias |
|---|---|---|
| RF-07 | El sistema debe permitir al cliente agregar productos a un pedido. | HU-01 |
| RF-08 | El sistema debe permitir al cliente modificar las cantidades de los productos antes de confirmar el pedido. | HU-01 |
| RF-09 | El sistema debe permitir al cliente confirmar y enviar un pedido. | HU-02 |
| RF-10 | El sistema debe generar un número identificador para cada pedido. | HU-02 |
| RF-11 | El sistema debe permitir al cliente consultar el estado actual de su pedido. | HU-03 |
| RF-12 | El sistema debe mostrar al personal las nuevas comandas recibidas. | HU-06 |
| RF-13 | El sistema debe permitir al personal actualizar el estado de una comanda. | HU-05, HU-07, HU-09 |
| RF-18 | El sistema debe permitir al administrador consultar los pedidos realizados. | HU-10 |
| RF-19 | El sistema debe mostrar al cliente los cambios de estado de su pedido. | HU-03 |
| RF-20 | El sistema debe permitir al cliente consultar el historial de sus pedidos. | HU-04 |
| Usuarios (§2) | El mesero crea pedidos para los clientes y ve los pendientes. | HU-08, HU-09 |

Requisitos del curso que cubre: **Datos en el navegador** (carrito en IndexedDB, unidad 2) y **Tiempo real** (Laravel Reverb, unidad 2).

## 3. Requisitos no funcionales y reglas de negocio vigentes

| Id | Regla | Estado en el código |
|---|---|---|
| RNF-01 | El estado global del pedido se deriva de sus estaciones y nunca se edita directo: sigue a la estación más atrasada. | Hecho (`PedidoService::avanzar`) |
| RNF-02 | El precio de cada ítem se congela al ordenar (`items_pedido.precio_unitario`) y el servidor recalcula el total; el cliente no puede enviar precios ni total. | Hecho |
| RNF-03 | Cada cambio de estado se registra en `historial_estados` con estación, estado anterior, estado nuevo, quién y cuándo. | Hecho |
| RNF-04 | Flujo de una estación: `recibido → preparacion → listo → entregado`. Solo se avanza al siguiente estado; no se regresa. `cancelado` es final. | Hecho |
| RNF-05 | Un reintento con el mismo `client_request_id` no crea un segundo pedido (bloqueo `pg_advisory_xact_lock` dentro de la transacción). | Hecho para todos los roles: el dueño siempre existe, así que el índice único `(id_usuario, client_request_id)` cubre también al personal (T-05) |
| RNF-06 | Los eventos se publican después del commit por la cola; solo llevan id, folio, estado y fecha. El detalle se pide por HTTP con autorización. El frontend tiene sondeo de 5 s como respaldo. | Hecho |
| RNF-07 | Autorización por objeto: el personal solo puede avanzar pedidos de su sala/restaurante y, el cocinero, solo su estación (superficie A01). | Pendiente (T-06) |
| RNF-08 | Límites de negocio en el servidor al crear pedido: total máximo, mesa válida, pedidos abiertos por cliente (superficie A08). | Pendiente (T-07) |
| RNF-09 | El carrito persiste en el navegador (IndexedDB `trackeat-menu`, almacén `carrito`) y no se envía sin conexión. | Hecho |
| RNF-10 | Funciona detrás del balanceador con dos nodos: todo bloqueo o contador vive en PostgreSQL, nunca en memoria de PHP. | Regla de diseño |
| RNF-11 | No se cancela un pedido con pagos activos: primero se corrigen o reembolsan. No se cancela un pedido entregado. | Hecho (`PedidoService::cancelar`) |

## 4. Decisiones tomadas el 2026-11-23

Estas decisiones cambian o completan lo documentado antes. Donde contradicen a `02-base-de-datos` o `05-bdd-pedidos-y-estados`, mandan estas.

| Tema | Decisión |
|---|---|
| Folio | Consecutivo que **reinicia cada día**. Guardado como `TE-AAAAMMDD-NNN`, visible como `#NNN`. Sustituye al actual `TE-` + ULID. |
| Confirmación y seguimiento | Una sola página `/pedidos/{id}` con el resumen y el seguimiento juntos. El menú redirige ahí al confirmar. |
| Seguimiento | Muestra el avance de **cada estación**, no solo el estado global. |
| Historial del cliente | Filtra **por día**, por defecto el día actual, con opción de ver todos. Internamente el día sale del **corte** en que se cobró el pedido (corte → pagos → pedidos). El cliente no ve el concepto de corte. |
| Historial del personal | Puede filtrar por corte, para separar los pedidos de un día con dos cortes. |
| Cancelación | Cliente: solo mientras todas las estaciones estén en `recibido`. Personal (mesero, cajero, administrador): hasta antes de `entregado`, eligiendo un motivo obligatorio. Se conserva la regla de no cancelar con pagos activos. |
| Estación del cocinero | Opción B: columna `users.estacion` asignada por el administrador, con valores `cocina`, `barra` o `general`. Un cocinero de `cocina` ve y avanza solo esa estación, uno de `barra` solo la suya, y uno de `general` ve y avanza **todas** (cocina y barra). Se coordina con el módulo 4. |
| Estaciones de producto (2026-11-24) | Las categorías, y por herencia sus productos, solo se preparan en `cocina` (alimentos) o `barra` (bebidas). `general` deja de ser estación de producto: ningún producto se asigna a `general`. Las categorías que eran `general` se reclasificaron por nombre (bebidas a `barra`, el resto a `cocina`). Las filas históricas de `pedidos_estacion` e `items_pedido` conservan `general` (esa tabla es inmutable por trigger); ya no se crean nuevas. |
| Tablero | Columnas `recibido`, `preparacion`, `listo`. Un pedido sigue visible en `listo` hasta que se entrega; una casilla "Mostrar entregados" permite consultarlos. |
| **Dueño del pedido** (2026-11-24) | `pedidos.id_usuario` es un **único campo, obligatorio**, y significa **quién levantó el pedido**. Si es un cliente, el pedido se hizo en la web. Si es mesero, cajero o administrador, lo capturó el personal y **no se vincula ningún cliente**. No hay búsqueda de clientes ni pedidos sin dueño; el servicio fija el dueño con el usuario que hace la petición y nadie puede registrarlo a nombre de otra cuenta. **Sustituye** a la decisión del 23 de noviembre de "pedidos sin cuenta" con `id_usuario` nullable (la migración de T-02 se revierte con una migración nueva). |
| Captura del mesero | Reutiliza la misma pantalla del menú y carrito. |
| Vista del mesero | Por defecto, pedidos para comer aquí; con opción de ver todos, incluidos los para llevar. |
| Entrega y cobro | Mesero y cajero pueden marcar **entregado** cualquier pedido. Solo el cajero puede **cobrar** (marcar pagado, en el módulo 3). |
| Admin de pedidos | Filtros por estado, fecha, mesa, tipo de entrega, cliente y corte. Sin exportación. |

### Supuestos por confirmar

- El motivo obligatorio de cancelación se guarda en `pedidos.motivo_cancelacion` (columna agregada en T-02, `string(50)` nullable). La lista de motivos se define en HU-05.
- Un pedido que todavía no tiene pago (y por tanto no tiene corte) aparece en el historial en el día en que se creó.
- Cifras de los límites de A08: total máximo por pedido, rango de mesas y pedidos abiertos por cliente.
- Qué ve un cocinero sin estación asignada.

## 5. Historias de usuario

### HU-01 · Carrito persistente
**Como** cliente, **quiero** armar mi carrito y que no se pierda al recargar o quedarme sin red, **para** no tener que volver a elegir mis productos.
Prioridad: Must · Requisitos: RF-07, RF-08 · **Estado: hecho** en `frontend/menu/` (IndexedDB).

Criterios de aceptación:
- Agrego y quito productos; la cantidad va de 1 a 100 por línea.
- Escribo una nota por producto.
- Elijo para llevar o comer aquí; la mesa es obligatoria en comer aquí.
- Al recargar o sin conexión, el carrito sigue igual.
- Sin conexión no deja confirmar; al reintentar usa el mismo identificador de solicitud y no duplica el pedido.

### HU-02 · Confirmar pedido y ver su resumen
**Como** cliente, **quiero** confirmar mi pedido y llegar a una página con su folio y su resumen, **para** saber qué pedí y con qué número me van a llamar.
Prioridad: Must · Requisitos: RF-09, RF-10.

Criterios de aceptación:
- Al confirmar, el menú me lleva a `/pedidos/{id}`.
- Veo el folio del día (`#045`), los productos con su precio congelado, las notas, el tipo de entrega, la mesa y el total.
- El folio es consecutivo, reinicia cada día y nunca se repite, aunque dos nodos creen pedidos al mismo tiempo.
- Solo el dueño del pedido o el personal pueden abrir la página; otro cliente recibe 404.

### HU-03 · Seguimiento en vivo por estación
**Como** cliente, **quiero** ver en la misma página el avance de cada estación de mi pedido en vivo, **para** saber cuánto falta sin preguntar.
Prioridad: Must · Requisitos: RF-11, RF-19.

Criterios de aceptación:
- Veo el estado global y el de cada estación (por ejemplo, "Cocina: en preparación · Barra: listo").
- Se actualiza sin recargar por mi canal privado `trackeat.usuario.{id}`; si el tiempo real se cae, el sondeo de 5 s lo mantiene al día y la página lo indica.
- Cancelado y entregado se muestran como estados finales.

### HU-04 · Mis pedidos por día
**Como** cliente, **quiero** consultar mis pedidos por día y abrir cualquiera para ver sus productos, **para** revisar lo que he pedido.
Prioridad: Must · Requisito: RF-20.

Criterios de aceptación:
- `/mis-pedidos` muestra por defecto el día actual; puedo elegir otro día o ver todos.
- El día de un pedido sale del corte en que se cobró (corte → pagos → pedidos); la página no menciona cortes.
- Un pedido aún sin pago aparece en el día en que se creó (supuesto por confirmar).
- Cada fila muestra folio, fecha, estado y total; los más recientes primero, con paginación.
- Al abrir uno voy a `/pedidos/{id}` con sus productos.
- Solo veo mis propios pedidos.

### HU-05 · Cancelar pedido
**Como** cliente o personal, **quiero** cancelar un pedido que todavía no se empezó a preparar (cliente) o que no se ha entregado (personal), **para** corregir errores sin desperdiciar comida.
Prioridad: Should · Requisito: RF-13.

Criterios de aceptación:
- Cliente: solo mientras todas las estaciones estén en `recibido`.
- Mesero, cajero y administrador: hasta antes de `entregado`, eligiendo un motivo de una lista obligatoria.
- No se cancela si hay pagos activos: primero se corrigen o reembolsan.
- Todas las estaciones pasan a `cancelado`, queda en el historial con quién lo hizo y se avisa en vivo.

### HU-06 · Tablero de comandas por estación
**Como** cocinero, **quiero** un tablero con columnas por estado que muestre solo mi estación, **para** concentrarme en lo que me toca preparar.
Prioridad: Must · Requisito: RF-12.

Criterios de aceptación:
- Columnas `recibido`, `preparacion` y `listo`.
- El cocinero ve solo la estación que el administrador le asignó (`users.estacion`): `cocina` ve las comandas de alimentos, `barra` las de bebidas y `general` ve ambas.
- El administrador ve todas y puede filtrar por estación.
- Un pedido sigue en `listo` hasta que se entrega; la casilla "Mostrar entregados" los deja ver como consulta.
- Las comandas nuevas aparecen sin recargar y muestran sus notas.

### HU-07 · Avanzar el estado de mi estación
**Como** cocinero, **quiero** avanzar el estado de mi estación y que quede registrado, **para** que el resto del equipo sepa cómo va cada pedido.
Prioridad: Must · Requisito: RF-13 · **Estado: parcial** (falta la autorización de T-06).

Criterios de aceptación:
- Solo se avanza al siguiente estado (`recibido → preparacion → listo`); no se regresa.
- El cocinero no puede marcar entregado ni avanzar una estación que no es la suya.
- Un pedido de otra sala de demostración responde 404.
- Cada cambio crea un registro en `historial_estados` con quién y cuándo.

### HU-08 · Captura de pedido por mesero o cajero
**Como** mesero, cajero o administrador, **quiero** capturar un pedido con la misma pantalla del menú, **para** atender en mesa o por teléfono sin obligar a nadie a registrarse.
Prioridad: Must · Requisito: Usuarios §2.

Criterios de aceptación:
- Uso la misma pantalla del menú y carrito.
- El pedido queda a mi nombre (`id_usuario` es quien lo levanta); no se vincula ningún cliente.
- Un cliente en la web solo puede ordenar con su propia cuenta y nadie puede registrar un pedido a nombre de otra.
- Los reintentos no duplican pedidos: el índice único `(id_usuario, client_request_id)` cubre a todos los roles.
- Un pedido del personal se identifica por su folio y su mesa.

### HU-09 · Pedidos por entregar
**Como** mesero, **quiero** ver los pedidos por entregar, empezando por los de comer aquí, **para** llevarlos en cuanto estén listos.
Prioridad: Should · Requisito: RF-13.

Criterios de aceptación:
- `/mesero/pedidos` muestra por defecto comer aquí; un filtro muestra todos, incluidos para llevar.
- Los pedidos listos aparecen primero.
- Mesero y cajero pueden marcar entregado cualquier pedido.
- Solo el cajero ve la acción "Cobrar", que lo lleva a `/caja/pedidos/{id}/cobrar` (módulo 3); el mesero no registra pagos.

### HU-10 · Administración de pedidos
**Como** administrador, **quiero** filtrar los pedidos y ver la bitácora de cada uno, **para** revisar la operación y aclarar reclamos.
Prioridad: Should · Requisito: RF-18.

Criterios de aceptación:
- `/admin/pedidos` con filtros por estado, fecha, mesa, tipo de entrega, cliente y corte.
- Con el filtro de corte se separan los pedidos de un día que tuvo dos cortes.
- El detalle muestra ítems, estaciones, historial (quién y cuándo) y el motivo si se canceló.
- Sin exportación; solo entra el rol administrador.

## 6. Backlog en orden de implementación

| # | Id | Elemento | Fase | Prioridad | Depende de |
|---|---|---|---|---|---|
| 1 | T-01 | Rama del módulo, `routes/pedidos.php` y `PedidoController` (mover endpoints de `TrackEatController` sin cambiar URLs) | 0 · Base | Must | — |
| 2 | T-02 | Migración: `motivo_cancelacion` en `pedidos`. **Se hizo también `id_usuario` nullable, que T-05 revirtió (el dueño vuelve a ser obligatorio).** | 0 · Base | Must | T-01 |
| 3 | T-03 | `users.estacion`: pedir migración y campo en admin al módulo 4; mientras, asignarla en el seeder. **Hecho por el módulo 2 como excepción al reparto (2026-11-24): migración, seeder y estaciones de producto (T-03b). Falta el campo en el admin de usuarios, que corresponde al módulo 4.** | 0 · Base | Must | — |
| 4 | T-04 | Folio consecutivo diario, generado en la transacción con bloqueo de PostgreSQL por día | 1 · Servidor | Must | T-01 |
| 5 | T-05 | Dueño del pedido = quien lo levanta (cliente en la web; mesero, cajero o administrador al capturar), `id_usuario` obligatorio de nuevo. Sin búsqueda de clientes ni pedidos sin dueño. | 1 · Servidor | Must | T-02 |
| 6 | T-06 | `PedidoPolicy@avanzar` (A01): sala/restaurante, transición por rol, estación del cocinero; 404 si no le pertenece | 1 · Servidor | Must | T-03 |
| 7 | T-07 | Límites de negocio al crear pedido (A08) en config; unificar límites controlador/servicio | 1 · Servidor | Must | T-01 |
| 8 | HU-02 + HU-03 | Página `/pedidos/{id}` con resumen y seguimiento | 2 · Cliente | Must | T-04 |
| 9 | HU-04 | Mis pedidos por día | 2 · Cliente | Must | HU-02 |
| 10 | HU-05 | Cancelar pedido | 2 · Cliente | Should | T-02, HU-02 |
| 11 | HU-06 | Tablero de comandas por estación | 3 · Personal | Must | T-03, T-06 |
| 12 | HU-07 | Avanzar estado de mi estación (se cierra con T-06) | 3 · Personal | Must | T-06 |
| 13 | HU-08 | Captura de pedido por mesero o cajero | 3 · Personal | Must | T-05, HU-02 |
| 14 | HU-09 | Pedidos por entregar | 3 · Personal | Should | HU-06 |
| 15 | HU-10 | Admin: pedidos con filtros y bitácora | 3 · Personal | Should | T-02 |
| 16 | T-08 | `tests/Feature/PedidosModuloTest.php`: folio, dueño del pedido, A01, A08, cancelación, historial, filtros; todo en verde | 4 · Cierre | Must | Fases 1–3 |
| 17 | T-09 | Avisar a los módulos 3 y 4 (en caja la etiqueta "Cliente" muestra al dueño del pedido, que en pedidos del personal es quien lo levantó; folio nuevo; `users.estacion`) y probar el flujo completo | 4 · Cierre | Should | T-02, T-04 |
| 18 | T-10 | Actualizar `docs/esquema.md` y la documentación del proyecto con estas decisiones | 4 · Cierre | Should | T-02 |

## 7. Estado actual del código (punto de partida)

| Archivo | Qué hay |
|---|---|
| `backend/routes/web.php` | `/comandas`, `/comandas/desglose` y el grupo `/api` (`catalogo`, `sesion`, `pedidos` GET/POST, `pedidos/{pedido}/estacion` PATCH, `tablero`). |
| `backend/app/Http/Controllers/TrackEatController.php` | Endpoints de catálogo, sesión, pedidos y tablero. `crear` fuerza `id_usuario` al usuario autenticado. `pedidos` devuelve hasta 200 sin paginar. |
| `backend/app/Services/PedidoService.php` | `crear` (exige un usuario con rol cliente; folio `TE-` + ULID), `avanzar` (flujo, rol, historial, estado global) y `cancelar` (sin ruta). |
| `backend/app/Events/PedidoActualizado.php` | Evento en canales `trackeat.personal`, `trackeat.usuario.{id}`, `trackeat.demo.{room}`. El canal del usuario es el de quien levantó el pedido (cliente o personal). |
| `backend/database/migrations/2026_10_13_000002_create_trackeat_tables.php` | `pedidos.id_usuario` es `foreignId(...)->constrained()` sin `nullable()`. `numero_pedido` es `string(50)` único. |
| `backend/database/migrations/2026_10_20_000001_add_live_demo_and_order_requests.php` | Índice único `(id_usuario, client_request_id)`. |
| `frontend/menu/Menu.svelte` | Catálogo y carrito (IndexedDB); al confirmar solo muestra un mensaje. |
| `frontend/src/pages/public/comandas.svelte` + `components/ui/comanda-card.svelte` | Tarjetas por pedido con todas las estaciones y botones de avance según rol. |
| `frontend/src/lib/trackeat.js` | Cliente de API, sesión, conexión a Reverb y sondeo de 5 s. |
| `backend/tests/Feature/LiveOrdersTest.php`, `TrackEatTest.php` | Pruebas existentes de las reglas base; deben seguir en verde. |
