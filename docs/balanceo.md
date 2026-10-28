# Balanceo de carga — TrackEat

Aplicaciones Web II · Unidad 3 · Sesión 15
Instituto Tecnológico de La Paz · Grupo 8-H

Equipo: Daniel Alexander Estrada Cosio, Angelica Menchaca Rueda, Andryk Manuel Leon Tapia, Carlos Omar Celis Calzada.

## Tabla de casos

| Caso | Algoritmo | Por qué | Qué pasaría con turno rotatorio |
|---|---|---|---|
| A · Catálogo público | Turno rotatorio (round robin) | Las peticiones son cortas y se parecen entre sí, y los servidores son iguales, así que repartir en orden deja la carga distribuida de forma equitativa. | Es el caso ideal del turno rotatorio, por lo que funcionaría bien. |
| B · Reportes de ventas | Menos conexiones (least connections) | Si un reporte mantiene la conexión abierta mucho tiempo, el balanceador ve que ese servidor está ocupado y manda las siguientes peticiones a otros. Aunque no haya recibido más conexiones nuevas, toma en cuenta las que siguen activas. | Mientras un servidor sigue procesando un reporte largo, le pueden seguir tocando peticiones por turno; las peticiones cortas esperarían a que se libere y el servidor se iría saturando poco a poco. |
| C · Máquinas desiguales | Turno rotatorio ponderado (weighted) | Como un servidor tiene menos capacidad que los otros, se le asigna menos peso para que cada servidor reciba carga de acuerdo con lo que puede manejar. | La carga se repartiría pareja aunque las capacidades no lo son, y el servidor con menos recursos se saturaría poco a poco. |
| D · Pantalla de cocina | Menos conexiones (least connections) | El WebSocket se mantiene abierto mientras dure la conexión, así que lo ideal es mandar cada pantalla nueva al servidor que tenga menos conexiones activas. | Solo repartiría las conexiones nuevas sin considerar cuáles siguen abiertas. Las conexiones quedarían disparejas y algunos servidores acumularían varias sesiones largas mientras otros siguen disponibles. |
| E · TrackEat | Menos conexiones (least connections) | TrackEat combina peticiones cortas (menú, pedidos, cobros) con conexiones largas (pantallas de cocina y barra por WebSocket) y consultas más pesadas (gráficos y cortes de caja). Menos conexiones toma en cuenta las conexiones que siguen activas, y con peticiones cortas se comporta prácticamente igual que el turno rotatorio, así que cubre todos los perfiles. La sesión se resuelve con almacén compartido (ver abajo). | Funcionaría bien para el menú, pero las pantallas de cocina y los reportes se repartirían sin considerar las conexiones abiertas, y algunos servidores se saturarían mientras otros quedan libres. |

## Detalle del caso E

### Perfiles de tráfico

| Perfil | Quién lo usa | Tipo de petición |
|---|---|---|
| Menú público (catálogo, búsqueda, filtros) | Visitantes y clientes | Corta y parecida. Es la de mayor volumen. |
| Operación (crear pedido, actualizar estado, registrar pago) | Clientes, meseros, cocineros y cajero | Corta, con escritura en base de datos. |
| Tiempo real (comandas y estado del pedido) | Cocineros, meseros y clientes | WebSocket que se queda conectado todo el turno. |
| Panel administrativo (gráficos, cortes de caja) | Administrador y cajero | Más pesada que el resto por las consultas de agregación. |

### Algoritmo

Se usa menos conexiones para todo el sistema. Al haber conexiones de duración muy distinta, es el algoritmo que mejor evita que un servidor se sature mientras otros están libres.

### Sesión

Se resuelve con **almacén compartido**: la sesión se guarda en la base de datos PostgreSQL que ya usa el proyecto (`SESSION_DRIVER=database`), así cualquier servidor puede leerla sin importar a cuál llegó la petición anterior.

Se descartan las otras dos opciones:

- **Afinidad (ip hash):** en un restaurante o food truck, el personal y muchos clientes salen por la misma red, así que caerían todos en un solo servidor.
- **Testigo firmado:** es difícil invalidarlo antes de que caduque; si el administrador desactiva a un empleado, su sesión seguiría siendo válida.

### Tiempo real

Para los WebSockets (Laravel Reverb) se usa **Redis** como canal compartido, para que una comanda publicada desde cualquier servidor llegue a todas las pantallas, sin importar a qué servidor esté conectada cada una.
