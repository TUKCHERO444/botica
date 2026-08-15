<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMarcaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:50|unique:marca,nombre',
            'descripcion' => 'nullable|string|max:50',

            'estado' => 'required|integer|in:0,1',
        ];
    }
}
