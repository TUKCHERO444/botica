<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComboRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('combos') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre'             => 'required|string|max:255',
            'descripcion'        => 'nullable|string|max:500',
            'imagen'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'precio_sugerido'    => 'required|numeric|min:0',
            'activo'             => 'boolean',
            'productos'          => 'required|array|min:1',
            'productos.*.id'     => 'required|integer|exists:producto,id_producto',
            'productos.*.cantidad' => 'required|integer|min:1',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre'             => 'nombre del combo',
            'descripcion'        => 'descripción',
            'imagen'             => 'imagen del combo',
            'precio_sugerido'    => 'precio sugerido',
            'productos'          => 'productos',
            'productos.*.id'     => 'producto',
            'productos.*.cantidad' => 'cantidad',
        ];
    }
}
