<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:25|unique:servicio,nombre,' . $this->route('servicio')->id_servicio . ',id_servicio',
            'descripcion' => 'nullable|string|max:100',
            'precio' => 'required|numeric|min:0',
        ];
    }
}
