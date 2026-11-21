<?php

namespace Tests\Feature;

use App\Models\InventarioInicial;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreaContextoCompra;
use Tests\TestCase;

class InventarioInicialTest extends TestCase
{
    use CreaContextoCompra, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararContextoCompra();
    }

    private function payloadManual(array $lineas = []): array
    {
        return [
            'fecha' => today()->toDateString(),
            'observaciones' => 'Conteo inicial de prueba',
            'lineas' => $lineas ?: [
                [
                    'producto_presentacion_id' => $this->unidad->id_producto_presentacion,
                    'codigo_lote' => 'LOT-A',
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                    'cantidad_presentacion' => 100,
                    'costo_unitario_presentacion' => 1.5,
                ],
            ],
        ];
    }

    private function crearBorrador(array $lineas = []): InventarioInicial
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.inventario-inicial.store'), $this->payloadManual($lineas))
            ->assertOk()
            ->assertJson(['success' => true]);

        return InventarioInicial::firstOrFail();
    }

    private function csv(array $filas): string
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($filas as $fila) {
            fputcsv($handle, $fila);
        }

        rewind($handle);
        $contenido = stream_get_contents($handle);
        fclose($handle);

        return $contenido;
    }

    private function archivoCsv(array $filas): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('inventario.csv', $this->csv($filas));
    }

    public function test_borrador_no_mueve_stock_lotes_ni_kardex(): void
    {
        $inventario = $this->crearBorrador();

        $this->assertSame(InventarioInicial::ESTADO_BORRADOR, $inventario->estado);
        $this->assertSame('0.000', $this->producto->fresh()->stock);
        $this->assertDatabaseCount('producto_lote', 0);
        $this->assertDatabaseCount('lote_ingreso', 0);
        $this->assertDatabaseCount('kardex_movimientos', 0);
        $this->assertSame(1, $inventario->detalles()->count());
    }

    public function test_confirmar_crea_lote_capa_stock_y_kardex(): void
    {
        $inventario = $this->crearBorrador();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.inventario-inicial.confirmar', $inventario))
            ->assertOk()
            ->assertJson(['success' => true]);

        $inventario->refresh();

        $this->assertTrue($inventario->estaConfirmado());
        $this->assertNotNull($inventario->confirmado_at);
        $this->assertSame('100.000', $this->producto->fresh()->stock);
        $this->assertSame('100.000', $this->producto->fresh()->inventario);

        $this->assertDatabaseHas('producto_lote', [
            'producto_id' => $this->producto->id_producto,
            'codigo_lote' => 'LOT-A',
            'cantidad_disponible_base' => 100,
            'es_inicial' => true,
        ]);

        $this->assertDatabaseHas('lote_ingreso', [
            'producto_lote_id' => $inventario->detalles()->first()->producto_lote_id,
            'ingreso_id' => null,
            'cantidad_base' => 100,
            'cantidad_disponible_base' => 100,
            'costo_unitario_base' => 1.5,
        ]);

        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'INGRESO',
            'referencia_tipo' => 'InventarioInicial',
            'referencia_id' => $inventario->id_inventario_inicial,
            'cantidad' => 100,
        ]);

        $detalle = $inventario->detalles()->first();
        $this->assertNotNull($detalle->producto_lote_id);
        $this->assertNotNull($detalle->lote_ingreso_id);
    }

    public function test_un_producto_puede_tener_varios_lotes(): void
    {
        $inventario = $this->crearBorrador([
            [
                'producto_presentacion_id' => $this->unidad->id_producto_presentacion,
                'codigo_lote' => 'LOT-A',
                'fecha_vencimiento' => now()->addMonths(6)->toDateString(),
                'cantidad_presentacion' => 100,
                'costo_unitario_presentacion' => 1.5,
            ],
            [
                'producto_presentacion_id' => $this->unidad->id_producto_presentacion,
                'codigo_lote' => 'LOT-B',
                'fecha_vencimiento' => now()->addYear()->toDateString(),
                'cantidad_presentacion' => 50,
                'costo_unitario_presentacion' => 1.6,
            ],
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.inventario-inicial.confirmar', $inventario))
            ->assertOk();

        $this->assertSame('150.000', $this->producto->fresh()->stock);
        $this->assertDatabaseCount('producto_lote', 2);
        $this->assertDatabaseCount('lote_ingreso', 2);
        $this->assertDatabaseCount('kardex_movimientos', 2);
    }

    public function test_no_se_puede_confirmar_dos_veces(): void
    {
        $inventario = $this->crearBorrador();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.inventario-inicial.confirmar', $inventario))
            ->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.inventario-inicial.confirmar', $inventario))
            ->assertStatus(422);

        $this->assertSame('100.000', $this->producto->fresh()->stock);
        $this->assertDatabaseCount('lote_ingreso', 1);
    }

    public function test_bloquea_producto_con_stock_existente(): void
    {
        $this->producto->update(['stock' => 10]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.inventario-inicial.store'), $this->payloadManual())
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseCount('inventario_inicial', 0);
    }

    public function test_bloquea_vencimiento_pasado_y_lote_duplicado(): void
    {
        $pasado = $this->payloadManual([
            [
                'producto_presentacion_id' => $this->unidad->id_producto_presentacion,
                'codigo_lote' => 'LOT-A',
                'fecha_vencimiento' => now()->subDay()->toDateString(),
                'cantidad_presentacion' => 10,
            ],
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.inventario-inicial.store'), $pasado)
            ->assertStatus(422)
            ->assertJsonValidationErrors('lineas.0.fecha_vencimiento');

        $duplicado = $this->payloadManual([
            [
                'producto_presentacion_id' => $this->unidad->id_producto_presentacion,
                'codigo_lote' => 'LOT-A',
                'fecha_vencimiento' => now()->addYear()->toDateString(),
                'cantidad_presentacion' => 10,
            ],
            [
                'producto_presentacion_id' => $this->unidad->id_producto_presentacion,
                'codigo_lote' => 'LOT-A',
                'fecha_vencimiento' => now()->addYear()->toDateString(),
                'cantidad_presentacion' => 20,
            ],
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.inventario-inicial.store'), $duplicado)
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseCount('inventario_inicial', 0);
    }

    public function test_csv_dry_run_no_escribe_nada(): void
    {
        $archivo = $this->archivoCsv([
            ['nombre', 'unidad_base', 'lote', 'vencimiento', 'cantidad_base', 'costo_unitario_base'],
            ['Paracetamol 500 mg', 'TAB', 'LOT-CSV', now()->addYear()->toDateString(), '40', '0.25'],
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.inventario-inicial.importar'), [
                'archivo' => $archivo,
                'dry_run' => 1,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'dry_run' => true,
            ])
            ->assertJsonPath('resumen.lineas', 1);

        $this->assertDatabaseCount('inventario_inicial', 0);
        $this->assertDatabaseCount('producto', 1);
        $this->assertDatabaseCount('kardex_movimientos', 0);
    }

    public function test_csv_crea_producto_y_borrador_y_luego_confirma(): void
    {
        $archivo = $this->archivoCsv([
            [
                'nombre',
                'unidad_base',
                'categoria',
                'marca',
                'precio_venta',
                'precio_compra',
                'lote',
                'vencimiento',
                'cantidad_base',
                'costo_unitario_base',
                'presentacion',
                'factor',
                'precio_presentacion',
            ],
            [
                'Paracetamol 500 mg',
                'TAB',
                'Analgésicos',
                'Genérico',
                '0.50',
                '0.25',
                'LOT-CSV',
                now()->addYear()->toDateString(),
                '40',
                '0.25',
                'Caja',
                '100',
                '45.00',
            ],
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.inventario-inicial.importar'), [
                'archivo' => $archivo,
                'dry_run' => 0,
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'dry_run' => false]);

        $producto = Producto::where('nombre', 'Paracetamol 500 mg')->firstOrFail();
        $this->assertSame('0.000', $producto->stock);
        $this->assertSame('0.000', $producto->inventario);

        $inventario = InventarioInicial::firstOrFail();
        $this->assertSame(InventarioInicial::ESTADO_BORRADOR, $inventario->estado);
        $this->assertNotNull($inventario->archivo_nombre);
        $this->assertNotNull($inventario->archivo_hash);

        $nuevaCaja = $producto->presentaciones()
            ->where('presentacion_id', Presentacion::where('nombre', 'Caja')->firstOrFail()->id_presentacion)
            ->firstOrFail();
        $this->assertSame('100.000', $nuevaCaja->factor);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.inventario-inicial.confirmar', $inventario))
            ->assertOk();

        $this->assertSame('40.000', $producto->fresh()->stock);
        $this->assertDatabaseHas('kardex_movimientos', [
            'producto_id' => $producto->id_producto,
            'tipo_movimiento' => 'INGRESO',
            'referencia_tipo' => 'InventarioInicial',
            'cantidad' => 40,
        ]);
    }

    public function test_csv_con_errores_no_crea_nada(): void
    {
        $archivo = $this->archivoCsv([
            ['nombre', 'unidad_base', 'lote', 'vencimiento', 'cantidad_base'],
            ['Producto sin unidad', 'NOEXISTE', 'LOT-X', now()->addYear()->toDateString(), '10'],
            ['Producto sin cantidad', 'TAB', 'LOT-Y', now()->addYear()->toDateString(), '0'],
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.inventario-inicial.importar'), [
                'archivo' => $archivo,
                'dry_run' => 0,
            ])
            ->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonCount(2, 'errores');

        $this->assertDatabaseCount('inventario_inicial', 0);
        $this->assertDatabaseCount('producto', 1);
    }

    public function test_conciliacion_queda_sin_diferencias_despues_de_confirmar(): void
    {
        $inventario = $this->crearBorrador();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.inventario-inicial.confirmar', $inventario))
            ->assertOk();

        $this->artisan('inventario:conciliar')->assertExitCode(0);
    }

    public function test_permiso_inventario_inicial_es_requerido(): void
    {
        $usuarioSinPermiso = User::factory()->create();

        $this->actingAs($usuarioSinPermiso)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.inventario-inicial.index'))
            ->assertForbidden();
    }

    public function test_permite_registrar_por_presentacion_y_convierte_a_base(): void
    {
        $inventario = $this->crearBorrador([
            [
                'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                'codigo_lote' => 'LOT-CAJA',
                'fecha_vencimiento' => now()->addYear()->toDateString(),
                'cantidad_presentacion' => 2,
                'costo_unitario_presentacion' => 50,
            ],
        ]);

        $detalle = $inventario->detalles()->firstOrFail();

        $this->assertSame('2.000', $detalle->cantidad_presentacion);
        $this->assertSame('100.000', $detalle->factor);
        $this->assertSame('200.000', $detalle->cantidad_base);
        $this->assertSame('0.5000', $detalle->costo_unitario_base);
        $this->assertSame('50.0000', $detalle->costo_unitario_presentacion);
        $this->assertSame('100.00', $detalle->costo_total);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.inventario-inicial.confirmar', $inventario))
            ->assertOk();

        $this->assertSame('200.000', $this->producto->fresh()->stock);
        $this->assertDatabaseHas('producto_lote', [
            'codigo_lote' => 'LOT-CAJA',
            'cantidad_disponible_base' => 200,
        ]);
        $this->assertDatabaseHas('lote_ingreso', [
            'producto_presentacion_id' => $this->caja->id_producto_presentacion,
            'cantidad_presentacion' => 2,
            'factor' => 100,
            'cantidad_base' => 200,
            'costo_unitario_base' => 0.5,
            'costo_total' => 100,
        ]);
        $this->assertDatabaseHas('kardex_movimientos', [
            'referencia_tipo' => 'InventarioInicial',
            'cantidad' => 200,
            'producto_presentacion_id' => $this->caja->id_producto_presentacion,
            'cantidad_presentacion' => 2,
            'factor' => 100,
        ]);
    }

    public function test_buscador_de_productos_devuelve_resultados_y_elegibilidad(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.inventario-inicial.presentaciones.search', ['q' => 'Ibuprofeno']))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.elegible', true)
            ->assertJsonPath('0.factor', 1)
            ->assertJsonPath('0.precio', 1)
            ->assertJsonPath('0.precio_venta', 1.5)
            ->assertJsonPath('1.factor', 100)
            ->assertJsonPath('1.precio', 100)
            ->assertJsonPath('1.precio_venta', 100)
            ->assertJsonPath('0.stock', 0);

        $this->producto->update(['stock' => 10, 'inventario' => 10]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.inventario-inicial.presentaciones.search', ['q' => 'Ibuprofeno']))
            ->assertOk()
            ->assertJsonPath('0.elegible', false)
            ->assertJsonPath('0.stock', 10);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.inventario-inicial.presentaciones.search', ['q' => 'I']))
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_formulario_manual_usa_buscador_dinamico(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.inventario-inicial.create'))
            ->assertOk()
            ->assertSee('producto-search', false)
            ->assertSee('presentaciones/search', false)
            ->assertDontSee('producto-select', false)
            ->assertSee('data-inventario-modal', false);
    }

    public function test_rechaza_costos_con_mas_de_dos_decimales_y_cantidades_con_mas_de_tres(): void
    {
        $costoInvalido = $this->payloadManual([
            [
                'producto_presentacion_id' => $this->unidad->id_producto_presentacion,
                'codigo_lote' => 'LOT-A',
                'fecha_vencimiento' => now()->addYear()->toDateString(),
                'cantidad_presentacion' => 1,
                'costo_unitario_presentacion' => 0.999,
            ],
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.inventario-inicial.store'), $costoInvalido)
            ->assertStatus(422)
            ->assertJsonValidationErrors('lineas.0.costo_unitario_presentacion');

        $cantidadInvalida = $this->payloadManual([
            [
                'producto_presentacion_id' => $this->unidad->id_producto_presentacion,
                'codigo_lote' => 'LOT-B',
                'fecha_vencimiento' => now()->addYear()->toDateString(),
                'cantidad_presentacion' => 0.0001,
                'costo_unitario_presentacion' => 1,
            ],
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.inventario-inicial.store'), $cantidadInvalida)
            ->assertStatus(422)
            ->assertJsonValidationErrors('lineas.0.cantidad_presentacion');
    }

    public function test_csv_redondea_precios_con_mas_de_dos_decimales(): void
    {
        $archivo = $this->archivoCsv([
            ['nombre', 'unidad_base', 'lote', 'vencimiento', 'cantidad_base', 'precio_venta', 'costo_unitario_base'],
            ['Producto Precio Redondeado', 'TAB', 'LOT-X', now()->addYear()->toDateString(), '10', '0.999', '0.50'],
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.inventario-inicial.importar'), [
                'archivo' => $archivo,
                'dry_run' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('resumen.lineas', 1)
            ->assertJsonCount(1, 'advertencias');
    }

    public function test_plantilla_csv_no_incluye_codigo_de_barras(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.inventario-inicial.plantilla'));

        $response->assertOk();
        $this->assertStringNotContainsString('codigo_barras', $response->streamedContent());
        $this->assertStringContainsString('unidad_base', $response->streamedContent());
    }

    public function test_editar_borrador_reemplaza_lineas_y_recalcula_totales(): void
    {
        $inventario = $this->crearBorrador();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.inventario-inicial.update', $inventario), [
                'fecha' => today()->toDateString(),
                'observaciones' => 'Borrador corregido',
                'lineas' => [
                    [
                        'producto_presentacion_id' => $this->unidad->id_producto_presentacion,
                        'codigo_lote' => 'LOT-X',
                        'fecha_vencimiento' => now()->addYear()->toDateString(),
                        'cantidad_presentacion' => 10,
                        'costo_unitario_presentacion' => 1,
                    ],
                    [
                        'producto_presentacion_id' => $this->unidad->id_producto_presentacion,
                        'codigo_lote' => 'LOT-Y',
                        'fecha_vencimiento' => now()->addMonths(6)->toDateString(),
                        'cantidad_presentacion' => 20,
                        'costo_unitario_presentacion' => 2,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $inventario->refresh();

        $this->assertSame('Borrador corregido', $inventario->observaciones);
        $this->assertSame(2, $inventario->detalles()->count());
        $this->assertSame('50.00', $inventario->total_costo);
        $this->assertSame('0.000', $this->producto->fresh()->stock);
        $this->assertDatabaseCount('producto_lote', 0);
        $this->assertDatabaseCount('kardex_movimientos', 0);

        $this->assertDatabaseHas('detalle_inventario_inicial', [
            'inventario_inicial_id' => $inventario->id_inventario_inicial,
            'codigo_lote' => 'LOT-X',
            'cantidad_presentacion' => 10,
            'costo_unitario_presentacion' => 1,
        ]);
        $this->assertDatabaseHas('detalle_inventario_inicial', [
            'inventario_inicial_id' => $inventario->id_inventario_inicial,
            'codigo_lote' => 'LOT-Y',
            'cantidad_presentacion' => 20,
            'costo_unitario_presentacion' => 2,
        ]);
    }

    public function test_formulario_de_edicion_renderiza_las_lineas_del_borrador(): void
    {
        $inventario = $this->crearBorrador();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.inventario-inicial.edit', $inventario))
            ->assertOk()
            ->assertSee('Editar inventario')
            ->assertSee('LOT-A')
            ->assertSee('data-initial-id', false)
            ->assertSee('Actualizar borrador');
    }

    public function test_no_se_puede_editar_un_inventario_confirmado(): void
    {
        $inventario = $this->crearBorrador();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.inventario-inicial.confirmar', $inventario))
            ->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.inventario-inicial.update', $inventario), $this->payloadManual())
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_listado_y_detalle_renderizan(): void
    {
        $inventario = $this->crearBorrador();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.inventario-inicial.index'))
            ->assertOk()
            ->assertSee('Inventario inicial')
            ->assertSee($inventario->correlativo);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.inventario-inicial.show', $inventario))
            ->assertOk()
            ->assertSee($this->producto->nombre)
            ->assertSee('Confirmar inventario');
    }
}
