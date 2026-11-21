<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ruc' => 'required|string|size:11|unique:proveedor,ruc',
            'razon_social' => 'required|string|max:150',
            'condicion' => 'required|string|max:15',
            'estado' => 'required|integer|in:0,1',
            'direccion' => 'nullable|string|max:255',
            'ubigeo' => 'nullable|string|size:6',
            'distrito' => 'nullable|string|max:100',
            'provincia' => 'nullable|string|max:100',
            'departamento' => 'nullable|string|max:100',
            'lead_time' => 'nullable|integer|min:0|max:365',
        ];
    }
}
