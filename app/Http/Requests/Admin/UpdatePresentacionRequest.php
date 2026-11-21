<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePresentacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('presentaciones') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:50',
                Rule::unique('presentacion', 'nombre')->ignore($this->route('presentacion')),
            ],
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
