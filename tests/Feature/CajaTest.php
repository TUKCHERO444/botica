<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\MetodoPago;
use App\Models\User;
use App\Services\CajaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CajaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private MetodoPago $efectivo;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'caja']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->user->givePermissionTo('caja');
        $this->actingAs($this->user);

        $this->efectivo = MetodoPago::create([
            'nombre' => 'Efectivo',
            'estado' => 1,
            'afecta_efectivo' => true,
        ]);
    }

    private function movimiento(Caja $caja, string $tipo, float $monto, string $categoria): CajaMovimiento
    {
        return CajaMovimiento::create([
            'caja_id_caja' => $caja->id_caja,
            'tipo' => $tipo,
            'monto' => $monto,
            'descripcion' => 'Movimiento de prueba',
            'referencia_tipo' => 'egreso_manual',
            'metodo_pago_id' => $this->efectivo->id_metodo_pago,
            'afecta_efectivo' => true,
            'categoria' => $categoria,
            'naturaleza' => 'operativo',
            'user_id' => $this->user->id,
        ]);
    }

    private function ajaxPost(string $uri, array $data = []): TestResponse
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->postJson($uri, $data);
    }

    private function ajaxGet(string $uri): TestResponse
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->getJson($uri);
    }

    public function test_rechaza_montos_con_mas_de_dos_decimales(): void
    {
        $this->ajaxPost(route('admin.caja.apertura'), ['monto_inicial' => 100.999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('monto_inicial');

        $this->ajaxPost(route('admin.caja.apertura'), ['monto_inicial' => 100])
            ->assertOk();

        $this->ajaxPost(route('admin.caja.egreso'), [
            'monto' => 10.999,
            'descripcion' => 'Prueba de decimales',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('monto');
    }

    public function test_can_view_caja_index_when_no_open_caja(): void
    {
        $response = $this->ajaxGet(route('admin.caja.index'));

        $response->assertOk();
        $response->assertSee('No hay caja abierta');
        $response->assertSee('Abrir caja');
    }

    public function test_can_open_caja_with_initial_amount(): void
    {
        $response = $this->ajaxPost(route('admin.caja.apertura'), [
            'monto_inicial' => 100.00,
        ]);

        $response->assertJson([
            'success' => true,
            'message' => 'Caja abierta correctamente con S/ 100.00',
        ]);

        $this->assertDatabaseHas('caja', [
            'monto_inicial' => 100.00,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
        ]);
    }

    public function test_cannot_open_caja_when_one_is_already_open(): void
    {
        Caja::create([
            'monto_inicial' => 50,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $response = $this->ajaxPost(route('admin.caja.apertura'), [
            'monto_inicial' => 100.00,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Ya hay una caja abierta. Ciérrela antes de abrir una nueva.',
        ]);
    }

    public function test_monto_inicial_is_required(): void
    {
        $response = $this->ajaxPost(route('admin.caja.apertura'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['monto_inicial']);
    }

    public function test_can_register_egreso(): void
    {
        $caja = Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $response = $this->ajaxPost(route('admin.caja.egreso'), [
            'monto' => 25.50,
            'descripcion' => 'Compra de material de oficina urgente',
            'metodo_pago_id' => $this->efectivo->id_metodo_pago,
            'categoria' => 'gasto_utiles',
        ]);

        $response->assertJson([
            'success' => true,
            'message' => 'Egreso registrado correctamente.',
        ]);

        $this->assertDatabaseHas('caja_movimiento', [
            'caja_id_caja' => $caja->id_caja,
            'tipo' => 'egreso',
            'monto' => 25.50,
            'descripcion' => 'Compra de material de oficina urgente',
            'referencia_tipo' => 'egreso_manual',
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('caja', [
            'id_caja' => $caja->id_caja,
            'total_egresos' => 25.50,
        ]);
    }

    public function test_egreso_requires_description(): void
    {
        Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $response = $this->ajaxPost(route('admin.caja.egreso'), [
            'monto' => 10,
            'descripcion' => '',
            'metodo_pago_id' => $this->efectivo->id_metodo_pago,
            'categoria' => 'gasto_utiles',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['descripcion']);
    }

    public function test_can_close_caja(): void
    {
        $caja = Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $this->movimiento($caja, 'ingreso', 200, 'venta');
        $this->movimiento($caja, 'egreso', 50, 'gasto_utiles');

        $response = $this->ajaxPost(route('admin.caja.cierre'), [
            'efectivo_contado' => 250,
        ]);

        $expectedFinal = 100 + 200 - 50;

        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('caja', [
            'id_caja' => $caja->id_caja,
            'estado' => 'cerrada',
            'monto_final' => $expectedFinal,
            'efectivo_esperado' => $expectedFinal,
            'efectivo_contado' => $expectedFinal,
            'diferencia' => 0,
            'cierre_user_id' => $this->user->id,
        ]);
    }

    public function test_cannot_close_already_closed_caja(): void
    {
        Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'monto_final' => 100,
            'estado' => 'cerrada',
            'apertura_user_id' => $this->user->id,
            'cierre_user_id' => $this->user->id,
            'apertura_at' => now()->subHours(8),
            'cierre_at' => now(),
        ]);

        $response = $this->ajaxPost(route('admin.caja.cierre'), [
            'efectivo_contado' => 100,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'No hay una caja abierta.',
        ]);
    }

    public function test_egreso_fails_when_no_open_caja(): void
    {
        $response = $this->ajaxPost(route('admin.caja.egreso'), [
            'monto' => 10,
            'descripcion' => 'Test egreso sin caja',
            'metodo_pago_id' => $this->efectivo->id_metodo_pago,
            'categoria' => 'gasto_utiles',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'No hay una caja abierta.',
        ]);
    }

    public function test_can_view_historial(): void
    {
        Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 50,
            'total_egresos' => 10,
            'monto_final' => 140,
            'efectivo_esperado' => 150,
            'efectivo_contado' => 140,
            'diferencia' => -10,
            'estado' => 'cerrada',
            'apertura_user_id' => $this->user->id,
            'cierre_user_id' => $this->user->id,
            'apertura_at' => now()->subHours(8),
            'cierre_at' => now(),
        ]);

        $response = $this->ajaxGet(route('admin.caja.historial'));

        $response->assertOk();
        $response->assertSee('S/ 140.00');
    }

    public function test_cierre_calculates_monto_final_correctly(): void
    {
        $caja = Caja::create([
            'monto_inicial' => 500,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $this->movimiento($caja, 'ingreso', 350, 'venta');
        $this->movimiento($caja, 'egreso', 120, 'gasto_utiles');

        $this->ajaxPost(route('admin.caja.cierre'), [
            'efectivo_contado' => 730,
        ]);

        $this->assertDatabaseHas('caja', [
            'id_caja' => $caja->id_caja,
            'monto_final' => 730,
        ]);
    }

    public function test_caja_model_monto_actual_accessor(): void
    {
        $caja = new Caja([
            'monto_inicial' => 100,
            'total_ingresos' => 50,
            'total_egresos' => 30,
        ]);

        $this->assertEquals(120, $caja->monto_actual);
    }

    public function test_arqueo_registra_diferencia_y_exige_observacion(): void
    {
        $caja = Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $this->movimiento($caja, 'ingreso', 50, 'venta');

        // Diferencia de -10 sin observación: se rechaza.
        $this->ajaxPost(route('admin.caja.cierre'), [
            'efectivo_contado' => 140,
            'motivo_diferencia' => 'faltante',
        ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('caja', [
            'id_caja' => $caja->id_caja,
            'estado' => 'abierta',
        ]);

        $this->ajaxPost(route('admin.caja.cierre'), [
            'efectivo_contado' => 140,
            'motivo_diferencia' => 'faltante',
            'observacion_cierre' => 'Faltante detectado en el conteo',
        ])->assertOk();

        $this->assertDatabaseHas('caja', [
            'id_caja' => $caja->id_caja,
            'estado' => 'cerrada',
            'efectivo_esperado' => 150,
            'efectivo_contado' => 140,
            'diferencia' => -10,
            'motivo_diferencia' => 'faltante',
            'monto_final' => 140,
            'arqueo_user_id' => $this->user->id,
        ]);
    }

    public function test_arqueo_por_denominaciones_calcula_el_contado(): void
    {
        $caja = Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $this->ajaxPost(route('admin.caja.cierre'), [
            'efectivo_contado' => 150.50,
            'denominaciones' => [
                '100' => 1,
                '50' => 1,
                '0.5' => 1,
            ],
            'motivo_diferencia' => 'sobrante',
            'observacion_cierre' => 'Sobrante por redondeo',
        ])->assertOk();

        $this->assertDatabaseHas('caja', [
            'id_caja' => $caja->id_caja,
            'efectivo_esperado' => 100,
            'efectivo_contado' => 150.50,
            'diferencia' => 50.50,
            'motivo_diferencia' => 'sobrante',
        ]);
    }

    public function test_egreso_no_puede_superar_el_efectivo_disponible(): void
    {
        $caja = Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $this->ajaxPost(route('admin.caja.egreso'), [
            'monto' => 150,
            'descripcion' => 'Intento de egreso mayor al disponible',
            'metodo_pago_id' => $this->efectivo->id_metodo_pago,
            'categoria' => 'gasto_utiles',
        ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseCount('caja_movimiento', 0);
        $this->assertDatabaseHas('caja', ['id_caja' => $caja->id_caja, 'total_egresos' => 0]);
    }

    public function test_formularios_de_caja_renderizan(): void
    {
        $caja = Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $this->ajaxGet(route('admin.caja.formIngreso'))
            ->assertOk()
            ->assertSee('Registrar ingreso')
            ->assertSee('Aporte de sencillo');

        $this->ajaxGet(route('admin.caja.formEgreso'))
            ->assertOk()
            ->assertSee('Registrar egreso')
            ->assertSee('Luz')
            ->assertSee('Efectivo disponible');

        $this->ajaxGet(route('admin.caja.formCierre'))
            ->assertOk()
            ->assertSee('Cerrar caja y arquear')
            ->assertSee('Efectivo esperado')
            ->assertSee('Conteo rápido')
            ->assertSee('Detallado por denominación')
            ->assertSee('Revisar cierre')
            ->assertSee('data-denominacion="200"', false)
            ->assertSee('id="conteo-rapido"', false);

        $this->movimiento($caja, 'ingreso', 50, 'venta');

        $this->ajaxPost(route('admin.caja.cierre'), [
            'efectivo_contado' => 150,
        ])->assertOk();

        $this->ajaxGet(route('admin.caja.historialShow', $caja))
            ->assertOk()
            ->assertSee('Efectivo esperado')
            ->assertSee('Efectivo contado');
    }

    public function test_cierre_en_cero_requiere_confirmacion_explicita(): void
    {
        $caja = Caja::create([
            'monto_inicial' => 0,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $this->ajaxPost(route('admin.caja.cierre'), [
            'efectivo_contado' => 0,
        ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('caja', [
            'id_caja' => $caja->id_caja,
            'estado' => 'abierta',
        ]);

        $this->ajaxPost(route('admin.caja.cierre'), [
            'efectivo_contado' => 0,
            'confirmo_cero' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('caja', [
            'id_caja' => $caja->id_caja,
            'estado' => 'cerrada',
            'efectivo_contado' => 0,
            'diferencia' => 0,
        ]);
    }

    public function test_metodo_digital_no_reduce_el_efectivo_esperado(): void
    {
        $caja = Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $yape = MetodoPago::create([
            'nombre' => 'Yape',
            'estado' => 1,
            'afecta_efectivo' => false,
        ]);

        DB::transaction(function () use ($yape) {
            app(CajaService::class)->registrarPagos('venta', 999, [
                ['metodo_pago_id' => $yape->id_metodo_pago, 'monto' => 50],
            ], 'Venta de prueba');
        });

        $caja->refresh();

        $this->assertSame('0.00', $caja->total_ingresos_efectivo);
        $this->assertSame('50.00', $caja->total_ingresos_digital);
        $this->assertSame('100.00', app(CajaService::class)->efectivoDisponible($caja));

        $this->ajaxGet(route('admin.caja.index'))
            ->assertOk()
            ->assertSee('Efectivo esperado')
            ->assertSee('S/ 100.00')
            ->assertSee('Yape');
    }
}
