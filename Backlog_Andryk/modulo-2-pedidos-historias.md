# Módulo 2 · Pedidos y comandas — Requisitos, historias de usuario y backlog

> Documento de trabajo del módulo 2 de TrackEat. Responsable: Andryk Manuel León Tapia. Redactado el 2026-11-23; **actualizado el 2026-11-25: el módulo está completo** (T-01 a T-10 y HU-01 a HU-10). Lo que cambió respecto al 23 de noviembre está marcado como *actualización*.
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
| RNF-05 | Un reintento con el mismo `client_request_id` no crea un segundo pedido (bloqueo `pg_advisory_xact_lock` dentro de la transacción). | Hecho. Todo pedido tiene dueño (ver la reversión en §4) |
| RNF-06 | Los eventos se publican después del commit por la cola; solo llevan id, folio, estado y fecha. El detalle se pide por HTTP con autorización. El frontend tiene sondeo de 5 s como respaldo. | Hecho |
| RNF-07 | Autorización por objeto: el personal solo puede avanzar pedidos de su sala/restaurante y, el cocinero, solo su estación (superficie A01). | Hecho (`PedidoPolicy`) |
| RNF-08 | Límites de negocio en el servidor al crear pedido: total máximo, mesa válida, pedidos abiertos por cliente (superficie A08). | Hecho (`config/trackeat.php` + `PedidoService::crear`) |
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
| Estación del cocinero | Opción B: columna `users.estacion` asignada por el administrador. El cocinero ve y avanza solo su estación. Se coordina con el módulo 4. |
| Tablero | Columnas `recibido`, `preparacion`, `listo`. Un pedido sigue visible en `listo` hasta que se entrega; una casilla "Mostrar entregados" permite consultarlos. |
| ~~Pedidos sin cuenta~~ **(revertida el 2026-11-24)** | *Actualización:* `id_usuario` se hizo nullable (`2026_11_24_000001`) y se volvió **NOT NULL** el mismo día (`2026_11_24_000005`). `id_usuario` es siempre **quien levanta el pedido**: el cliente en la web, o el mesero, cajero o administrador que lo captura. No existen pedidos sin cuenta ni se vincula un cliente aparte; vuelve a valer "no existen pedidos de invitado". |
| Captura del mesero y del cajero | Reutilizan la misma pantalla del menú y carrito; el pedido queda a nombre de quien lo captura. |
| Vista del mesero | Por defecto, pedidos para comer aquí; con opción de ver todos, incluidos los para llevar. |
| Entrega y cobro | Mesero y cajero pueden marcar **entregado** cualquier pedido. Solo el cajero puede **cobrar** (marcar pagado, en el módulo 3). |
| Admin de pedidos | Filtros por estado, fecha, mesa, tipo de entrega, cliente y corte. Sin exportación. |

### Supuestos (resueltos en la actualización del 2026-11-25)

- **Motivo de cancelación:** se guarda en `pedidos.motivo_cancelacion` (migración `2026_11_24_000001`). Si cancela el cliente se guarda "Cancelado por el cliente".
- **Pedido sin pago:** aparece en el historial en el día en que se creó. Implementado en `HistorialPedidos`.
- **Límites de A08** (`config/trackeat.php`, cambiables con `TRACKEAT_*`): 100 piezas por renglón, 50 renglones, mesas de 1 a 30, total máximo de $5,000 (cliente) y $20,000 (personal), 3 pedidos en curso por cliente.
- **Cocinero sin estación:** ya no existe ese caso. Un trigger de PostgreSQL le asigna `general` (ve y avanza cocina y barra), y el administrador puede cambiarla a `cocina` o `barra`.
- **Estación `general` de productos:** desaparece. Las categorías solo admiten `cocina` o `barra`; `general` es solo la estación de un cocinero.
- **Entrega:** es por pedido completo (todas las estaciones en `listo`), no por estación.

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
Prioridad: Must · Requisitos: RF-09, RF-10. · **Estado: hecho**

