<?php

namespace App\Http\Requests\Admin;

use App\Models\Ingreso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDevolucionProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ingreso_id_ingreso' => 'required|integer|exists:ingreso,id_ingreso',
            'fecha' => 'nullable|date',
            'motivo' => 'required|string|max:255',
            'reembolsado_a_caja' => 'nullable|boolean',
            'metodo_pago' => [
                'nullable',
                'string',
                Rule::in(array_keys(Ingreso::METODOS_PAGO)),
                Rule::requiredIf(fn () => filter_var($this->input('reembolsado_a_caja', false), FILTER_VALIDATE_BOOLEAN)),
            ],
            'referencia_pago' => 'nullable|string|max:100',
            'lineas' => 'required|array|min:1',
            'lineas.*.detalle_ingreso_id' => 'required|integer|exists:detalle_ingreso,id_detalle_ingreso',
            'lineas.*.cantidad' => 'nullable|numeric|decimal:0,3|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'ingreso_id_ingreso.required' => 'Seleccione la compra a devolver.',
            'motivo.required' => 'Indique el motivo de la devolución.',
            'metodo_pago.required' => 'Seleccione el método de reembolso cuando el dinero ingresa a caja.',
            'lineas.required' => 'Debe seleccionar al menos una línea.',
            'lineas.min' => 'Debe seleccionar al menos una línea.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $lineas = collect($this->input('lineas', []));

            if ($lineas->filter(fn ($linea) => (float) ($linea['cantidad'] ?? 0) > 0)->isEmpty()) {
                $validator->errors()->add('lineas', 'Ingrese una cantidad mayor a cero en al menos una línea.');
            }

            if ($lineas->pluck('detalle_ingreso_id')->duplicates()->isNotEmpty()) {
                $validator->errors()->add('lineas', 'No se puede repetir la misma línea en una devolución.');
            }
        });
    }
}
