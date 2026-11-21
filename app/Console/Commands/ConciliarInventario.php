<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ConciliacionInventarioService;
use Illuminate\Console\Command;

class ConciliarInventario extends Command
{
    protected $signature = 'inventario:conciliar
        {--dry-run : Compara sin guardar la conciliación}
        {--user= : ID del usuario que ejecuta}
        {--json : Muestra el resultado en JSON}';

    protected $description = 'Compara producto.stock y los lotes, y guarda una conciliación de inventario.';

    public function handle(ConciliacionInventarioService $service): int
    {
        $userId = null;

        if ($this->option('user')) {
            $userId = (int) $this->option('user');

            if (! User::whereKey($userId)->exists()) {
                $this->error("No existe el usuario {$userId}.");

                return self::FAILURE;
            }
        }

        $resultado = $service->ejecutar(
            $userId,
            'comando',
            ! $this->option('dry-run')
        );

        if ($this->option('json')) {
            $this->line(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $resultado['estado'] === 'ok' ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Conciliación de inventario');
        $this->line('Productos revisados: '.$resultado['total_productos']);
        $this->line('Productos con diferencia: '.$resultado['productos_con_diferencia']);
        $this->line('Lotes inconsistentes: '.$resultado['lotes_inconsistentes']);
        $this->line('Diferencia total: '.$resultado['diferencia_total']);

        if ($resultado['conciliacion']) {
            $this->line('Registro guardado: #'.$resultado['conciliacion']->id_conciliacion_inventario);
        } else {
            $this->warn('Modo dry-run: no se guardó la conciliación.');
        }

        if ($resultado['detalles'] !== []) {
            $this->newLine();
            $this->table(
                ['Producto', 'Stock caché', 'Stock lotes', 'Diferencia', 'Lotes'],
                collect($resultado['detalles'])->map(fn ($detalle) => [
                    $detalle['producto_nombre'],
                    $detalle['stock_cache'],
                    $detalle['stock_lotes'],
                    $detalle['diferencia'],
                    $detalle['lotes_inconsistentes'],
                ])->all()
            );

            foreach ($resultado['detalles'] as $detalle) {
                foreach ($detalle['lotes'] as $lote) {
                    $this->line(
                        "  {$detalle['producto_nombre']} / {$lote['codigo_lote']}: "
                        ."lote={$lote['cantidad_lote']}, capas={$lote['suma_capas']}, diferencia={$lote['diferencia']}"
                    );
                }
            }
        }

        return $resultado['estado'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}
