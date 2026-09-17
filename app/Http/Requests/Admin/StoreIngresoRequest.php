<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreIngresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proveedor_id_proveedor' => 'required|integer|exists:proveedor,id_proveedor',
            'fecha' => 'nullable|date',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id_producto' => 'required|integer|exists:producto,id_producto',
            'productos.*.cantidad' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'productos.required' => 'Debe agregar al menos un producto.',
            'productos.min' => 'Debe agregar al menos un producto.',
            'productos.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
        ];
    }
}
