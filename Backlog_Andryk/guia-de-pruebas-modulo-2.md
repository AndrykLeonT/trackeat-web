# Módulo 2 · Guía de pruebas manuales

> Cómo probar a mano todas las historias de usuario del módulo 2 (Pedidos y comandas): cuentas, flujos por rol y casos de error.
> Responsable: Andryk Manuel León Tapia. Redactada el 2026-11-25, con el módulo completo (HU-01 a HU-10) y la suite en verde (201 pruebas).
> Historias y reglas de negocio: [modulo-2-pedidos-historias.md](modulo-2-pedidos-historias.md). Diseño de datos: [`docs/esquema.md`](../docs/esquema.md).

## 1. Antes de empezar

- La aplicación debe estar levantada con Docker. Pasos en [`levantar-compose.md`](../levantar-compose.md). Si cambió el código, hay que reconstruir la imagen y aplicar las migraciones (`php artisan migrate --force` dentro de `nodo1`).
- **Dirección:** `https://localhost:8443` (el balanceador). El certificado es autofirmado: el navegador pide aceptar la advertencia una vez. Para probar un nodo directo, sin balanceador, usa `http://127.0.0.1:8001` o `:8002`.
- **Captcha:** el login de Docker usa el captcha de Cloudflare con llaves de prueba. Basta con hacer clic en "Verifica que eres humano", pero necesita internet.
- **Una sesión por navegador:** para ver dos roles a la vez, usa una ventana de incógnito o un navegador distinto por rol.

## 2. Cuentas de demostración

Las crea `DemoSeeder` (solo en `local` y `testing`). Son datos de demostración, no cuentas reales.

**Contraseña de todas:** `TrackEatDemo!2026`

| Rol | Correo | Qué puede hacer |
|---|---|---|
| Cliente | `cliente1@example.test` | Pedir desde `/menu/`, ver `/mis-pedidos` y `/pedidos/{id}`, cancelar si nada se ha empezado a preparar. |
| Cliente | `cliente2@example.test` | Igual; sirve para probar que no se ven los pedidos de otro cliente. |
| Mesero | `mesero@example.test` | Capturar pedidos de mesa desde `/menu/`, ver `/mesero/pedidos`, entregar y cancelar. |
| Cajero | `cajero@example.test` | Capturar pedidos por teléfono, cobrar en `/caja`, entregar y cancelar. |
| Cocinero | `cocinero@example.test` | Ver y avanzar comandas en `/comandas`. Su estación es `general`: atiende cocina y barra. |
| Administrador | `administrador@example.test` | Ver y filtrar todos los pedidos (`/admin/pedidos`), avanzar cualquier estación en `/comandas`, entregar y cancelar. Administra usuarios, productos y categorías (`/admin/usuarios`, `/admin/productos`, `/admin/categorias`). |

Datos de partida: hay 3 categorías (Hamburguesas y Empaquetados van a **cocina**, Bebidas va a **barra**), 4 productos de ejemplo y 4 pedidos con folio de ejemplo (`DEMO-001` a `DEMO-004`). El cajero ya tiene un **corte abierto**, así que puede cobrar de inmediato. El folio corto `#NNN` se ve en los pedidos que se creen a partir de ahora.

## 3. Flujo completo recomendado

Es el recorrido principal: un pedido de mesa que pasa por cliente, cocina, mesero, caja y administración. Dura unos 5 minutos y cubre casi todas las historias.

| # | Rol | Acción | Resultado esperado |
|---|---|---|---|
| 1 | Cliente (`cliente1`) | En `/menu/` agrega una hamburguesa y una limonada, elige **Comer aquí**, mesa 4, y confirma. | Redirige a `/pedidos/{id}` con el folio `#NNN`, el resumen con precios y el avance de cada estación en "Recibido". |
| 2 | Cocinero | En `/comandas` mira la columna "Recibido". | La comanda nueva aparece sin recargar, como dos tarjetas (cocina y barra), con sus notas. |
| 3 | Cocinero | Avanza cada tarjeta: Recibido, En preparación y Listo. | Cada paso queda registrado. Solo avanza al siguiente estado. |
| 4 | Cliente | Mira su página `/pedidos/{id}`. | El estado de cada estación cambia sola, sin recargar. |
| 5 | Mesero | En `/mesero/pedidos` busca el pedido (los listos van primero) y pulsa **Entregar**. | El pedido pasa a "Entregado". Solo se puede entregar cuando todas sus estaciones están listas. |
| 6 | Cajero | En `/caja` (Por cobrar) pulsa **Cobrar** y registra el pago (efectivo, o dividido con tarjeta y propina). | El pedido queda pagado y desaparece de "Por cobrar". El mensaje dice "Pedido #NNN pagado.". |
| 7 | Administrador | En `/admin/pedidos` filtra por el corte del cajero y abre el detalle. | Aparece el pedido y la bitácora trae cada cambio de estado con quién lo hizo y cuándo. |
| 8 | Cliente | En `/mis-pedidos` mira el día de hoy. | El pedido aparece con folio, fecha, estado y total. |

