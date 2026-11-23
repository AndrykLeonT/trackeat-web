<?php

namespace App\Http\Requests\Catalogo;

use App\Models\Categoria;
use App\Support\CatalogName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === 'administrador' && ! $this->user()->eliminado;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->nombre)) {
            $this->merge(['nombre' => trim($this->nombre)]);
        }
    }

    public function rules(): array
    {
        return ['nombre' => ['required', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/u'], 'estacion' => ['required', Rule::in(['general', 'cocina', 'barra'])], 'disponible' => ['required', 'boolean']];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $id = $this->route('categoria')?->id;
            if (Categoria::whereRaw('catalogo_nombre_key(nombre) = ?', [CatalogName::key($this->nombre)])->when($id, fn ($q) => $q->where('id', '<>', $id))->exists()) {
                $validator->errors()->add('nombre', 'Ya existe esa categoría, incluso si está eliminada. Puede restaurarla.');
            }
        }];
    }
}
