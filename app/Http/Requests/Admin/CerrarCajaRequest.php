<?php

namespace App\Http\Requests\Admin;

use App\Models\Caja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CerrarCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'efectivo_contado' => 'required|numeric|decimal:0,2|min:0',
            'observacion_cierre' => 'nullable|string|max:1000',
            'motivo_diferencia' => [
                'nullable',
                'string',
                Rule::in(array_keys(Caja::MOTIVOS_DIFERENCIA)),
            ],
            'confirmo_cero' => 'nullable|boolean',
            'denominaciones' => 'nullable|array',
            'denominaciones.*' => 'nullable|integer|min:0|max:100000',
        ];
    }

    public function messages(): array
    {
        return [
            'efectivo_contado.required' => 'Debe ingresar el efectivo contado en el arqueo.',
            'efectivo_contado.min' => 'El efectivo contado no puede ser negativo.',
            'motivo_diferencia.in' => 'El motivo de la diferencia no es válido.',
            'denominaciones.*.integer' => 'Las cantidades del conteo deben ser números enteros.',
            'denominaciones.*.min' => 'Las cantidades del conteo no pueden ser negativas.',
        ];
    }
}
