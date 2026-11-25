# Punto 4 · Prueba de bloqueo de un script inline

Verificación del 25 de noviembre de 2026 en Firefox con la CA local de TrackEat y el balanceador Nginx activo. El resultado cubre la comprobación funcional de CSP de la Práctica 6.

## Cómo funciona

La prueba obtiene la cabecera `Content-Security-Policy` de la respuesta HTTPS de Nginx a `/salud`. Playwright carga dos veces el mismo HTML de prueba en un navegador aislado. La primera respuesta de prueba sirve de control. La segunda lleva la política exacta obtenida de Nginx. El HTML de prueba y su script externo existen solo dentro de Playwright. Se comprobó que, fuera de la prueba, el servidor devuelve HTTP 404 para esa URL. [CSP del W3C](https://www.w3.org/TR/CSP3/#csp-header) · [Respuestas de prueba con Playwright](https://playwright.dev/docs/api/class-route#route-fulfill)

El script inline solo cambia un texto de la página de prueba. El script externo del mismo origen marca su propia ejecución y muestra el evento `securitypolicyviolation` del navegador. El evento se comprueba en modo `enforce`. [Evento de violación de CSP](https://www.w3.org/TR/CSP3/#eventdef-globaleventhandlers-securitypolicyviolation)

## Reproducir en vivo

En la laptop Windows, con las dependencias de `frontend` instaladas, Edge disponible, Docker corriendo y la CA del Punto 2 ya instalada, ejecutar desde la raíz del repositorio:

```powershell
pnpm --dir frontend verify:csp --visible
```

La terminal muestra el resultado de ambos casos. La pestaña queda en el caso protegido para que se pueda enseñar el resultado y revisar la consola de F12. Cierra la pestaña para terminar. El comando sin `--visible` hace la misma comprobación y termina automáticamente.

En macOS, para usar Firefox con la CA solo en un perfil temporal, se necesitan el `certutil` de NSS y el Firefox de Playwright. El siguiente comando es el que se ejecutó para la evidencia:

```bash
TRACKEAT_CSP_CA="$PWD/docker/infra/tls-root.pem" pnpm --dir frontend verify:csp
```

## Evidencia observada

| Caso | Script externo autorizado | Script inline de prueba | Evento |
| --- | --- | --- | --- |
| Control sin CSP | Ejecutado | Ejecutado | Ninguno |
| CSP activa de Nginx | Ejecutado | Sin ejecutar | `script-src-elem bloqueó inline` |

Además de verificar los textos, la prueba compara la cabecera de la página protegida con la obtenida de Nginx. La captura reproducible queda en `output/playwright/csp-trackeat.png`. La ejecución en Windows sigue disponible con el comando de demostración.