## 4. Historia por historia

### HU-01 · Carrito persistente (cliente)
1. En `/menu/` agrega y quita productos; cambia cantidades (de 1 a 100) y escribe una nota en un producto.
2. Elige **Para llevar** o **Comer aquí** (la mesa es obligatoria al comer aquí).
3. Recarga la página.
4. Abre las herramientas del navegador (F12), pestaña Red, y marca **Sin conexión**.

**Esperado:** el carrito sigue igual al recargar y sin conexión. Sin conexión no deja confirmar. Al reconectar y reintentar usa la misma solicitud, así que no se crea un pedido duplicado.

### HU-02 · Confirmar y ver el resumen (cliente)
1. Confirma un pedido desde el menú.
2. Con otro cliente (`cliente2`) intenta abrir `/pedidos/{id}` de ese pedido.

**Esperado:** el menú lleva a `/pedidos/{id}`. Se ve el folio del día (`#045`), los productos con su precio congelado, las notas, el tipo de entrega, la mesa y el total. El otro cliente recibe **404**.

### HU-03 · Seguimiento en vivo por estación (cliente)
1. El cliente deja abierto `/pedidos/{id}`.
2. En otra ventana, el cocinero avanza las estaciones.

**Esperado:** se ve el estado global y el de cada estación ("Cocina: en preparación · Barra: listo") sin recargar. El indicador de conexión avisa si el tiempo real se cae; en ese caso el sondeo de 5 s mantiene la página al día. "Cancelado" y "Entregado" se muestran como estados finales.

### HU-04 · Mis pedidos por día (cliente)
1. Entra a `/mis-pedidos`.
2. Cambia el día y prueba la opción de ver todos.
3. Abre un pedido.

**Esperado:** por defecto muestra **hoy**. Cada fila trae folio, fecha, estado y total, los más recientes primero, de 20 en 20. Al abrir uno va a `/pedidos/{id}`. Solo se ven los pedidos propios. La página no menciona cortes.

### HU-05 · Cancelar pedido (cliente y personal)
- **Cliente:** en `/pedidos/{id}`, con todas las estaciones en "Recibido", cancela. Luego repite con un pedido en el que el cocinero ya empezó a preparar.
- **Personal (mesero, cajero, administrador):** cancela desde `/mesero/pedidos`, `/comandas` (administrador) o `/pedidos/{id}`, eligiendo un motivo de la lista.
- Cobra un pedido y luego intenta cancelarlo.

**Esperado:** el cliente solo cancela mientras todo esté en "Recibido". El personal puede cancelar hasta antes de "Entregado" y el motivo es obligatorio. Con un pago registrado se rechaza: primero hay que corregir o reembolsar el cobro. Al cancelar, todas las estaciones pasan a "Cancelado", queda en la bitácora y se avisa en vivo. El cocinero no puede cancelar.

### HU-06 · Tablero de comandas (cocinero y administrador)
1. Como cocinero, entra a `/comandas`.
2. Como administrador, entra a `/comandas` y usa el filtro de estación.
3. Marca "Mostrar entregados".

**Esperado:** columnas Recibido, En preparación y Listo. Un pedido sigue en "Listo" hasta que se entrega. Las comandas nuevas aparecen sin recargar y muestran sus notas. Las más antiguas aparecen primero.

### HU-07 · Avanzar mi estación (cocinero)
1. Como cocinero, avanza una comanda de Recibido a En preparación y a Listo.
2. Intenta saltarte un paso o entregar.

**Esperado:** solo se avanza al siguiente estado y no se regresa. El cocinero **no puede entregar**. Cada cambio crea un registro en la bitácora con quién y cuándo.