Criterios de aceptación:
- Al confirmar, el menú me lleva a `/pedidos/{id}`.
- Veo el folio del día (`#045`), los productos con su precio congelado, las notas, el tipo de entrega, la mesa y el total.
- El folio es consecutivo, reinicia cada día y nunca se repite, aunque dos nodos creen pedidos al mismo tiempo.
- Solo el dueño del pedido o el personal pueden abrir la página; otro cliente recibe 404.

### HU-03 · Seguimiento en vivo por estación
**Como** cliente, **quiero** ver en la misma página el avance de cada estación de mi pedido en vivo, **para** saber cuánto falta sin preguntar.
Prioridad: Must · Requisitos: RF-11, RF-19. · **Estado: hecho**

Criterios de aceptación:
- Veo el estado global y el de cada estación (por ejemplo, "Cocina: en preparación · Barra: listo").
- Se actualiza sin recargar por mi canal privado `trackeat.usuario.{id}`; si el tiempo real se cae, el sondeo de 5 s lo mantiene al día y la página lo indica.
- Cancelado y entregado se muestran como estados finales.

### HU-04 · Mis pedidos por día
**Como** cliente, **quiero** consultar mis pedidos por día y abrir cualquiera para ver sus productos, **para** revisar lo que he pedido.
Prioridad: Must · Requisito: RF-20. · **Estado: hecho**

Criterios de aceptación:
- `/mis-pedidos` muestra por defecto el día actual; puedo elegir otro día o ver todos.
- El día de un pedido sale del corte en que se cobró (corte → pagos → pedidos); la página no menciona cortes.
- Un pedido aún sin pago aparece en el día en que se creó.
- Cada fila muestra folio, fecha, estado y total; los más recientes primero, con paginación.
- Al abrir uno voy a `/pedidos/{id}` con sus productos.
- Solo veo mis propios pedidos.

### HU-05 · Cancelar pedido
**Como** cliente o personal, **quiero** cancelar un pedido que todavía no se empezó a preparar (cliente) o que no se ha entregado (personal), **para** corregir errores sin desperdiciar comida.
Prioridad: Should · Requisito: RF-13. · **Estado: hecho**

Criterios de aceptación:
- Cliente: solo mientras todas las estaciones estén en `recibido`.
- Mesero, cajero y administrador: hasta antes de `entregado`, eligiendo un motivo de una lista obligatoria.
- No se cancela si hay pagos activos: primero se corrigen o reembolsan.
- Todas las estaciones pasan a `cancelado`, queda en el historial con quién lo hizo y se avisa en vivo.

### HU-06 · Tablero de comandas por estación
**Como** cocinero, **quiero** un tablero con columnas por estado que muestre solo mi estación, **para** concentrarme en lo que me toca preparar.
Prioridad: Must · Requisito: RF-12. · **Estado: hecho**

Criterios de aceptación:
- Columnas `recibido`, `preparacion` y `listo`.
- El cocinero ve solo la estación que el administrador le asignó (`users.estacion`).
- El administrador ve todas y puede filtrar por estación.
- Un pedido sigue en `listo` hasta que se entrega; la casilla "Mostrar entregados" los deja ver como consulta.
- Las comandas nuevas aparecen sin recargar y muestran sus notas.

### HU-07 · Avanzar el estado de mi estación
**Como** cocinero, **quiero** avanzar el estado de mi estación y que quede registrado, **para** que el resto del equipo sepa cómo va cada pedido.
Prioridad: Must · Requisito: RF-13 · **Estado: hecho**.

Criterios de aceptación:
- Solo se avanza al siguiente estado (`recibido → preparacion → listo`); no se regresa.
- El cocinero no puede marcar entregado ni avanzar una estación que no es la suya.
- Un pedido de otra sala de demostración responde 404.
- Cada cambio crea un registro en `historial_estados` con quién y cuándo.

### HU-08 · Captura de pedido por mesero o cajero
**Como** mesero o cajero, **quiero** capturar un pedido con la misma pantalla del menú, **para** atender en mesa o por teléfono.
Prioridad: Must · Requisito: Usuarios §2. · **Estado: hecho**

