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
            'monto_inicial' => 'required|numeric|min:0',
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