### HU-08 · Captura por mesero o cajero
- **Mesero:** en `/menu/` (viene en "Comer aquí"), captura un pedido con mesa.
- **Cajero:** en `/caja`, pulsa **Capturar pedido** (viene en "Para llevar").

**Esperado:** usan la misma pantalla del menú y carrito. El pedido queda **a nombre de quien lo captura**; nunca queda sin dueño ni se asocia a otro cliente. Un reintento no duplica el pedido. Al confirmar, redirige al pedido.

### HU-09 · Pedidos por entregar (mesero y cajero)
1. Como mesero, entra a `/mesero/pedidos`.
2. Cambia el filtro a "todos".
3. Entra como cajero a la misma página.

**Esperado:** por defecto muestra comer aquí; el filtro agrega los de para llevar. Los listos aparecen primero. Mesero, cajero y administrador pueden marcar **Entregado** cualquier pedido listo. Solo el cajero ve el botón **Cobrar**, que lleva a `/caja/pedidos/{id}/cobrar`; el mesero no registra pagos. Entrar a `/comandas` como mesero o cajero redirige a esta pantalla.

### HU-10 · Administración de pedidos (administrador)
1. Como administrador, entra a `/admin/pedidos` (sidebar: Pedidos).
2. Prueba cada filtro y combínalos: estado, día (o "Todos los días"), mesa, entrega, cliente (nombre, correo o teléfono) y corte.
3. Abre el detalle de un pedido cancelado y de uno entregado.

**Esperado:** por defecto muestra hoy. Con el filtro de corte se separan los pedidos de un día que tuvo dos cortes. El detalle muestra productos, estaciones, bitácora (quién y cuándo) y el motivo si se canceló. No hay exportación. Cliente, mesero, cajero y cocinero reciben **403** en `/admin/pedidos`.

## 5. Reglas y errores que conviene demostrar

| Qué probar | Cómo | Esperado |
|---|---|---|
| Estación del cocinero | Administrador: en `/admin/usuarios` edita al cocinero y pon la estación `cocina` o `barra`. Después entra como cocinero a `/comandas`. | Solo ve y avanza su estación. Con `general` ve ambas. |
| Pedidos en curso del cliente | Con `cliente1` crea pedidos sin que se entreguen. | Máximo 3 en curso; el siguiente se rechaza con un aviso. (Los pedidos de ejemplo cuentan.) |
| Total máximo | Pedido que supere $5,000 como cliente, o $20,000 como personal. | Se rechaza con el máximo en el mensaje. |
| Mesa válida | Mesa 0 o mayor a 30. | Se rechaza (mesas de 1 a 30). |
| Cantidad máxima | 101 piezas de un producto. | Se rechaza (máximo 100 por renglón). |
| Entrega todo o nada | Pedido con cocina y barra; solo una estación en "Listo". | No se puede entregar hasta que todas estén listas. |
| Folio diario | Crea dos pedidos seguidos. | Folios consecutivos (`#NNN` y `#NNN+1`); al día siguiente reinicia en `#001`. |
| Folio corto en caja | Crea un pedido nuevo y abre `/caja`. | Se ve `#NNN`; los folios de ejemplo (`DEMO-001`) se ven igual. |
| Pedido ajeno | Con `cliente2`, abre la dirección de un pedido de `cliente1`. | 404. |
| Acceso por rol | Cliente en `/admin/pedidos`; cocinero en `/caja`. | 403. |
| Cobro y corte | El cajero abre `/caja/cerrar`, cierra el corte y trata de cobrar. | Debe abrir otro corte en `/caja/abrir` para volver a cobrar. |

## 6. Si algo no se ve como se espera

- **El tiempo real no actualiza:** la página lo indica ("Reconectando…") y el sondeo de 5 s la sigue actualizando. Revisa que `trackeat-reverb1` y `trackeat-reverb2` estén `Up` en `docker compose -f docker/compose.yml ps`.
- **Falta una pantalla o ruta:** la imagen de Docker es de una versión anterior. Reconstrúyela y aplica las migraciones pendientes.
- **El login no avanza:** completa el captcha y comprueba que hay internet.
- **Un cliente ya no puede pedir:** probablemente llegó al límite de 3 pedidos en curso; entrega o cancela alguno.
