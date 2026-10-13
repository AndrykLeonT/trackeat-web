<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends DomainModel
{
    protected $fillable = ['id_categoria', 'nombre', 'descripcion', 'precio', 'disponible', 'eliminado'];

    protected $table = 'productos';

    protected function casts(): array
    {
        return [...parent::casts(), 'precio' => 'decimal:2', 'disponible' => 'boolean'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'id_categoria');
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(ImagenProducto::class, 'id_producto')->orderBy('orden');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemPedido::class, 'id_producto');
    }
}
