# Catálogo y menú

Implementación local de catálogo y seguridad asignada. Conserva Laravel 12, Svelte 5 y PostgreSQL. El menú continúa usando IndexedDB y un service worker independiente del módulo Personal.

## Ejecutar

1. Instalar las dependencias habituales y habilitar PHP `gd`, `fileinfo`, `pdo_pgsql`, `mbstring` y `bcmath`. Los lanzadores de Windows ya cargan estas extensiones; la imagen Docker incluye GD con WebP.
2. Desde `backend`, ejecutar `../scripts/php.ps1 artisan migrate`. La migración nueva comprueba duplicados antes de crear índices únicos. Si hay conflictos, se detiene sin cambiar nombres ni borrar datos. No ejecutar `migrate:fresh` sobre datos de trabajo.
3. Desde `frontend`, ejecutar `pnpm build`. Abrir `/menu/`; con una cuenta administradora, abrir `/admin/productos` o `/admin/categorias`.

Los datos y archivos subidos son locales: Git comparte el código y las migraciones, no las imágenes ni la base de cada integrante.

## Comportamiento acordado

- Categorías únicas; productos únicos dentro de su categoría. Mayúsculas, acentos y espacios extremos no distinguen nombres. Una baja lógica conserva el nombre reservado.
- Precio final en MXN, mayor que cero, hasta dos decimales. Sin variantes ni extras.
- Productos agotados visibles y sin botón habilitado para pedir. Categorías desactivadas y registros eliminados ocultos del catálogo público.
- Eliminar una categoría exige mover o eliminar sus productos. Restaurar un producto exige restaurar antes su categoría. Los pedidos anteriores no cambian.
- La estación de una categoría solo afecta pedidos nuevos; los ítems ya guardados conservan su estación y precio.
- Máximo cinco JPG, PNG o WebP, de 2 MB y 4096 × 4096 píxeles por imagen. La primera es portada. Se validan bytes, se recodifica a WebP y se descartan metadatos y nombres originales. Las imágenes retiradas conservan baja lógica y ya no se sirven por su URL.

## Interfaces

| Entrada | Permiso y comportamiento |
| --- | --- |
| `GET /api/catalogo` | Público. Categorías disponibles y productos sin eliminar, incluidos agotados. Precio decimal como texto, disponibilidad, portada y arreglo `imagenes` con `id`, `url`, `orden`. |
| `/menu/` y `/menu/{producto}` | Catálogo y detalle enlazable con galería. Una navegación offline usa el último catálogo descargado; online el servidor rechaza un producto oculto o inexistente. |
| `/admin/productos` y `/admin/categorias` | Listados, búsqueda y filtro de bajas. Solo administrador activo. |
| `/{recurso}/crear` y `/{recurso}/{id}/editar` bajo `/admin` | Formularios de administración. |
| `POST /admin/productos[/{id}]` | Alta/edición; JSON o multipart. `imagenes_existentes` conserva IDs propios, `imagenes` contiene archivos y `orden_imagenes` usa referencias `existente:ID` o `nueva:INDICE`. Cada imagen debe figurar exactamente una vez. |
| `POST /admin/categorias[/{id}]` | Alta/edición de nombre, estación y disponibilidad. |
| `DELETE /admin/{recurso}/{id}` y `POST .../{id}/restaurar` | Baja lógica y restauración. |
| `GET /catalogo/imagenes/{uuid}.webp` | Sirve bytes recodificados, con MIME fijo y `nosniff`; sin acceso público a archivos de productos ocultos. |
| `/pedidos/{pedido}` y `GET /api/pedidos/{pedido}` | Cliente propietario o personal autorizado. Pedido ajeno, eliminado o inexistente: 404. |
| `POST /api/pedidos` | Conserva UUID de idempotencia; el menú añade `items.*.precio_confirmado`. El servidor compara bajo bloqueo con el precio de la base y devuelve 409 si cambió. Clientes anteriores que omiten ese campo siguen usando precio calculado por servidor. |

## Funcionamiento sin conexión

IndexedDB conserva textos, precios, categorías y carrito. Cache Storage conserva únicamente la aplicación pública y las imágenes que provienen del catálogo público. Las imágenes se descargan sin cookies. Sesiones, pedidos y respuestas administrativas no se almacenan en esa caché.

El estado «Menú y galerías disponibles sin conexión» solo aparece cuando la aplicación está controlada por el service worker y están presentes todas las imágenes del catálogo guardado. Un fallo de red o cuota muestra descarga incompleta. El navegador puede desalojar almacenamiento; se vuelve a comprobar al abrir.

El catálogo anterior puede quedar desactualizado. Se indica su fecha; se actualiza al abrir, recuperar conexión o pulsar Actualizar menú. El carrito solo se envía online. Un precio cambiado exige revisión explícita, que permanece pendiente incluso tras recargar. Un producto agotado o retirado debe quitarse. Una respuesta perdida conserva el UUID y el cuerpo del envío para reintentar sin duplicar el pedido.

## Pruebas reproducibles

Desde la raíz, `./scripts/verificar.ps1` ejecuta PHPUnit únicamente en PostgreSQL `trackeat_test`. La clase base impide usar una base cuyo nombre no termine en `_test`. No ejecutar simultáneamente dos suites que compartan esa base.

Para navegador:

```powershell
./scripts/verificar.ps1 -PrepareDemo
./scripts/presentacion.ps1 -TestDatabase -SkipBuild -WebPort 8005 -SocketPort 8085 -RunSeconds 3600
# En otra terminal, dentro de frontend:
$env:TRACKEAT_TEST_URL='http://localhost:8005'
node tests/catalogo-browser.mjs
node --experimental-vm-modules --test tests/realtime.mjs tests/catalogo-db.mjs
```

El servidor anterior usa exclusivamente `trackeat_demo_test`. Las pruebas de navegador crean registros ficticios con sufijo único; las capturas y el resultado JSON quedan en `output/playwright/`. La prueba usa la biblioteca Playwright ya instalada en el proyecto, sin necesitar instalar su CLI.

## Bitácora

`SecurityAudit` registra operaciones y rechazos de autenticación, catálogo, pedidos y autorización de canales. El canal `security` escribe JSON en `backend/storage/logs/security-AAAA-MM-DD.jsonl`, fuera del directorio público, con rotación diaria y retención de 30 días naturales. La limpieza sucede al escribir eventos y rotar; no es un borrado programado durante periodos sin actividad.

Campos permitidos: fecha del evento de Monolog, evento, rol, resultado, motivo, plantilla de ruta, método, estado HTTP y UUID aleatorio de solicitud. Nunca se incorporan valores recibidos, IDs de usuarios, IP, nombres, correos, teléfonos, notas, contraseñas, cookies ni tokens. El historial de estados continúa separado y conserva sus reglas operativas.

En Windows revisar permisos NTFS de `storage/logs`; en Linux el archivo se crea con modo `0600`. Los logs generales de Laravel, el proxy y PHP se revisan por separado: esta implementación no garantiza que esos otros logs estén depurados de información sensible.
