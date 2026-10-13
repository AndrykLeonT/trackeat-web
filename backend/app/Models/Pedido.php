<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends DomainModel
{
    protected $fillable = ['id_usuario', 'numero_pedido', 'estado', 'tipo_entrega', 'num_mesa', 'total', 'eliminado'];

    protected $table = 'pedidos';

    protected function casts(): array
    {
        return [...parent::casts(), 'total' => 'decimal:2', 'num_mesa' => 'integer'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function estaciones(): HasMany
    {
        return $this->hasMany(PedidoEstacion::class, 'id_pedido');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemPedido::class, 'id_pedido');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialEstado::class, 'id_pedido')->orderBy('id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'id_pedido');
    }

    public function saldo(): string
    {
        return Money::subtract($this->total, (string) $this->pagos()->activos()->where('reembolso', false)->sum('monto'));
    }

    public function estadoPago(): string
    {
        $saldo = $this->saldo();

        return bccomp($saldo, '0', 2) <= 0 ? 'pagado' : (bccomp($saldo, $this->total, 2) === 0 ? 'pendiente' : 'parcial');
    }
}
