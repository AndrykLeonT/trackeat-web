# Punto 6 · Tarea 4

El documento [Firma electrónica, certificados y HTTPS](../Tarea_4_firma_certificados_HTTPS.pdf) contiene cinco páginas tamaño carta, con texto principal Arial 12 y todo el texto en negro, conforme al ajuste solicitado.

Incluye cifrado, firma, certificados y cadena de confianza, DV, OV, EV y wildcard, e.firma y NOM-151, el procedimiento de Certbot, una configuración comentada de Nginx, HSTS y la decisión de terminación TLS de TrackEat. Las fuentes técnicas y oficiales están citadas en el texto y enlazadas al final.

Se verificaron el número de páginas con `pdfinfo`, el texto y los enlaces con `pypdf` y la presentación visual de las cinco páginas mediante `pdftoppm`. El procedimiento de CA pública describe un despliegue futuro con dominio controlado por el equipo. El entorno actual usa la CA local descrita en la [evidencia del Punto 2](02-nginx-https.md).
