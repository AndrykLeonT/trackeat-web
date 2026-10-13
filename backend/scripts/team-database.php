<?php

use App\Support\LocalEnvironment;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Contratos por JSON en stdin: ninguna contraseña aparece como argumento del proceso.
require __DIR__.'/../vendor/autoload.php';

function envValue(string $value): string
{
    if (strpbrk($value, "\r\n\0") !== false) {
        throw new RuntimeException('Los valores de conexion no pueden contener saltos de linea.');
    }

    return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
}

try {
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    if (($input['mode'] ?? '') === 'configure') {
        $root = dirname(__DIR__);
        $host = $input['host'] ?? '';
        $database = $input['database'] ?? '';
        $username = $input['username'] ?? '';
        $port = filter_var($input['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $database)
            || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $username) || ! $port || empty($input['password'])) {
            throw new RuntimeException('Se requiere IPv4, puerto valido, base, usuario y contrasena.');
        }
        // Validar antes de tocar la configuracion actual.
        try {
            $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$database;connect_timeout=5;sslmode=prefer", $username, $input['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->query('SELECT 1')->fetchColumn();
        } catch (PDOException $error) {
            throw new RuntimeException('No se pudo conectar a PostgreSQL local. Ejecuta iniciar-base-datos.bat y revisa que el puerto este disponible.');
        }
        $path = $input['env_file'] ?? null;
        if (! $path) {
            $path = $root.'/.env';
        } else {
            // Ruta alternativa solo para verificaciones aisladas dentro del proyecto.
            $directory = realpath(dirname($path));
            $testing = realpath($root.'/storage/framework/testing');
            if (! $directory || $directory !== $testing || ! preg_match('/^[a-zA-Z0-9_-]+\.env$/D', basename($path))) {
                throw new RuntimeException('La ruta alternativa debe ser un archivo *.env dentro de storage/framework/testing.');
            }
            $path = $directory.'/'.basename($path);
        }
        $existing = is_file($path) ? file_get_contents($path) : file_get_contents($root.'/.env.example');
        $values = ['DB_CONNECTION' => 'pgsql', 'DB_HOST' => $host, 'DB_PORT' => (string) $port, 'DB_DATABASE' => $database,
            'DB_USERNAME' => $username, 'DB_PASSWORD' => $input['password'], 'DB_URL' => '', 'APP_ENV' => 'local', 'APP_TIMEZONE' => 'UTC'];
        $newKey = LocalEnvironment::applicationKey($existing);
        if ($newKey !== null) {
            $values['APP_KEY'] = $newKey;
        }
        $updated = $existing;
        foreach ($values as $key => $value) {
            $line = $key.'='.envValue($value);
            if (preg_match('/^'.preg_quote($key, '/').'\s*=.*$/m', $updated)) {
                $updated = preg_replace_callback('/^'.preg_quote($key, '/').'\s*=.*$/m', fn () => $line, $updated);
            } else {
                $updated = rtrim($updated)."\n".$line."\n";
            }
        }
        if (is_file($path)) {
            $backup = $path.'.backup-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
            if (! copy($path, $backup)) {
                throw new RuntimeException('No se pudo respaldar el archivo de configuracion.');
            }
        }
        $temp = $path.'.tmp-'.bin2hex(random_bytes(6));
        try {
            if (file_put_contents($temp, $updated, LOCK_EX) === false || ! rename($temp, $path)) {
                throw new RuntimeException('No se pudo guardar la configuracion.');
            }
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
        echo "Conexion PostgreSQL comprobada y configuracion guardada.\n";
    } elseif (in_array($input['mode'] ?? '', ['check', 'configuration'], true)) {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $connection = DB::connection();
        if ($input['mode'] === 'configuration') {
            echo json_encode(['connection' => $connection->getDriverName(), 'host' => $connection->getConfig('host'), 'database' => $connection->getDatabaseName()], JSON_THROW_ON_ERROR)."\n";
            exit(0);
        }
        if ($connection->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Ejecuta iniciar-base-datos.bat para configurar PostgreSQL.');
        }
        try {
            $connection->select('SELECT 1');
            if (! Schema::hasTable('pedidos')) {
                throw new RuntimeException('La base no tiene el esquema de TrackEat. Ejecuta iniciar-base-datos.bat.');
            }
        } catch (QueryException $error) {
            throw new RuntimeException('PostgreSQL local no esta disponible. Ejecuta primero iniciar-base-datos.bat.');
        }
        echo "Base PostgreSQL disponible.\n";
    } else {
        throw new RuntimeException('Operacion desconocida.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error instanceof RuntimeException && ! $error instanceof PDOException ? $error->getMessage()."\n" : "No se pudo completar la configuracion de base de datos.\n");
    exit(1);
}
