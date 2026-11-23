<?php

namespace App\Http\Requests\Catalogo;

use App\Models\Producto;
use App\Support\CatalogName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductoRequest extends FormRequest
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
        $this->merge(['imagenes_existentes' => $this->input('imagenes_existentes', []), 'orden_imagenes' => $this->input('orden_imagenes', [])]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/u'], 'descripcion' => ['nullable', 'string', 'max:5000', 'not_regex:/\x00/u'],
            'id_categoria' => ['required', 'integer', Rule::exists('categorias', 'id')->where(fn ($query) => $query->where('eliminado', false))],
            'precio' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'disponible' => ['required', 'boolean'],
            'imagenes_existentes' => ['present', 'array', 'max:5'], 'imagenes_existentes.*' => ['integer', 'distinct'],
            'imagenes' => ['sometimes', 'array', 'max:5'],
            'imagenes.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
            'orden_imagenes' => ['present', 'array', 'max:5'], 'orden_imagenes.*' => ['required', 'string', 'distinct', 'regex:/^(existente|nueva):\d+$/'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $product = $this->route('producto');
            if (Producto::where('id_categoria', $this->id_categoria)->whereRaw('catalogo_nombre_key(nombre) = ?', [CatalogName::key($this->nombre)])->when($product, fn ($q) => $q->where('id', '<>', $product->id))->exists()) {
                $validator->errors()->add('nombre', 'Ya existe ese producto en la categoría, incluso si está eliminado.');
            }
            $existing = array_map('intval', $this->input('imagenes_existentes', []));
            $owned = $product ? $product->imagenes()->activos()->whereIn('id', $existing)->count() : 0;
            if ($owned !== count($existing)) {
                $validator->errors()->add('imagenes_existentes', 'Las imágenes no pertenecen a este producto.');
            }
            $expected = array_merge(array_map(fn ($id) => 'existente:'.$id, $existing), array_map(fn ($i) => 'nueva:'.$i, array_keys($this->file('imagenes', []))));
            $order = $this->input('orden_imagenes', []);
            sort($expected);
            sort($order);
            if (count($expected) > 5 || $expected !== $order) {
                $validator->errors()->add('orden_imagenes', 'El orden debe incluir cada imagen exactamente una vez, máximo cinco.');
            }
        }];
    }
}