Criterios de aceptación:
- Uso la misma pantalla del menú y carrito.
- *Actualización:* el pedido queda **a nombre de quien lo captura** (`id_usuario`); no se deja vacío ni se vincula a otro cliente.
- Nadie puede registrar un pedido a nombre de otra cuenta; el cuerpo de la petición no elige al dueño.
- Los reintentos del personal no duplican el pedido (mismo `client_request_id`).
- Un pedido de mesa se identifica por su folio y su mesa; uno para llevar, por su folio.

### HU-09 · Pedidos por entregar
**Como** mesero, **quiero** ver los pedidos por entregar, empezando por los de comer aquí, **para** llevarlos en cuanto estén listos.
Prioridad: Should · Requisito: RF-13. · **Estado: hecho**

Criterios de aceptación:
- `/mesero/pedidos` muestra por defecto comer aquí; un filtro muestra todos, incluidos para llevar.
- Los pedidos listos aparecen primero.
- Mesero y cajero pueden marcar entregado cualquier pedido.
- Solo el cajero ve la acción "Cobrar", que lo lleva a `/caja/pedidos/{id}/cobrar` (módulo 3); el mesero no registra pagos.

### HU-10 · Administración de pedidos
**Como** administrador, **quiero** filtrar los pedidos y ver la bitácora de cada uno, **para** revisar la operación y aclarar reclamos.
Prioridad: Should · Requisito: RF-18. · **Estado: hecho**

Criterios de aceptación:
- `/admin/pedidos` con filtros por estado, fecha, mesa, tipo de entrega, cliente y corte.
- Con el filtro de corte se separan los pedidos de un día que tuvo dos cortes.
- El detalle muestra ítems, estaciones, historial (quién y cuándo) y el motivo si se canceló.
- Sin exportación; solo entra el rol administrador.

## 6. Backlog en orden de implementación

| # | Id | Elemento | Fase | Prioridad | Depende de | Estado |
|---|---|---|---|---|---|---|
| 1 | T-01 | Rama del módulo, `routes/pedidos.php` y `PedidoController` (mover endpoints de `TrackEatController` sin cambiar URLs) | 0 · Base | Must | — | Hecho |
| 2 | T-02 | Migración: `motivo_cancelacion` (el `id_usuario` nullable se hizo y se revirtió: queda NOT NULL) | 0 · Base | Must | T-01 | Hecho |
| 3 | T-03 | `users.estacion`: pedir migración y campo en admin al módulo 4; mientras, asignarla en el seeder | 0 · Base | Must | — | Hecho |
| 4 | T-04 | Folio consecutivo diario, generado en la transacción con bloqueo de PostgreSQL por día | 1 · Servidor | Must | T-01 | Hecho |
| 5 | T-05 | Dueño del pedido = quien lo levanta (cliente, mesero, cajero o administrador); sin pedidos sin cuenta | 1 · Servidor | Must | T-02 | Hecho |
| 6 | T-06 | `PedidoPolicy@avanzar` (A01): sala/restaurante, transición por rol, estación del cocinero; 404 si no le pertenece | 1 · Servidor | Must | T-03 | Hecho |
| 7 | T-07 | Límites de negocio al crear pedido (A08) en config; unificar límites controlador/servicio | 1 · Servidor | Must | T-01 | Hecho |
| 8 | HU-02 + HU-03 | Página `/pedidos/{id}` con resumen y seguimiento | 2 · Cliente | Must | T-04 | Hecho |
| 9 | HU-04 | Mis pedidos por día | 2 · Cliente | Must | HU-02 | Hecho |
| 10 | HU-05 | Cancelar pedido | 2 · Cliente | Should | T-02, HU-02 | Hecho |
| 11 | HU-06 | Tablero de comandas por estación | 3 · Personal | Must | T-03, T-06 | Hecho |
| 12 | HU-07 | Avanzar estado de mi estación (se cierra con T-06) | 3 · Personal | Must | T-06 | Hecho |
| 13 | HU-08 | Captura de pedido por mesero o cajero | 3 · Personal | Must | T-05, HU-02 | Hecho |
| 14 | HU-09 | Pedidos por entregar | 3 · Personal | Should | HU-06 | Hecho |
| 15 | HU-10 | Admin: pedidos con filtros y bitácora | 3 · Personal | Should | T-02 | Hecho |
| 16 | T-08 | `tests/Feature/PedidosModuloTest.php`: folio, dueño del pedido, A01, A08, cancelación, historial, filtros; todo en verde | 4 · Cierre | Must | Fases 1–3 | Hecho |
| 17 | T-09 | Avisar a los módulos 3 y 4 (folio nuevo en caja, `users.estacion`) y probar el flujo completo (prueba de flujo completo agregada; folio corto aplicado en caja) | 4 · Cierre | Should | T-02, T-04 | Hecho |
| 18 | T-10 | Actualizar `docs/esquema.md` y la documentación del proyecto con estas decisiones | 4 · Cierre | Should | T-02 | Hecho |

