<?php

namespace Tests\Concerns;

use App\Models\MetodoPago;

trait RegistraPagos
{
    protected ?MetodoPago $metodoEfectivo = null;

    protected function metodoEfectivo(): MetodoPago
    {
        if (! $this->metodoEfectivo) {
            $this->metodoEfectivo = MetodoPago::firstOrCreate(
                ['nombre' => 'Efectivo'],
                ['descripcion' => 'Pago en efectivo', 'estado' => 1]
            );
        }

        return $this->metodoEfectivo;
    }

    /**
     * Payload de pago simple con el importe indicado.
     */
    protected function pagos(float|int|string $monto): array
    {
        return [
            'pagos' => [
                [
                    'metodo_pago_id' => $this->metodoEfectivo()->id_metodo_pago,
                    'monto' => number_format((float) $monto, 2, '.', ''),
                ],
            ],
        ];
    }
}
