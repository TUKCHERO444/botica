<?php

namespace App\Http\Requests\Admin;

use App\Models\CajaMovimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIngresoCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monto' => 'required|numeric|decimal:0,2|min:0.01',
            'descripcion' => 'required|string|min:5|max:500',
            'metodo_pago_id' => [
                'required',
                'integer',
                Rule::exists('metodo_pago', 'id_metodo_pago')->where('estado', 1),
            ],
            'categoria' => ['required', 'string', Rule::in(array_keys(CajaMovimiento::CATEGORIAS_INGRESO_MANUAL))],
            'comprobante' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'monto.required' => 'Debe ingresar un monto.',
            'monto.min' => 'El monto debe ser mayor a cero.',
            'descripcion.required' => 'Debe ingresar una descripción del ingreso.',
            'descripcion.min' => 'La descripción debe tener al menos 5 caracteres.',
            'metodo_pago_id.required' => 'Seleccione el método de pago del ingreso.',
            'metodo_pago_id.exists' => 'El método de pago seleccionado no está activo.',
            'categoria.required' => 'Seleccione la categoría del ingreso.',
            'categoria.in' => 'La categoría seleccionada no es válida.',
        ];
    }
}
