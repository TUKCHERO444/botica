<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreEgresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monto' => 'required|numeric|min:0.01',
            'descripcion' => 'required|string|min:5',
        ];
    }

    public function messages(): array
    {
        return [
            'monto.required' => 'Debe ingresar un monto.',
            'monto.min' => 'El monto debe ser mayor a cero.',
            'descripcion.required' => 'Debe ingresar una descripción del egreso.',
            'descripcion.min' => 'La descripción debe tener al menos 5 caracteres.',
        ];
    }
}
