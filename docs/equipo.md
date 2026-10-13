# Iniciar TrackEat localmente

Se utilizan dos archivos `.bat` y un solo script auxiliar: `scripts/trackeat.ps1`.

1. Ejecutar **iniciar-base-datos.bat** para encender PostgreSQL local, configurar `.env`, aplicar migraciones y cargar los ejemplos.
2. Ejecutar **iniciar.bat** para abrir el sitio web con Laravel, Vite y colas.

No se pide IP, subred, usuario ni contrasena. Cada computadora tiene la misma estructura y datos de ejemplo iniciales; los cambios posteriores se guardan localmente. Los siguientes arranques conservan los datos y no duplican los ejemplos.

## Requisitos

Instalar PostgreSQL 15+, PHP 8.2+ y Node.js. Instalar las dependencias una vez:

```powershell
cd backend
composer install
cd ../frontend
pnpm install
```

Tambien puede usarse npm para el frontend. El script detecta PHP en el PATH, XAMPP o Laragon, y activa las extensiones disponibles para esa ejecucion. Para una instalacion personalizada de PostgreSQL, agregar su carpeta `bin` al PATH antes del primer arranque.

## Puertos y datos

El sitio intenta usar el puerto 8000 y Vite el 5173. Si alguno esta ocupado, elige automaticamente el siguiente disponible y muestra la direccion que abre en el navegador. No cierra otros proyectos ni otras bases de datos.

La base se llama `trackeat`, escucha solo en `127.0.0.1` y utiliza normalmente el puerto 55433. Si ese puerto esta ocupado en la primera creacion, elige otro disponible y lo recuerda. Se guarda en `backend/storage/app/private/local-postgres/data`. Reutiliza la base que ya exista en ese directorio, sin borrarla ni reinicializarla.

Las claves locales se generan automaticamente y se guardan en `connection.json` dentro del directorio privado. El `.env` anterior se respalda y se conserva la APP_KEY. Los datos y las credenciales estan excluidos de Git.

Los ejemplos incluyen 6 usuarios, 3 categorias, 4 productos y 4 pedidos, con partidas, estaciones, historial, pagos y cortes. Las cuentas son `cliente1@example.test`, `cliente2@example.test`, `administrador@example.test`, `cocinero@example.test`, `cajero@example.test` y `mesero@example.test`. Contrasena de demostracion: `TrackEatDemo!2026`.

## Detener o comprobar

Pulsar **Ctrl+C** en la consola del sitio para cerrar sus procesos. PostgreSQL sigue disponible hasta apagar la computadora o detenerlo con:

```powershell
.\iniciar-base-datos.bat -Stop
```

Otros comandos opcionales:

```powershell
.\iniciar.bat -CheckOnly
.\iniciar.bat -NoBrowser
.\iniciar.bat -BackendPort 8100 -FrontendPort 5178
```

Los logs estan en `backend/storage/logs` y el de PostgreSQL en `backend/storage/app/private/local-postgres/server.log`. Cuando lleguen nuevas migraciones, ejecutar otra vez el `.bat` de la base. Las pruebas automatizadas siguen usando una base dedicada `trackeat_test`.

Se comprobo la carga repetida sin duplicados y el arranque del sitio con un puerto de Vite ocupado, eligiendo automaticamente otro.
