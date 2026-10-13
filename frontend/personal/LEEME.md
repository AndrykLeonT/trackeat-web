# Práctica 4 · Catálogo de personal de TrackEat

**Esquema definido antes de implementar.** Base `trackeat-personal`, almacén
`personal`, clave `id` numérica autoincremental. Versión 1: `nombre` (texto),
`email` (texto), `telefono` (texto), `area` (Cocina, Servicio, Caja o
Administración), `puesto` (texto), `fechaIngreso` (fecha ISO `AAAA-MM-DD`) y
`foto` (Blob de imagen PNG, JPEG o WebP, máximo 5 MiB). Son siete campos además
de la clave; fecha y fotografía son obligatorias. Versión 2 añade `turno`
(Matutino, Vespertino o Mixto, Matutino para registros antiguos) y
`nombreBusqueda` (nombre normalizado sin acentos y en minúsculas). Un almacén
`metadatos` guarda la fecha y el número de registros migrados.

**Índices y migración.** `porArea` indexa `area` para consultar una estación.
En v2, `porNombre` indexa `nombreBusqueda`: el nombre se consulta frecuentemente
y permite buscar por prefijo sin recorrer todo el almacén; «angel» encuentra
«Angélica». `onupgradeneeded` aplica la migración en una transacción, conservando
claves, campos y fotografías. La demostración integrada copia un registro real
a una base temporal v1, la migra a v2 y compara campos, clave, cantidad y cada
byte de la foto; también consulta el nuevo índice. No altera la base principal.

**Caché y funcionamiento sin conexión.** El build genera un service worker con
HTML, JavaScript, CSS y logotipo locales. Se eligió cache-first porque este
catálogo no requiere consultas al servidor; una revisión del build renueva la
caché. El ámbito se limita al módulo. El aviso de disponibilidad comprueba
recursos y control de la página. Después de una primera visita con conexión se
puede abrir, listar, buscar, crear, editar y eliminar sin red. Las fotos son
binarios de IndexedDB mostrados mediante URLs temporales. Toda la carpeta
compilada es portable a otra ruta en localhost o HTTPS; los datos pertenecen
al origen y perfil del navegador, no se transfieren a otro dominio o puerto.

**Gráfico y accesibilidad.** «¿Cómo se distribuye el personal por área?» se
responde con barras SVG calculadas desde todos los registros de IndexedDB y
actualizadas tras cada operación. Hay tabla alternativa, formularios etiquetados,
marcado semántico, foco visible, mensajes anunciados y diálogos nativos;
la baja exige confirmación y permite cancelar con Escape.
La identidad visual y el menú se reutilizan desde `AppLayout`, `Sidebar`,
`Header`, `Footer` y los estilos compartidos de TrackEat.

**Ejecución y entrega.** Desde `frontend`: `pnpm build`; abrir
`http://localhost:8000/personal/` con Laravel iniciado, o `pnpm preview:personal`
para usar el catálogo por separado. La carpeta fuente es `frontend/personal/`;
la salida portable es `backend/public/personal/`. El guion de demostración y
la matriz de requisitos están en `DEMOSTRACION.md`.

**Uso de IA.** OpenAI Codex asistió en el esquema, IndexedDB, migración, interfaz
Svelte, gráfico, service worker, compilación, documentación y verificaciones.
El equipo debe revisar, explicar y depurar el código, registrar sus aportaciones
en Git y presentar la demostración.
