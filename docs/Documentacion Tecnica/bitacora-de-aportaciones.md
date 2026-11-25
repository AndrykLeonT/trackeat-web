# TrackEat · Bitácora de aportaciones

Aplicaciones Web II · ITLP 8-H · Corte al 25 de noviembre de 2026

Esta bitácora reúne lo que hizo cada integrante en dos frentes: el **módulo de programación** que le tocó según el [reparto de módulos](../../TrackEat_reparto_de_modulos%20(1).pdf) y su **parte de la Práctica 6, las tareas y la documentación técnica**. Las fechas y descripciones de los commits salen del historial de Git (`git log --no-merges`).

## 1. Resumen del equipo

| Integrante | Usuario de Git | Módulo de programación | Parte de Práctica 6 y documentación |
| --- | --- | --- | --- |
| Angelica Menchaca Rueda | `Anye1990` | Módulo 1 · Catálogo y menú | Persona 3 · Pedidos y bitácora + Tarea 5 |
| Andryk León | `AndrykLeonT` | Módulo 2 · Pedidos y comandas | Persona 4 · Documentación técnica |
| Daniel Estrada | `ChocorrolInCode` | Módulo 3 · Pagos y caja | Persona 2 · Registro e inicio de sesión + Manual de usuario |
| Carlos Celis | `Carlos Celis` | Módulo 4 · Usuarios, panel admin y gráficos | Persona 1 · Servidor + Tarea 4 |

---

## 2. Angelica Menchaca Rueda

### Módulo 1 · Catálogo y menú

Entidades: `Categoria`, `Producto`, `ImagenProducto`. Cubre la galería de imágenes (U1) y el menú que funciona sin conexión (U2).

- Menú público con búsqueda y filtro por categoría, y detalle de producto con galería.
- Menú sin conexión con service worker.
- Administración de productos y categorías, con subida de varias imágenes (se convierten a WebP, se guardan con nombre UUID en disco privado).
- Validación con Form Requests (`ProductoRequest`, `CategoriaRequest`).

### Práctica 6 · Persona 3 (3 medidas)

| Entregable | Evidencia |
| --- | --- |
| Control de acceso en pedidos: cambiar el número en la URL da 403/404 | Medida 8 de la [lista de verificación](../evidencias/lista-verificacion-practica-6.md): `cliente2` abre `/pedidos/3` y recibe 404 |
| Bitácora de seguridad sin contraseñas ni datos personales | `backend/app/Http/Middleware/SecurityAudit.php`, canal `security` (medida 10) |
| Validación en el servidor y salida codificada | Form Requests del catálogo y escape de Svelte (medida 7) |
| Actualizar `superficies.md` con la medida que cubre cada superficie | [docs/superficies.md](../superficies.md) |
| Tarea 5 · PDF de la propuesta de seguridad (6 apartados) | [output/seguridad/TrackEat_propuesta_seguridad.pdf](../../output/seguridad/TrackEat_propuesta_seguridad.pdf), generado desde [docs/propuesta-seguridad.md](../propuesta-seguridad.md) |
| Tarea 5 · Exposición de 5 minutos | Por confirmar: el guion `docs/exposicion-seguridad.md` ya no está en `main` |

### Otras aportaciones

- PostgreSQL local y arranque con `iniciar-base-datos.bat` / `iniciar.bat`.
- Práctica 4: catálogo de personal con IndexedDB.
- Avance 2: menú offline y pedidos en tiempo real, desglose de comandas.
- Panel del carrito y sincronización de documentación.

### Commits

| Fecha | Commit |
| --- | --- |
| 2026-09-17 | Add frontend package-lock and Windows start script |
| 2026-10-13 | feat(database): implementar PostgreSQL y arranque local |
| 2026-10-13 | feat(personal): agregar catálogo offline con diseño compartido |
| 2026-10-14 | fix(personal): simplificar la pantalla del catálogo |
| 2026-10-20 | feat: integrar menu offline y pedidos en tiempo real para Avance 2 |
| 2026-10-21 | feat: agregar desglose de comandas y mejorar actualizaciones en tiempo real |
| 2026-11-23 | feat(catalogo): implementar catálogo, menú offline y seguridad de pedidos |
| 2026-11-23 | feat(catalogo): mejorar interfaz de administración y carga de imágenes |
| 2026-11-24 | feat: mejorar panel de carrito, ampliar auditoria de seguridad y sincronizar documentacion |

---

## 3. Andryk León

### Módulo 2 · Pedidos y comandas

