<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

// Módulo 2 · Pedidos. Como CajaConcurrencyTest: procesos reales contra PostgreSQL, sin la transacción de prueba.
class PedidosConcurrencyTest extends TestCase
{
    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->assertExitCode(0);
        $this->seed(DemoSeeder::class);
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($this->workers as $worker) {
            $worker->stop();
        }
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    private function worker(int $pedidos, float $arranque): Process
    {
        $config = config('database.connections.pgsql');
        $command = [PHP_BINARY, '-d', 'extension_dir='.ini_get('extension_dir')];
        $iniFiles = array_filter([php_ini_loaded_file(), ...preg_split('/,\s*/', (string) php_ini_scanned_files())]);
        $contents = implode("\n", array_map(fn ($file) => (string) file_get_contents(trim($file)), $iniFiles));
        foreach (['pdo_pgsql', 'mbstring', 'openssl', 'fileinfo', 'bcmath'] as $extension) {
            $shared = is_file(ini_get('extension_dir').'/php_'.$extension.'.dll') || is_file(ini_get('extension_dir').'/'.$extension.'.so');
            if (extension_loaded($extension) && $shared) {
                // Las extensiones ya presentes en php.ini no necesitan repetirse.
                if (! preg_match('/^\s*extension\s*=\s*["\']?(?:php_)?'.preg_quote($extension, '/').'\b/m', $contents)) {
                    array_push($command, '-d', 'extension='.$extension);
                }
            }
        }
        $command = [...$command, base_path('tests/Support/pedido-worker.php'), 'trackeat-folio-'.bin2hex(random_bytes(4)),
            (string) User::where('rol', 'cliente')->firstOrFail()->id, (string) Producto::firstOrFail()->id,
            (string) $pedidos, (string) $arranque];
        $process = new Process($command, base_path(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_DATABASE' => $config['database'],
            'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'], 'DB_URL' => '',
            'BROADCAST_CONNECTION' => 'log', 'QUEUE_CONNECTION' => 'sync', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'], timeout: 60);
        $process->start();
        $this->workers[] = $process;

        return $process;
    }

    public function test_pedidos_simultaneos_nunca_repiten_el_folio_y_no_dejan_huecos(): void
    {
        $dia = now(config('trackeat.zona_folio'))->toDateString();
        $previo = (int) DB::table('folios_diarios')->where('fecha', $dia)->value('ultimo');
        $this->assertGreaterThan(0, $previo, 'El seeder ya consumió folios del día.');

        // Cuatro procesos, cinco pedidos cada uno, todos arrancando en el mismo instante.
        $arranque = microtime(true) + 4;
        $procesos = array_map(fn () => $this->worker(5, $arranque), range(1, 4));
        $folios = [];
        foreach ($procesos as $proceso) {
            $this->assertSame(0, $proceso->wait(), $proceso->getErrorOutput().$proceso->getOutput());
            array_push($folios, ...json_decode(trim($proceso->getOutput()), true, flags: JSON_THROW_ON_ERROR));
        }

        $this->assertCount(20, $folios);
        $this->assertCount(20, array_unique($folios), 'Hubo folios repetidos.');
        $prefijo = 'TE-'.str_replace('-', '', $dia).'-';
        $numeros = array_map(fn ($folio) => (int) substr($folio, strlen($prefijo)), $folios);
        sort($numeros);
        $this->assertSame(range($previo + 1, $previo + 20), $numeros, 'Los folios deben ser consecutivos y sin huecos.');
        $this->assertSame($previo + 20, (int) DB::table('folios_diarios')->where('fecha', $dia)->value('ultimo'));
    }
}
