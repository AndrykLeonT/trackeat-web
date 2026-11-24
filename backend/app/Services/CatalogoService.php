<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogoService
{
    public function categoria(array $data, ?Categoria $category = null): Categoria
    {
        return $this->unique(fn () => DB::transaction(function () use ($data, $category) {
            if ($category) {
                $category = Categoria::lockForUpdate()->findOrFail($category->id);
            }
            if ($category?->eliminado) {
                $this->invalid('categoria', 'Restaure la categoría antes de editarla.');
            }
            $category ??= new Categoria;
            $category->fill($data)->save();

            return $category;
        }));
    }

    public function producto(array $data, array $files, ?Producto $product = null): Producto
    {
        $created = [];
        try {
            return $this->unique(function () use ($data, $files, $product, &$created) {
                return DB::transaction(function () use ($data, $files, $product, &$created) {
                    // Orders also lock products before categories.
                    if ($product) {
                        $product = Producto::lockForUpdate()->findOrFail($product->id);
                    }
                    $category = Categoria::lockForUpdate()->findOrFail($data['id_categoria']);
                    if ($category->eliminado) {
                        $this->invalid('id_categoria', 'La categoría está eliminada.');
                    }
                    if ($product?->eliminado) {
                        $this->invalid('producto', 'Restaure el producto antes de editarlo.');
                    }
                    $product ??= new Producto;
                    $product->fill(array_intersect_key($data, array_flip(['nombre', 'descripcion', 'id_categoria', 'precio', 'disponible'])))->save();
                    $retained = $product->imagenes()->activos()->whereIn('id', $data['imagenes_existentes'])->get()->keyBy('id');
                    if ($retained->count() !== count($data['imagenes_existentes'])) {
                        $this->invalid('imagenes_existentes', 'La galería cambió. Vuelva a cargar el formulario.');
                    }
                    // The unique index includes deleted rows: move all rows out of the target range first.
                    $offset = ($product->imagenes()->max('orden') ?? 0) + 10;
                    $product->imagenes()->update(['orden' => DB::raw('orden + '.(int) $offset)]);
                    $product->imagenes()->whereNotIn('id', $data['imagenes_existentes'])->update(['eliminado' => true]);
                    // Compact tombstone positions so repeated edits do not double the maximum order.
                    foreach ($product->imagenes()->where('eliminado', true)->orderBy('id')->get() as $index => $deleted) {
                        $deleted->update(['orden' => 5 + $index]);
                    }
                    foreach ($data['orden_imagenes'] as $position => $ref) {
                        [$kind, $id] = explode(':', $ref);
                        if ($kind === 'existente') {
                            $retained[(int) $id]->update(['orden' => $position]);

                            continue;
                        }
                        $file = $files[(int) $id];
                        $bytes = file_get_contents($file->getRealPath());
                        $image = @imagecreatefromstring($bytes);
                        if ($image === false) {
                            $this->invalid('imagenes', 'No se pudo decodificar la imagen.');
                        }
                        $path = 'catalogo/'.Str::uuid().'.webp';
                        try {
                            if (! imageistruecolor($image) && ! imagepalettetotruecolor($image)) {
                                throw new \RuntimeException('No se pudo convertir la imagen.');
                            }
                            imagealphablending($image, true);
                            imagesavealpha($image, true);
                            ob_start();
                            $ok = imagewebp($image, null, 85);
                            $encoded = ob_get_clean();
                            if (! $ok || ! $encoded) {
                                throw new \RuntimeException('No se pudo convertir la imagen.');
                            }
                            $created[] = $path;
                            if (! Storage::disk('local')->put($path, $encoded)) {
                                throw new \RuntimeException('No se pudo guardar la imagen.');
                            }
                        } finally {
                            imagedestroy($image);
                        }
                        $product->imagenes()->create(['url' => '/catalogo/imagenes/'.basename($path), 'orden' => $position]);
                    }

                    return $product;
                });
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($created);
            throw $error;
        }
    }

    public function bajaCategoria(Categoria $category, bool $restore): void
    {
        DB::transaction(function () use ($category, $restore) {
            $category = Categoria::lockForUpdate()->findOrFail($category->id);
            if (! $restore && $category->productos()->activos()->exists()) {
                $this->invalid('categoria', 'Mueva o elimine primero los productos de esta categoría.');
            }
            $category->update(['eliminado' => ! $restore]);
        });
    }

    public function bajaProducto(Producto $product, bool $restore): void
    {
        DB::transaction(function () use ($product, $restore) {
            $product = Producto::lockForUpdate()->findOrFail($product->id);
            $category = Categoria::lockForUpdate()->findOrFail($product->id_categoria);
            if ($restore && ($category->eliminado || $category->id !== $product->id_categoria)) {
                $this->invalid('producto', 'Restaure la categoría y vuelva a cargar antes de restaurar el producto.');
            }
            $product->update(['eliminado' => ! $restore]);
        });
    }

    private function unique(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (QueryException $e) {
            if ($e->getCode() === '23505' && str_contains($e->getMessage(), 'nombre_catalogo_unique')) {
                $this->invalid('nombre', 'Ya existe ese nombre. Revise también los registros eliminados.');
            }
            throw $e;
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
