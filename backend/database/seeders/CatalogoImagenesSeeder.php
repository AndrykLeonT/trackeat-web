<?php

namespace Database\Seeders;

use App\Models\ImagenProducto;
use App\Models\Producto;
use App\Support\CatalogName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

// Fotos de ejemplo del catálogo. Cada archivo de `database/seeders/imagenes/` se asocia al producto que se llama
// igual (sin acentos ni mayúsculas): `hamburguesa.jpg` -> "Hamburguesa". Acepta JPG, PNG y WebP; un formato que GD no
// pueda leer (por ejemplo AVIF) se omite con un aviso.
//
// - Se recodifica a WebP, como la subida segura del admin, y se guarda con un UUID **fijo** derivado del nombre
//   (`catalogo/<uuid>.webp`): repetir el seeder, o correrlo en otro nodo, produce el mismo archivo y la misma ruta.
// - Solo reemplaza el marcador `/logo.svg` (o su propia ruta): nunca pisa una foto que alguien subió desde el admin
//   ni una imagen que el admin dio de baja.
//
// Docker:  php artisan db:seed --class=CatalogoImagenesSeeder --force
// (una vez si los nodos comparten el volumen `trackeat-catalogo`; en cada PC si están repartidos).
class CatalogoImagenesSeeder extends Seeder
{
    private const MARCADOR = '/logo.svg';

    public function run(): void
    {
        // Sin GLOB_BRACE: no existe en Alpine (musl), que es la base de la imagen de Docker.
        $archivos = array_values(array_filter(glob(database_path('seeders/imagenes/*')) ?: [], fn (string $f) => (bool) preg_match('/\.(jpe?g|png|webp)$/i', $f)));
        sort($archivos);
        $productos = Producto::activos()->get()->keyBy(fn (Producto $p) => CatalogName::key($p->nombre));

        foreach ($archivos as $archivo) {
            $nombre = basename($archivo);
            $clave = CatalogName::key(pathinfo($archivo, PATHINFO_FILENAME));
            $producto = $productos->get($clave);
            if (! $producto) {
                $this->aviso("{$nombre}: no hay un producto llamado \"{$clave}\"; se omite.");

                continue;
            }
            $webp = $this->aWebp($archivo);
            if ($webp === null) {
                $this->aviso("{$nombre}: no se pudo leer como imagen (formatos: JPG, PNG, WebP); se omite.");

                continue;
            }

            $archivoWebp = Uuid::uuid5(Uuid::NAMESPACE_DNS, 'imagenes.trackeat.demo/'.$clave)->toString().'.webp';
            $url = '/catalogo/imagenes/'.$archivoWebp;

            DB::transaction(function () use ($producto, $url, $webp, $archivoWebp, $nombre) {
                // Incluye filas dadas de baja: el índice único (producto, orden) también las cuenta.
                $fila = ImagenProducto::where('id_producto', $producto->id)->where('orden', 0)->first();
                if ($fila && ($fila->eliminado || ! in_array($fila->url, [self::MARCADOR, $url], true))) {
                    $this->aviso("{$nombre}: \"{$producto->nombre}\" ya tiene una imagen propia; no se toca.");

                    return;
                }
                // El archivo se escribe siempre: así un nodo o un volumen nuevo recupera la foto aunque la fila ya esté.
                Storage::disk('local')->put('catalogo/'.$archivoWebp, $webp);
                if ($fila) {
                    $fila->update(['url' => $url]);
                } else {
                    ImagenProducto::create(['id_producto' => $producto->id, 'url' => $url, 'orden' => 0]);
                }
            });
        }
    }

    /** Recodifica a WebP (calidad 85) con GD; devuelve los bytes o null si GD no puede leer el archivo. */
    private function aWebp(string $archivo): ?string
    {
        $imagen = @imagecreatefromstring((string) file_get_contents($archivo));
        if ($imagen === false) {
            return null;
        }
        try {
            if (! imageistruecolor($imagen) && ! imagepalettetotruecolor($imagen)) {
                return null;
            }
            imagealphablending($imagen, true);
            imagesavealpha($imagen, true);
            ob_start();
            $ok = imagewebp($imagen, null, 85);
            $bytes = ob_get_clean();

            return $ok && $bytes ? $bytes : null;
        } finally {
            imagedestroy($imagen);
        }
    }

    private function aviso(string $mensaje): void
    {
        $this->command?->warn($mensaje);
    }
}
