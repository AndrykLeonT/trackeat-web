<?php

namespace Tests\Feature;

use App\Models\Corte;
use App\Models\Pedido;
use App\Models\User;
use App\Services\CajaService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CajaConcurrencyTest extends TestCase
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

    private function worker(string $action, string $amount): array
    {
        $name = 'trackeat-race-'.bin2hex(random_bytes(6));
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
        $command = [...$command, base_path('tests/Support/caja-worker.php'), $name, $action,
            (string) User::where('rol', 'cajero')->first()->id,
            (string) Pedido::where('numero_pedido', 'DEMO-001')->first()->id,
            (string) Corte::latest('id')->first()->id, $amount];
        $process = new Process($command, base_path(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_DATABASE' => $config['database'],
            'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'], 'DB_URL' => ''], timeout: 30);
        $process->start();
        $this->workers[] = $process;

        return [$process, $name];
    }

    private function awaitLock(Process $process, string $name): void
    {
        $deadline = microtime(true) + 15;
        do {
            DB::select('SELECT pg_stat_clear_snapshot()');
            $waiting = DB::selectOne("SELECT count(*) AS n FROM pg_stat_activity WHERE application_name = ? AND wait_event_type = 'Lock'", [$name]);
            if ((int) $waiting->n > 0) {
                return;
            }
            if (! $process->isRunning()) {
                $this->fail('El proceso terminó antes del bloqueo: '.$process->getOutput().$process->getErrorOutput());
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $this->fail('El proceso no alcanzó el bloqueo esperado.');
    }

    private function workerResult(Process $process): string
    {
        $this->assertSame(0, $process->wait(), $process->getErrorOutput());

        return trim($process->getOutput());
    }

    public function test_two_simultaneous_payments_cannot_overpay(): void
    {
        $order = Pedido::where('numero_pedido', 'DEMO-001')->first();
        DB::beginTransaction();
        Pedido::lockForUpdate()->findOrFail($order->id);
        [$a, $nameA] = $this->worker('cobrar', '25');
        $this->awaitLock($a, $nameA);
        [$b, $nameB] = $this->worker('cobrar', '25');
        $this->awaitLock($b, $nameB);
        DB::commit();
        $results = [$this->workerResult($a), $this->workerResult($b)];
        sort($results);
        $this->assertSame(['ok', 'rejected'], $results);
        $this->assertSame('0.00', $order->saldo());
        $this->assertSame(1, $order->pagos()->count());
    }

    public function test_closure_waits_for_payment_then_includes_it_in_snapshot(): void
    {
        $order = Pedido::where('numero_pedido', 'DEMO-001')->first();
        $cut = Corte::where('estado', 'abierto')->first();
        $actor = User::where('rol', 'cajero')->first();
        DB::beginTransaction();
        app(CajaService::class)->cobrar($actor, $order->id, $cut->id, 'efectivo', '25');
        [$process, $name] = $this->worker('cerrar', '385');
        $this->awaitLock($process, $name);
        DB::commit();
        $this->assertSame('ok', $this->workerResult($process));
        $this->assertSame('85.00', $cut->fresh()->total_efectivo);
        $this->assertSame('cerrado', $cut->fresh()->estado);
    }

    public function test_payment_waits_for_closure_then_is_rejected(): void
    {
        $cut = Corte::where('estado', 'abierto')->first();
        DB::beginTransaction();
        app(CajaService::class)->cerrar(User::where('rol', 'cajero')->first(), $cut->id, '360', '0', '0');
        [$process, $name] = $this->worker('cobrar', '25');
        $this->awaitLock($process, $name);
        DB::commit();
        $this->assertSame('rejected', $this->workerResult($process));
        $this->assertSame('60.00', $cut->fresh()->total_efectivo);
        $this->assertSame(0, Pedido::where('numero_pedido', 'DEMO-001')->first()->pagos()->count());
    }
}
