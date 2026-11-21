<?php

namespace Tests\Feature;

use App\Models\Ingreso;
use App\Services\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaContextoCompra;
use Tests\TestCase;

class CompraPagoReporteTest extends TestCase
{
    use CreaContextoCompra, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararContextoCompra();
    }

    private function recibir(Ingreso $ingreso, array $overrides = [])
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.recibir', $ingreso), array_merge([
                'pagado_desde_caja' => 0,
            ], $overrides));
    }

    private function crearCompraRecibida(
        string $documento,
        bool $desdeCaja,
        string $metodo = 'efectivo',
        int $cantidad = 1
    ): Ingreso {
        $this->crearCompraBorrador([
            'numero_documento' => $documento,
            'pagado_desde_caja' => $desdeCaja ? 1 : 0,
            'metodo_pago' => $metodo,
            'productos' => [
                [
                    'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                    'cantidad' => $cantidad,
                    'lote' => 'LOT-'.$documento,
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                    'costo_unitario' => 100,
                ],
            ],
        ]);

        $ingreso = Ingreso::where('numero_documento', $documento)->firstOrFail();

        $this->recibir($ingreso, [
            'pagado_desde_caja' => $desdeCaja ? 1 : 0,
            'metodo_pago' => $metodo,
        ])->assertOk();

        return $ingreso->fresh();
    }

    public function test_reporte_de_compras_calcula_totales_por_forma_de_pago(): void
    {
        $this->crearCompraRecibida('F001-000101', true, 'efectivo', 1);
        $this->crearCompraRecibida('F001-000102', false, 'transferencia', 2);

        // Un borrador no debe contar en el reporte.
        $this->crearCompraBorrador(['numero_documento' => 'F001-000103']);

        $data = app(ReporteService::class)->ingresos(
            today()->toDateString(),
            today()->toDateString()
        );

        $this->assertSame(2, $data['cantidad']);
        $this->assertSame(300.0, (float) $data['total_compras']);
        $this->assertSame(100.0, (float) $data['total_caja']);
        $this->assertSame(200.0, (float) $data['total_externo']);
        $this->assertSame(1, $data['cantidad_caja']);
        $this->assertSame(1, $data['cantidad_externo']);
    }

    public function test_reporte_filtra_solo_compras_pagadas_desde_caja(): void
    {
        $this->crearCompraRecibida('F001-000201', true, 'efectivo', 1);
        $this->crearCompraRecibida('F001-000202', false, 'transferencia', 2);

        $data = app(ReporteService::class)->ingresos(
            today()->toDateString(),
            today()->toDateString(),
            null,
            null,
            'caja'
        );

        $this->assertSame(1, $data['cantidad']);
        $this->assertSame(100.0, (float) $data['total_compras']);
        $this->assertSame(100.0, (float) $data['total_caja']);
        $this->assertSame(0.0, (float) $data['total_externo']);
        $this->assertSame('caja', $data['pago']);
    }

    public function test_reporte_filtra_solo_compras_pagadas_fuera_de_caja(): void
    {
        $this->crearCompraRecibida('F001-000301', true, 'efectivo', 1);
        $this->crearCompraRecibida('F001-000302', false, 'transferencia', 2);

        $data = app(ReporteService::class)->ingresos(
            today()->toDateString(),
            today()->toDateString(),
            null,
            null,
            'externo'
        );

        $this->assertSame(1, $data['cantidad']);
        $this->assertSame(200.0, (float) $data['total_compras']);
        $this->assertSame(0.0, (float) $data['total_caja']);
        $this->assertSame(200.0, (float) $data['total_externo']);
        $this->assertSame('externo', $data['pago']);
    }

    public function test_vista_previa_del_reporte_muestra_conciliacion_de_pagos(): void
    {
        $this->crearCompraRecibida('F001-000401', true, 'efectivo', 1);
        $this->crearCompraRecibida('F001-000402', false, 'transferencia', 2);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.reportes.preview', [
                'tipo' => 'ingresos',
                'desde' => today()->toDateString(),
                'hasta' => today()->toDateString(),
            ]));

        $response->assertOk()
            ->assertSee('Reporte de Compras')
            ->assertSee('Desde caja (1)')
            ->assertSee('Fuera de caja (1)')
            ->assertSee('S/ 100.00')
            ->assertSee('S/ 200.00')
            ->assertSee('Efectivo')
            ->assertSee('Transferencia');
    }

    public function test_listado_de_compras_muestra_la_forma_de_pago(): void
    {
        $this->crearCompraRecibida('F001-000501', true, 'efectivo', 1);
        $this->crearCompraRecibida('F001-000502', false, 'transferencia', 1);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ingresos.index'))
            ->assertOk()
            ->assertSee('Caja')
            ->assertSee('Fuera de caja')
            ->assertSee('Efectivo')
            ->assertSee('Transferencia');
    }
}
