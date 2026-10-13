<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class DomainModel extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['eliminado' => 'boolean'];
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('eliminado', false);
    }
}
