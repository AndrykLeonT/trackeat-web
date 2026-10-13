<?php

use App\Models\User;
use App\Services\CajaService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Proceso independiente para probar bloqueos reales de PostgreSQL.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_ends_with(config('database.connections.pgsql.database'), '_test')) {
    throw new RuntimeException('Este proceso solo opera sobre una base de pruebas.');
}
DB::select("SELECT set_config('application_name', ?, false)", [$argv[1]]);
$actor = User::findOrFail((int) $argv[3]);
$service = app(CajaService::class);
try {
    if ($argv[2] === 'cobrar') {
        $service->cobrar($actor, (int) $argv[4], (int) $argv[5], 'efectivo', $argv[6]);
    } else {
        $service->cerrar($actor, (int) $argv[5], $argv[6], '0', '0');
    }
    echo 'ok';
} catch (ValidationException $error) {
    echo 'rejected';
}
