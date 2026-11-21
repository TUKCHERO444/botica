<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMetodoPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:50|unique:metodo_pago,nombre',
            'descripcion' => 'nullable|string|max:100',
            'estado' => 'required|integer|in:0,1',
            'afecta_efectivo' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe un método de pago con ese nombre.',
        ];
    }
}
