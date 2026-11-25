# Práctica 6 · Lista de verificación de las 14 medidas

Ejecución del 25 de noviembre de 2026 contra TrackEat en Docker (`https://localhost:8443`), con cuentas `@example.test`. Las evidencias completas están en [Evidencias-Practica-6.pdf](Evidencias-Practica-6.pdf). La columna **Figura** indica dónde está cada una en el PDF.

**Resultado:** 12 medidas cumplen y 2 son parciales (2 y 14). Las dos parciales están documentadas con su mitigación.

| # | Medida | Estado | Implementación | Cómo se comprueba en vivo | Figura |
| --- | --- | --- | --- | --- | --- |
| 1 | Contraseñas derivadas (bcrypt) | ✅ Cumple | `User.php:53` cast `hashed`, `BCRYPT_ROUNDS=12` | `psql`: el hash empieza con `$2y$12$` y mide 60 | 1 |
| 2 | Error genérico en el login | ⚠️ Parcial | `LoginRequest.php:59` usa un solo mensaje `auth.failed` | Login con un correo inexistente y con uno real con clave mala: el mensaje es el mismo. El registro sí dice «The email has already been taken.» | 2, 3, 4 |
| 3 | Captcha y campo trampa | ✅ Cumple | `Rules/Turnstile.php`, campo `website` en el registro | Login sin token → «Confirma que no eres un robot.» Registro con la trampa llena → rechazado | 5, 6, 7 |
| 4 | Límite de intentos | ✅ Cumple | `LoginRequest.php:56-82` (5 por correo+IP), limitador `registro` (5/min por IP) | Al 6.º intento: «Too many login attempts…» y «Demasiados intentos de registro…» con `Retry-After` | 8, 9 |
| 5 | Cookies de sesión | ✅ Cumple | `config/session.php:172,185,202` | `Set-Cookie: trackeat_session` con `secure; httponly; samesite=lax` | 10 |
| 6 | Consultas parametrizadas | ✅ Cumple | Eloquent/PDO; `UserController.php:27-30` | Buscar `' OR 1=1 --` en Usuarios → «No hay cuentas que coincidan.» | 11 |
| 7 | Validación y salida codificada | ✅ Cumple | Form Requests (`ProductoRequest.php:28-34`) y el escape de Svelte | La categoría `<img src=x onerror=alert(1)>` se ve como texto. Precio -5 → «must be greater than 0» | 12, 13 |
| 8 | Control de acceso (rol y objeto) | ✅ Cumple | `RequireRole` en `routes/admin.php:15`; `PedidoController.php:79` | `cliente2` → `/pedidos/3` da 404. `/admin/usuarios` y `/caja` dan 403 | 14, 15 |
| 9 | Subida segura | ✅ Cumple | `ProductoRequest.php:34`; `CatalogoService.php:71-89` (WebP, UUID, disco privado) | Un PHP renombrado a `.jpg` → «The imagenes.0 field must be an image.» | 16 |
| 10 | Bitácora sin datos personales | ✅ Cumple | `Middleware/SecurityAudit.php`, canal `security` (JSON) | Tras los rechazos, `storage/logs/security-*.jsonl` no contiene correos, IP ni tokens | 17 |
| 11 | HTTPS con redirección 301 | ✅ Cumple | `nginx.conf.template:68-88` | `curl -I http://127.0.0.1:8080/login` → 301 a https. `openssl` → TLS 1.3 | 18, 19 |
| 12 | Cinco cabeceras | ✅ Cumple | `nginx.conf.template:94-102` | La respuesta de `/login` trae CSP, HSTS, nosniff, Referrer-Policy y Permissions-Policy | 20 |
| 13 | La CSP bloquea un script | ✅ Cumple | CSP sin `'unsafe-inline'` | Un `<script>` en línea inyectado queda bloqueado (`script-src-elem`) y `window.__pruebaCsp` sigue `undefined` | 21 |
| 14 | Debug apagado y secretos locales | ⚠️ Parcial | `docker/nodo/compose.yml:20` `APP_DEBUG: "false"`, `.gitignore` | `config('app.debug')` = false. `git check-ignore` cubre los `.env` y los `*.pem` | 22, 23 |

## Medidas parciales

- **M2 · Registro.** El formulario de registro revela que un correo ya existe. Para enumerar cuentas así habría que pasar primero el captcha (M3) y respetar el límite de 5 registros por minuto por IP (M4), así que no se puede automatizar. Los mensajes de autenticación siguen en inglés.
- **M14 · Entorno.** Los nodos de Docker tienen debug apagado, pero heredan `APP_ENV=local`. Además, el `backend/.env` de desarrollo tiene `APP_DEBUG=true`. Para producción hay que poner `APP_ENV=production` en `docker/nodo/compose.yml`.

## Pruebas automatizadas (Anexo A del PDF, figuras 24 y 25)

Las pruebas de seguridad pasan: `CaptchaTest`, `RegistroSeguroTest`, `CatalogoSecurityTest`, `CajaModuloTest`, `SecurityIntegrationReviewTest` y `SaludTest`. La suite completa también pasa, sin fallas, con este comando:

```powershell
docker exec -e DB_DATABASE=trackeat_test -e APP_ENV=testing -e MAIL_MAILER=array trackeat-nodo1 php artisan test
```

Hay que pasar `MAIL_MAILER=array` porque el contenedor define `MAIL_MAILER=log` y esa variable tiene prioridad sobre `phpunit.xml`. Sin ella fallan las 3 pruebas de `PasswordResetTest`.
