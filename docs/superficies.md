# Superficies de ataque — TrackEat

Actualización de catálogo y seguridad asignada: el levantamiento inicial se conserva abajo como antecedente. El estado vigente y sus comprobaciones están en la matriz añadida al final y en `seguridad-verificacion.md`.

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

## Cobertura vigente por superficie

Los números corresponden a las 14 medidas de `seguridad-verificacion.md`. Que una medida cubra una superficie no implica que toda la práctica esté aprobada.

| Superficie | Amenaza | Medidas y ubicación | Comprobación y límite |
| --- | --- | --- | --- |
| `/login`, `/register` | Fuerza bruta, cuentas falsas, enumeración | M1 hash en `User` y registro; M2 error genérico y M4 límite en `LoginRequest`; M5 cookies de sesión | Pruebas de hash, error uniforme y sexto intento. M3 CAPTCHA y límite de registro pendientes del equipo; M5 comprobada en el túnel de prueba; repetir en el dominio final. |
| Recuperación, cambio de contraseña y verificación | Robo de token, manipulación del enlace | M1 hash, M6 consultas enlazadas, M7 validación; enlaces firmados y protección CSRF del framework | Regresiones de autenticación. No almacenar tokens o URLs completas en el log; revisión de mensajes y servidor pendiente. |
| `GET /api/catalogo`, `/menu/`, `/menu/{producto}` | XSS almacenado, información obsoleta | M6 Eloquent; M7 texto escapado; filtro de categorías y bajas en `CatalogoController` | Texto de ataque visible sin ejecutar; agotados visibles sin pedir; fecha de actualización offline. La caché puede conservar datos públicos anteriores hasta sincronizar. |
| `/admin/productos`, `/admin/categorias` y operaciones sobre IDs | Cambio de catálogo sin permiso, duplicados, pérdida histórica | M8 administrador activo en servidor; M7 Form Requests; M6 índices únicos y transacciones; M10 bitácora reducida | Cliente/personal no administrador: 403. Bajas recuperables y restauración condicionada. No se modifican ítems históricos. |
| Subida de imágenes y `/catalogo/imagenes/{archivo}` | Ejecutables disfrazados, metadatos privados, agotamiento de recursos | M9 bytes/formato/tamaño/dimensiones, recodificación WebP, UUID y almacenamiento privado; M8 permiso de administrador; M7 pertenencia y orden de galería | Rechazo de SVG/PHP, archivos grandes, seis imágenes e IDs ajenos. Endpoint con MIME fijo y nosniff. Archivos retirados dejan de servirse públicamente. |
| `POST /api/pedidos` | Precio o dueño manipulado, duplicación por reintentos | M6 transacción y parámetros; M7 enum/límites, precio desde servidor y comparación del precio confirmado; M8 dueño desde sesión; M10 rechazo sin payload | 409 si cambia el precio y revisión explícita; UUID idempotente. Catálogo desactualizado no autoriza un precio. Límites adicionales del negocio siguen siendo decisiones del equipo. |
| `/pedidos/{pedido}`, `/api/pedidos/{pedido}`, listado | Lectura de pedidos ajenos cambiando un número | M8 `OrderAccess`, cuenta activa y filtro por dueño; M10 plantilla de ruta sin número; M7 salida codificada | Dos clientes: propio 200, ajeno/inexistente 404. Personal autorizado conserva lectura del restaurante. Sin caché pública de respuestas privadas. |
| `PATCH /api/pedidos/{pedido}/estacion` | Modificación de pedido ajeno o estado inválido | M8 `OrderAccess` antes de operar y roles en servicio; M7 transición secuencial; M10 evento reducido | Cliente ajeno 404; cliente propietario sin rol operativo 403; estaciones y estados permitidos. No se implementaron asignaciones por trabajador porque no existen en el modelo acordado. |
| `/api/tablero`, páginas operativas y canales privados | Suscripción ajena, fuga de notas/datos | M8 autenticación, rol, dueño/canal y sala demo; eventos con campos mínimos; M10 rechazo de autorización | Regresiones de pedidos/tiempo real. Las páginas públicas no equivalen a acceso público a la API. No se amplió el modelo a múltiples restaurantes. |
| IndexedDB y service worker de menú | Datos privados en caché, carrito obsoleto | M7 revalidación de servidor y aviso persistente; separación de caché pública | Prueba offline de detalle/galería, cambio de precio y cuota insuficiente. Solo catálogo público e imágenes sin cookies; pedidos y sesión fuera de Cache Storage. |
| `security` y logs generales | Filtración o inyección en bitácora | M10 allowlist de evento, rol, motivo, plantilla, método, estado y UUID; JSON fuera de public | Prueba con valores sensibles ficticios ausentes. Retención de 30 días naturales al escribir eventos. Logs generales/proxy requieren revisión independiente. |
| `/demo/{room}` y `/salud` | Abuso de demo o información de infraestructura | Enlace firmado, caducidad y demo explícita; M8 aislamiento de cliente demo; M14 configuración por entorno | Mantener demo desactivada fuera de presentaciones y restringir diagnóstico de infraestructura. No se expusieron credenciales nuevas. |
| HTTPS, cookies, HTML y recursos estáticos | Intercepción, clickjacking, scripts externos, errores detallados | M11 túnel HTTPS; M12 cabeceras, M13 CSP y M14 depuración/secretos | Ver `seguridad-servidor.md`: M11 y cookies verificadas en el túnel de prueba; M12 y M13 pendientes de integración; M14 parcial. Aplican también a `/personal/` y otros archivos estáticos. |
