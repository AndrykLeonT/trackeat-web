<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Corte;
use App\Services\CajaService;
use App\Support\DomainAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CorteController extends Controller
{
    use PresentaCaja;

    public function __construct(private readonly CajaService $caja) {}

    public function create(Request $request): Response|RedirectResponse
    {
        DomainAccess::requireRole($request->user(), ['cajero']);
        // No se abre otro turno sin hacer el corte del que sigue abierto.
        if ($this->corteDe($request->user())) {
            return to_route('caja.turno')->with('status', 'Primero haz el corte de tu turno actual.');
        }

        return Inertia::render('caja/abrir');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['total_caja_inicial' => $this->reglaDinero()], $this->mensajesDinero(['total_caja_inicial']));
        $this->caja->abrir($request->user(), $request->input('total_caja_inicial'));

        return to_route('caja.index')->with('status', 'Turno abierto.');
    }

    public function turno(Request $request): Response|RedirectResponse
    {
        DomainAccess::requireRole($request->user(), ['cajero']);
        $corte = $this->corteDe($request->user());
        if (! $corte) {
            return to_route('caja.abrir')->with('status', 'No tienes un turno abierto.');
        }

        return Inertia::render('caja/turno', $this->detalle($request, $corte));
    }

    public function cerrarForm(Request $request): Response|RedirectResponse
    {
        DomainAccess::requireRole($request->user(), ['cajero']);
        $corte = $this->corteDe($request->user());
        if (! $corte) {
            return to_route('caja.abrir')->with('status', 'No tienes un turno abierto.');
        }

        return Inertia::render('caja/cerrar', [
            'corte' => $this->corte($corte->load('usuario:id,name')),
            'resumen' => $this->caja->resumen($request->user(), $corte->id),
        ]);
    }

    public function cerrar(Request $request): RedirectResponse
    {
        $campos = ['total_caja_final', 'total_tarjeta_real', 'total_transferencia_real'];
        $request->validate(array_fill_keys($campos, $this->reglaDinero()), $this->mensajesDinero($campos));
        DomainAccess::requireRole($request->user(), ['cajero']);
        $corte = $this->corteDe($request->user());
        if (! $corte) {
            return to_route('caja.abrir')->with('status', 'No tienes un turno abierto.');
        }
        $this->caja->cerrar($request->user(), $corte->id, $request->input('total_caja_final'),
            $request->input('total_tarjeta_real'), $request->input('total_transferencia_real'));

        return to_route('caja.cortes.show', $corte)->with('status', 'Corte cerrado.');
    }

    // El cajero ve sus cortes cerrados; el administrador, los de todos.
    public function index(Request $request): Response
    {
        $actor = $request->user();
        DomainAccess::requireRole($actor, ['cajero', 'administrador']);
        $cortes = Corte::activos()->where('estado', 'cerrado')
            ->when($actor->rol !== 'administrador', fn ($q) => $q->where('id_usuario', $actor->id))
            ->with('usuario:id,name')
            ->orderByDesc('fecha_cierre')
            ->limit(100)
            ->get();

        return Inertia::render('caja/cortes', [
            'cortes' => $cortes->map(fn (Corte $corte) => [
                ...$this->corte($corte),
                'total_sistema' => $corte->total_sistema,
                'total_propinas' => $corte->total_propinas,
            ]),
        ]);
    }

    public function show(Request $request, Corte $corte): Response
    {
        // resumen() valida que sea su corte o que sea administrador.
        return Inertia::render('caja/corte', $this->detalle($request, $corte));
    }

    private function detalle(Request $request, Corte $corte): array
    {
        $resumen = $this->caja->resumen($request->user(), $corte->id);
        $corte->load(['usuario:id,name', 'pagos' => fn ($q) => $q->with(['pedido:id,numero_pedido', 'usuario:id,name'])->orderBy('id')]);

        return [
            'corte' => $this->corte($corte),
            'resumen' => $resumen,
            'pagos' => $corte->pagos->map(fn ($pago) => $this->pago($pago)),
        ];
    }
}
