<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ConfiguracionSistema;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DatosSistemaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['datos-sistema', 'dashboard', 'ventas'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $this->user = User::factory()->create(['activo' => true]);
        $this->user->givePermissionTo(['datos-sistema', 'dashboard', 'ventas']);
        $this->actingAs($this->user);
    }

    public function test_muestra_el_panel_de_datos_del_sistema(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.datos-sistema.index'));

        $response->assertOk();
        $response->assertSee('Datos del sistema');
        $response->assertSee('nombre_negocio', false);
        $response->assertSee('data-brand-preview', false);
    }

    public function test_actualiza_el_nombre_de_negocio(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.datos-sistema.update'), [
                'nombre_negocio' => 'Farmacia Central',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'nombre_negocio' => 'Farmacia Central',
            ]);

        $this->assertDatabaseHas('configuracion_sistema', [
            'nombre_negocio' => 'Farmacia Central',
        ]);
        $this->assertSame('Farmacia Central', ConfiguracionSistema::nombreNegocio());
    }

    public function test_el_sidebar_muestra_el_nombre_configurado(): void
    {
        ConfiguracionSistema::guardarNombre('Farmacia Central');

        $response = $this->get('/admin/dashboard');

        $response->assertOk();
        $response->assertSee('id="sidebar-brand"', false);
        $response->assertSee('Farmacia Central');
        $response->assertDontSee('Botica Admin');
    }

    public function test_el_ticket_muestra_el_nombre_configurado(): void
    {
        ConfiguracionSistema::guardarNombre('Farmacia Central');

        $cliente = Cliente::create([
            'dni' => '12345678',
            'nombre' => 'JUAN',
            'apellido_paterno' => 'PEREZ',
            'registro_web' => false,
        ]);

        $venta = Venta::create([
            'correlativo' => 'T001-00000001',
            'total' => '10.00',
            'cliente_id_cliente' => $cliente->id_cliente,
            'user_id' => $this->user->id,
        ]);

        $response = $this->get(route('admin.ventas.ticket', $venta));

        $response->assertOk();
        $response->assertSee('Farmacia Central');
    }

    public function test_el_modulo_requiere_el_permiso_correspondiente(): void
    {
        $sinPermiso = User::factory()->create(['activo' => true]);
        $this->actingAs($sinPermiso);

        $this->get(route('admin.datos-sistema.index'))->assertForbidden();
    }
}
