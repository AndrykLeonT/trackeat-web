# Práctica 4 · Catálogo de personal de TrackEat

**Esquema.** Base IndexedDB `trackeat-personal`, almacén `personal`, clave `id`
numérica autoincremental. Versión 1: `nombre`, `email`, `telefono`, `area`
(Cocina, Servicio, Caja o Administración), `puesto`, `fechaIngreso` (ISO) y
`foto` (Blob PNG/JPEG/WebP de hasta 5 MiB). Fecha y fotografía son obligatorias.
La versión 2 agrega `turno` (Matutino, Vespertino o Mixto) y `nombreBusqueda`
(normalizado sin acentos y en minúsculas). `metadatos` registra fecha y cantidad
migrada. El esquema está definido en `db.js`.

**Índices y migración.** `porArea` consulta una estación; `porNombre` permite
buscar prefijos frecuentes mediante `.index('porNombre')` e `IDBKeyRange`, sin
leer todo el almacén: «angel» encuentra «Angélica». `onupgradeneeded` aplica las
versiones dentro de una transacción y conserva claves, campos y Blobs. En una
instalación nueva ejecuta v1 y v2; desde v1 sólo aplica v2 y asigna turno Matutino.
La demostración integrada migra una copia temporal de un registro real y
compara clave, campos, todos los bytes de la foto y la consulta por índice.
No modifica la base principal. Una v3 añadiría un bloque `oldVersion < 3`,
sin borrar registros ni repetir cambios ya aplicados.

**Caché.** El service worker precarga HTML, JavaScript, CSS y logotipo locales.
Se eligió cache-first: este módulo no consulta el servidor. Una revisión por
contenido renueva recursos; su ámbito `/personal/` no afecta al menú del
proyecto. Tras una primera visita con red permite recargar, consultar, buscar,
crear, editar y eliminar offline. Las fotos se leen de IndexedDB con URLs
temporales; no dependen del archivo original. Los datos pertenecen al origen
y perfil del navegador, no viajan con la carpeta ni entre dominios.

**Gráfico y accesibilidad.** «¿Cómo se distribuye el personal por área?» se
responde con barras SVG calculadas desde todos los registros y actualizadas
con cada operación. Incluye tabla alternativa, formularios etiquetados,
foco visible, mensajes anunciados y baja con confirmación cancelable mediante
Escape. Comparte diseño y navegación con TrackEat.

**Ejecución.** `pnpm build` desde `frontend`; abrir `/personal/` con Laravel,
o `pnpm preview:personal` sin PHP. Fuentes en `frontend/personal/`; salida
portable en `backend/public/personal/`. Ver `DEMOSTRACION.md` y `VERIFICACION.md`.

**Uso de IA.** OpenAI Codex asistió en esquema, migración, interfaz, gráfico,
service worker, documentación y pruebas. El equipo revisa, explica y depura el
código y registra sus aportaciones reales en Git.
