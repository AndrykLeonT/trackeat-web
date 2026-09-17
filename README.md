# TrackEat

Sistema web de gestión de pedidos y comandas en tiempo real para restaurantes y food trucks.

**Instituto Tecnológico de La Paz** — Ingeniería en Sistemas Computacionales
Materia: Aplicaciones Web II · Docente: Ing. Osmar Alejandro Liera Gómez
Grupo: 8-H · Turno: Vespertino

---

## 1. Objetivo del sistema

TrackEat busca facilitar la gestión de pedidos en restaurantes y food trucks. Permite que los
clientes consulten el menú, seleccionen productos y realicen pedidos, mientras el personal recibe,
genera y gestiona las comandas desde un solo lugar. El cliente además puede consultar el estado de
su pedido, lo que evita confusiones y reduce el tiempo que el personal dedica a tomar y comunicar
órdenes.

## 2. Usuarios del sistema

| Tipo de usuario | Qué puede hacer |
| --- | --- |
| Visitante sin cuenta | Consultar el menú, buscar productos, filtrar por categorías y ver la información de los productos. |
| Cliente | Realizar pedidos, consultar sus pedidos, revisar el estado de un pedido y consultar su historial. |
| Cocinero | Consultar las comandas recibidas, ver los productos de cada pedido y actualizar su estado conforme se preparan. |
| Mesero / Cajero | Crear pedidos para los clientes, consultar comandas, actualizar el estado de los pedidos y ver los pendientes. |
| Administrador | Gestionar productos, categorías, usuarios y pedidos, y consultar información general del negocio. |

## 3. Alcance

**Sí incluye:** catálogo digital de productos con fotografías, organización por categorías, búsqueda
y filtrado, registro e inicio de sesión, gestión de tipos de usuario, carrito de pedidos,
confirmación de pedidos con identificador, sistema de comandas, actualización y consulta del estado
del pedido, historial del cliente, panel administrativo con gráficos básicos, actualización en
tiempo real, consulta básica del menú sin conexión y protección CAPTCHA en formularios públicos.

**No incluye:** pagos en línea, facturación electrónica, app móvil nativa, integración con Uber Eats
o DiDi Food, repartidores propios, geolocalización o GPS, reservación de mesas, programa de puntos,
impresoras de tickets, terminales bancarias, recomendaciones con inteligencia artificial ni
integración con sistemas contables externos.

---

## 4. Qué se encuentra en la primera página

La primera página es la **landing page pública de TrackEat**, disponible en la raíz del sitio
(`http://localhost:8000/`). Presenta el producto a quien llega por primera vez y está dividida en
las siguientes secciones:

### Encabezado
Logotipo de TrackEat y un menú de navegación con enlaces internos a las tres secciones principales
de la página: *Qué es TrackEat*, *Por qué usar TrackEat* y *Acerca de nosotros*.

### Hero (portada)
Mensaje principal del sistema — "Pedidos y seguimiento en tiempo real para restaurantes y food
trucks" — acompañado de una descripción breve, un botón **Conocer más** y una ilustración de un
chef. Esta sección se anima automáticamente al cargar la página.

### ¿Qué es TrackEat?
Explicación del sistema y tres tarjetas con las funcionalidades centrales:

1. **Menú Digital Interactivo** — la carta completa con fotos, descripciones y precios actualizados,
   accesible desde cualquier dispositivo móvil vía QR o terminal.
2. **Gestión Ágil de Comandas** — toma y despacho de órdenes sin los errores del comandeo manual;
   las notas especiales llegan sin alteraciones a cocina.
3. **Monitoreo en Tiempo Real** — estados visibles de cada pedido: *Recibido*, *En preparación* y
   *Listo para entrega*.

### ¿Por qué usar TrackEat?
Tres beneficios clave presentados con cifras:

- **−35 %** de optimización en tiempos de cocina mediante pantallas KDS sincronizadas.
- **0 %** de comisiones ocultas: tarifa transparente, sin porcentajes por comensal o ticket.
- **< 15 min** para arrancar, sin hardware costoso: funciona en tabletas, smartphones y
  computadoras estándar.

### Acerca de nosotros
Presentación del equipo desarrollador. Cada integrante aparece en una tarjeta con su nombre, rol y
correo de contacto; al pasar el cursor, la tarjeta gira en 3D y muestra la fotografía de la persona.

### Llamado a la acción y pie de página
Cierre con los botones **Contactar al equipo** y **Ver funcionalidades**, seguido del pie de página
con el logotipo, una descripción del proyecto y los correos de contacto de los cuatro integrantes.

### Detalles de interacción y accesibilidad
- Las secciones aparecen con una animación de entrada conforme se hace scroll hasta ellas
  (`IntersectionObserver`).
- Las tarjetas de funcionalidades se elevan al pasar el cursor y las del equipo giran en 3D.
- Los títulos son enfocables por teclado y muestran un anillo de foco visible.
- Todas las animaciones se desactivan cuando el sistema operativo tiene activada la preferencia
  *reducir movimiento* (`prefers-reduced-motion`).

---

## 5. Tecnologías

| Capa | Tecnología |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12 |
| Puente servidor–cliente | Inertia.js 2 |
| Frontend | React 19, TypeScript |
| Estilos | Tailwind CSS 4 |
| Empaquetador | Vite 6 |
| Rutas en el cliente | Ziggy |
| Base de datos | SQLite |
| Gestores de paquetes | Composer y pnpm |

## 7. Estructura del repositorio

```
track-eat/
├── backend/                  Aplicación Laravel
│   ├── routes/web.php        Ruta raíz (/) que entrega la landing
│   ├── routes/auth.php       Rutas de registro, login y contraseñas
│   ├── app/Http/             Controladores y middleware de Inertia
│   ├── resources/views/      Plantilla raíz app.blade.php
│   └── public/               Punto de entrada y assets compilados
└── frontend/                 Aplicación React
    ├── src/app.tsx           Arranque de Inertia en el navegador
    ├── src/pages/public/     landing.tsx (la primera página)
    ├── src/pages/auth/       Pantallas de autenticación
    ├── src/components/       Componentes de interfaz reutilizables
    ├── src/assets/images/    Logotipo, ilustraciones y fotos del equipo
    └── vite.config.js        Configuración de Vite apuntando al backend
```

## 8. Cómo ejecutar el proyecto

### Requisitos

PHP 8.2 o superior, Composer, Node.js y pnpm.

### Instalación

```bash
# Backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate

# Frontend
cd ../frontend
pnpm install
```

### Ejecución en desarrollo

Se necesitan dos procesos: el servidor de Laravel y el de Vite.

```bash
# Terminal 1
cd frontend && pnpm dev

# Terminal 2
cd backend && php artisan serve
```

O bien, ambos de una sola vez:

```bash
cd backend && composer dev
```

El sitio queda disponible en **http://localhost:8000**, donde se muestra directamente la landing
page.

> **Nota:** si se ejecuta únicamente `php artisan serve` sin el servidor de Vite, Laravel sirve los
> archivos ya compilados. Para verlos actualizados hay que generar el build con
> `cd frontend && pnpm build`.

## 9. Equipo

| Integrante | No. de control | Rol |
| --- | --- | --- |
| Angélica Menchaca Rueda | 22310573 | Desarrollo Frontend |
| Andryk Manuel León Tapia | 22310560 | Desarrollo Backend |
| Daniel Alexander Estrada Cosio | 22310572 | Base de Datos y Cloud |
| Carlos Omar Celis Calzada | 22310531 | QA y Arquitectura |

**Repositorio:** https://github.com/AndrykLeonT/trackeat-web.git