Entidades: `Pedido`, `PedidoEstacion`, `ItemPedido`, `HistorialEstado`. Cubre el carrito en el navegador y el tiempo real (U2).

- Flujo completo de pedidos y comandas, con pantallas nuevas para meseros y cajeros.
- Validación de que no existan pedidos sin usuario asignado.
- Solicitud y cancelación de pedidos; límite de pedidos.
- Asignación de comandas según el rol (estación).
- Rediseño de la vista de folios.
- Navegación por rol e imágenes para los alimentos.

### Práctica 6 y documentación técnica · Persona 4 (4 medidas)

| Entregable | Evidencia |
| --- | --- |
| Contraseñas con bcrypt | Medida 1: cast `hashed` en `User.php`, `BCRYPT_ROUNDS=12` |
| Consultas parametrizadas | Medida 6: Eloquent/PDO, búsqueda de `' OR 1=1 --` sin resultados |
| Subida de archivos | Medida 9: sí aplica (imágenes del catálogo); se valida tipo de imagen y se guarda con UUID en disco privado |
| Depuración apagada y secretos en `.env` fuera de Git | Medida 14 (parcial): `APP_DEBUG: "false"` en `docker/nodo/compose.yml`, `.gitignore` |
| Declaración de uso de IA en el README | Pendiente: el README solo la menciona para la Práctica 4 |
| Modelo de datos | [docs/esquema.md](../esquema.md) |
| Diagrama de despliegue | [diagrama-de-despliegue.md](diagrama-de-despliegue.md) |
| Manual de instalación | [Manual-de-instalacion.md](Manual-de-instalacion.md) |
| Bitácora de aportaciones | Este documento |

### Otras aportaciones

- Creación del repositorio, landing page (diseño, animaciones, íconos) y README inicial.
- Migración del frontend de React a Svelte.
- Práctica 5: Reverb por nodo, Redis e informe de balanceo.

### Commits

| Fecha | Commit |
| --- | --- |
| 2026-09-09 | primer commit · pdf |
| 2026-09-16 | creacion de proyecto y landing page |
| 2026-09-17 | Diseño de landing (sin animaciones) · Añadir animaciones · Subir iconos · añadir readme |
| 2026-09-21 | cambio de react a svelte |
| 2026-11-05 | feat: Practica_5 con Reverb por nodo, Redis e informe · instrucciones balanceo |
| 2026-11-24 | termino de modulo de pedidos y comandas, nuevas pantallas para meseros y cajeros |
| 2026-11-24 | refactor de PedidosController y validacion de pedidos sin usuario asignado |
| 2026-11-24 | refactor solicitud de pedidos y cancelacion |
| 2026-11-24 | Limite de pedidos/asignacion de comandas en base al rol |
| 2026-11-24 | chore: dejar fuera de main el CSV del backlog, la guia de arquitectura y el cambio local de levantar-compose |
| 2026-11-25 | Navegacion por rol e imagenes para alimentos |
| 2026-11-25 | rework a la vista de los folios y actualizacion de documentacion |
| 2026-11-25 | manual de implementacion |

---

## 4. Daniel Estrada

### Módulo 3 · Pagos y caja

Entidades: `Pago`, `Corte`. Cubre las reglas de cobro y el cierre de caja.

- Módulo de caja: cobro de pedidos, pagos y cortes.
- Correcciones a la lógica de pagos.

### Práctica 6 · Persona 2 (4 medidas)

| Entregable | Evidencia |
| --- | --- |
| Captcha en el registro y campo trampa oculto | Medida 3: `Rules/Turnstile.php`, campo `website` |
| Límite de intentos en el registro | Medida 4: limitador `registro` (5 por minuto por IP) |
| Cookie de sesión con Secure, HttpOnly y SameSite | Medida 5: `config/session.php` |
| Mensaje de error genérico en el login | Medida 2: un solo mensaje `auth.failed` |
| Lista de verificación de las 14 medidas | [lista-verificacion-practica-6.md](../evidencias/lista-verificacion-practica-6.md) y [Evidencias-Practica-6.pdf](../evidencias/Evidencias-Practica-6.pdf) |
| Manual de usuario con capturas | [Manual-de-usuario-TrackEat.pdf](../manual-de-usuario/Manual-de-usuario-TrackEat.pdf) |

### Otras aportaciones

- Corrección de `iniciar.bat`, diseño de comandas y del menú.
- Pruebas de Lighthouse y Tarea 3 (catálogo de componentes HTML5).
- Práctica 5: balanceo de carga (`docs/balanceo.md`).

### Commits

