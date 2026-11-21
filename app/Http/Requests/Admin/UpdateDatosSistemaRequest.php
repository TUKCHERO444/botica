<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDatosSistemaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nombre_negocio')) {
            $this->merge([
                'nombre_negocio' => trim((string) $this->input('nombre_negocio')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'nombre_negocio' => 'required|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_negocio.required' => 'Ingrese el nombre de la empresa o negocio.',
            'nombre_negocio.max' => 'El nombre del negocio no puede superar los 100 caracteres.',
        ];
    }
}
