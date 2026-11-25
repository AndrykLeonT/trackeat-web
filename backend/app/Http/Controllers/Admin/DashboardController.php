<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItemPedido;
use App\Models\Pago;
use App\Models\Pedido;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $timezone = 'America/Mazatlan';
        $today = CarbonImmutable::today($timezone);
        $start = $today->subDays(29);
        $end = $today->addDay();
        $from = $start->utc();
        $until = $end->utc();

        $orders = Pedido::activos()->whereNull('demo_room')->where('estado', '!=', 'cancelado');
        $ordersInRange = (clone $orders)->where('created_at', '>=', $from)->where('created_at', '<', $until);
        $counts = (clone $ordersInRange)->selectRaw('DATE(created_at AT TIME ZONE ?) AS dia, COUNT(*) AS pedidos', [$timezone])
            ->groupBy('dia')->pluck('pedidos', 'dia');
        $days = [];
        for ($day = $start; $day < $end; $day = $day->addDay()) {
            $date = $day->toDateString();
            $days[] = ['label' => $date, 'value' => (int) $counts->get($date, 0)];
        }

        $products = ItemPedido::query()->where('items_pedido.eliminado', false)->whereIn('id_pedido', (clone $ordersInRange)->select('id'))
            ->join('productos', 'productos.id', '=', 'items_pedido.id_producto')
            ->selectRaw('productos.nombre AS label, SUM(items_pedido.cantidad) AS value')
            ->groupBy('productos.id', 'productos.nombre')->orderByDesc('value')->orderBy('productos.id')->limit(10)
            ->get()->map(fn ($product) => ['label' => $product->label, 'value' => (int) $product->value]);

        $payments = Pago::activos()->where('reembolso', false)->whereIn('id_pedido', (clone $orders)->select('id'))
            ->where('created_at', '>=', $from)->where('created_at', '<', $until)
            ->selectRaw('metodo_pago, SUM(monto) AS total')->groupBy('metodo_pago')->pluck('total', 'metodo_pago');
        $sales = collect(['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia'])
            ->map(fn ($label, $method) => ['label' => $label, 'value' => (float) $payments->get($method, 0)])->values();

        // La recepción queda fechada al crear la estación. El historial fecha cuándo llegó a listo.
        $stations = DB::table('historial_estados as historial')
            ->join('pedidos_estacion as estaciones', function ($join) {
                $join->on('estaciones.id_pedido', '=', 'historial.id_pedido')
                    ->on('estaciones.estacion', '=', 'historial.estacion');
            })
            ->whereIn('historial.id_pedido', $orders->select('id'))->where('estaciones.eliminado', false)
            ->where('historial.estado_nuevo', 'listo')->whereColumn('historial.created_at', '>=', 'estaciones.created_at')
            ->where('historial.created_at', '>=', $from)->where('historial.created_at', '<', $until)
            ->selectRaw('historial.estacion AS label, AVG(EXTRACT(EPOCH FROM (historial.created_at - estaciones.created_at)) / 60) AS value')
            ->groupBy('historial.estacion')->orderBy('historial.estacion')->get()
            ->map(fn ($station) => ['label' => ucfirst($station->label), 'value' => round((float) $station->value, 2)]);

        return Inertia::render('admin/panel', [
            'from' => $start->toDateString(),
            'through' => $today->toDateString(),
            'days' => $days,
            'products' => $products,
            'sales' => $sales,
            'stations' => $stations,
            'salesTotal' => $payments->reduce(fn (string $total, $amount) => bcadd($total, (string) $amount, 2), '0.00'),
        ]);
    }
}
