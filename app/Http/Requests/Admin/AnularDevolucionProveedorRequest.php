<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AnularDevolucionProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_anulacion' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_anulacion.required' => 'Indique el motivo de la anulación.',
        ];
    }
}
