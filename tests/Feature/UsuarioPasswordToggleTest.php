<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UsuarioPasswordToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'usuarios']);

        $user = User::factory()->create();
        $user->givePermissionTo('usuarios');
        $this->actingAs($user);
    }

    public function test_formulario_de_creacion_muestra_boton_para_revelar_la_contrasena(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.usuarios.create'));

        $response->assertOk();
        $response->assertSee('data-password-field', false);
        $response->assertSee('data-toggle-password', false);
        $response->assertSee('aria-label="Mostrar contraseña"', false);
        $response->assertSee('type="password"', false);
    }

    public function test_formulario_de_edicion_tambien_muestra_boton_para_revelar_la_contrasena(): void
    {
        $usuario = User::factory()->create();

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.usuarios.edit', $usuario));

        $response->assertOk();
        $response->assertSee('data-password-field', false);
        $response->assertSee('data-toggle-password', false);
    }
}
