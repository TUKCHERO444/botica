<?php

namespace App\Services;

use App\Helpers\CantidadHelper;
use App\Models\Pago;

class PagoService
{
    /**
     * Registra el desglose de pagos de una venta o atención.
     */
    public function registrar(string $referenciaTipo, int $referenciaId, array $pagos, ?int $userId = null): void
    {
        foreach ($pagos as $pago) {
            Pago::create([
                'referencia_tipo' => $referenciaTipo,
                'referencia_id' => $referenciaId,
                'metodo_pago_id' => $pago['metodo_pago_id'],
                'monto' => CantidadHelper::redondear($pago['monto'], 2),
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * Reemplaza el desglose de pagos (usado al editar una atención).
     */
    public function reemplazar(string $referenciaTipo, int $referenciaId, array $pagos, ?int $userId = null): void
    {
        $this->eliminar($referenciaTipo, $referenciaId);
        $this->registrar($referenciaTipo, $referenciaId, $pagos, $userId);
    }

    public function eliminar(string $referenciaTipo, int $referenciaId): void
    {
        Pago::where('referencia_tipo', $referenciaTipo)
            ->where('referencia_id', $referenciaId)
            ->delete();
    }
}
