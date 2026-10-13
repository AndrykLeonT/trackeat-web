<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPedido extends DomainModel
{
    protected $fillable = ['id_pedido', 'id_producto', 'cantidad', 'precio_unitario', 'estacion', 'notas', 'eliminado'];

    protected $table = 'items_pedido';

    protected function casts(): array
    {
        return [...parent::casts(), 'precio_unitario' => 'decimal:2', 'cantidad' => 'integer'];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto');
    }
}
