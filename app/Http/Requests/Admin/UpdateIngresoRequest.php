<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIngresoRequest extends FormRequest
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
            'productos.*.lote' => 'required|string|max:50',
            'productos.*.fecha_vencimiento' => 'required|date',
        ];
    }

    public function messages(): array
    {
        return [
            'productos.required' => 'Debe agregar al menos un producto.',
            'productos.min' => 'Debe agregar al menos un producto.',
            'productos.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
            'productos.*.lote.required' => 'El número de lote es obligatorio para cada producto.',
            'productos.*.fecha_vencimiento.required' => 'La fecha de vencimiento es obligatoria para cada producto.',
        ];
    }
}
