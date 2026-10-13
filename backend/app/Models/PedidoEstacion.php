<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoEstacion extends DomainModel
{
    protected $fillable = ['id_pedido', 'estacion', 'estado', 'eliminado'];

    protected $table = 'pedidos_estacion';

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido');
    }
}
