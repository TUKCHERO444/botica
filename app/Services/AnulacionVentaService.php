<?php

namespace App\Services;

use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class AnulacionVentaService
{
    public function __construct(
        private readonly InventarioService $inventario,
        private readonly CajaService $cajas
    ) {}

    public function anular(Venta $venta, string $motivo, ?int $userId = null): Venta
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new \RuntimeException('El motivo de anulación es obligatorio.');
        }

        return DB::transaction(function () use ($venta, $motivo, $userId) {
            $venta = Venta::whereKey($venta->id_venta)
                ->lockForUpdate()
                ->firstOrFail();

            if ($venta->estaAnulada()) {
                throw new \RuntimeException('La venta ya fue anulada.');
            }

            $this->inventario->revertirConsumos('Venta', $venta->id_venta, $venta->correlativo);

            $this->cajas->reembolsarVenta(
                $venta,
                "Anulación de venta {$venta->correlativo}",
                $userId ?? auth()->id()
            );

            $venta->update([
                'estado' => Venta::ESTADO_ANULADA,
                'anulada_at' => now(),
                'anulada_user_id' => $userId ?? auth()->id(),
                'motivo_anulacion' => $motivo,
            ]);

            return $venta->fresh(['cliente', 'user', 'anuladaPor', 'productos']);
        });
    }
}
