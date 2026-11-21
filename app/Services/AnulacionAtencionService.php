<?php

namespace App\Services;

use App\Models\Atencion;
use Illuminate\Support\Facades\DB;

class AnulacionAtencionService
{
    public function __construct(
        private readonly InventarioService $inventario,
        private readonly CajaService $cajas
    ) {}

    public function anular(Atencion $atencion, string $motivo, ?int $userId = null): Atencion
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new \RuntimeException('El motivo de anulación es obligatorio.');
        }

        return DB::transaction(function () use ($atencion, $motivo, $userId) {
            $atencion = Atencion::whereKey($atencion->id_atencion)
                ->lockForUpdate()
                ->firstOrFail();

            if ($atencion->estaAnulada()) {
                throw new \RuntimeException('La atención ya fue anulada.');
            }

            $this->inventario->revertirConsumos(
                'Atencion',
                $atencion->id_atencion,
                $atencion->correlativo ?? '#'.$atencion->id_atencion
            );

            $this->cajas->reembolsarAtencion(
                $atencion,
                "Anulación de atención {$atencion->correlativo}",
                $userId ?? auth()->id()
            );

            $atencion->update([
                'estado' => Atencion::ESTADO_ANULADA,
                'anulada_at' => now(),
                'anulada_user_id' => $userId ?? auth()->id(),
                'motivo_anulacion' => $motivo,
            ]);

            return $atencion->fresh(['cliente', 'user', 'anuladaPor', 'productos', 'servicios', 'pagos.metodoPago']);
        });
    }
}
