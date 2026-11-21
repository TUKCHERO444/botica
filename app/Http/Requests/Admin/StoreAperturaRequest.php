<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAperturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monto_inicial' => 'required|numeric|decimal:0,2|min:0',
            'observacion_apertura' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'monto_inicial.required' => 'Debe ingresar un monto inicial para abrir la caja.',
            'monto_inicial.min' => 'El monto inicial no puede ser negativo.',
        ];
    }
}
