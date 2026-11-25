<?php

namespace Tests\Feature;

use App\Models\ImagenProducto;
use App\Models\Producto;
use Database\Seeders\CatalogoImagenesSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Fotos de ejemplo del catálogo (database/seeders/imagenes): se siembran de forma repetible y sin pisar fotos del admin.
class CatalogoImagenesSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
    }

    private function urlDe(string $producto): string
    {
        return ImagenProducto::where('id_producto', Producto::where('nombre', $producto)->value('id'))->where('orden', 0)->value('url');
    }

    public function test_cada_foto_de_la_carpeta_es_un_jpg_png_o_webp_que_se_puede_leer(): void
    {
        $archivos = glob(database_path('seeders/imagenes/*'));
        $this->assertNotEmpty($archivos);
        foreach ($archivos as $archivo) {
            // Un AVIF con extensión .jpg, o un nombre con extensión doble, no sirve: la subida del admin tampoco lo acepta.
            $this->assertMatchesRegularExpression('/^[a-z0-9]+\.(jpg|jpeg|png|webp)$/', basename($archivo), basename($archivo));
            $info = getimagesize($archivo);
            $this->assertContains($info['mime'] ?? null, ['image/jpeg', 'image/png', 'image/webp'], basename($archivo));
            $this->assertNotFalse(@imagecreatefromstring(file_get_contents($archivo)), basename($archivo).' no se puede decodificar con GD.');
        }
    }

    public function test_siembra_una_foto_por_producto_y_la_sirve_en_webp_a_un_visitante(): void
    {
        $this->assertSame('/logo.svg', $this->urlDe('Hamburguesa'));

        $this->seed(CatalogoImagenesSeeder::class);

        foreach (['Hamburguesa', 'Papas', 'Limonada', 'Snack'] as $producto) {
            $url = $this->urlDe($producto);
            $this->assertMatchesRegularExpression('#^/catalogo/imagenes/[a-f0-9-]{36}\.webp$#', $url, $producto);
            Storage::disk('local')->assertExists('catalogo/'.basename($url));
            // El menú es público: un visitante (sin sesión) la ve.
            $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
        }
    }

    public function test_repetirlo_deja_las_mismas_rutas_y_una_sola_fila_por_producto(): void
    {
        $this->seed(CatalogoImagenesSeeder::class);
        $antes = $this->urlDe('Papas');
        $filas = ImagenProducto::count();

        $this->seed(CatalogoImagenesSeeder::class);

        $this->assertSame($antes, $this->urlDe('Papas'));
        $this->assertSame($filas, ImagenProducto::count());
    }

    public function test_recupera_el_archivo_en_un_nodo_o_volumen_nuevo_aunque_la_fila_ya_exista(): void
    {
        $this->seed(CatalogoImagenesSeeder::class);
        $archivo = 'catalogo/'.basename($this->urlDe('Limonada'));
        Storage::disk('local')->delete($archivo);

        $this->seed(CatalogoImagenesSeeder::class);

        Storage::disk('local')->assertExists($archivo);
    }

    public function test_no_pisa_una_foto_propia_ni_una_imagen_dada_de_baja(): void
    {
        $propia = '/catalogo/imagenes/11111111-1111-4111-8111-111111111111.webp';
        ImagenProducto::where('id_producto', Producto::where('nombre', 'Hamburguesa')->value('id'))->update(['url' => $propia]);
        ImagenProducto::where('id_producto', Producto::where('nombre', 'Snack')->value('id'))->update(['eliminado' => true]);

        $this->seed(CatalogoImagenesSeeder::class);

        $this->assertSame($propia, $this->urlDe('Hamburguesa'));
        $this->assertSame('/logo.svg', $this->urlDe('Snack'));
        $this->assertCount(2, Storage::disk('local')->files('catalogo'), 'Solo Papas y Limonada se siembran.');
    }

    public function test_un_producto_nuevo_sin_imagen_recibe_su_foto_si_hay_un_archivo_con_su_nombre(): void
    {
        ImagenProducto::where('id_producto', Producto::where('nombre', 'Papas')->value('id'))->delete();

        $this->seed(CatalogoImagenesSeeder::class);

        $this->assertMatchesRegularExpression('#^/catalogo/imagenes/[a-f0-9-]{36}\.webp$#', $this->urlDe('Papas'));
    }
}
