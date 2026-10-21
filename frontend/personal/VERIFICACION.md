# Verificación de la Práctica 4

**Reverificación del 20 de octubre de 2026:** el recorrido de entrega se repitió
con Chrome y perfiles temporales: creación nueva v2, recarga offline, alta con
Blob, búsqueda, demostración de conservación v1 → v2, edición y baja confirmada.
Sin errores JavaScript. El informe conjunto está en
[verificacion-entrega.md](../../docs/verificacion-entrega.md).

La matriz siguiente conserva como antecedente la verificación más extensa del
13 de octubre; no representa una nueva ejecución de todos esos casos.

Ejecutada el **13 de octubre de 2026**, con Chrome real, perfiles temporales,
Playwright y la red desactivada mediante el contexto del navegador. Los datos
de prueba no se cargan automáticamente en el catálogo del usuario.

**Resultado: 13 comprobaciones aprobadas, sin errores JavaScript.**

| Comprobación                        | Resultado                                                                            |
| ----------------------------------- | ------------------------------------------------------------------------------------ |
| Base v1 con registro existente → v2 | Misma clave, campos y todos los bytes de la foto; turno e índice nuevos              |
| Demostración integrada de migración | Base temporal aislada; limpieza posterior; catálogo real conservado                  |
| Recarga sin conexión                | Abre, lista y muestra fotografías desde IndexedDB                                    |
| Alta sin conexión                   | Guarda todos los campos y una fotografía binaria                                     |
| Consulta y búsqueda                 | Ficha completa; prefijo sin acentos; filtro por área; gráfico sin filtrar            |
| Modificación sin conexión           | Área, turno y fotografía reemplazados; persisten después de recargar                 |
| Baja confirmada                     | Escape cancela; Cancelar recibe el foco; confirmación elimina y actualiza el gráfico |
| Archivo de foto inválido            | Se rechaza sin crear un registro incompleto                                          |
| Vista móvil y semántica             | Sin desbordamiento a 390 px; campos etiquetados; SVG descrito y tabla alternativa    |
| Reconexión                          | Conserva los registros y no genera errores JavaScript                                |
| Ruta `/personal/` servida por PHP   | Carga y permite guardar un integrante                                                |
| Copia física en otra carpeta        | Abre sin red y conserva la fotografía después de retirar su archivo original         |
| Alta offline en la copia            | Visible en la ruta original del mismo origen; cachés separadas por ámbito            |

También pasaron la compilación completa (`pnpm build`), ESLint y Prettier de
los archivos del módulo y la barra lateral, `git diff --check`, la sintaxis PHP
de las rutas y el análisis de sintaxis del script PowerShell. Laravel reconoce
la ruta nombrada `personal`.

La compilación conserva dos avisos previos de Svelte en
`src/pages/auth/reset-password.svelte`, relacionados con los valores iniciales
de `token` y `email`; no impiden compilar el proyecto.

**Revisión de identidad visual:** Personal reutiliza `AppLayout`, `Sidebar`,
`Header` y `Footer`, junto con los tokens de colores, tipografía e iconos de
las otras páginas. Se comprobó la barra de 256 px con sus mismas secciones y
el elemento Personal activo, además del menú móvil con foco dentro del diálogo,
cierre por Escape y por el fondo. Las operaciones CRUD, búsquedas y el gráfico
se verificaron otra vez sin red y sin errores JavaScript.

Para repetir la verificación manual y presentar la práctica, seguir
[DEMOSTRACION.md](DEMOSTRACION.md). La presentación o grabación y la subida de
los cambios al repositorio del equipo quedan como parte de la entrega.
