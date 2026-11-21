<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:25|unique:servicio,nombre',
            'descripcion' => 'nullable|string|max:100',
            'precio' => 'required|numeric|decimal:0,2|min:0',
            'costo' => 'nullable|numeric|decimal:0,2|min:0',
        ];
    }
}
