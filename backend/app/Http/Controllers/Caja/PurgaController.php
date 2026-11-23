<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Services\PurgaService;
use App\Support\DomainAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurgaController extends Controller
{
    use PresentaCaja;

    public function __construct(private readonly PurgaService $purga) {}

    // Solo se pueden purgar capturas eliminadas de un corte abierto: lo cerrado es inmutable.
    public function index(Request $request): Response
    {
        DomainAccess::requireRole($request->user(), ['administrador']);
        $pagos = Pago::where('eliminado', true)
            ->whereHas('corte', fn ($q) => $q->where('estado', 'abierto'))
            ->with(['pedido:id,numero_pedido', 'usuario:id,name'])
            ->orderBy('id')
            ->get();

        return Inertia::render('caja/purga', ['pagos' => $pagos->map(fn (Pago $pago) => $this->pago($pago))]);
    }

    public function destroy(Request $request, Pago $pago): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        // PurgaService vuelve a comprobar el rol y la contraseña antes de borrar.
        $this->purga->pago($request->user(), $pago->id, $request->input('password'));

        return back()->with('status', 'Pago purgado definitivamente.');
    }
}
