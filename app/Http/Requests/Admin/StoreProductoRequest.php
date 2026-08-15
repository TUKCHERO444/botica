<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:50|unique:producto,nombre',
            'descripcion' => 'nullable|string|max:50',
            'precio_compra' => 'nullable|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0',
            'estado' => 'required|integer|in:0,1',
            'inventario' => 'required|integer|min:0',
            'stock' => 'required|integer|min:0',
            'categoria_id_categoria' => 'required|integer|exists:categoria,id_categoria',
            'marca_id_marca' => 'required|integer|exists:marca,id_marca',
            'imagenes' => 'nullable|array|max:4',
            'imagenes.*' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
            'orden_imagenes' => 'nullable|array',
            'orden_imagenes.*' => 'integer|min:0',
        ];
    }

    public function attributes(): array
    {
        return [
            'imagenes' => 'imágenes del producto',
            'imagenes.*' => 'imagen',
        ];
    }
}
