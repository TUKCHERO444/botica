<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnidadMedidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('unidades-medida') ?? false;
    }

    public function rules(): array
    {
        return [
            'codigo' => [
                'required',
                'string',
                'max:10',
                Rule::unique('unidad_medida', 'codigo')->ignore($this->route('unidadMedida'), 'id_unidad_medida'),
            ],
            'nombre' => 'required|string|max:50',
            'permite_fraccion' => 'required|boolean',
            'presentacion_base_id' => 'nullable|integer|exists:presentacion,id_presentacion',
            'activo' => 'required|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'nombre' => 'nombre',
            'permite_fraccion' => 'permite fracción',
            'presentacion_base_id' => 'presentación base',
            'activo' => 'estado',
        ];
    }
}
