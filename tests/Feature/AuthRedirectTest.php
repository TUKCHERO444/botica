<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'dashboard', 'ventas', 'atenciones', 'caja', 'productos',
            'usuarios', 'roles', 'clientes',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        Role::firstOrCreate(['name' => 'Administrador'])->syncPermissions($permisos);
        Role::firstOrCreate(['name' => 'Vendedor'])->syncPermissions(['ventas', 'atenciones']);
    }

    private function usuarioConRol(string $rol, array $atributos = []): User
    {
        $user = User::factory()->create(array_merge([
            'activo' => true,
            'password' => 'secreto123',
        ], $atributos));

        $user->assignRole($rol);

        return $user;
    }

    public function test_login_muestra_el_formulario_a_invitados(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Inicia sesión para administrar el sistema');
    }

    public function test_login_redirige_al_dashboard_con_sesion_administrativa_activa(): void
    {
        $this->actingAs($this->usuarioConRol('Administrador'))
            ->get(route('login'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_login_redirige_al_primer_modulo_permitido_si_no_tiene_dashboard(): void
    {
        $vendedor = $this->usuarioConRol('Vendedor', ['email' => 'vendedor-redirect@example.com']);

        $this->actingAs($vendedor)
            ->get(route('login'))
            ->assertRedirect(route('admin.ventas.index'));
    }

    public function test_login_publico_redirige_al_inicio_con_sesion_de_cliente_activa(): void
    {
        $cliente = Cliente::create([
            'dni' => '87654321',
            'nombre' => 'Cliente',
            'apellido_paterno' => 'Web',
            'email' => 'cliente-redirect@example.com',
            'password' => 'secret123',
            'registro_web' => true,
        ]);

        $this->actingAs($cliente, 'clientes')
            ->get(route('public.login'))
            ->assertRedirect(route('home'));

        $this->actingAs($cliente, 'clientes')
            ->post('/iniciar-sesion')
            ->assertRedirect(route('home'));
    }

    public function test_vendedor_no_cae_en_403_despues_del_login(): void
    {
        $this->usuarioConRol('Vendedor', ['email' => 'vendedor-login@example.com']);

        $this->post('/login', [
            'email' => 'vendedor-login@example.com',
            'password' => 'secreto123',
        ])->assertRedirect(route('admin.ventas.index'));

        $this->get(route('admin.ventas.index'))->assertOk();
    }

    public function test_administrador_entra_al_dashboard_despues_del_login(): void
    {
        $this->usuarioConRol('Administrador', ['email' => 'admin-login@example.com']);

        $this->post('/login', [
            'email' => 'admin-login@example.com',
            'password' => 'secreto123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_se_puede_cambiar_de_cuenta_con_una_sesion_activa(): void
    {
        $vendedor = $this->usuarioConRol('Vendedor', ['email' => 'vendedor-activo@example.com']);
        $admin = $this->usuarioConRol('Administrador', ['email' => 'admin-activo@example.com']);

        $this->actingAs($vendedor)
            ->post('/login', [
                'email' => 'admin-activo@example.com',
                'password' => 'secreto123',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_no_respeta_una_url_intentada_sin_permiso(): void
    {
        $this->usuarioConRol('Vendedor', ['email' => 'vendedor-intended@example.com']);

        // Un invitado pide el dashboard: queda guardado como URL intentada.
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        // Al entrar como Vendedor no debe volver al dashboard (403), sino a ventas.
        $this->post('/login', [
            'email' => 'vendedor-intended@example.com',
            'password' => 'secreto123',
        ])->assertRedirect(route('admin.ventas.index'));
    }
}
