<?php

namespace App\Http\Requests\Admin;

use App\Models\Ingreso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecibirCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pagado_desde_caja' => 'nullable|boolean',
            'metodo_pago' => [
                'nullable',
                'string',
                Rule::in(array_keys(Ingreso::METODOS_PAGO)),
                Rule::requiredIf(fn () => filter_var($this->input('pagado_desde_caja', false), FILTER_VALIDATE_BOOLEAN)),
            ],
            'referencia_pago' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'metodo_pago.required' => 'Seleccione el método de pago para registrar el egreso de caja.',
            'metodo_pago.in' => 'El método de pago no es válido.',
        ];
    }
}
