<?php

namespace App\Http\Requests\Admin;

use App\Helpers\CantidadHelper;
use App\Models\Ingreso;
use App\Models\ProductoPresentacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIngresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proveedor_id_proveedor' => 'required|integer|exists:proveedor,id_proveedor',
            'fecha' => 'nullable|date',
            'tipo_documento' => ['required', 'string', Rule::in(array_keys(Ingreso::TIPOS_DOCUMENTO))],
            'numero_documento' => [
                'required',
                'string',
                'max:30',
                Rule::unique('ingreso', 'numero_documento')
                    ->ignore($this->route('ingreso')?->id_ingreso, 'id_ingreso')
                    ->where(fn ($query) => $query
                        ->where('proveedor_id_proveedor', $this->input('proveedor_id_proveedor'))
                        ->where('tipo_documento', $this->input('tipo_documento'))),
            ],
            'observaciones' => 'nullable|string|max:500',
            'pagado_desde_caja' => 'nullable|boolean',
            'metodo_pago' => [
                'nullable',
                'string',
                Rule::in(array_keys(Ingreso::METODOS_PAGO)),
                Rule::requiredIf(fn () => $this->pagaDesdeCaja()),
            ],
            'referencia_pago' => 'nullable|string|max:100',
            'productos' => 'required|array|min:1',
            'productos.*.producto_presentacion_id' => 'required|integer|exists:producto_presentacion,id_producto_presentacion',
            'productos.*.cantidad' => 'required|numeric|decimal:0,3|gt:0',
            'productos.*.lote' => 'required|string|max:50',
            'productos.*.fecha_vencimiento' => 'required|date|after_or_equal:today',
            'productos.*.costo_unitario' => 'nullable|numeric|decimal:0,2|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'proveedor_id_proveedor.required' => 'Seleccione un proveedor.',
            'tipo_documento.required' => 'Seleccione el tipo de documento del proveedor.',
            'tipo_documento.in' => 'El tipo de documento no es válido.',
            'numero_documento.required' => 'Ingrese el número de documento del proveedor.',
            'numero_documento.unique' => 'Ya existe una compra registrada con ese documento para este proveedor.',
            'metodo_pago.required' => 'Seleccione el método de pago cuando la compra se paga desde caja.',
            'productos.required' => 'Debe agregar al menos un producto.',
            'productos.min' => 'Debe agregar al menos un producto.',
            'productos.*.cantidad.gt' => 'La cantidad debe ser mayor que cero.',
            'productos.*.lote.required' => 'El código de lote es obligatorio.',
            'productos.*.fecha_vencimiento.required' => 'La fecha de vencimiento es obligatoria.',
            'productos.*.fecha_vencimiento.after_or_equal' => 'El vencimiento no puede ser pasado.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('productos', []) as $i => $p) {
                $presentacion = ProductoPresentacion::find($p['producto_presentacion_id'] ?? null);

                if (! $presentacion || ! $presentacion->activo) {
                    $validator->errors()->add(
                        "productos.{$i}.producto_presentacion_id",
                        'La presentación seleccionada no está disponible.'
                    );

                    continue;
                }

                if (! CantidadHelper::esMultiplo((string) $p['cantidad'], $presentacion->paso)) {
                    $validator->errors()->add(
                        "productos.{$i}.cantidad",
                        "La cantidad debe ser múltiplo de {$presentacion->paso} para esta presentación."
                    );
                }
            }
        });
    }

    protected function pagaDesdeCaja(): bool
    {
        return filter_var($this->input('pagado_desde_caja', false), FILTER_VALIDATE_BOOLEAN);
    }
}
