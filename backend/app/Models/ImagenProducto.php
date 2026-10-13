<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImagenProducto extends DomainModel
{
    protected $fillable = ['id_producto', 'url', 'orden', 'eliminado'];

    protected $table = 'imagenes_producto';

    protected function casts(): array
    {
        return [...parent::casts(), 'orden' => 'integer'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto');
    }
}
