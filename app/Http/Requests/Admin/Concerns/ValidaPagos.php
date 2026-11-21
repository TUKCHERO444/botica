<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Helpers\CantidadHelper;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidaPagos
{
    protected function reglasPagos(): array
    {
        return [
            'pagos' => 'required|array|min:1',
            'pagos.*.metodo_pago_id' => [
                'required',
                'integer',
                Rule::exists('metodo_pago', 'id_metodo_pago')->where('estado', 1),
            ],
            'pagos.*.monto' => 'required|numeric|decimal:0,2|gt:0',
        ];
    }

    /**
     * Quita las filas sin importe para que no bloqueen la validación "gt:0".
     */
    protected function prepareForValidation(): void
    {
        $pagos = $this->input('pagos');

        if (! is_array($pagos)) {
            return;
        }

        $limpios = array_filter($pagos, function ($pago) {
            if (! is_array($pago)) {
                return false;
            }

            return ($pago['metodo_pago_id'] ?? '') !== ''
                && ($pago['monto'] ?? '') !== ''
                && (float) $pago['monto'] != 0.0;
        });

        $this->merge(['pagos' => array_values($limpios)]);
    }

    /**
     * Verifica que el desglose cubra exactamente el total y no repita métodos.
     */
    protected function validarPagos(Validator $validator, string $total): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $pagos = $this->input('pagos', []);
        $suma = '0.00';
        $metodos = [];

        foreach ($pagos as $pago) {
            $suma = CantidadHelper::sumar($suma, $pago['monto'], 2);

            $metodoId = (int) $pago['metodo_pago_id'];
            if (isset($metodos[$metodoId])) {
                $validator->errors()->add(
                    'pagos',
                    'Cada método de pago debe aparecer una sola vez en el desglose.'
                );

                return;
            }

            $metodos[$metodoId] = true;
        }

        if (CantidadHelper::comparar($suma, $total, 2) !== 0) {
            $validator->errors()->add(
                'pagos',
                'La suma de los pagos (S/ '.$suma.') debe ser igual al total (S/ '.$total.').'
            );
        }
    }

    protected function mensajesPagos(): array
    {
        return [
            'pagos.required' => 'Debe registrar al menos un método de pago.',
            'pagos.min' => 'Debe registrar al menos un método de pago.',
            'pagos.*.metodo_pago_id.required' => 'Seleccione un método de pago.',
            'pagos.*.metodo_pago_id.exists' => 'El método de pago seleccionado no está activo.',
            'pagos.*.monto.required' => 'Ingrese el importe pagado con este método.',
            'pagos.*.monto.gt' => 'El importe de cada método de pago debe ser mayor que cero.',
        ];
    }
}
