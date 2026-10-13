<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialEstado extends DomainModel
{
    protected $fillable = ['id_pedido', 'cambiado_por', 'estacion', 'estado_anterior', 'estado_nuevo'];

    protected $table = 'historial_estados';

    public const UPDATED_AT = null;

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cambiado_por');
    }
}
