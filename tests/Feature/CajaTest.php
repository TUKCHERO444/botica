<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CajaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'caja.ver']);
        Permission::firstOrCreate(['name' => 'caja.crear']);
        Permission::firstOrCreate(['name' => 'caja.editar']);
        Permission::firstOrCreate(['name' => 'caja.eliminar']);

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->user->givePermissionTo(['caja.ver', 'caja.crear', 'caja.editar', 'caja.eliminar']);

        $this->actingAs($this->user);
    }

    private function ajaxPost(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->postJson($uri, $data);
    }

    private function ajaxGet(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->getJson($uri);
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
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['descripcion']);
    }

    public function test_can_close_caja(): void
    {
        $caja = Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 200,
            'total_egresos' => 50,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $response = $this->ajaxPost(route('admin.caja.cierre'));

        $expectedFinal = 100 + 200 - 50;

        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('caja', [
            'id_caja' => $caja->id_caja,
            'estado' => 'cerrada',
            'monto_final' => $expectedFinal,
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

        $response = $this->ajaxPost(route('admin.caja.cierre'));

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
            'total_ingresos' => 350,
            'total_egresos' => 120,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $this->ajaxPost(route('admin.caja.cierre'));

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
}
