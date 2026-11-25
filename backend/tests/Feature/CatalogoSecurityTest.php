<?php

namespace Tests\Feature;

use App\Events\PedidoActualizado;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Services\CatalogoService;
use App\Support\CatalogName;
use App\Support\SecurityLogRetention;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogoSecurityTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([PedidoActualizado::class]);
        $this->seed(DemoSeeder::class);
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::where('rol', 'administrador')->firstOrFail();
    }

    private function client(): User
    {
        return User::where('email', 'cliente1@example.test')->firstOrFail();
    }

    private function productData(): array
    {
        return ['nombre' => 'Taco', 'descripcion' => 'Texto de prueba', 'id_categoria' => Categoria::first()->id, 'precio' => '40.50', 'disponible' => true, 'imagenes_existentes' => [], 'orden_imagenes' => []];
    }

    public function test_only_active_administrators_can_manage_catalog(): void
    {
        $this->getJson('/admin/productos')->assertUnauthorized();
        foreach (['cliente', 'cocinero', 'mesero', 'cajero'] as $role) {
            $this->actingAs(User::where('rol', $role)->first())->get('/admin/productos')->assertForbidden();
            $this->postJson('/admin/productos', $this->productData())->assertForbidden();
            $this->deleteJson('/admin/productos/'.Producto::first()->id)->assertForbidden();
            $this->postJson('/admin/categorias', ['nombre' => 'Prueba', 'estacion' => 'cocina', 'disponible' => true])->assertForbidden();
        }
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/productos')->assertOk();
        $admin->forceFill(['eliminado' => true])->save();
        $this->get('/admin/productos')->assertForbidden();
    }

    public function test_public_catalog_includes_exhausted_but_hides_deleted_and_hidden_categories(): void
    {
        $product = Producto::first();
        $product->update(['disponible' => false]);
        $this->getJson('/api/catalogo')->assertJsonFragment(['id' => $product->id, 'disponible' => false]);
        $product->update(['eliminado' => true]);
        $this->getJson('/api/catalogo')->assertJsonMissing(['nombre' => $product->nombre]);
        $product->update(['eliminado' => false]);
        $product->categoria->update(['disponible' => false]);
        $this->getJson('/api/catalogo')->assertJsonMissing(['nombre' => $product->nombre]);
        $this->get('/menu/'.$product->id)->assertNotFound();
    }

    public function test_category_duplicates_are_normalized_and_reserved_after_deletion(): void
    {
        $this->actingAs($this->admin());
        $this->postJson('/admin/categorias', ['nombre' => ' Café ', 'estacion' => 'barra', 'disponible' => true])->assertRedirect('/admin/categorias');
        $category = Categoria::where('nombre', 'Café')->firstOrFail();
        $this->postJson('/admin/categorias', ['nombre' => 'cAFE', 'estacion' => 'barra', 'disponible' => true])->assertUnprocessable()->assertJsonValidationErrors('nombre');
        $this->deleteJson('/admin/categorias/'.$category->id)->assertRedirect();
        $this->postJson('/admin/categorias', ['nombre' => 'cafe', 'estacion' => 'barra', 'disponible' => true])->assertUnprocessable();
        $this->postJson('/admin/categorias/'.$category->id.'/restaurar')->assertRedirect();
        $this->assertFalse($category->fresh()->eliminado);
    }

    public function test_product_names_are_unique_within_category_and_price_is_validated(): void
    {
        $this->actingAs($this->admin());
        $data = $this->productData();
        $this->postJson('/admin/productos', $data)->assertRedirect('/admin/productos');
        $this->postJson('/admin/productos', [...$data, 'nombre' => ' TÁCO '])->assertUnprocessable()->assertJsonValidationErrors('nombre');
        $this->postJson('/admin/productos', [...$data, 'id_categoria' => Categoria::orderByDesc('id')->first()->id])->assertRedirect();
        foreach (['0', '-1', '1.234', '1e2', '10000000000.00'] as $price) {
            $this->postJson('/admin/productos', [...$data, 'nombre' => 'Otro', 'precio' => $price])->assertUnprocessable()->assertJsonValidationErrors('precio');
        }
        $this->assertSame(CatalogName::key('  CAFÉ '), DB::selectOne('SELECT catalogo_nombre_key(?) AS nombre', ['  CAFÉ '])->nombre);
    }

    public function test_database_also_rejects_duplicate_names(): void
    {
        $this->expectException(QueryException::class);
        Categoria::create(['nombre' => ' bébidas ', 'estacion' => 'barra']);
    }

    public function test_sql_metacharacters_are_saved_as_values(): void
    {
        $name = "Sopa'); DROP TABLE productos;--";
        $this->actingAs($this->admin())->postJson('/admin/productos', [...$this->productData(), 'nombre' => $name])->assertRedirect();
        $this->assertSame(5, Producto::count());
        $this->assertTrue(Producto::where('nombre', $name)->exists());
    }

    public function test_logical_removal_and_restoration_preserve_historical_items(): void
    {
        $this->actingAs($this->admin());
        $product = Producto::first();
        $category = $product->categoria;
        $before = DB::table('items_pedido')->get()->toJson();
        $this->deleteJson('/admin/categorias/'.$category->id)->assertUnprocessable();
        foreach ($category->productos as $row) {
            $this->deleteJson('/admin/productos/'.$row->id)->assertRedirect();
        }
        $this->deleteJson('/admin/categorias/'.$category->id)->assertRedirect();
        $this->postJson('/admin/productos/'.$product->id.'/restaurar')->assertUnprocessable();
        $this->postJson('/admin/categorias/'.$category->id.'/restaurar')->assertRedirect();
        $this->postJson('/admin/productos/'.$product->id.'/restaurar')->assertRedirect();
        $this->assertSame($before, DB::table('items_pedido')->get()->toJson());
    }

    public function test_station_edits_do_not_rewrite_existing_orders(): void
    {
        $category = Categoria::where('estacion', 'cocina')->first();
        $before = DB::table('items_pedido')->get()->toJson();
        $this->actingAs($this->admin())->postJson('/admin/categorias/'.$category->id, ['nombre' => $category->nombre, 'estacion' => 'barra', 'disponible' => true])->assertRedirect();
        $this->assertSame($before, DB::table('items_pedido')->get()->toJson());
    }

    public function test_uploads_are_reencoded_ordered_and_served_as_inert_webp(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/productos', [...$this->productData(), 'imagenes' => [UploadedFile::fake()->image('one.jpg', 60, 40), UploadedFile::fake()->image('two.png', 30, 30)], 'orden_imagenes' => ['nueva:1', 'nueva:0']])->assertRedirect('/admin/productos');
        $product = Producto::where('nombre', 'Taco')->firstOrFail();
        $images = $product->imagenes;
        $this->assertCount(2, $images);
        $this->assertStringEndsWith('.webp', $images[0]->url);
        $this->get($images[0]->url)->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->postJson('/admin/productos/'.$product->id, [...$this->productData(), 'imagenes_existentes' => [$images[1]->id, $images[0]->id], 'orden_imagenes' => ['existente:'.$images[1]->id, 'existente:'.$images[0]->id]])->assertRedirect();
        $this->assertSame($images[1]->id, $product->imagenes()->activos()->first()->id);
        $single = [...$this->productData(), 'imagenes_existentes' => [$images[1]->id], 'orden_imagenes' => ['existente:'.$images[1]->id]];
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/admin/productos/'.$product->id, $single)->assertRedirect();
        }
        $this->assertLessThanOrEqual(6, $product->imagenes()->max('orden'));
        $this->postJson('/admin/productos/'.$product->id, $this->productData())->assertRedirect();
        $this->getJson($images[0]->url)->assertNotFound();
        $this->assertSame(0, $product->imagenes()->activos()->count());
    }

    public function test_palette_png_uploads_are_converted_to_webp_without_error(): void
    {
        $this->actingAs($this->admin());
        $palette = imagecreate(24, 24);
        imagecolorallocate($palette, 20, 120, 80);
        ob_start();
        imagepng($palette);
        $bytes = ob_get_clean();
        imagedestroy($palette);
        $file = UploadedFile::fake()->createWithContent('indexed.png', $bytes);
        $this->post('/admin/productos', [...$this->productData(), 'imagenes' => [$file], 'orden_imagenes' => ['nueva:0']])->assertRedirect('/admin/productos');
        $product = Producto::where('nombre', 'Taco')->firstOrFail();
        $this->assertCount(1, $product->imagenes);
        $this->get($product->imagenes[0]->url)->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_upload_validation_rejects_disguises_svg_large_images_and_six_files(): void
    {
        $this->actingAs($this->admin());
        foreach ([UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo "unsafe";'), UploadedFile::fake()->createWithContent('evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'), UploadedFile::fake()->image('large.jpg')->size(2049), UploadedFile::fake()->image('wide.jpg', 4097, 1)] as $file) {
            $this->postJson('/admin/productos', [...$this->productData(), 'imagenes' => [$file], 'orden_imagenes' => ['nueva:0']])->assertUnprocessable();
        }
        $this->postJson('/admin/productos', [...$this->productData(), 'imagenes' => array_map(fn ($i) => UploadedFile::fake()->image("$i.jpg"), range(0, 5)), 'orden_imagenes' => array_map(fn ($i) => 'nueva:'.$i, range(0, 5))])->assertUnprocessable();
        $this->assertSame(4, Producto::count());
        $this->assertEmpty(Storage::disk('local')->allFiles('catalogo'));
    }

    public function test_foreign_image_ids_and_incomplete_order_are_rejected(): void
    {
        $this->actingAs($this->admin());
        $foreign = Producto::orderByDesc('id')->first()->imagenes()->first();
        $this->postJson('/admin/productos/'.Producto::first()->id, [...$this->productData(), 'imagenes_existentes' => [$foreign->id], 'orden_imagenes' => ['existente:'.$foreign->id]])->assertUnprocessable();
        $this->postJson('/admin/productos', [...$this->productData(), 'orden_imagenes' => ['nueva:0']])->assertUnprocessable();
    }

    public function test_transaction_failure_cleans_new_files_and_database_rows(): void
    {
        $bad = UploadedFile::fake()->createWithContent('bad.jpg', 'invalid');
        try {
            app(CatalogoService::class)->producto([...$this->productData(), 'orden_imagenes' => ['nueva:0', 'nueva:1']], [UploadedFile::fake()->image('ok.jpg'), $bad]);
            $this->fail('The second image must fail.');
        } catch (ValidationException $e) {
            $this->assertSame(4, Producto::count());
            $this->assertEmpty(Storage::disk('local')->allFiles('catalogo'));
        }
    }

    public function test_clients_cannot_enumerate_orders_by_id(): void
    {
        $client = $this->client();
        $own = Pedido::where('id_usuario', $client->id)->firstOrFail();
        $other = Pedido::where('id_usuario', '<>', $client->id)->firstOrFail();
        $this->getJson('/api/pedidos/'.$own->id)->assertUnauthorized();
        $this->actingAs($client)->getJson('/api/pedidos/'.$own->id)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/pedidos/'.$own->id)->assertOk();
        $this->getJson('/api/pedidos/'.$other->id)->assertNotFound();
        $this->get('/pedidos/'.$other->id)->assertNotFound();
        $this->getJson('/api/pedidos/999999')->assertNotFound();
        $this->patchJson('/api/pedidos/'.$other->id.'/estacion', ['estacion' => 'cocina', 'estado' => 'preparacion'])->assertNotFound();
        foreach (['administrador', 'cocinero', 'mesero', 'cajero'] as $role) {
            $this->actingAs(User::where('rol', $role)->first())->getJson('/api/pedidos/'.$other->id)->assertOk();
        }
    }

    public function test_changed_price_is_rejected_but_lost_response_retries_remain_idempotent(): void
    {
        $product = Producto::first();
        $data = ['client_request_id' => (string) Str::uuid(), 'tipo_entrega' => 'para_llevar', 'items' => [['id_producto' => $product->id, 'cantidad' => 1, 'precio_confirmado' => $product->precio]]];
        $this->actingAs($this->client());
        $product->update(['precio' => '999.00']);
        $this->postJson('/api/pedidos', $data)->assertStatus(409);
        $this->assertSame(4, Pedido::count());
        $data['items'][0]['precio_confirmado'] = '999.00';
        $first = $this->postJson('/api/pedidos', $data)->assertOk()->json('id');
        $product->update(['precio' => '1000.00']);
        $this->assertSame($first, $this->postJson('/api/pedidos', $data)->assertOk()->json('id'));
    }

    public function test_security_audit_uses_allowlist_and_never_records_input_or_identifiers(): void
    {
        $events = [];
        $logger = \Mockery::mock();
        $logger->shouldReceive('info')->andReturnUsing(function ($event, $context) use (&$events) {
            $events[] = $context;
        });
        Log::shouldReceive('channel')->with('security')->andReturn($logger);
        $this->actingAs($this->client())->postJson('/admin/productos', [...$this->productData(), 'nombre' => "secret@example.test\r\nforged", 'password' => 'NeverLogMe', 'token' => 'PrivateToken'])->assertForbidden();
        $this->assertNotEmpty($events);
        $json = json_encode($events);
        foreach (['secret@example.test', 'NeverLogMe', 'PrivateToken', 'id_usuario', 'password', 'token', 'forged'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $this->assertSame(['event', 'role', 'result', 'reason', 'route', 'method', 'status', 'request_id'], array_keys($events[0]));
        $this->assertSame(403, $events[0]['status']);
    }

    public function test_security_audit_marks_unauthenticated_redirects_as_rejected(): void
    {
        $events = [];
        $logger = \Mockery::mock();
        $logger->shouldReceive('info')->andReturnUsing(function ($event, $context) use (&$events) {
            $events[] = $context;
        });
        Log::shouldReceive('channel')->with('security')->andReturn($logger);
        $this->post('/admin/productos', $this->productData())->assertRedirect('/login');
        $this->assertCount(1, $events);
        $this->assertSame('request_rejected', $events[0]['event']);
        $this->assertSame('rejected', $events[0]['result']);
        $this->assertSame('authentication_required', $events[0]['reason']);
        $this->assertSame('visitante', $events[0]['role']);
        $this->assertSame(302, $events[0]['status']);
    }

    public function test_security_retention_uses_calendar_days_and_does_not_touch_other_logs(): void
    {
        $directory = Storage::disk('local')->path('test-logs');
        mkdir($directory, 0700, true);
        $old = $directory.'/security-'.now()->subDays(30)->format('Y-m-d').'.jsonl';
        $recent = $directory.'/security-'.now()->subDays(29)->format('Y-m-d').'.jsonl';
        file_put_contents($old, '{}');
        file_put_contents($recent, '{}');
        file_put_contents($directory.'/laravel.log', 'untouched');
        SecurityLogRetention::prune($directory);
        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
        $this->assertFileExists($directory.'/laravel.log');
    }
}
