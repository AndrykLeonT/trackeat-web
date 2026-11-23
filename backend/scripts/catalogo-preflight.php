<?php

use App\Support\CatalogName;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Read-only by default. --apply installs only this module's new, transactional migration.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if ($connection->getDriverName() !== 'pgsql') {
    throw new RuntimeException('Se requiere PostgreSQL.');
}
$tables = ['categorias', 'productos', 'imagenes_producto', 'pedidos', 'items_pedido'];
$counts = fn () => array_combine($tables, array_map(fn ($table) => DB::table($table)->count(), $tables));
$duplicates = [];
foreach (['categorias', 'productos'] as $table) {
    $seen = [];
    foreach (DB::table($table)->get() as $row) {
        $key = ($table === 'productos' ? $row->id_categoria.':' : '').CatalogName::key($row->nombre);
        if (isset($seen[$key])) {
            $duplicates[] = ['tabla' => $table, 'ids' => [$seen[$key], $row->id]];
        }
        $seen[$key] = $row->id;
    }
}
$before = $counts();
echo json_encode(['database' => $connection->getDatabaseName(), 'counts' => $before, 'duplicates' => $duplicates], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
if ($duplicates) {
    exit(2);
}
if (in_array('--apply', $argv, true)) {
    if (! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
        throw new RuntimeException('La aplicación automática está limitada a la base local.');
    }
    Artisan::call('migrate', ['--path' => 'database/migrations/2026_11_23_000001_catalog_name_uniqueness.php', '--force' => true]);
    echo Artisan::output();
    if ($counts() !== $before) {
        throw new RuntimeException('La cantidad de registros cambió durante la comprobación.');
    }
    echo 'Migración aplicada; cantidades de registros conservadas.'.PHP_EOL;
}
