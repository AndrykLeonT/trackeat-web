<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends DomainModel
{
    protected $fillable = ['id_pedido', 'id_usuario', 'id_corte', 'metodo_pago', 'monto', 'propina', 'reembolso', 'eliminado'];

    protected $table = 'pagos';

    protected function casts(): array
    {
        return [...parent::casts(), 'monto' => 'decimal:2', 'propina' => 'decimal:2', 'reembolso' => 'boolean'];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function corte(): BelongsTo
    {
        return $this->belongsTo(Corte::class, 'id_corte');
    }
}
