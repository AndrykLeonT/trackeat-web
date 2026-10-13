<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Corte extends DomainModel
{
    protected $fillable = ['id_usuario', 'estado', 'fecha_apertura', 'fecha_cierre', 'total_caja_inicial', 'total_caja_final', 'total_efectivo', 'total_tarjeta', 'total_transferencia', 'total_tarjeta_real', 'total_transferencia_real', 'total_propinas', 'total_sistema', 'total_real', 'eliminado'];

    protected $table = 'cortes';

    protected function casts(): array
    {
        return [...parent::casts(), 'fecha_apertura' => 'immutable_datetime', 'fecha_cierre' => 'immutable_datetime',
            ...array_fill_keys(['total_caja_inicial', 'total_caja_final', 'total_efectivo', 'total_tarjeta', 'total_transferencia',
                'total_tarjeta_real', 'total_transferencia_real', 'total_propinas', 'total_sistema', 'total_real'], 'decimal:2')];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'id_corte');
    }
}
