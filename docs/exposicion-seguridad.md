# La decisión sobre acceso a pedidos y privacidad de la bitácora

Material para una exposición de cinco minutos. El guion es para ensayar; durante la presentación se usan las tarjetas y una demostración con datos ficticios.

## Guion cronometrado

**0:00 a 0:40 — El problema.** TrackEat tiene clientes y personal del restaurante. Un cliente necesita seguir su pedido, pero no consultar el de otra persona. La dirección contiene un número y cambiarlo es fácil. La decisión más difícil fue definir qué revisar en el servidor y qué registrar cuando alguien intenta acceder a lo ajeno, sin guardar más información personal.

**0:40 a 1:35 — Los permisos.** Elegimos que cada cliente vea únicamente sus pedidos. Cocina, meseros, caja y administración conservan la consulta necesaria para operar este restaurante. No inventamos asignaciones por empleado porque el modelo actual no las tiene. La autorización se aplica antes de entregar el detalle, también en la API. No depende de ocultar enlaces. Para un cliente, un pedido ajeno responde 404, igual que uno inexistente; así la respuesta no confirma si el número pertenece a alguien.

**1:35 a 2:35 — Demostración.** En la primera sesión abrimos un pedido del cliente A y mostramos su resumen. En otra sesión iniciamos como cliente B y pegamos la misma dirección. La respuesta es 404. Mostramos en Red que la API también devuelve 404 y no contiene el resumen. Si probamos una pantalla administrativa con el cliente, responde 403. La diferencia es deliberada: una función prohibida por rol no necesita fingir que no existe.

**2:35 a 3:35 — La bitácora.** Necesitábamos saber si hubo rechazos, pero guardar toda la petición habría copiado correos, notas o contraseñas. Elegimos una lista cerrada de campos: fecha, evento, rol, resultado, motivo, plantilla de ruta, método, estado y un identificador aleatorio de solicitud. Mostramos un evento con datos ficticios. La ruta guarda llaves, como pedidos/{pedido}, y no el número real. No incluye usuario, IP, cuerpo del formulario ni tokens.

**3:35 a 4:25 — El costo de la decisión.** Reducir la información de la bitácora limita la atribución individual. Con estos eventos podemos contar y revisar rechazos, pero no reconstruir toda la actividad de una cuenta. Aceptamos esa limitación para esta entrega. El historial operativo de estados queda separado: sirve para saber cómo avanzó una comanda y tiene otras reglas. Tampoco una bitácora correcta vuelve seguros automáticamente los logs del servidor.

**4:25 a 5:00 — Qué comprobamos y qué falta.** Comprobamos los permisos con dos clientes, intentos directos a la API y pruebas automáticas. También verificamos archivos disfrazados y texto que debe mostrarse sin ejecutarse. La práctica completa sigue necesitando las medidas de cuentas y servidor que están señaladas en nuestra lista: no las damos por cumplidas sin evidencia. La lección fue definir permisos y datos necesarios antes de implementar la pantalla.

## Tarjetas para hablar sin leer

1. Problema: cambiar un número no debe mostrar el pedido de otra persona.
2. Decisión: dueño o personal autorizado; 404 para pedido ajeno, 403 para administración.
3. Demo: cliente A funciona; cliente B con la misma URL falla; comprobar también la API.
4. Bitácora: lista cerrada de campos; nunca contenido del formulario ni datos personales.
5. Costo: menos atribución individual; historial operativo separado; treinta días de eventos.
6. Cierre: evidencias reales y pendientes del equipo visibles.

## Preparar la demostración

Usar `trackeat_demo_test`, dos perfiles de navegador o una ventana incógnita adicional y las cuentas ficticias del proyecto. Crear un pedido nuevo, anotar su dirección y confirmar que pertenece al cliente A. Tener listas las dos pestañas, la pestaña Red y una copia depurada del archivo `security`.

No depender de que el número 1 pertenezca al cliente correcto. No mostrar `.env`, cookies, enlaces de demo firmados ni credenciales de conexión. Como respaldo, tener las capturas y los resultados de pruebas, indicando que son evidencia grabada y no una demostración en vivo.

Ensayar una vez con cronómetro. La demostración tiene un minuto; si falla la red, explicar el control y mostrar el resultado guardado sin afirmar que se comprobó en ese momento. Evitar recorrer todas las pantallas: el tema es una decisión concreta.

## Preguntas de ensayo

- ¿Por qué usar 404? Para no confirmar al cliente que existe un pedido ajeno.
- ¿Por qué el personal puede verlo? Es la regla operativa elegida para un restaurante. Una asignación por estación requeriría un modelo adicional y otra revisión de permisos.
- ¿Un UUID sustituye permisos? No; un identificador difícil de adivinar no autoriza acceso.
- ¿Por qué no guardar el usuario en el log? Para respetar el alcance de privacidad acordado. El costo es menor atribución individual.
- ¿Se pueden pedir productos offline? Se puede preparar el carrito, pero la confirmación exige conexión y validación del servidor.
- ¿Qué pasa si cambia el precio? El servidor rechaza el precio anterior; el cliente revisa el nuevo total antes de volver a enviar.
- ¿Ya están las 14 medidas? No. Mostrar la lista actualizada y las evidencias de cada responsable.
