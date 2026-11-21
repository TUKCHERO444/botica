<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePresentacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('presentaciones') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:50|unique:presentacion,nombre',
            'abreviatura' => 'nullable|string|max:10',
            'activo' => 'required|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre de la presentación',
            'abreviatura' => 'abreviatura',
            'activo' => 'estado',
        ];
    }
}
