# Superficies de ataque — TrackEat

Equipo: Daniel Alexander Estrada Cosio, Angelica Menchaca Rueda, Andryk Manuel Leon Tapia, Carlos Omar Celis Calzada.

Aplicaciones Web II · Unidad 3 · Tema 3.2 · Grupo 8-H

## Entradas del sistema

Todo lo que recibe datos de afuera, revisado contra `backend/routes/web.php`, `backend/routes/auth.php` y `backend/routes/channels.php`.

**Cuentas**
- `/login` (formulario de inicio de sesión: correo y contraseña)
- `/register` (registro de clientes: nombre, correo, contraseña)
- `/forgot-password` (correo para recuperar contraseña)
- `/reset-password/{token}` (token en la dirección y contraseña nueva)
- `/verify-email/{id}/{hash}` (enlace firmado de verificación)
- `/confirm-password` y `/logout`

**Pedidos y operación (API)**
- `GET /api/pedidos` (lista de pedidos del usuario o de todo el restaurante si es personal)
- `POST /api/pedidos` (crea un pedido: productos, cantidades, notas, tipo de servicio)
- `PATCH /api/pedidos/{pedido}/estacion` (avanza el estado de un pedido en una estación)
- `GET /api/tablero` (tablero de comandas para el personal)
- `GET /api/catalogo` (menú público)
- `GET /api/sesion` (entrega el token CSRF y la llave del socket)

**Tiempo real**
- `/broadcasting/auth` y los canales privados `trackeat.personal`, `trackeat.usuario.{id}`, `trackeat.demo.{room}` (WebSocket de Reverb)

**Demo y páginas**
- `/demo/{room}` (enlace firmado que crea un usuario cliente temporal)
- `/dashboard`, `/comandas`, `/comandas/desglose`, `/recetas`, `/metricas` (páginas del personal; se abren sin sesión, pero sus datos vienen de la API protegida)
- `/personal/` y `/menu/` (aplicaciones estáticas con datos guardados en el navegador)
- `/salud` (comprobación del balanceador: responde el nodo y si la base contesta)

**Configuración**
- `APP_DEBUG=true` en `.env` y `.env.example`: si se publica así, un error muestra rutas, consultas y variables (A02).

## Las tres más expuestas

| Superficie | Categoría | Qué podría pasar | Medida |
|---|---|---|---|
| `PATCH /api/pedidos/{pedido}/estacion` | A01 Control de acceso roto | Cualquier usuario con rol de personal puede avanzar **cualquier** pedido con solo cambiar el número en la dirección, aunque sea de otra sala de demostración. Hoy el servidor revisa el rol y que el estado avance de uno en uno, pero no a quién o a qué sala pertenece el pedido. | Una política (`PedidoPolicy@avanzar`) que en el servidor revise el pedido contra el usuario: misma sala o restaurante, estación que le toca a su rol, y responder 404 si no le pertenece. |
| `/login` y `/register` | A07 Fallas de autenticación | El login ya limita a 5 intentos por correo e IP, pero el registro no tiene límite ni captcha: un script puede crear miles de cuentas falsas y llenar la base. El README dice que hay captcha, pero el código no lo tiene. | `throttle` en `POST /register`, captcha en el registro (y en el login después de varios fallos) y un mensaje genérico ("correo o contraseña incorrectos") que no diga si el correo existe. |
| `POST /api/pedidos` | A08 Integridad de software y datos (y A06 Diseño inseguro) | Alguien manda la petición sin pasar por la página con un precio o total inventado, o con un pedido enorme (100 productos × 100 piezas) o un número de mesa que no existe, y el pedido entra a la cocina. | El servidor ya ignora el precio que manda el cliente, lo recalcula desde la base, fuerza el dueño del pedido y limita cantidades y notas: mantenerlo así. Agregar los límites del negocio que faltan en el servidor (total máximo por pedido, mesa válida, pedidos abiertos por cliente). Validar en JavaScript no cuenta. |
