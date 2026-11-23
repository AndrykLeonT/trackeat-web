# Validación del catálogo y seguridad asignada

Implementación en la rama local, sin publicación ni integración a otras ramas. Las comprobaciones usan datos ficticios salvo la aplicación aditiva de la migración a la base de trabajo, que conservó sus cantidades de registros.

## Resultados ejecutados

| Comprobación | Resultado | Evidencia reproducible |
| --- | --- | --- |
| PHPUnit con PostgreSQL | 83 pruebas y 398 aserciones aprobadas | `scripts/verificar.ps1`; incluye catálogo, archivos (incluido PNG con paleta indexada), permisos, precio, bitácora (incluidas redirecciones sin sesión) y regresiones de autenticación, pedidos, caja y migraciones. |
| JavaScript | 9 pruebas aprobadas | `node --experimental-vm-modules --test tests/realtime.mjs tests/catalogo-db.mjs` desde frontend. |
| Compilación | Aprobada para Inertia, Personal y Menú | `pnpm build` desde frontend. Persisten advertencias anteriores de inicialización de props en la pantalla ajena de recuperación de contraseña. |
| ESLint de archivos frontend modificados | Aprobado sin errores | Ejecución sin `--fix` sobre menú, nuevos componentes y páginas, Sidebar y AppLayout. |
| Formato PHP | Aplicado a los archivos de esta contribución | Laravel Pint, sin modificar archivos ajenos a los cambios. |
| Navegador de catálogo | 10 escenarios agrupados aprobados, sin errores JavaScript | `frontend/tests/catalogo-browser.mjs`, Chrome, servidor de `trackeat_demo_test` en puerto 8005. |
| Regresión de tiempo real | Aprobada con socket y con socket bloqueado | `frontend/tests/realtime-browser.mjs`: nueva comanda y tres cambios de estado visibles sin recargar. |
| HTTPS temporal | HTTPS 200, HTTP 301 a HTTPS | `docs/evidencias/seguridad-entorno.json`. Solo se guardan cabeceras y atributos; no valores de cookies o tokens. |
| Cookie de sesión en HTTPS | HttpOnly, Secure y SameSite lax observados | Cookie `trackeat_session` en `/login` y `/api/sesion`; la cookie XSRF separada es legible por JavaScript por diseño. |
| Cabeceras y CSP | Ausentes en HTML del entorno probado | No se observaron HSTS, nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy ni CSP en `/menu/` y `/login`. Pendiente del equipo. |
| Word y PDF | Ocho páginas, revisadas visualmente | Portada, seis apartados y referencias. Exportación con Microsoft Word nativo y render de las ocho páginas con pdfplumber. |

## Recorrido de navegador

Se crearon una categoría y un producto ficticios desde sus formularios, con dos imágenes PNG reales recodificadas a WebP. Se reordenó la galería y se comprobó el resultado público. El texto de prueba con una etiqueta y un manejador de evento se mostró literalmente y no ejecutó JavaScript.

La búsqueda parcial se combinó con la categoría. Se abrió el detalle enlazable, se agregó el producto al carrito y se recargó sin conexión: persistieron el carrito y la fotografía y el envío quedó bloqueado. Un cambio posterior de precio produjo 409; el aviso de revisión permaneció después de recargar y el pedido solo se confirmó después de reconocer el nuevo total.

Un segundo cliente recibió 404 al abrir el mismo pedido por página y API; un ID inexistente también devolvió 404. Un cliente recibió 403 en administración y el administrador pudo consultar el pedido. Se comprobó el agotado visible sin botón habilitado y se ejecutaron baja lógica y restauración desde la interfaz.

Las capturas de 390 píxeles muestran menú y administración en móvil; se revisaron visualmente. La tabla administrativa permite desplazamiento horizontal para sus acciones. Un contexto con error de cuota simulado informó imágenes incompletas y no anunció disponibilidad offline completa.

Las pruebas automáticas del servidor complementan el recorrido: precios inválidos, duplicados normalizados, SQL tratado como datos, categorías eliminadas, imágenes ajenas, SVG, ejecutable disfrazado, exceso de tamaño/dimensiones/cantidad, limpieza de archivos tras rollback y conservación de estaciones/precios históricos. La retención se comprueba con archivos ficticios: elimina el día vencido y conserva archivos recientes y logs de otros canales.

## Base de trabajo

El preflight de `backend/scripts/catalogo-preflight.php` comprobó que `trackeat` no tenía nombres duplicados. Se aplicó exclusivamente la migración nueva de unicidad. Antes y después se conservaron: 3 categorías, 4 productos, 4 imágenes, 28 pedidos y 33 ítems. No se reinició, sembró ni utilizaron esos datos para la suite.

Las pruebas del servidor usan `trackeat_test`; el navegador utiliza `trackeat_demo_test`. Los productos, pedidos y fotografías ficticios de esas bases no se transfieren a la base de trabajo. Las pruebas de navegador pueden acumular registros con sufijos únicos; no se deben ejecutar contra datos reales.

## Documentos y limitaciones

El renderizador empaquetado `render_docx.py` se ejecutó y falló por ausencia de LibreOffice. Se diagnosticó el requisito y se utilizó Microsoft Word instalado para repaginar y exportar el DOCX, sin instalar software adicional. El PDF resultante se renderizó a PNG para verificar todas las páginas; no se sustituyó esa comprobación por extracción de texto.

Los resultados HTTPS pertenecen al túnel temporal de esta prueba. Se debe repetir la comprobación en el dominio de entrega. No se ejecutó la infraestructura Docker distribuida ni se comprobó almacenamiento compartido de imágenes entre nodos; la dependencia GD se añadió a su imagen, pero ese build no se certifica aquí.

Siguen pendientes CAPTCHA, límite del registro, las cinco cabeceras de la consigna, CSP con bloqueo demostrado y cierre de la revisión de secretos y depuración en el entorno final. Ver `seguridad-verificacion.md`. La bitácora reducida no acredita la privacidad de los logs generales de PHP, Laravel, Nginx o Cloudflare.

## Archivos de evidencia

- `docs/evidencias/catalogo-browser.json`: resultado resumido del recorrido con datos ficticios.
- `docs/evidencias/seguridad-entorno.json`: cabeceras, redirección y atributos de cookies.
- `output/playwright/catalogo-offline.png`, `catalogo-menu-movil.png` y `catalogo-admin-movil.png`: capturas locales.
- `output/seguridad/TrackEat_propuesta_seguridad.docx` y su PDF: entregables de ocho páginas.
- `docs/exposicion-seguridad.md`: guion, tarjetas, preparación de demo y preguntas de ensayo.

Las imágenes de revisión del documento quedan en `tmp/propuesta-render/` como intermediarios locales. No forman parte del PDF ni se necesitan para presentarlo.