## 7. Estado actual del código (actualizado el 2026-11-25)

| Archivo | Qué hay |
|---|---|
| `backend/routes/pedidos.php` | Todas las rutas del módulo: `/api/pedidos` (GET, POST, detalle), `PATCH /api/pedidos/{pedido}/estacion`, `POST …/cancelar`, `POST …/entregar`, `/api/mesero/pedidos`, `/api/tablero`, `/api/comandas`; páginas `/pedidos/{pedido}`, `/mis-pedidos`, `/mesero/pedidos`; y, solo administrador, `/admin/pedidos` y `/admin/pedidos/{pedido}`. |
| `backend/app/Http/Controllers/Pedidos/PedidoController.php` | Endpoints de pedidos, comandas, entrega y cancelación. |
| `backend/app/Http/Controllers/Pedidos/AdminPedidoController.php` | HU-10: lista con filtros (estado, día, mesa, entrega, cliente, corte) y detalle con bitácora. |
| `backend/app/Services/PedidoService.php` | `crear` (dueño = quien levanta, límites A08, folio diario), `avanzar`, `entregar` (por pedido completo) y `cancelar` (motivo obligatorio para el personal). |
| `backend/app/Policies/PedidoPolicy.php` | A01: `avanzar`, `entregar` y `cancelar` por rol, estación del cocinero y sala de demostración. |
| `backend/app/Support/` | `HistorialPedidos` (día del pedido por corte; lista de administración), `TableroComandas`, `PedidosPorEntregar`, `OrderAccess`. |
| `backend/app/Events/PedidoActualizado.php` | Evento en `trackeat.personal`, `trackeat.usuario.{id}` y `trackeat.demo.{room}`; el dueño siempre existe, así que siempre emite al canal del usuario. |
| `backend/config/trackeat.php` | Límites A08, motivos de cancelación y zona horaria del folio. |
| Migraciones `2026_11_24_*` y `2026_11_25_000001` | `motivo_cancelacion`, `users.estacion`, categorías solo `cocina`/`barra`, `folios_diarios`, `id_usuario` NOT NULL y trigger del cocinero. Detalle en `docs/esquema.md`. |
| `frontend/menu/Menu.svelte` | Catálogo y carrito (IndexedDB); al confirmar redirige a `/pedidos/{id}`. Mesero y cajero la usan para capturar. |
| `frontend/src/pages/public/` | `pedido.svelte` (resumen y seguimiento), `mis-pedidos.svelte`, `comandas.svelte` (tablero). |
| `frontend/src/pages/mesero/pedidos.svelte` | Pedidos por entregar (mesero, cajero y administrador). |
| `frontend/src/pages/admin/pedidos.svelte`, `pedido.svelte` | HU-10: lista con filtros y detalle con bitácora. |
| `backend/tests/Feature/PedidosModuloTest.php` | Pruebas del módulo, incluida la de flujo completo (T-09). La suite completa pasa: 201 pruebas. |
| Folio corto en caja (módulo 3) | Aplicado el 2026-11-25 con el visto bueno del equipo: `caja/index.svelte`, `cobrar.svelte`, `turno.svelte`, `purga.svelte` y `components/caja/pagos-tabla.svelte` usan `formatoFolio`, y el mensaje de `PagoController` usa `App\Support\Folio::visible()`. La API sigue entregando el folio completo. |