| Fecha | Commit |
| --- | --- |
| 2026-09-23 | fix: Inicar.bat |
| 2026-09-24 | feat: diseño-comandas · fix: diseño-menu |
| 2026-10-07 | test: lighthouse · test: lighthouses |
| 2026-10-08 | feat: Tarea 3 · fix: Tarea 3 |
| 2026-10-28 | Create balanceo.md |
| 2026-11-03 | feat: Practica_5 |
| 2026-11-04 | fix: Practica_5 |
| 2026-11-05 | fix: practica_5_V2 |
| 2026-11-23 | feat: caja · fix: pagos |
| 2026-11-24 | feat: Captcha en el Registro y el Login · fix: captcha |
| 2026-11-24 | feat: Acceso al Login y campo trampa en resgistrarse |
| 2026-11-24 | feat: limite de intentos en los registros · delete doc |
| 2026-11-25 | feat(docs): Manual de Usuario · fix(docs): manual de usuario |
| 2026-11-25 | feat(docs): verificacion de las medidas de seguridad · fix: lista de verificacion |
| 2026-11-25 | fix(docs): correcion de evidencias · fix: pruebas y evidencia |

---

## 5. Carlos Celis

### Módulo 4 · Usuarios, panel admin y gráficos

Entidad: `User`. Cubre el registro y login (U3) y los gráficos (U2).

- Perfil de usuario.
- Panel administrativo con gráficos.
- Sidebar, header, footer y menú digital (estructura base del frontend).

### Práctica 6 · Persona 1 (3 medidas)

| Entregable | Evidencia |
| --- | --- |
| HTTPS con certificado y redirección HTTP → HTTPS en Nginx | Medida 11: `nginx.conf.template`, 301 a https y TLS 1.3. [02-nginx-https.md](../evidencias/02-nginx-https.md) |
| Las 5 cabeceras de seguridad | Medida 12: CSP, HSTS, nosniff, Referrer-Policy y Permissions-Policy. [03-cabeceras-nginx.md](../evidencias/03-cabeceras-nginx.md) |
| CSP que bloquea un script de prueba | Medida 13. [04-prueba-csp.md](../evidencias/04-prueba-csp.md) |
| `nginx.conf` comentado línea por línea | [docker/infra/nginx.conf.template](../../docker/infra/nginx.conf.template) |
| Tarea 4 · PDF sobre firma electrónica, certificados y HTTPS | [Tarea_4_firma_certificados_HTTPS.pdf](../Tarea_4_firma_certificados_HTTPS.pdf) |

### Otras aportaciones

- Configuración base del proyecto y estilos globales en `app.css`, corrección del modo oscuro.
- `menu-card.svelte` con `srcset` y `picture`; tarjetas con movimiento reducido.
- Guía de instalación en Windows ([docs/demo-windows.md](../demo-windows.md)) y actualización del README.
- Documentación de controles y cierre de la Práctica 6.

### Commits

| Fecha | Commit |
| --- | --- |
| 2026-09-23 | build: basic config |
| 2026-09-24 | feat: sidebar, header, footer and digital menu added |
| 2026-10-07 | feat: ahora todos los archivos usan app.css para los estilos |
| 2026-10-07 | feat: funcion para tarjetas con movimiento reducido |
| 2026-10-07 | feat: menu-card.svelte ahora usa srcset y picture para mejor diseño |
| 2026-10-07 | fix: error del modo oscuro |
| 2026-11-25 | feat(nginx): configurar HTTPS y redirigir HTTP con 301 |
| 2026-11-25 | feat(nginx): aplicar cabeceras de seguridad y CSP |
| 2026-11-25 | test(nginx): verificar el bloqueo de scripts por CSP |
| 2026-11-25 | feat(usuarios): integrar perfil, panel administrativo y gráficos |
| 2026-11-25 | docs(seguridad): agregar informe de la tarea 4 |
| 2026-11-25 | docs(seguridad): documentar controles y cierre de práctica 6 |
| 2026-11-25 | docs(demo): agregar guía de instalación en Windows |
| 2026-11-25 | docs(demo): limitar guía de Windows a los seis puntos |
| 2026-11-25 | docs: actualizacion del readme |

---

## 6. Pendientes

- **Declaración de uso de IA en el README** (Persona 4): falta una sección general.
- **Medida 14 parcial**: los nodos de Docker heredan `APP_ENV=local`; para producción hay que poner `APP_ENV=production` en `docker/nodo/compose.yml`.
- **Manual de instalación**: falta probarlo en otra computadora.
- Cada integrante debe revisar su sección y avisar si falta algo que no quedó en un commit (trabajo en pareja, investigación, pruebas manuales).
