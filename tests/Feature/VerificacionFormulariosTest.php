<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VerificacionFormulariosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'clientes']);
        Permission::firstOrCreate(['name' => 'proveedores']);

        $user = User::factory()->create();
        $user->givePermissionTo(['clientes', 'proveedores']);

        $this->actingAs($user);
    }

    public function test_formulario_de_cliente_muestra_boton_verificar_cliente(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.clientes.create'));

        $response->assertOk();
        $response->assertSee('btn-verificar-dni-cliente', false);
        $response->assertSee('Verificar cliente');
    }

    public function test_formulario_de_proveedor_muestra_boton_verificar_proveedor(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.proveedores.create'));

        $response->assertOk();
        $response->assertSee('btn-verificar-ruc', false);
        $response->assertSee('Verificar proveedor');
    }

    public function test_formulario_publico_muestra_boton_verificar_dni(): void
    {
        $response = $this->get(route('public.registro'));

        $response->assertOk();
        $response->assertSee('btn-verificar-dni-registro', false);
        $response->assertSee('Verificar DNI');
    }
}
