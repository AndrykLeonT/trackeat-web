# Práctica 4: verificación y demostración en clase

## Preparación

1. Instalar las dependencias habituales del proyecto: `pnpm install` en `frontend`.
2. Ejecutar `pnpm build` en esa carpeta. Genera TrackEat y el catálogo portable en
   `backend/public/personal/`, incluido el service worker con sus archivos de caché.
3. Iniciar TrackEat con `iniciar.bat`. Entrar a **Personal** desde la barra lateral,
   o abrir `/personal/` en el mismo origen y puerto del proyecto.
4. Como alternativa sin PHP ni PostgreSQL, ejecutar `pnpm preview:personal` en
   `frontend` y abrir la dirección que muestra Vite. Requiere haber compilado.
5. Esperar el aviso **Listo para trabajar sin conexión**. El primer acceso
   requiere red para instalar la caché; el catálogo no usa archivos externos.
6. Tener a mano dos fotografías PNG/JPEG/WebP válidas de hasta 5 MiB. Las pruebas
   se pueden realizar en un perfil de navegador separado para no alterar datos.

## Guion de diez minutos

1. Mostrar la carpeta `frontend/personal/` y el esquema del `LEEME.md`.
2. Registrar «Angélica Menchaca»: correo, teléfono, área Servicio, puesto Mesera,
   fecha de ingreso, turno y fotografía. Ver su ficha y el gráfico de Servicio.
3. Abrir **Acerca de la base local y su migración** → **Demostrar migración
   v1 → v2**. Mostrar el resultado: registro, clave, seis campos, fotografía
   idéntica byte a byte, turno agregado e índice nuevo utilizable. Explicar
   `onupgradeneeded` en `db.js`. La prueba usa una base temporal y la elimina
   después; no elimina ni reemplaza el catálogo principal.
4. En DevTools → Application → IndexedDB → `trackeat-personal`, mostrar la
   versión 2, el almacén `personal`, los índices `porArea` y `porNombre`, el Blob
   `foto` y el almacén `metadatos`. En una base que realmente venía de v1, este
   último registra cuántos registros se migraron; una instalación nueva registra
   `desde: 0`, sin fingir que existían registros previos.
5. Abrir Network y elegir **Offline**. Mantener desmarcada la opción de omitir
   el service worker (**Bypass for network** en Application). Recargar la página:
   debe volver a abrir, conservar la fotografía y listar el registro.
6. Sin conexión, agregar otra persona de Cocina con otra foto. Buscar «angel»:
   debe devolver a Angélica aunque no se escriba el acento. El campo dice
   **empieza con** porque la consulta usa `porNombre` con `IDBKeyRange.bound`.
   Limpiar y filtrar Cocina para consultar `porArea`.
7. Ver una ficha, editar el área/turno y sustituir la foto. Guardar, recargar y
   comprobar que persisten los cambios. Mostrar el gráfico actualizado; siempre
   representa todos los registros, incluso mientras la lista está filtrada.
8. Eliminar un registro: cancelar primero y comprobar que sigue listado.
   Después confirmar la baja. La barra correspondiente y el total disminuyen.
9. Usar Tab/Shift+Tab, Enter y Escape en formularios y diálogos. El botón Cancelar
   recibe el foco inicial al confirmar una baja. En el gráfico, inspeccionar el
   título SVG y la tabla alternativa para lectores de pantalla.
10. Reactivar la red. El catálogo continúa funcionando. Presentar esta
    demostración en vivo o grabarla; no se incluye un video pregrabado.

## Correspondencia con el documento

| Requisito                                     | Implementación / evidencia                                   |
| --------------------------------------------- | ------------------------------------------------------------ |
| Siete campos, foto y fecha; esquema previo    | `LEEME.md`; siete campos v1 más clave y dos campos v2        |
| IndexedDB                                     | `db.js`, base `trackeat-personal`; sin localStorage          |
| Alta, consulta, modificación, baja confirmada | Formulario, ficha y diálogo de baja en `Catalogo.svelte`     |
| Índice no primario usado al buscar            | `porNombre` por prefijo y `porArea` por coincidencia         |
| Versión 2 y migración sin pérdida             | `upgradeDatabase`, metadatos y demostración de bytes         |
| Fotografía binaria                            | Campo `foto: Blob`; URLs temporales solo para visualización  |
| Abrir/listar/buscar/alta sin red              | HTML estático, `offline.js`, `service-worker.js` y build     |
| Gráfico con datos reales y pregunta           | SVG y tabla de distribución por área desde IndexedDB         |
| Semántica y teclado                           | `main`, `section`, formularios etiquetados, diálogos nativos |
| Carpeta identificada y LEEME con IA           | `frontend/personal/`; `LEEME.md`                             |
| Video o demostración en vivo                  | Guion anterior para la presentación del equipo               |

## Portabilidad y límites

Copiar **toda** la carpeta `backend/public/personal/` y servirla desde otra
carpeta HTTP en localhost o HTTPS. El HTML, los recursos y el service worker
usan rutas relativas. No abrir con `file://`, que no permite este service worker.
El navegador conserva los registros por origen (protocolo, dominio y puerto),
no por ruta: mover la carpeta dentro del mismo origen conserva la base; moverla
a otro origen o perfil comienza con otro almacén. Las fotografías no dependen
de la carpeta desde la que se seleccionaron. Los enlaces al resto de TrackEat
requieren su servidor; las operaciones del catálogo son independientes.

Antes de entregar, el equipo debe revisar sus cambios, registrar commits con
autoría real y subirlos al repositorio del equipo. El uso de IA está declarado
en el LEEME. La grabación/presentación y la publicación en Git son tareas de
entrega; este guion no las sustituye.
