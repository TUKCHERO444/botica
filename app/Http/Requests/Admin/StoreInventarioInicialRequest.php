<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventarioInicialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.producto_presentacion_id' => [
                'required',
                'integer',
                'exists:producto_presentacion,id_producto_presentacion',
            ],
            'lineas.*.codigo_lote' => ['required', 'string', 'max:50'],
            'lineas.*.fecha_vencimiento' => ['required', 'date', 'after_or_equal:today'],
            'lineas.*.cantidad_presentacion' => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
            'lineas.*.costo_unitario_presentacion' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'lineas.required' => 'Agrega al menos una línea.',
            'lineas.*.producto_presentacion_id.required' => 'Selecciona un producto.',
            'lineas.*.codigo_lote.required' => 'Indica el lote.',
            'lineas.*.fecha_vencimiento.required' => 'Indica el vencimiento.',
            'lineas.*.fecha_vencimiento.after_or_equal' => 'El vencimiento no puede ser pasado.',
            'lineas.*.cantidad_presentacion.gt' => 'La cantidad debe ser mayor que cero.',
            'lineas.*.costo_unitario_presentacion.min' => 'El costo no puede ser negativo.',
        ];
    }
}
