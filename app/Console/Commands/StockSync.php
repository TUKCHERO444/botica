<?php

namespace App\Console\Commands;

use App\Models\LoteProducto;
use App\Models\Producto;
use Illuminate\Console\Command;

class StockSync extends Command
{
    protected $signature = 'stock:sync
        {--dry-run : Mostrar cambios sin ejecutarlos}
        {--product= : Sincronizar solo un producto específico (ID)}
        {--create-lotes : Crear registros en lote_producto para productos sin lotes}';

    protected $description = 'Sincroniza producto.stock con la suma real de lote_producto.cantidad';

    public function handle(): int
    {
        $query = Producto::query();

        if ($productId = $this->option('product')) {
            $query->where('id_producto', (int) $productId);
        }

        $productos = $query->get();
        $dryRun = $this->option('dry-run');
        $createLotes = $this->option('create-lotes');
        $syncCount = 0;
        $loteCreados = 0;

        foreach ($productos as $producto) {
            $lotesSum = (int) LoteProducto::where('producto_id_producto', $producto->id_producto)
                ->sum('cantidad');

            if ($lotesSum !== $producto->stock) {
                $this->line(sprintf(
                    '[%s] #%d "%s": stock=%d → lotes=%d',
                    $dryRun ? 'SIMULACIÓN' : ($createLotes ? 'CREAR LOTE' : 'CORREGIR'),
                    $producto->id_producto,
                    $producto->nombre,
                    $producto->stock,
                    $lotesSum
                ));

                if (!$dryRun) {
                    if ($createLotes && $producto->stock > 0 && $lotesSum === 0) {
                        LoteProducto::create([
                            'producto_id_producto' => $producto->id_producto,
                            'codigo_lote' => 'LOTE-' . str_pad($producto->id_producto, 4, '0', STR_PAD_LEFT),
                            'fecha_vencimiento' => now()->addYears(2),
                            'cantidad' => $producto->stock,
                            'ingreso_id_ingreso' => null,
                        ]);
                        $loteCreados++;
                        $this->info("  ✓ Lote creado con cantidad={$producto->stock}");
                    } else {
                        Producto::where('id_producto', $producto->id_producto)
                            ->update(['stock' => $lotesSum]);
                    }
                }

                $syncCount++;
            }
        }

        $accion = $dryRun ? 'simulados' : "procesados ({$loteCreados} lotes creados, " . ($syncCount - $loteCreados) . ' stocks corregidos)';
        $this->info("{$syncCount} producto(s) {$accion}.");

        return Command::SUCCESS;
    }
}
