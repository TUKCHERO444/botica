<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class RegistroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dni' => 'required|string|size:8|unique:cliente,dni',
            'nombre' => 'required|string|max:50',
            'apellido_paterno' => 'required|string|max:25',
            'apellido_materno' => 'required|string|max:25',
            'telefono' => ['required', 'string', 'size:9', 'regex:/^9[0-9]{8}$/'],
            'email' => 'required|email|max:255|unique:cliente,email',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'dni.unique' => 'Este DNI ya está registrado.',
            'dni.size' => 'El DNI debe tener 8 dígitos.',
            'telefono.regex' => 'Ingrese un número de celular peruano válido (9 dígitos empezando con 9).',
            'telefono.size' => 'El teléfono debe tener 9 dígitos.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }
}
