<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends DomainModel
{
    protected $fillable = ['nombre', 'estacion', 'disponible', 'eliminado'];

    protected $table = 'categorias';

    protected function casts(): array
    {
        return [...parent::casts(), 'disponible' => 'boolean'];
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'id_categoria');
    }
}
