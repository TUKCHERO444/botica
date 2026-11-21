<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\MetodoPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MetodoPagoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['metodos-pago', 'ventas', 'atenciones'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['metodos-pago', 'ventas', 'atenciones']);
        $this->actingAs($this->user);
    }

    public function test_crea_un_metodo_de_pago(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.metodos-pago.store'), [
                'nombre' => 'Yape',
                'descripcion' => 'Billetera digital',
                'estado' => 1,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('metodo_pago', [
            'nombre' => 'Yape',
            'descripcion' => 'Billetera digital',
            'estado' => 1,
        ]);
    }

    public function test_no_permite_nombres_duplicados(): void
    {
        MetodoPago::create(['nombre' => 'Efectivo', 'estado' => 1]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.metodos-pago.store'), [
                'nombre' => 'Efectivo',
                'estado' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);
    }

    public function test_activa_y_desactiva_el_metodo(): void
    {
        $metodo = MetodoPago::create(['nombre' => 'Plin', 'estado' => 1]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->patchJson(route('admin.metodos-pago.toggleStatus', $metodo))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertFalse($metodo->fresh()->estado);
    }

    public function test_solo_los_metodos_activos_aparecen_en_los_formularios(): void
    {
        Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        MetodoPago::create(['nombre' => 'Efectivo', 'estado' => 1]);
        MetodoPago::create(['nombre' => 'Yape', 'estado' => 1]);
        MetodoPago::create(['nombre' => 'BCP inactivo', 'estado' => 0]);

        $venta = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ventas.create'));

        $venta->assertOk();
        $venta->assertSee('Efectivo');
        $venta->assertSee('Yape');
        $venta->assertSee('data-metodo-btn', false);
        $venta->assertSee('Pago mixto');
        $venta->assertSee('pagos[0][monto]', false);
        $venta->assertDontSee('BCP inactivo');

        $atencion = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.atenciones.create'));

        $atencion->assertOk();
        $atencion->assertSee('Efectivo');
        $atencion->assertSee('data-metodo-btn', false);
        $atencion->assertSee('Pago mixto');
        $atencion->assertSee('pagos[0][monto]', false);
        $atencion->assertDontSee('BCP inactivo');
    }

    public function test_el_modulo_lista_los_metodos_registrados(): void
    {
        MetodoPago::create(['nombre' => 'Efectivo', 'estado' => 1]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.metodos-pago.index'));

        $response->assertOk();
        $response->assertSee('Métodos de pago');
        $response->assertSee('Efectivo');
    }
}
