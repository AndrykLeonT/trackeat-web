# Punto 3 · Cinco cabeceras de seguridad en Nginx

Verificación del 25 de noviembre de 2026 en Docker Desktop. Nginx añade las cinco cabeceras en el servidor HTTPS, con `always` para incluir redirecciones, errores y recursos estáticos. El diagnóstico `X-Nodo` se mantiene en el mismo nivel para conservar la herencia de cabeceras. [Módulo de cabeceras de Nginx](https://nginx.org/en/docs/http/ngx_http_headers_module.html#add_header)

| Cabecera | Valor y propósito en TrackEat |
| --- | --- |
| Content-Security-Policy | Parte de `default-src 'none'`. Autoriza JavaScript del mismo origen y el CDN de Turnstile, su iframe, el origen WSS de `URL_PUBLICA`, la fuente Bunny Fonts, los service workers propios y las imágenes locales, `data:` y `blob:` necesarias para galerías y vistas previas. Limita formularios y URL base al mismo origen, y deshabilita el uso de la app dentro de iframes y el contenido `object`. |
| Strict-Transport-Security | `max-age=300`, únicamente por HTTPS. Se eligieron cinco minutos para el despliegue local de demo. |
| X-Content-Type-Options | `nosniff` para respetar el tipo de contenido que declara el servidor. |
| Referrer-Policy | `strict-origin-when-cross-origin` como valor general. Se conserva `no-referrer` en la pantalla de recuperación para proteger la URL con el token. |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()`. TrackEat selecciona imágenes mediante un campo de archivo. |

Ziggy entrega el listado de rutas como JSON y Vite empaqueta la función `route()`. El indicador de navegación de Inertia toma su estilo del CSS compilado. La CSP se aplica sin `unsafe-inline` ni `unsafe-eval`. [Ziggy y CSP](https://github.com/tighten/ziggy#using-routes-with-a-content-security-policy) · [Turnstile y CSP](https://developers.cloudflare.com/turnstile/reference/content-security-policy/)

## Reproducir la verificación

Con el certificado del Punto 2 y Docker arriba, ejecutar desde la raíz del repositorio:

```bash
docker compose -f docker/compose.yml exec -T balanceador nginx -t
curl --cacert docker/infra/tls-root.pem -sSI https://localhost:8443/login
curl -sSI 'http://127.0.0.1:8080/login?comprobacion=1'
```

En Windows, con la CA de mkcert instalada según el Punto 2, se puede usar `curl.exe -sSI https://localhost:8443/login`. También se ven las cinco cabeceras en **F12 → Red → login → Encabezados**. La petición HTTP debe devolver `301` con destino a `https://localhost:8443/login?comprobacion=1`. Para repetir esa prueba en un navegador, iniciar por la IP `127.0.0.1`. Si el navegador ya memorizó HSTS para `localhost`, puede intentar TLS directamente en el puerto HTTP 8080 porque HSTS conserva los puertos explícitos distintos de 80. [RFC 6797, sección 8.3](https://www.rfc-editor.org/rfc/rfc6797.html#section-8.3)

## Resultados observados

- `nginx -t` pasó en el contenedor activo. Las cinco cabeceras se verificaron en respuestas 200, 204, 302 y 403, y en los service workers de Menú Digital y Personal. La recuperación respondió con un solo `Referrer-Policy: no-referrer`.
- El 301 de HTTP conservó la consulta y respondió sin HSTS.
- En Firefox, con la CA instalada únicamente en el perfil temporal de prueba, cargaron el login y el script de Turnstile. Funcionó la navegación al registro y se activaron los service workers de Menú Digital y Personal. Firefox recibió el saludo de Reverb mediante WSS con la CSP aplicada, sin violaciones observadas en los flujos comprobados.
- El frontend compiló con `pnpm build`. Las 186 pruebas de Laravel pasaron sobre PostgreSQL aislado, con 1337 aserciones.

La prueba con un script no autorizado corresponde al Punto 4.
