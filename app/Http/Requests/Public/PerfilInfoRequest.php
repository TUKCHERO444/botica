<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PerfilInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:50',
            'apellido_paterno' => 'required|string|max:25',
            'apellido_materno' => 'required|string|max:25',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('cliente', 'email')->ignore($this->user()->id_cliente, 'id_cliente'),
            ],
            'telefono' => ['required', 'string', 'size:9', 'regex:/^9[0-9]{8}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'telefono.regex' => 'Ingrese un número de celular peruano válido (9 dígitos empezando con 9).',
            'telefono.size' => 'El teléfono debe tener 9 dígitos.',
        ];
    }
}
