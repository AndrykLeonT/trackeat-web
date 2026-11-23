<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalogo\CategoriaRequest;
use App\Http\Requests\Catalogo\ProductoRequest;
use App\Models\Categoria;
use App\Models\ImagenProducto;
use App\Models\Producto;
use App\Services\CatalogoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CatalogoController extends Controller
{
    public function publico()
    {
        return response()->json([
            'categorias' => Categoria::activos()->where('disponible', true)->orderBy('nombre')->get(['id', 'nombre', 'estacion']),
            'productos' => Producto::activos()->whereHas('categoria', fn ($q) => $q->activos()->where('disponible', true))
                ->with(['imagenes' => fn ($q) => $q->activos()])->orderBy('nombre')->get()->map(fn ($p) => [
                    'id' => $p->id, 'id_categoria' => $p->id_categoria, 'nombre' => $p->nombre,
                    'descripcion' => $p->descripcion, 'precio' => $p->precio, 'disponible' => $p->disponible,
                    'imagen' => $p->imagenes->first()?->url ?? '/logo.svg',
                    'imagenes' => $p->imagenes->map(fn ($i) => ['id' => $i->id, 'url' => $i->url, 'orden' => $i->orden]),
                ]),
            'actualizado' => now()->toISOString(),
        ])->header('Cache-Control', 'no-store');
    }

    public function menu(?string $producto = null)
    {
        if ($producto !== null) {
            abort_unless(Producto::activos()->whereKey($producto)->whereHas('categoria', fn ($q) => $q->activos()->where('disponible', true))->exists(), 404);
        }

        return response()->file(public_path('menu/index.html'), ['Cache-Control' => 'no-cache']);
    }

    public function imagen(Request $request, string $archivo)
    {
        $url = '/catalogo/imagenes/'.$archivo;
        $image = ImagenProducto::activos()->where('url', $url)->with('producto.categoria')->firstOrFail();
        $public = ! $image->producto->eliminado && ! $image->producto->categoria->eliminado && $image->producto->categoria->disponible;
        $admin = $request->user()?->rol === 'administrador' && ! $request->user()->eliminado;
        abort_unless($public || $admin, 404);
        $path = Storage::disk('local')->path('catalogo/'.$archivo);
        abort_unless(is_file($path), 404);

        // Always serve inert, re-encoded bytes, never an uploaded filename or MIME type.
        return response()->file($path, ['Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
    }

    public function productos()
    {
        return Inertia::render('admin/productos', ['productos' => Producto::with('categoria')->orderBy('nombre')->get()]);
    }

    public function formularioProducto(?Producto $producto = null)
    {
        abort_if($producto?->eliminado, 404);

        return Inertia::render('admin/producto-form', [
            'producto' => $producto?->load(['imagenes' => fn ($q) => $q->activos()]),
            'categorias' => Categoria::activos()->orderBy('nombre')->get(),
        ]);
    }

    public function guardarProducto(ProductoRequest $request, CatalogoService $service, ?Producto $producto = null)
    {
        $service->producto($request->validated(), $request->file('imagenes', []), $producto);

        return redirect('/admin/productos')->with('success', 'Producto guardado.');
    }

    public function bajaProducto(Producto $producto, CatalogoService $service)
    {
        $service->bajaProducto($producto, false);

        return back()->with('success', 'Producto eliminado. Puede restaurarlo.');
    }

    public function restaurarProducto(Producto $producto, CatalogoService $service)
    {
        $service->bajaProducto($producto, true);

        return back()->with('success', 'Producto restaurado.');
    }

    public function categorias()
    {
        return Inertia::render('admin/categorias', ['categorias' => Categoria::withCount(['productos' => fn ($q) => $q->activos()])->orderBy('nombre')->get()]);
    }

    public function formularioCategoria(?Categoria $categoria = null)
    {
        abort_if($categoria?->eliminado, 404);

        return Inertia::render('admin/categoria-form', ['categoria' => $categoria]);
    }

    public function guardarCategoria(CategoriaRequest $request, CatalogoService $service, ?Categoria $categoria = null)
    {
        $service->categoria($request->validated(), $categoria);

        return redirect('/admin/categorias')->with('success', 'Categoría guardada.');
    }

    public function bajaCategoria(Categoria $categoria, CatalogoService $service)
    {
        $service->bajaCategoria($categoria, false);

        return back()->with('success', 'Categoría eliminada. Puede restaurarla.');
    }

    public function restaurarCategoria(Categoria $categoria, CatalogoService $service)
    {
        $service->bajaCategoria($categoria, true);

        return back()->with('success', 'Categoría restaurada.');
    }
}
