<?php

namespace App\Http\Requests\Admin;

use App\Models\AjusteInventario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAjusteInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'string', Rule::in(array_keys(AjusteInventario::TIPOS))],
            'fecha' => 'nullable|date',
            'motivo' => 'required|string|max:255',
            'observaciones' => 'nullable|string|max:500',
            'lineas' => 'required|array|min:1',
            'lineas.*.producto_lote_id' => 'required|integer|exists:producto_lote,id',
            'lineas.*.cantidad' => 'required|numeric|decimal:0,3|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'Seleccione el tipo de ajuste.',
            'tipo.in' => 'El tipo de ajuste no es válido.',
            'motivo.required' => 'Indique el motivo del ajuste.',
            'lineas.required' => 'Debe agregar al menos un lote.',
            'lineas.min' => 'Debe agregar al menos un lote.',
            'lineas.*.producto_lote_id.required' => 'Seleccione un lote.',
            'lineas.*.cantidad.required' => 'Ingrese una cantidad.',
            'lineas.*.cantidad.min' => 'La cantidad no puede ser negativa.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $lineas = collect($this->input('lineas', []));

            if ($lineas->pluck('producto_lote_id')->duplicates()->isNotEmpty()) {
                $validator->errors()->add('lineas', 'No se puede repetir el mismo lote en un ajuste.');
            }

            if ($this->input('tipo') !== 'conteo_fisico'
                && $lineas->filter(fn ($linea) => (float) ($linea['cantidad'] ?? 0) > 0)->isEmpty()) {
                $validator->errors()->add('lineas', 'Ingrese una cantidad mayor a cero en al menos un lote.');
            }
        });
    }
}
