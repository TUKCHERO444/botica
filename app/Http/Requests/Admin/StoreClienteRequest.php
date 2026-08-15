<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dni' => 'nullable|string|size:8|unique:cliente,dni',
            'nombre' => 'required|string|max:50',
            'apellido_paterno' => 'nullable|string|max:25',
            'apellido_materno' => 'nullable|string|max:25',
            'email' => 'nullable|email|max:255|unique:cliente,email',
            'telefono' => ['nullable', 'string', 'size:9', 'regex:/^9[0-9]{8}$/'],
        ];
    }
}
