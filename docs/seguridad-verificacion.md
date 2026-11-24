# Verificación de seguridad de TrackEat

Entrega de Práctica 6. Esta lista distingue las medidas implementadas en esta contribución de los pendientes de autenticación y servidor. No es una declaración de que las 14 medidas ya cumplen.

## Lista de las 14 medidas

| N.º | Medida | Estado de esta revisión | Dónde está y cómo demostrarla |
| --- | --- | --- | --- |
| 1 | Bcrypt o Argon2 | Verificada en pruebas aisladas | `RegisteredUserController`, cast `hashed` de `User`; `SecurityIntegrationReviewTest` comprueba el algoritmo y la verificación de una contraseña ficticia. No mostrar hashes de usuarios reales. |
| 2 | Error de autenticación genérico | Verificada para login | `LoginRequest`: mismo error para correo inexistente y contraseña incorrecta. Prueba automática compara ambos. Recuperación y registro requieren revisión de sus mensajes por el responsable de cuentas. |
| 3 | CAPTCHA en registro | Falta | El registro actual no incluye desafío ni verificación del servidor. Integrar y probar un desafío válido, inválido y ausente. |
| 4 | Límite de intentos | Parcial | Login limita cinco fallos por correo e IP; la sexta petición se comprueba en pruebas. Registro aún carece de límite específico. |
| 5 | Cookies HttpOnly, Secure y SameSite | Verificada en el túnel de prueba | La cookie `trackeat_session` llegó con HttpOnly, Secure y SameSite=lax en `/login` y `/api/sesion`. Evidencia sin valores de cookies en `docs/evidencias/seguridad-entorno.json`. Repetir en el dominio final. |
| 6 | Consultas parametrizadas | Verificada en los flujos revisados | Eloquent y Query Builder enlazan los valores. Los `whereRaw` de nombres usan `?`. El SQL de migración contiene identificadores constantes, sin entradas del usuario. Pruebas de integridad y solicitudes manipuladas en catálogo/pedidos. |
| 7 | Validación y salida codificada | Implementada y comprobada en catálogo/pedidos | Form Requests, `PedidoService` y plantillas Svelte sin HTML dinámico. Probar precio inválido, categoría inexistente y texto `<img ... onerror=...>` visible sin ejecución. |
| 8 | Control de acceso por recurso | Implementada y comprobada en catálogo/pedidos | `CatalogAdmin`, `ActiveTrackEatUser`, `OrderAccess` y canales privados. Cliente A abre su pedido; B cambia la URL y recibe 404. Rol no administrador recibe 403. |
| 9 | Subida de archivos segura | Implementada y comprobada | `ProductoRequest` y `CatalogoService`: formatos, bytes, tamaño, dimensiones, recodificación y UUID. Probar archivo PHP con extensión JPG, SVG, 2049 KB, seis archivos y orden manipulado. |
| 10 | Bitácora sin secretos ni datos personales | Implementada y comprobada en canal security | `SecurityAudit`, canal privado JSON y allowlist; prueba envía correo/contraseña/token ficticios y comprueba que no se registren. Revisar por separado logs de infraestructura y errores generales. |
| 11 | HTTPS y redirección | Verificada en el túnel de prueba | HTTPS respondió 200 y HTTP redirigió con 301 a HTTPS. Evidencia en `docs/evidencias/seguridad-entorno.json`; repetir si cambia el entorno. |
| 12 | Cinco cabeceras de seguridad | Falta integrar | En el túnel de prueba se confirmó la ausencia de HSTS, nosniff, X-Frame-Options, Referrer-Policy y Permissions-Policy en HTML. Usar la lista exacta del PDF de la práctica; candidatos en `seguridad-servidor.md`. Validar también respuestas de archivos estáticos. |
| 13 | CSP bloqueando un script | Falta | No se encontró política CSP de aplicación. El equipo debe integrar una política compatible con Svelte, Ziggy y Reverb y capturar un bloqueo real, además de probar que el sitio funciona. |
| 14 | Depuración apagada y secretos fuera de Git | Parcial | `presentacion.ps1` fuerza APP_DEBUG=false, `.env` está ignorado. `.env.example` es de desarrollo y Docker conserva credenciales predeterminadas de demostración. Revisar imagen, variables del entorno y cambios de claves antes de publicar. No se certificó todo el historial Git. |

## Evidencias y resultados

La ejecución del navegador guarda `output/playwright/catalogo-resultados.json` y capturas de menú offline, menú móvil y administración móvil. La suite del servidor contiene los casos en `CatalogoSecurityTest`, `SecurityIntegrationReviewTest` y las regresiones existentes. El registro final de esta entrega se añade en `docs/validacion-catalogo.md`.

Para la demostración de acceso se necesitan dos sesiones separadas, con clientes ficticios diferentes, y una sesión administradora. Ocultar botones no sustituye la autorización: mostrar también el estado de la respuesta en la pestaña Red.

Para bitácora, generar un rechazo con datos ficticios, abrir solo el archivo `security` y comprobar la plantilla `api/pedidos/{pedido}`, el rol y el motivo. No enseñar `.env`, cookies, tokens CSRF, conexiones de base ni los logs generales durante la exposición.

## Pendientes que impiden cerrar la Práctica 6

CAPTCHA y limitación del registro; conjunto exacto de cinco cabeceras; CSP con bloqueo demostrado; revisión de secretos y depuración en el entorno final. Cada responsable debe adjuntar su evidencia. Esta contribución no sustituye la integración ni la demostración de esas medidas.
