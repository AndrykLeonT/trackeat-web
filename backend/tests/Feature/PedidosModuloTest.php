<?php

namespace Tests\Feature;

use App\Events\PedidoActualizado;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Services\CajaService;
use App\Services\PedidoService;
use App\Support\Folio;
use Carbon\Carbon;
use Database\Seeders\DemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// Módulo 2 · Pedidos y comandas. T-08 completa este archivo con el resto de reglas del módulo.
class PedidosModuloTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    private function usuario(string $clave): User
    {
        return User::where('email', "$clave@example.test")->firstOrFail();
    }

    private function levantarPedido(User $actor, array $extra = []): Pedido
    {
        return app(PedidoService::class)->crear($actor, [...['tipo_entrega' => 'para_llevar',
            'items' => [['id_producto' => Producto::firstOrFail()->id, 'cantidad' => 1]]], ...$extra]);
    }

    private function cuerpoApi(array $extra = []): array
    {
        return [...['client_request_id' => (string) Str::uuid(), 'tipo_entrega' => 'para_llevar',
            'items' => [['id_producto' => Producto::firstOrFail()->id, 'cantidad' => 1]]], ...$extra];
    }

    public function test_un_pedido_siempre_tiene_dueno_y_la_llave_foranea_sigue_vigente(): void
    {
        $base = ['numero_pedido' => 'TE-PRUEBA-1', 'tipo_entrega' => 'comer_aqui', 'num_mesa' => 4, 'total' => '10.00'];
        foreach ([[null, '23502'], [99999, '23503']] as [$dueno, $codigo]) {
            try {
                DB::transaction(fn () => Pedido::create([...$base, 'id_usuario' => $dueno]));
                $this->fail('La base debe rechazar un pedido sin dueño o con un dueño inexistente.');
            } catch (QueryException $error) {
                $this->assertSame($codigo, $error->getCode());
            }
        }
        $cliente = $this->usuario('cliente1');
        $this->assertSame($cliente->id, Pedido::create([...$base, 'id_usuario' => $cliente->id])->id_usuario);
    }

    public function test_cliente_mesero_cajero_y_administrador_levantan_pedidos_a_su_nombre(): void
    {
        foreach (['cliente1', 'mesero', 'cajero', 'administrador'] as $clave) {
            $usuario = $this->usuario($clave);
            $this->assertSame($usuario->id, $this->levantarPedido($usuario)->id_usuario, $clave);
        }
    }

    public function test_nadie_puede_registrar_un_pedido_a_nombre_de_otra_cuenta(): void
    {
        $cliente = $this->usuario('cliente1');
        $otro = $this->usuario('cliente2');
        $this->assertSame($cliente->id, $this->levantarPedido($cliente, ['id_usuario' => $otro->id])->id_usuario);
        $this->assertSame($this->usuario('mesero')->id, $this->levantarPedido($this->usuario('mesero'), ['id_usuario' => $otro->id])->id_usuario);

        $this->actingAs($cliente)->postJson('/api/pedidos', $this->cuerpoApi(['id_usuario' => $otro->id]))
            ->assertOk()->assertJsonPath('id_usuario', $cliente->id);
    }

    public function test_el_personal_levanta_pedidos_desde_la_api_y_quedan_a_su_nombre(): void
    {
        foreach (['mesero', 'cajero'] as $clave) {
            $usuario = $this->usuario($clave);
            $this->actingAs($usuario)->postJson('/api/pedidos', $this->cuerpoApi(['tipo_entrega' => 'comer_aqui', 'num_mesa' => 3]))
                ->assertOk()->assertJsonPath('id_usuario', $usuario->id)->assertJsonPath('num_mesa', 3);
        }
    }

    public function test_un_reintento_del_personal_no_duplica_el_pedido(): void
    {
        $mesero = $this->usuario('mesero');
        $solicitud = (string) Str::uuid();
        $primero = $this->levantarPedido($mesero, ['client_request_id' => $solicitud]);
        $segundo = $this->levantarPedido($mesero, ['client_request_id' => $solicitud]);
        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, Pedido::where('client_request_id', $solicitud)->count());

        // El índice único (id_usuario, client_request_id) cubre al personal igual que a los clientes.
        try {
            DB::transaction(fn () => Pedido::create(['id_usuario' => $mesero->id, 'client_request_id' => $solicitud, 'numero_pedido' => 'TE-PRUEBA-2',
                'tipo_entrega' => 'para_llevar', 'total' => '10.00']));
            $this->fail('La base debe rechazar un reintento duplicado.');
        } catch (QueryException $error) {
            $this->assertSame('23505', $error->getCode());
        }
    }

    public function test_el_motivo_de_cancelacion_se_guarda(): void
    {
        $pedido = Pedido::where('numero_pedido', 'DEMO-001')->firstOrFail();
        $this->assertNull($pedido->motivo_cancelacion);
        $pedido->update(['motivo_cancelacion' => 'error_de_captura']);
        $this->assertSame('error_de_captura', $pedido->fresh()->motivo_cancelacion);
    }

    public function test_el_evento_avisa_al_canal_de_quien_levanto_el_pedido(): void
    {
        $mesero = $this->usuario('mesero');
        $canales = array_map(fn ($canal) => $canal->name, PedidoActualizado::forOrder($this->levantarPedido($mesero))->broadcastOn());
        $this->assertSame(['private-trackeat.personal', 'private-trackeat.usuario.'.$mesero->id], $canales);
    }

    public function test_el_seeder_asigna_estacion_solo_al_cocinero_de_ejemplo(): void
    {
        // `general`: el cocinero de ejemplo ve y atiende las comandas de cocina y de barra.
        $this->assertSame('general', User::where('email', 'cocinero@example.test')->firstOrFail()->estacion);
        $this->assertSame(0, User::where('email', '!=', 'cocinero@example.test')->whereNotNull('estacion')->count());
    }

    public function test_la_base_rechaza_una_estacion_invalida(): void
    {
        try {
            DB::transaction(fn () => DB::table('users')->where('email', 'cocinero@example.test')->update(['estacion' => 'postres']));
            $this->fail('Una estación fuera de general, cocina y barra debe ser rechazada.');
        } catch (QueryException $error) {
            $this->assertSame('23514', $error->getCode());
        }
    }

    private function crearUsuario(string $clave, string $rol, ?string $estacion = null): User
    {
        $usuario = (new User)->forceFill(['name' => "$clave Prueba", 'primer_nombre' => $clave, 'primer_apellido' => 'Prueba',
            'email' => "$clave@prueba.test", 'password' => 'TrackEatDemo!2026', 'rol' => $rol, 'estacion' => $estacion]);
        $usuario->save();

        return $usuario->fresh();
    }

    public function test_un_cocinero_nuevo_sin_estacion_queda_en_general(): void
    {
        $this->assertSame('general', $this->crearUsuario('cocinero_nuevo', 'cocinero')->estacion);
        $this->assertSame('barra', $this->crearUsuario('cocinero_barra', 'cocinero', 'barra')->estacion);
        $this->assertSame('cocina', $this->crearUsuario('cocinero_cocina', 'cocinero', 'cocina')->estacion);
        $this->assertNull($this->crearUsuario('mesero_nuevo', 'mesero')->estacion);
    }

    public function test_cambiar_a_rol_cocinero_asigna_general_y_nunca_queda_sin_estacion(): void
    {
        $usuario = $this->crearUsuario('ascendido', 'mesero');
        DB::table('users')->where('id', $usuario->id)->update(['rol' => 'cocinero']);
        $this->assertSame('general', $usuario->fresh()->estacion);

        // Quitarle la estación a un cocinero lo regresa a `general`, nunca a nulo.
        DB::table('users')->where('id', $usuario->id)->update(['estacion' => 'barra']);
        DB::table('users')->where('id', $usuario->id)->update(['estacion' => null]);
        $this->assertSame('general', $usuario->fresh()->estacion);
    }

    public function test_la_migracion_hace_generales_a_los_cocineros_existentes_sin_estacion(): void
    {
        $migracion = require database_path('migrations/2026_11_25_000001_cocinero_estacion_general.php');
        $migracion->down();
        $sinEstacion = $this->crearUsuario('cocinero_viejo', 'cocinero');
        $deBarra = $this->crearUsuario('cocinero_viejo_barra', 'cocinero', 'barra');
        $this->assertNull($sinEstacion->estacion);
        $migracion->up();

        $this->assertSame('general', $sinEstacion->fresh()->estacion);
        $this->assertSame('barra', $deBarra->fresh()->estacion);
        $this->assertSame(0, User::where('rol', 'cocinero')->whereNull('estacion')->count());
    }

    public function test_las_categorias_solo_admiten_cocina_o_barra(): void
    {
        $this->assertSame(['barra', 'cocina'], Categoria::orderBy('estacion')->distinct()->pluck('estacion')->all());

        try {
            DB::transaction(fn () => Categoria::where('nombre', 'Hamburguesas')->update(['estacion' => 'general']));
            $this->fail('La base debe rechazar una categoría de estación general.');
        } catch (QueryException $error) {
            $this->assertSame('23514', $error->getCode());
        }

        $admin = User::where('rol', 'administrador')->firstOrFail();
        $this->actingAs($admin)->postJson('/admin/categorias', ['nombre' => 'Nueva', 'estacion' => 'general', 'disponible' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('estacion');
        $this->postJson('/admin/categorias', ['nombre' => 'Nueva', 'estacion' => 'barra', 'disponible' => true])->assertRedirect('/admin/categorias');
    }

    public function test_la_migracion_reclasifica_las_categorias_general_por_su_nombre(): void
    {
        $migracion = require database_path('migrations/2026_11_24_000003_categorias_solo_cocina_y_barra.php');
        $migracion->down();
        $esperado = ['Jugos naturales' => 'barra', 'Café' => 'barra', 'Aguas frescas' => 'barra', 'Bebidas frías' => 'barra',
            'Tacos' => 'cocina', 'Aguacates' => 'cocina', 'Postres' => 'cocina'];
        foreach (array_keys($esperado) as $nombre) {
            Categoria::create(['nombre' => $nombre, 'estacion' => 'general']);
        }
        $migracion->up();

        foreach ($esperado as $nombre => $estacion) {
            $this->assertSame($estacion, Categoria::where('nombre', $nombre)->firstOrFail()->estacion, $nombre);
        }
        $this->assertSame('cocina', Categoria::where('nombre', 'Hamburguesas')->firstOrFail()->estacion);
        $this->assertSame('barra', Categoria::where('nombre', 'Bebidas')->firstOrFail()->estacion);
    }

    private function crearPedido(): string
    {
        // El mesero no tiene límite de pedidos en curso (T-07); estas pruebas tratan del folio, no del dueño.
        return $this->levantarPedido($this->usuario('mesero'))->numero_pedido;
    }

    private function numeroDeFolio(string $folio): int
    {
        return (int) substr($folio, strrpos($folio, '-') + 1);
    }

    public function test_el_folio_es_consecutivo_y_lleva_la_fecha_del_dia(): void
    {
        $hoy = now(config('trackeat.zona_folio'))->format('Ymd');
        $primero = $this->crearPedido();
        $segundo = $this->crearPedido();
        $this->assertMatchesRegularExpression("/^TE-$hoy-\\d{3,}$/", $primero);
        $this->assertSame($this->numeroDeFolio($primero) + 1, $this->numeroDeFolio($segundo));
    }

    public function test_el_folio_reinicia_a_medianoche_de_la_zona_del_restaurante(): void
    {
        try {
            // 05:30 UTC del 25 es todavía el 24 en Mazatlán (22:30): el folio conserva la fecha del 24.
            Carbon::setTestNow(Carbon::parse('2020-11-25 05:30:00', 'UTC'));
            $this->assertMatchesRegularExpression('/^TE-20201124-\d{3,}$/', $this->crearPedido());
            // 07:30 UTC del 25 son las 00:30 del 25 en Mazatlán: cambia el día y el contador vuelve a 001.
            Carbon::setTestNow(Carbon::parse('2020-11-25 07:30:00', 'UTC'));
            $this->assertSame('TE-20201125-001', $this->crearPedido());
            $this->assertSame('TE-20201125-002', $this->crearPedido());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_una_creacion_revertida_no_consume_folio(): void
    {
        $antes = $this->numeroDeFolio($this->crearPedido());
        try {
            DB::transaction(function () {
                $this->crearPedido();
                throw new \RuntimeException('Fallo forzado después de asignar el folio.');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame($antes + 1, $this->numeroDeFolio($this->crearPedido()));
    }

    // ---------- T-06 · Autorización por pedido al avanzar estación (A01) ----------

    private function pedidoCocinaYBarra(?string $sala = null): Pedido
    {
        $producto = fn (string $estacion) => Producto::whereHas('categoria', fn ($q) => $q->where('estacion', $estacion))->firstOrFail()->id;
        // Lo levanta el mesero: sin límite de pedidos en curso, así cada prueba puede crear los que necesite.
        $pedido = app(PedidoService::class)->crear($this->usuario('mesero'), ['tipo_entrega' => 'para_llevar', 'items' => [
            ['id_producto' => $producto('cocina'), 'cantidad' => 1], ['id_producto' => $producto('barra'), 'cantidad' => 1]]]);
        if ($sala) {
            $pedido->forceFill(['demo_room' => $sala])->save();
        }

        return $pedido;
    }

    /** Lleva todas las estaciones a "listo" (cocinero) y entrega el pedido completo (mesero), como en servicio. */
    private function entregarCompleto(Pedido $pedido): Pedido
    {
        foreach ($pedido->estaciones()->pluck('estacion') as $estacion) {
            foreach (['preparacion', 'listo'] as $estado) {
                app(PedidoService::class)->avanzar($this->usuario('cocinero'), $pedido->id, $estacion, $estado);
            }
        }

        return app(PedidoService::class)->entregar($this->usuario('mesero'), $pedido->id);
    }

    private function avanzarApi(User $actor, Pedido $pedido, string $estacion, string $estado = 'preparacion')
    {
        return $this->actingAs($actor)->patchJson("/api/pedidos/{$pedido->id}/estacion", ['estacion' => $estacion, 'estado' => $estado]);
    }

    public function test_el_cocinero_de_cocina_o_barra_solo_avanza_su_estacion(): void
    {
        $pedido = $this->pedidoCocinaYBarra();
        $barra = $this->crearUsuario('cocinero_barra', 'cocinero', 'barra');
        $cocina = $this->crearUsuario('cocinero_cocina', 'cocinero', 'cocina');

        $this->avanzarApi($barra, $pedido, 'cocina')->assertForbidden()->assertJsonPath('message', 'Esta comanda es de la estación cocina.');
        $this->avanzarApi($cocina, $pedido, 'barra')->assertForbidden()->assertJsonPath('message', 'Esta comanda es de la estación barra.');
        $this->avanzarApi($barra, $pedido, 'barra')->assertOk();
        $this->avanzarApi($cocina, $pedido, 'cocina')->assertOk();
        $this->assertSame(['barra' => 'preparacion', 'cocina' => 'preparacion'],
            $pedido->estaciones()->orderBy('estacion')->pluck('estado', 'estacion')->all());
    }

    public function test_el_cocinero_general_y_el_administrador_avanzan_cualquier_estacion(): void
    {
        $pedido = $this->pedidoCocinaYBarra();
        $this->avanzarApi($this->usuario('cocinero'), $pedido, 'cocina')->assertOk();
        $this->avanzarApi($this->usuario('cocinero'), $pedido, 'barra')->assertOk();
        $this->avanzarApi($this->usuario('administrador'), $pedido, 'cocina', 'listo')->assertOk();
        $this->avanzarApi($this->usuario('administrador'), $pedido, 'barra', 'listo')->assertOk();
    }

    public function test_mesero_y_cajero_no_preparan_y_nadie_entrega_una_estacion_suelta(): void
    {
        $pedido = $this->pedidoCocinaYBarra();
        $this->avanzarApi($this->usuario('mesero'), $pedido, 'cocina')->assertForbidden();
        $this->avanzarApi($this->usuario('cajero'), $pedido, 'cocina')->assertForbidden();
        $this->avanzarApi($this->usuario('cocinero'), $pedido, 'cocina')->assertOk();
        $this->avanzarApi($this->usuario('cocinero'), $pedido, 'cocina', 'listo')->assertOk();
        // HU-09: la entrega es por pedido completo; nadie, ni el administrador, entrega una estación.
        foreach (['cocinero', 'cajero', 'mesero', 'administrador'] as $rol) {
            $this->avanzarApi($this->usuario($rol), $pedido, 'cocina', 'entregado')
                ->assertForbidden()->assertJsonPath('message', 'La entrega es por pedido completo.');
        }
        $this->assertSame('listo', $pedido->estaciones()->where('estacion', 'cocina')->value('estado'));
    }

    public function test_las_comandas_general_de_pedidos_anteriores_solo_las_avanza_el_cocinero_general(): void
    {
        $pedido = $this->levantarPedido($this->usuario('cliente1'));
        $pedido->estaciones()->create(['estacion' => 'general']);
        $this->avanzarApi($this->crearUsuario('cocinero_barra', 'cocinero', 'barra'), $pedido, 'general')->assertForbidden();
        $this->avanzarApi($this->usuario('cocinero'), $pedido, 'general')->assertOk();
    }

    public function test_la_regla_de_estacion_no_se_salta_llamando_al_servicio(): void
    {
        $pedido = $this->pedidoCocinaYBarra();
        try {
            app(PedidoService::class)->avanzar($this->crearUsuario('cocinero_barra', 'cocinero', 'barra'), $pedido->id, 'cocina', 'preparacion');
            $this->fail('El servicio debe aplicar la política aunque no pase por el controlador.');
        } catch (AuthorizationException $error) {
            $this->assertSame('Esta comanda es de la estación cocina.', $error->getMessage());
        }
        $this->assertSame('recibido', $pedido->estaciones()->where('estacion', 'cocina')->value('estado'));
    }

    public function test_un_cambio_de_estacion_aplica_sin_volver_a_iniciar_sesion(): void
    {
        $pedido = $this->pedidoCocinaYBarra();
        $cocinero = $this->crearUsuario('cocinero_barra', 'cocinero', 'barra');
        $this->avanzarApi($cocinero, $pedido, 'cocina')->assertForbidden();
        DB::table('users')->where('id', $cocinero->id)->update(['estacion' => 'cocina']);
        // La instancia en sesión sigue diciendo `barra`; el servidor relee la base.
        $this->avanzarApi($cocinero, $pedido, 'cocina')->assertOk();
    }

    public function test_un_usuario_de_una_sala_demo_no_toca_pedidos_de_otra_sala(): void
    {
        $sala = (string) Str::uuid();
        $cocinero = $this->crearUsuario('cocinero_demo', 'cocinero');
        $cocinero->forceFill(['demo_room' => $sala])->save();
        $ajeno = $this->pedidoCocinaYBarra((string) Str::uuid());
        $restaurante = $this->pedidoCocinaYBarra();

        foreach ([$ajeno, $restaurante] as $pedido) {
            try {
                app(PedidoService::class)->avanzar($cocinero, $pedido->id, 'cocina', 'preparacion');
                $this->fail('Un usuario de sala no debe avanzar pedidos fuera de su sala.');
            } catch (AuthorizationException $error) {
                $this->assertSame(404, $error->status());
            }
        }
        $propio = $this->pedidoCocinaYBarra($sala);
        app(PedidoService::class)->avanzar($cocinero, $propio->id, 'cocina', 'preparacion');
        $this->assertSame('preparacion', $propio->estaciones()->where('estacion', 'cocina')->value('estado'));
    }

    public function test_el_personal_del_restaurante_avanza_los_pedidos_de_la_demostracion(): void
    {
        $pedido = $this->pedidoCocinaYBarra((string) Str::uuid());
        $this->avanzarApi($this->usuario('administrador'), $pedido, 'cocina')->assertOk();
    }

    public function test_un_pedido_inexistente_responde_404(): void
    {
        $this->actingAs($this->usuario('cocinero'))
            ->patchJson('/api/pedidos/999999/estacion', ['estacion' => 'cocina', 'estado' => 'preparacion'])->assertNotFound();
    }

    public function test_la_sesion_expone_la_estacion_del_usuario(): void
    {
        $this->actingAs($this->usuario('cocinero'))->getJson('/api/sesion')->assertJsonPath('user.estacion', 'general');
        $this->actingAs($this->usuario('mesero'))->getJson('/api/sesion')->assertJsonPath('user.estacion', null);
    }

    // ---------- T-07 · Límites de negocio al crear pedido (A08) ----------

    private function intentar(User $actor, array $extra = []): array
    {
        try {
            return ['ok' => $this->levantarPedido($actor, $extra)];
        } catch (ValidationException $error) {
            return ['error' => $error->errors()];
        }
    }

    private function renglon(int $cantidad, ?Producto $producto = null): array
    {
        return ['id_producto' => ($producto ?? Producto::orderByDesc('precio')->firstOrFail())->id, 'cantidad' => $cantidad];
    }

    public function test_la_cantidad_por_producto_y_los_renglones_tienen_tope(): void
    {
        $cliente = $this->crearUsuario('cliente_limites', 'cliente');
        $barato = Producto::orderBy('precio')->firstOrFail();
        $this->assertArrayHasKey('items.0.cantidad', $this->intentar($cliente, ['items' => [$this->renglon(101, $barato)]])['error']);
        $this->assertArrayHasKey('items', $this->intentar($cliente, ['items' => array_fill(0, 51, $this->renglon(1, $barato))])['error']);
        // El controlador aplica los mismos topes que el servicio.
        $this->actingAs($cliente)->postJson('/api/pedidos', $this->cuerpoApi(['items' => [$this->renglon(101, $barato)]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.cantidad');
        $this->assertSame(0, Pedido::where('id_usuario', $cliente->id)->count());
    }

    public function test_la_mesa_debe_estar_entre_1_y_el_maximo(): void
    {
        $mesero = $this->usuario('mesero');
        $this->assertArrayHasKey('num_mesa', $this->intentar($mesero, ['tipo_entrega' => 'comer_aqui', 'num_mesa' => 31])['error']);
        $this->assertArrayHasKey('num_mesa', $this->intentar($mesero, ['tipo_entrega' => 'comer_aqui', 'num_mesa' => 0])['error']);
        $this->assertSame(30, $this->intentar($mesero, ['tipo_entrega' => 'comer_aqui', 'num_mesa' => 30])['ok']->num_mesa);
    }

    public function test_el_total_maximo_es_de_5000_para_clientes_y_20000_para_el_personal(): void
    {
        $cliente = $this->crearUsuario('cliente_limites', 'cliente');
        $caro = Producto::orderByDesc('precio')->firstOrFail();
        $piezas = (int) floor(5000 / (float) $caro->precio) + 1;   // apenas por encima de $5,000
        $error = $this->intentar($cliente, ['items' => [$this->renglon($piezas)]])['error'];
        $this->assertSame(['El pedido supera el máximo de $5,000.00 MXN. Divídelo en varios pedidos o pide ayuda al personal.'], $error['total']);
        $this->assertArrayHasKey('ok', $this->intentar($this->usuario('mesero'), ['items' => [$this->renglon($piezas)]]));

        $porRenglon = min(100, (int) floor(20000 / (float) $caro->precio));
        $renglones = array_fill(0, (int) ceil(20000 / ($porRenglon * (float) $caro->precio)) + 1, $this->renglon($porRenglon));
        $this->assertArrayHasKey('total', $this->intentar($this->usuario('cajero'), ['items' => $renglones])['error']);
    }

    public function test_un_cliente_tiene_como_maximo_3_pedidos_en_curso(): void
    {
        $cliente = $this->crearUsuario('cliente_limites', 'cliente');
        $primero = $this->actingAs($cliente)->postJson('/api/pedidos', $cuerpo = $this->cuerpoApi())->assertOk()->json();
        $this->levantarPedido($cliente);
        $this->levantarPedido($cliente);

        $this->actingAs($cliente)->postJson('/api/pedidos', $this->cuerpoApi())->assertUnprocessable()
            ->assertJsonPath('errors.pedidos.0', 'Ya tienes 3 pedidos en curso. Espera a que se entregue alguno para hacer otro.');
        // Un reintento del primero devuelve el mismo pedido aunque el cliente esté en el límite.
        $this->actingAs($cliente)->postJson('/api/pedidos', $cuerpo)->assertOk()->assertJsonPath('id', $primero['id']);

        // Al entregarse uno, el cliente vuelve a poder pedir.
        $pedido = Pedido::findOrFail($primero['id']);
        $this->entregarCompleto($pedido);
        $this->actingAs($cliente)->postJson('/api/pedidos', $this->cuerpoApi())->assertOk();
    }

    public function test_un_pedido_cancelado_no_cuenta_como_en_curso(): void
    {
        $cliente = $this->crearUsuario('cliente_limites', 'cliente');
        $pedidos = [$this->levantarPedido($cliente), $this->levantarPedido($cliente), $this->levantarPedido($cliente)];
        app(PedidoService::class)->cancelar($cliente, $pedidos[0]->id);
        $this->assertArrayHasKey('ok', $this->intentar($cliente));
    }

    public function test_el_personal_no_tiene_limite_de_pedidos_en_curso(): void
    {
        $mesero = $this->usuario('mesero');
        foreach (range(1, 5) as $_) {
            $this->levantarPedido($mesero);
        }
        $this->assertSame(5, Pedido::where('id_usuario', $mesero->id)->whereIn('estado', ['recibido', 'preparacion', 'listo'])->count());
    }

    public function test_la_sesion_expone_los_limites_segun_el_rol(): void
    {
        $this->actingAs($this->usuario('cliente1'))->getJson('/api/sesion')
            ->assertJsonPath('limites', ['mesas_max' => 30, 'cantidad_max' => 100, 'total_max' => '5000.00']);
        $this->actingAs($this->usuario('mesero'))->getJson('/api/sesion')->assertJsonPath('limites.total_max', '20000.00');
    }

    // ---------- HU-02 + HU-03 · Resumen y seguimiento /pedidos/{id} ----------

    public function test_la_pagina_del_pedido_trae_el_resumen_con_precios_congelados_y_estaciones(): void
    {
        $cliente = $this->crearUsuario('cliente_resumen', 'cliente');
        $producto = Producto::orderByDesc('precio')->firstOrFail();
        $pedido = $this->levantarPedido($cliente, ['tipo_entrega' => 'comer_aqui', 'num_mesa' => 7,
            'items' => [['id_producto' => $producto->id, 'cantidad' => 2, 'notas' => 'Sin cebolla']]]);
        $precio = $producto->precio;
        // Si el precio cambia después, el pedido conserva el de cuando se ordenó.
        $producto->forceFill(['precio' => '999.00'])->save();

        $this->actingAs($cliente)->get("/pedidos/{$pedido->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('public/pedido', false)
                ->where('pedido.numero_pedido', $pedido->numero_pedido)
                ->where('pedido.tipo_entrega', 'comer_aqui')->where('pedido.num_mesa', 7)
                ->where('pedido.items.0.cantidad', 2)->where('pedido.items.0.precio_unitario', $precio)
                ->where('pedido.items.0.notas', 'Sin cebolla')->where('pedido.items.0.producto.nombre', $producto->nombre)
                ->where('pedido.total', number_format(2 * (float) $precio, 2, '.', ''))
                ->has('pedido.estaciones', 1)->where('pedido.estaciones.0.estado', 'recibido'));
    }

    public function test_solo_el_dueno_y_el_personal_abren_el_pedido(): void
    {
        $pedido = $this->levantarPedido($this->usuario('cliente1'));
        $this->actingAs($this->usuario('cliente1'))->get("/pedidos/{$pedido->id}")->assertOk();
        foreach (['mesero', 'cajero', 'cocinero', 'administrador'] as $rol) {
            $this->actingAs($this->usuario($rol))->get("/pedidos/{$pedido->id}")->assertOk();
        }
        $this->actingAs($this->usuario('cliente2'))->get("/pedidos/{$pedido->id}")->assertNotFound();
        $this->actingAs($this->usuario('cliente2'))->getJson("/api/pedidos/{$pedido->id}")->assertNotFound();
        $this->actingAs($this->usuario('cliente1'))->get('/pedidos/99999999')->assertNotFound();
    }

    public function test_el_detalle_refleja_el_avance_por_estacion_y_los_estados_finales(): void
    {
        $cliente = $this->crearUsuario('cliente_seguimiento', 'cliente');
        $pedido = $this->levantarPedido($cliente);
        $estacion = $pedido->estaciones->first()->estacion;
        app(PedidoService::class)->avanzar($this->usuario('cocinero'), $pedido->id, $estacion, 'preparacion');
        $this->actingAs($cliente)->getJson("/api/pedidos/{$pedido->id}")->assertOk()
            ->assertJsonPath('estado', 'preparacion')->assertJsonPath('estaciones.0.estado', 'preparacion');

        // Un pedido cancelado se sigue pudiendo consultar y trae su motivo.
        // Ya en preparación, solo el personal puede cancelar (HU-05) y debe dar un motivo.
        app(PedidoService::class)->cancelar($this->usuario('mesero'), $pedido->id, 'Cliente se retiró');
        $this->actingAs($cliente)->getJson("/api/pedidos/{$pedido->id}")->assertOk()
            ->assertJsonPath('estado', 'cancelado')->assertJsonPath('motivo_cancelacion', 'Cliente se retiró')
            ->assertJsonPath('estaciones.0.estado', 'cancelado');
    }

    // ---------- HU-04 · Mis pedidos por día ----------

    /** Pedido del usuario creado en un instante local del restaurante (America/Mazatlan). */
    private function pedidoCreadoEl(User $usuario, string $local): Pedido
    {
        $pedido = $this->levantarPedido($usuario);
        $pedido->forceFill(['created_at' => Carbon::parse($local, 'America/Mazatlan')])->save();

        return $pedido;
    }

    /** Cobra el pedido completo en un corte abierto en el instante local indicado. */
    private function cobrarEnCorteAbiertoEl(Pedido $pedido, string $aperturaLocal, string $clave = 'cajero_historial')
    {
        $cajero = User::where('email', "$clave@prueba.test")->first() ?? $this->crearUsuario($clave, 'cajero');
        $corte = app(CajaService::class)->abrir($cajero, '100');
        $corte->forceFill(['fecha_apertura' => Carbon::parse($aperturaLocal, 'America/Mazatlan')])->save();

        return app(CajaService::class)->cobrar($cajero, $pedido->id, $corte->id, 'efectivo', $pedido->total);
    }

    private function misPedidos(User $usuario, array $query = [])
    {
        return $this->actingAs($usuario)->get('/mis-pedidos'.($query ? '?'.http_build_query($query) : ''))->assertOk();
    }

    private function folios($respuesta): array
    {
        return array_column($respuesta->viewData('page')['props']['pedidos']['data'], 'numero_pedido');
    }

    public function test_un_pedido_cobrado_cuenta_en_el_dia_en_que_abrio_su_corte(): void
    {
        $cliente = $this->crearUsuario('cliente_historial', 'cliente');
        // Turno que abre el 20 a las 18:00 y cobra un pedido hecho el 21 a las 00:30: el pedido es del 20.
        $pedido = $this->pedidoCreadoEl($cliente, '2026-11-21 00:30:00');
        $this->cobrarEnCorteAbiertoEl($pedido, '2026-11-20 18:00:00');

        $this->assertSame([$pedido->numero_pedido], $this->folios($this->misPedidos($cliente, ['dia' => '2026-11-20'])));
        $this->assertSame([], $this->folios($this->misPedidos($cliente, ['dia' => '2026-11-21'])));
    }

    public function test_un_pedido_sin_pago_cuenta_en_el_dia_en_que_se_creo(): void
    {
        $cliente = $this->crearUsuario('cliente_historial', 'cliente');
        // 23:30 del 21 en Mazatlán ya es el 22 en UTC: debe contar como 21.
        $pedido = $this->pedidoCreadoEl($cliente, '2026-11-21 23:30:00');

        $this->assertSame([$pedido->numero_pedido], $this->folios($this->misPedidos($cliente, ['dia' => '2026-11-21'])));
        $this->assertSame([], $this->folios($this->misPedidos($cliente, ['dia' => '2026-11-22'])));
    }

    public function test_un_pago_eliminado_no_define_el_dia(): void
    {
        $cliente = $this->crearUsuario('cliente_historial', 'cliente');
        $pedido = $this->pedidoCreadoEl($cliente, '2026-11-21 12:00:00');
        $pago = $this->cobrarEnCorteAbiertoEl($pedido, '2026-11-19 18:00:00');
        app(CajaService::class)->corregir(User::where('email', 'cajero_historial@prueba.test')->firstOrFail(), $pago->id);

        $this->assertSame([$pedido->numero_pedido], $this->folios($this->misPedidos($cliente, ['dia' => '2026-11-21'])));
        $this->assertSame([], $this->folios($this->misPedidos($cliente, ['dia' => '2026-11-19'])));
    }

    public function test_por_defecto_muestra_hoy_y_un_dia_invalido_tambien(): void
    {
        $cliente = $this->crearUsuario('cliente_historial', 'cliente');
        $ayer = $this->pedidoCreadoEl($cliente, now('America/Mazatlan')->subDay()->setTime(12, 0)->toDateTimeString());
        $hoy = $this->levantarPedido($cliente);
        $diaHoy = now('America/Mazatlan')->toDateString();

        foreach ([[], ['dia' => 'hola'], ['dia' => '2026-02-30']] as $query) {
            $respuesta = $this->misPedidos($cliente, $query)
                ->assertInertia(fn (Assert $page) => $page->component('public/mis-pedidos', false)
                    ->where('dia', $diaHoy)->where('hoy', $diaHoy));
            $this->assertSame([$hoy->numero_pedido], $this->folios($respuesta));
        }
        $this->assertNotContains($ayer->numero_pedido, $this->folios($this->misPedidos($cliente)));
    }

    public function test_ver_todos_lista_solo_los_propios_del_mas_reciente_al_mas_antiguo(): void
    {
        $cliente = $this->crearUsuario('cliente_historial', 'cliente');
        $viejo = $this->pedidoCreadoEl($cliente, '2026-10-01 12:00:00');
        $nuevo = $this->levantarPedido($cliente);
        $ajeno = $this->levantarPedido($this->usuario('cliente2'));

        $folios = $this->folios($this->misPedidos($cliente, ['dia' => 'todos']));
        $this->assertSame([$nuevo->numero_pedido, $viejo->numero_pedido], $folios);
        $this->assertNotContains($ajeno->numero_pedido, $folios);
    }

    public function test_el_historial_se_pagina_de_20_en_20(): void
    {
        $cliente = $this->crearUsuario('cliente_historial', 'cliente');
        foreach (range(1, 25) as $i) {
            // Pedidos ya entregados, para no chocar con el límite de pedidos en curso (T-07).
            $pedido = $this->pedidoCreadoEl($cliente, '2026-11-10 12:00:00');
            $pedido->estaciones()->update(['estado' => 'entregado']);
            $pedido->forceFill(['estado' => 'entregado'])->save();
        }
        $this->misPedidos($cliente, ['dia' => '2026-11-10'])->assertInertia(fn (Assert $page) => $page
            ->has('pedidos.data', 20)->where('pedidos.current_page', 1)->where('pedidos.last_page', 2)->where('pedidos.total', 25));
        $this->misPedidos($cliente, ['dia' => '2026-11-10', 'page' => 2])->assertInertia(fn (Assert $page) => $page
            ->has('pedidos.data', 5)->where('pedidos.current_page', 2));
    }

    public function test_mis_pedidos_requiere_sesion(): void
    {
        $this->get('/mis-pedidos')->assertRedirect('/login');
    }

    // ---------- HU-05 · Cancelar pedido ----------

    private function cancelarApi(User $actor, Pedido $pedido, ?string $motivo = null)
    {
        return $this->actingAs($actor)->postJson("/api/pedidos/{$pedido->id}/cancelar", $motivo ? ['motivo' => $motivo] : []);
    }

    public function test_el_cliente_cancela_su_pedido_mientras_todo_esta_en_recibido(): void
    {
        Event::fake([PedidoActualizado::class]);
        $cliente = $this->crearUsuario('cliente_cancela', 'cliente');
        $pedido = $this->levantarPedido($cliente);

        $this->cancelarApi($cliente, $pedido)->assertOk()
            ->assertJsonPath('estado', 'cancelado')->assertJsonPath('motivo_cancelacion', 'Cancelado por el cliente');
        $this->assertSame(['cancelado'], $pedido->estaciones()->pluck('estado')->unique()->all());
        // Queda en el historial con quién lo hizo, y se avisa en vivo.
        $this->assertSame([$cliente->id], $pedido->historial()->where('estado_nuevo', 'cancelado')->pluck('cambiado_por')->unique()->all());
        Event::assertDispatched(PedidoActualizado::class, fn ($e) => $e->pedido['id'] === $pedido->id && $e->pedido['estado'] === 'cancelado');
    }

    public function test_el_cliente_no_cancela_si_ya_se_empezo_a_preparar(): void
    {
        $cliente = $this->crearUsuario('cliente_cancela', 'cliente');
        $producto = fn (string $estacion) => Producto::whereHas('categoria', fn ($q) => $q->where('estacion', $estacion))->firstOrFail()->id;
        $pedido = $this->levantarPedido($cliente, ['items' => [['id_producto' => $producto('cocina'), 'cantidad' => 1], ['id_producto' => $producto('barra'), 'cantidad' => 1]]]);
        // Basta con que UNA estación haya empezado.
        app(PedidoService::class)->avanzar($this->usuario('cocinero'), $pedido->id, 'barra', 'preparacion');

        $this->cancelarApi($cliente, $pedido)->assertForbidden()
            ->assertJsonPath('message', 'Tu pedido ya se empezó a preparar. Pide ayuda al personal.');
        $this->assertSame('recibido', $pedido->fresh()->estado);
    }

    public function test_un_cliente_no_cancela_el_pedido_de_otro(): void
    {
        $pedido = $this->levantarPedido($this->usuario('cliente1'));
        $this->cancelarApi($this->usuario('cliente2'), $pedido)->assertNotFound();
        try {
            app(PedidoService::class)->cancelar($this->usuario('cliente2'), $pedido->id);
            $this->fail('El servicio debe aplicar la política aunque no pase por el controlador.');
        } catch (AuthorizationException $error) {
            $this->assertSame(404, $error->status());
        }
        $this->assertSame('recibido', $pedido->fresh()->estado);
    }

    public function test_el_personal_cancela_hasta_antes_de_entregar_eligiendo_un_motivo(): void
    {
        foreach (['mesero', 'cajero', 'administrador'] as $rol) {
            $pedido = $this->pedidoCocinaYBarra();
            app(PedidoService::class)->avanzar($this->usuario('cocinero'), $pedido->id, 'cocina', 'preparacion');
            app(PedidoService::class)->avanzar($this->usuario('cocinero'), $pedido->id, 'cocina', 'listo');

            $this->cancelarApi($this->usuario($rol), $pedido)->assertUnprocessable()
                ->assertJsonPath('errors.motivo.0', 'Elige el motivo de la cancelación.');
            $this->cancelarApi($this->usuario($rol), $pedido, 'Porque sí')->assertUnprocessable()
                ->assertJsonPath('errors.motivo.0', 'Elige un motivo de la lista.');
            $this->cancelarApi($this->usuario($rol), $pedido, 'Producto agotado')->assertOk()
                ->assertJsonPath('estado', 'cancelado')->assertJsonPath('motivo_cancelacion', 'Producto agotado');
        }
    }

    public function test_el_cocinero_no_cancela(): void
    {
        $pedido = $this->pedidoCocinaYBarra();
        $this->cancelarApi($this->usuario('cocinero'), $pedido, 'Producto agotado')->assertForbidden();
        $this->assertSame('recibido', $pedido->fresh()->estado);
    }

    public function test_no_se_cancela_un_pedido_entregado_ni_uno_ya_cancelado(): void
    {
        $pedido = $this->entregarCompleto($this->pedidoCocinaYBarra());
        $this->cancelarApi($this->usuario('administrador'), $pedido, 'Otro')->assertUnprocessable()->assertJsonValidationErrors('estado');

        $otro = $this->pedidoCocinaYBarra();
        $this->cancelarApi($this->usuario('mesero'), $otro, 'Pedido duplicado')->assertOk();
        $this->cancelarApi($this->usuario('mesero'), $otro, 'Pedido duplicado')->assertUnprocessable()->assertJsonValidationErrors('pedido');
    }

    public function test_no_se_cancela_con_pagos_activos(): void
    {
        $pedido = $this->pedidoCocinaYBarra();
        $this->cobrarEnCorteAbiertoEl($pedido, now('America/Mazatlan')->toDateTimeString());
        $this->cancelarApi($this->usuario('mesero'), $pedido, 'Cliente se retiró')->assertUnprocessable()->assertJsonValidationErrors('pagos');
        $this->assertSame('recibido', $pedido->fresh()->estado);
    }

    public function test_la_sesion_da_los_motivos_solo_al_personal_que_cancela(): void
    {
        $motivos = ['Cliente se retiró', 'Error en la captura', 'Producto agotado', 'Pedido duplicado', 'Demora excesiva', 'Otro'];
        foreach (['mesero', 'cajero', 'administrador'] as $rol) {
            $this->actingAs($this->usuario($rol))->getJson('/api/sesion')->assertJsonPath('motivos_cancelacion', $motivos);
        }
        foreach (['cliente1', 'cocinero'] as $clave) {
            $this->actingAs($this->usuario($clave))->getJson('/api/sesion')->assertJsonPath('motivos_cancelacion', []);
        }
        foreach ($motivos as $motivo) {
            $this->assertLessThanOrEqual(50, mb_strlen($motivo), 'La columna motivo_cancelacion es de 50 caracteres.');
        }
    }

    // ---------- HU-06 · Tablero de comandas por estación ----------

    private function tableroDe(User $actor, array $query = []): array
    {
        return $this->actingAs($actor)->getJson('/api/comandas'.($query ? '?'.http_build_query($query) : ''))->assertOk()->json();
    }

    /** Comandas del tablero que pertenecen a los pedidos indicados, como "folio|estacion". */
    private function comandasDe(array $tablero, Pedido ...$pedidos): array
    {
        $ids = array_map(fn (Pedido $p) => $p->id, $pedidos);

        return array_values(array_map(fn ($c) => $c['numero_pedido'].'|'.$c['estacion'],
            array_filter($tablero['comandas'], fn ($c) => in_array($c['id_pedido'], $ids, true))));
    }

    public function test_el_cocinero_de_una_estacion_solo_recibe_sus_comandas_y_sus_productos(): void
    {
        $pedido = $this->pedidoCocinaYBarra();
        $barra = $this->crearUsuario('cocinero_barra', 'cocinero', 'barra');

        // Aunque pida la otra estación, el servidor le impone la suya.
        foreach ([[], ['estacion' => 'cocina']] as $query) {
            $tablero = $this->tableroDe($barra, $query);
            $this->assertSame(['barra'], $tablero['estaciones']);
            $this->assertSame([], array_values(array_filter($tablero['comandas'], fn ($c) => $c['estacion'] !== 'barra')));
            $this->assertSame(["{$pedido->numero_pedido}|barra"], $this->comandasDe($tablero, $pedido));
        }
        $comanda = collect($this->tableroDe($barra)['comandas'])->firstWhere('id_pedido', $pedido->id);
        $nombresBarra = Producto::whereHas('categoria', fn ($q) => $q->where('estacion', 'barra'))->pluck('nombre')->all();
        $this->assertNotEmpty($comanda['items']);
        foreach ($comanda['items'] as $item) {
            $this->assertContains($item['nombre'], $nombresBarra, 'La comanda de barra no debe traer productos de cocina.');
        }
    }

    public function test_el_cocinero_general_ve_las_dos_estaciones_como_comandas_separadas(): void
    {
        $pedido = $this->pedidoCocinaYBarra();
        $tablero = $this->tableroDe($this->usuario('cocinero'), ['estacion' => 'barra']);
        $this->assertNull($tablero['estaciones']);
        $comandas = $this->comandasDe($tablero, $pedido);
        sort($comandas);
        $this->assertSame(["{$pedido->numero_pedido}|barra", "{$pedido->numero_pedido}|cocina"], $comandas);
    }

    public function test_el_administrador_ve_todas_y_puede_filtrar(): void
    {
        $pedido = $this->pedidoCocinaYBarra();
        $admin = $this->usuario('administrador');
        $this->assertCount(2, $this->comandasDe($this->tableroDe($admin), $pedido));
        $filtrado = $this->tableroDe($admin, ['estacion' => 'barra']);
        $this->assertSame(['barra'], $filtrado['estaciones']);
        $this->assertSame(["{$pedido->numero_pedido}|barra"], $this->comandasDe($filtrado, $pedido));
        // Un filtro desconocido equivale a "todas".
        $this->assertCount(2, $this->comandasDe($this->tableroDe($admin, ['estacion' => 'postres']), $pedido));
    }

    public function test_el_tablero_de_comandas_es_solo_para_cocineros_y_administrador(): void
    {
        foreach (['mesero', 'cajero', 'cliente1'] as $clave) {
            $this->actingAs($this->usuario($clave))->getJson('/api/comandas')->assertForbidden();
        }
        auth()->logout();
        $this->getJson('/api/comandas')->assertUnauthorized();
    }

    public function test_cancelados_no_aparecen_y_entregados_solo_con_la_casilla_y_de_hoy(): void
    {
        $admin = $this->usuario('administrador');
        $cancelado = $this->pedidoCocinaYBarra();
        app(PedidoService::class)->cancelar($this->usuario('mesero'), $cancelado->id, 'Pedido duplicado');

        $entregadoHoy = $this->entregarCompleto($this->pedidoCocinaYBarra());
        $entregadoAyer = $this->entregarCompleto($this->pedidoCocinaYBarra());
        $entregadoAyer->estaciones()->update(['updated_at' => now(config('trackeat.zona_folio'))->subDay()->setTime(12, 0)]);

        $this->assertSame([], $this->comandasDe($this->tableroDe($admin), $cancelado, $entregadoHoy, $entregadoAyer));
        $conEntregados = $this->tableroDe($admin, ['entregados' => 1]);
        $this->assertSame([], $this->comandasDe($conEntregados, $cancelado, $entregadoAyer));
        $this->assertCount(2, $this->comandasDe($conEntregados, $entregadoHoy));
    }

    public function test_las_comandas_van_de_la_mas_antigua_a_la_mas_nueva_y_traen_sus_notas(): void
    {
        $nuevo = $this->levantarPedido($this->usuario('mesero'), ['items' => [['id_producto' => Producto::firstOrFail()->id, 'cantidad' => 2, 'notas' => 'Sin cebolla']]]);
        $viejo = $this->levantarPedido($this->usuario('mesero'));
        $viejo->forceFill(['created_at' => now()->subMinutes(30)])->save();

        $tablero = $this->tableroDe($this->usuario('administrador'));
        $orden = array_values(array_filter(array_column($tablero['comandas'], 'id_pedido'), fn ($id) => in_array($id, [$nuevo->id, $viejo->id], true)));
        $this->assertSame([$viejo->id, $nuevo->id], $orden);
        $comanda = collect($tablero['comandas'])->firstWhere('id_pedido', $nuevo->id);
        $this->assertSame([['cantidad' => 2, 'notas' => 'Sin cebolla']], array_map(fn ($i) => ['cantidad' => $i['cantidad'], 'notas' => $i['notas']], $comanda['items']));
    }

    // ---------- HU-08 · Captura de pedido por mesero o cajero ----------

    public function test_el_cocinero_no_captura_pedidos(): void
    {
        $antes = Pedido::count();
        $this->actingAs($this->usuario('cocinero'))->postJson('/api/pedidos', $this->cuerpoApi())->assertForbidden();
        $this->assertSame($antes, Pedido::count());
    }

    public function test_el_pedido_del_mesero_llega_a_cocina_y_a_caja_con_su_folio_y_mesa(): void
    {
        $mesero = $this->usuario('mesero');
        $pedido = $this->actingAs($mesero)->postJson('/api/pedidos', $this->cuerpoApi(['tipo_entrega' => 'comer_aqui', 'num_mesa' => 12]))
            ->assertOk()->assertJsonPath('id_usuario', $mesero->id)->assertJsonPath('num_mesa', 12)->json();

        $comanda = collect($this->tableroDe($this->usuario('cocinero'))['comandas'])->firstWhere('id_pedido', $pedido['id']);
        $this->assertSame([$pedido['numero_pedido'], 'comer_aqui', 12], [$comanda['numero_pedido'], $comanda['tipo_entrega'], $comanda['num_mesa']]);

        $this->actingAs($this->usuario('cajero'))->get('/caja')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('caja/index', false)
                ->where('pedidos', fn ($pedidos) => collect($pedidos)->contains('id', $pedido['id'])));
    }

    public function test_el_cajero_captura_un_pedido_para_llevar_a_su_nombre(): void
    {
        $cajero = $this->usuario('cajero');
        $this->actingAs($cajero)->postJson('/api/pedidos', $this->cuerpoApi(['id_usuario' => $this->usuario('cliente1')->id]))
            ->assertOk()->assertJsonPath('id_usuario', $cajero->id)->assertJsonPath('tipo_entrega', 'para_llevar')->assertJsonPath('num_mesa', null);
    }

    // ---------- HU-09 · Pedidos por entregar (todo o nada) ----------

    private function porEntregar(User $actor, array $query = []): array
    {
        return $this->actingAs($actor)->getJson('/api/mesero/pedidos'.($query ? '?'.http_build_query($query) : ''))->assertOk()->json('pedidos');
    }

    private function listoPorCompleto(Pedido $pedido): Pedido
    {
        foreach ($pedido->estaciones()->pluck('estacion') as $estacion) {
            foreach (['preparacion', 'listo'] as $estado) {
                app(PedidoService::class)->avanzar($this->usuario('cocinero'), $pedido->id, $estacion, $estado);
            }
        }

        return $pedido->fresh();
    }

    public function test_la_entrega_es_todo_o_nada(): void
    {
        Event::fake([PedidoActualizado::class]);
        $pedido = $this->pedidoCocinaYBarra();
        foreach (['preparacion', 'listo'] as $estado) {
            app(PedidoService::class)->avanzar($this->usuario('cocinero'), $pedido->id, 'barra', $estado);
        }
        // Con cocina sin terminar, el pedido no se entrega (ni siquiera la barra que ya está lista).
        $this->actingAs($this->usuario('mesero'))->postJson("/api/pedidos/{$pedido->id}/entregar")->assertUnprocessable()
            ->assertJsonPath('errors.estado.0', 'El pedido todavía no está listo: todas sus estaciones deben estar listas.');
        $this->assertSame(['barra' => 'listo', 'cocina' => 'recibido'], $pedido->estaciones()->orderBy('estacion')->pluck('estado', 'estacion')->all());

        foreach (['preparacion', 'listo'] as $estado) {
            app(PedidoService::class)->avanzar($this->usuario('cocinero'), $pedido->id, 'cocina', $estado);
        }
        $mesero = $this->usuario('mesero');
        $this->actingAs($mesero)->postJson("/api/pedidos/{$pedido->id}/entregar")->assertOk()->assertJsonPath('estado', 'entregado');
        $this->assertSame(['entregado'], $pedido->estaciones()->pluck('estado')->unique()->all());
        $this->assertSame([$mesero->id], $pedido->historial()->where('estado_nuevo', 'entregado')->pluck('cambiado_por')->unique()->all());
        $this->assertSame(2, $pedido->historial()->where('estado_nuevo', 'entregado')->count());
        Event::assertDispatched(PedidoActualizado::class, fn ($e) => $e->pedido['id'] === $pedido->id && $e->pedido['estado'] === 'entregado');
        // No se entrega dos veces.
        $this->actingAs($mesero)->postJson("/api/pedidos/{$pedido->id}/entregar")->assertUnprocessable()->assertJsonPath('errors.estado.0', 'El pedido ya se entregó.');
    }

    public function test_entregan_mesero_cajero_y_administrador_pero_no_cocinero_ni_cliente(): void
    {
        foreach (['mesero', 'cajero', 'administrador'] as $rol) {
            $pedido = $this->listoPorCompleto($this->pedidoCocinaYBarra());
            $this->actingAs($this->usuario($rol))->postJson("/api/pedidos/{$pedido->id}/entregar")->assertOk();
        }
        $pedido = $this->listoPorCompleto($this->pedidoCocinaYBarra());
        $this->actingAs($this->usuario('cocinero'))->postJson("/api/pedidos/{$pedido->id}/entregar")->assertForbidden();
        $cliente = $this->crearUsuario('cliente_entrega', 'cliente');
        $propio = $this->listoPorCompleto($this->levantarPedido($cliente));
        $this->actingAs($cliente)->postJson("/api/pedidos/{$propio->id}/entregar")->assertForbidden();
        $this->assertSame('listo', $propio->fresh()->estado);
    }

    public function test_por_entregar_filtra_comer_aqui_y_pone_los_listos_primero(): void
    {
        $mesero = $this->usuario('mesero');
        $viejoEnPreparacion = $this->levantarPedido($mesero, ['tipo_entrega' => 'comer_aqui', 'num_mesa' => 1]);
        $viejoEnPreparacion->forceFill(['created_at' => now()->subMinutes(40)])->save();
        $nuevoListo = $this->listoPorCompleto($this->levantarPedido($mesero, ['tipo_entrega' => 'comer_aqui', 'num_mesa' => 2]));
        $paraLlevar = $this->levantarPedido($mesero);
        $entregado = $this->entregarCompleto($this->levantarPedido($mesero, ['tipo_entrega' => 'comer_aqui', 'num_mesa' => 3]));

        $ids = fn (array $lista) => array_values(array_filter(array_column($lista, 'id'),
            fn ($id) => in_array($id, [$viejoEnPreparacion->id, $nuevoListo->id, $paraLlevar->id, $entregado->id], true)));
        // Por defecto solo comer aquí, el listo primero aunque sea más nuevo; sin entregados.
        $this->assertSame([$nuevoListo->id, $viejoEnPreparacion->id], $ids($this->porEntregar($mesero)));
        $this->assertSame([$nuevoListo->id, $viejoEnPreparacion->id, $paraLlevar->id], $ids($this->porEntregar($mesero, ['entrega' => 'todos'])));
    }

    public function test_por_entregar_trae_productos_con_su_estacion_y_estado_y_el_saldo(): void
    {
        $pedido = $this->pedidoCocinaYBarra();
        foreach (['preparacion', 'listo'] as $estado) {
            app(PedidoService::class)->avanzar($this->usuario('cocinero'), $pedido->id, 'barra', $estado);
        }
        $pagado = $this->listoPorCompleto($this->pedidoCocinaYBarra());
        $this->cobrarEnCorteAbiertoEl($pagado, now('America/Mazatlan')->toDateTimeString());

        $lista = collect($this->porEntregar($this->usuario('cajero'), ['entrega' => 'todos']))->keyBy('id');
        $fila = $lista[$pedido->id];
        $this->assertSame('recibido', $fila['estado'], 'El pedido no está listo hasta que todas las estaciones lo están.');
        $this->assertSame(['barra' => 'listo', 'cocina' => 'recibido'], collect($fila['items'])->pluck('estado', 'estacion')->sortKeys()->all());
        $this->assertSame($pedido->total, $fila['saldo']);
        $this->assertSame('0.00', $lista[$pagado->id]['saldo']);
    }

    public function test_por_entregar_es_para_mesero_cajero_y_administrador(): void
    {
        foreach (['mesero', 'cajero', 'administrador'] as $rol) {
            $this->actingAs($this->usuario($rol))->getJson('/api/mesero/pedidos')->assertOk();
            $this->actingAs($this->usuario($rol))->get('/mesero/pedidos')->assertOk();
        }
        foreach (['cocinero', 'cliente1'] as $clave) {
            $this->actingAs($this->usuario($clave))->getJson('/api/mesero/pedidos')->assertForbidden();
            $this->actingAs($this->usuario($clave))->get('/mesero/pedidos')->assertForbidden();
        }
    }

    public function test_mesero_y_cajero_que_entran_a_comandas_van_a_por_entregar(): void
    {
        foreach (['mesero', 'cajero'] as $rol) {
            $this->actingAs($this->usuario($rol))->get('/comandas')->assertRedirect('/mesero/pedidos');
        }
        $this->actingAs($this->usuario('cocinero'))->get('/comandas')->assertOk();
        $this->actingAs($this->usuario('administrador'))->get('/comandas')->assertOk();
    }

    public function test_la_estacion_no_se_puede_asignar_en_masa(): void
    {
        $usuario = (new User)->fill(['name' => 'Prueba', 'estacion' => 'cocina']);
        $this->assertSame('Prueba', $usuario->name);
        $this->assertNull($usuario->estacion);
    }

    // ───── HU-10 · Administración de pedidos ─────

    private function adminPedidos(array $query = [])
    {
        return $this->actingAs($this->usuario('administrador'))->get('/admin/pedidos'.($query ? '?'.http_build_query($query) : ''));
    }

    /** Folios de la respuesta que pertenecen a los pedidos dados, en el orden de la lista (el seeder también crea pedidos de hoy). */
    private function propios($respuesta, Pedido ...$pedidos): array
    {
        $mios = array_map(fn (Pedido $p) => $p->numero_pedido, $pedidos);

        return array_values(array_filter($this->folios($respuesta), fn (string $folio) => in_array($folio, $mios, true)));
    }

    public function test_solo_el_administrador_entra_a_la_administracion_de_pedidos(): void
    {
        $pedido = $this->levantarPedido($this->usuario('cliente1'));
        foreach (['cliente1', 'cocinero', 'mesero', 'cajero'] as $clave) {
            $this->actingAs($this->usuario($clave))->get('/admin/pedidos')->assertForbidden();
            $this->actingAs($this->usuario($clave))->get("/admin/pedidos/{$pedido->id}")->assertForbidden();
        }
        $this->adminPedidos()->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/pedidos', false));
        $this->actingAs($this->usuario('administrador'))->get("/admin/pedidos/{$pedido->id}")->assertOk();
        auth()->logout();
        $this->get('/admin/pedidos')->assertRedirect('/login');
    }

    public function test_por_defecto_muestra_hoy_y_se_elige_otro_dia_o_todos(): void
    {
        $mesero = $this->usuario('mesero');
        $ayer = $this->pedidoCreadoEl($mesero, now('America/Mazatlan')->subDay()->setTime(12, 0)->toDateTimeString());
        $hoy = $this->levantarPedido($mesero);
        $diaHoy = now('America/Mazatlan')->toDateString();
        $diaAyer = now('America/Mazatlan')->subDay()->toDateString();

        foreach ([[], ['dia' => 'hola'], ['dia' => '2026-02-30']] as $query) {
            $respuesta = $this->adminPedidos($query)->assertInertia(fn (Assert $page) => $page->where('filtros.dia', $diaHoy));
            $this->assertSame([$hoy->numero_pedido], $this->propios($respuesta, $hoy, $ayer));
        }
        $this->assertSame([$ayer->numero_pedido], $this->propios($this->adminPedidos(['dia' => $diaAyer]), $hoy, $ayer));
        // Más reciente primero.
        $this->assertSame([$hoy->numero_pedido, $ayer->numero_pedido], $this->propios($this->adminPedidos(['dia' => 'todos']), $hoy, $ayer));
    }

    public function test_filtra_por_estado(): void
    {
        $mesero = $this->usuario('mesero');
        $cancelado = $this->levantarPedido($mesero);
        $activo = $this->levantarPedido($mesero);
        app(PedidoService::class)->cancelar($this->usuario('administrador'), $cancelado->id, 'Error en la captura');

        $this->assertSame([$cancelado->numero_pedido], $this->propios($this->adminPedidos(['estado' => 'cancelado']), $cancelado, $activo));
        $this->assertSame([$activo->numero_pedido], $this->propios($this->adminPedidos(['estado' => 'recibido']), $cancelado, $activo));
        $this->assertSame([], $this->propios($this->adminPedidos(['estado' => 'entregado']), $cancelado, $activo));
    }

    public function test_filtra_por_mesa_y_tipo_de_entrega_y_se_combinan(): void
    {
        $mesero = $this->usuario('mesero');
        $mesa5 = $this->levantarPedido($mesero, ['tipo_entrega' => 'comer_aqui', 'num_mesa' => 5]);
        $mesa7 = $this->levantarPedido($mesero, ['tipo_entrega' => 'comer_aqui', 'num_mesa' => 7]);
        $llevar = $this->levantarPedido($mesero);

        $todos = [$mesa5, $mesa7, $llevar];
        $this->assertSame([$mesa5->numero_pedido], $this->propios($this->adminPedidos(['mesa' => 5]), ...$todos));
        $this->assertSame([$mesa7->numero_pedido, $mesa5->numero_pedido], $this->propios($this->adminPedidos(['entrega' => 'comer_aqui']), ...$todos));
        $this->assertSame([$llevar->numero_pedido], $this->propios($this->adminPedidos(['entrega' => 'para_llevar']), ...$todos));
        $this->assertSame([$mesa7->numero_pedido], $this->propios($this->adminPedidos(['entrega' => 'comer_aqui', 'mesa' => 7, 'estado' => 'recibido']), ...$todos));
        $this->assertSame([], $this->propios($this->adminPedidos(['entrega' => 'para_llevar', 'mesa' => 7]), ...$todos));
    }

    public function test_filtra_por_cliente_por_nombre_correo_o_telefono_y_los_comodines_son_literales(): void
    {
        $ana = $this->crearUsuario('cliente_ana', 'cliente');
        $ana->forceFill(['telefono' => '6671112233'])->save();
        $beto = $this->crearUsuario('cliente_beto', 'cliente');
        $deAna = $this->levantarPedido($ana);
        $deBeto = $this->levantarPedido($beto);

        $this->assertSame([$deAna->numero_pedido], $this->folios($this->adminPedidos(['cliente' => 'cliente_ana Prueba'])));
        $this->assertSame([$deBeto->numero_pedido], $this->folios($this->adminPedidos(['cliente' => 'CLIENTE_BETO@prueba'])));
        $this->assertSame([$deAna->numero_pedido], $this->folios($this->adminPedidos(['cliente' => '1112233'])));
        // `%` y `_` se buscan tal cual: ningún usuario los tiene, así que no debe coincidir todo.
        $this->assertSame([], $this->folios($this->adminPedidos(['cliente' => '%'])));
        $this->assertSame([], $this->folios($this->adminPedidos(['cliente' => 'cliente_a_a'])));
    }

    public function test_el_corte_separa_los_pedidos_de_un_dia_con_dos_cortes(): void
    {
        $mesero = $this->usuario('mesero');
        $enPrimero = $this->pedidoCreadoEl($mesero, '2026-11-20 12:00:00');
        $enSegundo = $this->pedidoCreadoEl($mesero, '2026-11-20 17:00:00');
        $sinCobro = $this->pedidoCreadoEl($mesero, '2026-11-20 18:00:00');
        $corte1 = $this->cobrarEnCorteAbiertoEl($enPrimero, '2026-11-20 08:00:00', 'cajero_turno1')->id_corte;
        $corte2 = $this->cobrarEnCorteAbiertoEl($enSegundo, '2026-11-20 15:00:00', 'cajero_turno2')->id_corte;

        // Con el filtro de corte y sin día elegido no se aplica el día de hoy: el corte ya acota los pedidos.
        $this->assertSame([$enPrimero->numero_pedido], $this->folios($this->adminPedidos(['corte' => $corte1])));
        $this->assertSame([$enSegundo->numero_pedido], $this->folios($this->adminPedidos(['corte' => $corte2])));
        // Sin corte, el día reúne los tres.
        $this->assertSame([$sinCobro->numero_pedido, $enSegundo->numero_pedido, $enPrimero->numero_pedido], $this->folios($this->adminPedidos(['dia' => '2026-11-20'])));
        // El selector trae los cortes con su cajero.
        $cortes = collect($this->adminPedidos()->viewData('page')['props']['cortes'])->keyBy('id');
        $this->assertTrue($cortes->has($corte1));
        $this->assertSame('cajero_turno2 Prueba', $cortes[$corte2]['cajero']);
    }

    public function test_un_pago_corregido_ya_no_ubica_el_pedido_en_su_corte(): void
    {
        $pedido = $this->pedidoCreadoEl($this->usuario('mesero'), '2026-11-20 12:00:00');
        $pago = $this->cobrarEnCorteAbiertoEl($pedido, '2026-11-20 08:00:00', 'cajero_turno1');
        $this->assertSame([$pedido->numero_pedido], $this->folios($this->adminPedidos(['corte' => $pago->id_corte])));

        app(CajaService::class)->corregir(User::where('email', 'cajero_turno1@prueba.test')->firstOrFail(), $pago->id);
        $this->assertSame([], $this->folios($this->adminPedidos(['corte' => $pago->id_corte])));
    }

    public function test_los_filtros_invalidos_se_rechazan(): void
    {
        foreach ([['estado', 'raro'], ['mesa', 0], ['mesa', 999], ['mesa', 'x'], ['entrega', 'volando'], ['corte', 999999], ['corte', 'x'],
            ['cliente', str_repeat('a', 121)]] as [$campo, $valor]) {
            $this->adminPedidos([$campo => $valor])->assertSessionHasErrors($campo);
        }
    }

    public function test_las_salas_de_demostracion_no_aparecen_en_la_administracion(): void
    {
        $sala = $this->crearUsuario('cliente_sala', 'cliente');
        $sala->forceFill(['demo_room' => (string) Str::uuid()])->save();
        $deSala = $this->levantarPedido($sala);
        $real = $this->levantarPedido($this->usuario('mesero'));

        $folios = $this->folios($this->adminPedidos(['dia' => 'todos']));
        $this->assertContains($real->numero_pedido, $folios);
        $this->assertNotContains($deSala->numero_pedido, $folios);
        $this->actingAs($this->usuario('administrador'))->get("/admin/pedidos/{$deSala->id}")->assertNotFound();
    }

    public function test_el_detalle_trae_items_estaciones_bitacora_y_motivo_de_cancelacion(): void
    {
        $cliente = $this->usuario('cliente1');
        $pedido = $this->levantarPedido($cliente, ['items' => [['id_producto' => Producto::firstOrFail()->id, 'cantidad' => 2, 'notas' => 'sin cebolla']]]);
        $estacion = $pedido->estaciones()->firstOrFail()->estacion;
        app(PedidoService::class)->avanzar($this->usuario('cocinero'), $pedido->id, $estacion, 'preparacion');
        app(PedidoService::class)->cancelar($this->usuario('administrador'), $pedido->id, 'Pedido duplicado');

        $this->actingAs($this->usuario('administrador'))->get("/admin/pedidos/{$pedido->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/pedido', false)
                ->where('pedido.numero_pedido', $pedido->numero_pedido)
                ->where('pedido.motivo_cancelacion', 'Pedido duplicado')
                ->where('pedido.usuario.email', $cliente->email)
                ->where('pedido.items.0.notas', 'sin cebolla')
                ->where('pedido.items.0.producto.nombre', Producto::firstOrFail()->nombre)
                ->has('pedido.estaciones', 1)
                ->has('pedido.historial', 2)
                ->where('pedido.historial.0.estado_nuevo', 'preparacion')
                ->where('pedido.historial.0.responsable.rol', 'cocinero')
                ->where('pedido.historial.1.estado_nuevo', 'cancelado')
                ->where('pedido.historial.1.responsable.rol', 'administrador')
                ->missing('pedido.historial.0.responsable.email'));
    }

    public function test_un_pedido_inexistente_en_el_detalle_responde_404(): void
    {
        $this->actingAs($this->usuario('administrador'))->get('/admin/pedidos/999999')->assertNotFound();
    }

    public function test_la_lista_se_pagina_de_20_en_20_sin_consultas_por_fila(): void
    {
        $mesero = $this->usuario('mesero');
        foreach (range(1, 3) as $i) {
            $this->pedidoCreadoEl($mesero, '2026-11-10 12:00:00');
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->adminPedidos(['dia' => '2026-11-10'])->assertInertia(fn (Assert $page) => $page->has('pedidos.data', 3));
        $consultasConPocos = count(DB::getQueryLog());

        foreach (range(1, 22) as $i) {
            $this->pedidoCreadoEl($mesero, '2026-11-10 12:00:00');
        }
        DB::flushQueryLog();
        $this->adminPedidos(['dia' => '2026-11-10'])->assertInertia(fn (Assert $page) => $page
            ->has('pedidos.data', 20)->where('pedidos.current_page', 1)->where('pedidos.last_page', 2)->where('pedidos.total', 25));
        $this->assertSame($consultasConPocos, count(DB::getQueryLog()), 'Las consultas no deben crecer con el número de filas.');
        DB::disableQueryLog();
        $this->adminPedidos(['dia' => '2026-11-10', 'page' => 2])->assertInertia(fn (Assert $page) => $page->has('pedidos.data', 5));
    }

    // ───── T-09 · Flujo completo entre módulos ─────

    public function test_flujo_completo_mesero_cocina_entrega_caja_y_administracion(): void
    {
        $mesero = $this->usuario('mesero');
        $cocinero = $this->usuario('cocinero');
        $cajero = $this->crearUsuario('cajero_flujo', 'cajero');
        $servicio = app(PedidoService::class);

        // 1. El mesero captura un pedido de mesa con un producto de cocina y uno de barra: queda a su nombre.
        $pedido = $servicio->crear($mesero, ['tipo_entrega' => 'comer_aqui', 'num_mesa' => 4, 'items' => [
            ['id_producto' => Producto::where('nombre', 'Hamburguesa')->firstOrFail()->id, 'cantidad' => 2],
            ['id_producto' => Producto::where('nombre', 'Limonada')->firstOrFail()->id, 'cantidad' => 1],
        ]]);
        $this->assertMatchesRegularExpression('/^TE-\d{8}-\d{3}$/', $pedido->numero_pedido);
        $this->assertSame($mesero->id, $pedido->id_usuario);
        $this->assertSame('270.00', $pedido->total);
        $this->assertEqualsCanonicalizing(['cocina', 'barra'], $pedido->estaciones->pluck('estacion')->all());

        // 2. El cocinero atiende ambas estaciones hasta `listo`; el pedido sigue a la más atrasada.
        $servicio->avanzar($cocinero, $pedido->id, 'cocina', 'preparacion');
        $servicio->avanzar($cocinero, $pedido->id, 'cocina', 'listo');
        $this->assertSame('recibido', $pedido->fresh()->estado);
        $servicio->avanzar($cocinero, $pedido->id, 'barra', 'preparacion');
        $servicio->avanzar($cocinero, $pedido->id, 'barra', 'listo');
        $this->assertSame('listo', $pedido->fresh()->estado);

        // 3. El mesero lo entrega completo.
        $this->assertSame('entregado', $servicio->entregar($mesero, $pedido->id)->estado);

        // 4. El cajero lo cobra en su corte.
        $corte = app(CajaService::class)->abrir($cajero, '100');
        $pago = app(CajaService::class)->cobrar($cajero, $pedido->id, $corte->id, 'efectivo', '270.00');
        $this->assertSame($corte->id, $pago->id_corte);
        $this->assertSame('pagado', $pedido->fresh()->estadoPago());

        // 5. El administrador lo encuentra por su corte y ve la bitácora completa, con quién hizo cada paso.
        $this->assertSame([$pedido->numero_pedido], $this->folios($this->adminPedidos(['corte' => $corte->id, 'estado' => 'entregado'])));
        $this->actingAs($this->usuario('administrador'))->get("/admin/pedidos/{$pedido->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('pedido.historial', 6)
                ->where('pedido.historial.0.responsable.rol', 'cocinero')
                ->where('pedido.historial.5.estado_nuevo', 'entregado')->where('pedido.historial.5.responsable.rol', 'mesero'));

        // 6. Quien lo levantó lo sigue viendo en su página, ya entregado.
        $this->actingAs($mesero)->get("/pedidos/{$pedido->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pedido.estado', 'entregado')->where('pedido.numero_pedido', $pedido->numero_pedido));
    }

    // ───── Folio corto en caja ─────

    public function test_el_folio_visible_es_el_numero_del_dia_y_los_folios_anteriores_quedan_igual(): void
    {
        $this->assertSame('#006', Folio::visible('TE-20261125-006'));
        $this->assertSame('#1234', Folio::visible('TE-20261125-1234'));
        // Folios anteriores o de ejemplo, y valores que no encajan, se muestran tal cual.
        $this->assertSame('DEMO-001', Folio::visible('DEMO-001'));
        $this->assertSame('TE-01JABCDEFGHJKMNPQRSTVWXYZ0', Folio::visible('TE-01JABCDEFGHJKMNPQRSTVWXYZ0'));
        $this->assertSame('TE-20261125-006x', Folio::visible('TE-20261125-006x'));
        $this->assertSame('', Folio::visible(null));
    }

    public function test_al_cobrar_por_completo_el_mensaje_de_caja_usa_el_folio_corto(): void
    {
        $cajero = $this->crearUsuario('cajero_folio', 'cajero');
        app(CajaService::class)->abrir($cajero, '100');
        $nuevo = $this->levantarPedido($this->usuario('cliente1'));
        $anterior = $this->levantarPedido($this->usuario('cliente2'));
        $anterior->update(['numero_pedido' => 'DEMO-777']);

        $this->actingAs($cajero)->post("/caja/pedidos/{$nuevo->id}/cobrar", ['metodo_pago' => 'efectivo', 'monto' => $nuevo->total])
            ->assertRedirect()->assertSessionHas('status', 'Pedido #'.substr($nuevo->numero_pedido, -3).' pagado.');
        $this->actingAs($cajero)->post("/caja/pedidos/{$anterior->id}/cobrar", ['metodo_pago' => 'efectivo', 'monto' => $anterior->total])
            ->assertRedirect()->assertSessionHas('status', 'Pedido DEMO-777 pagado.');
    }
}
