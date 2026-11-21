<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAJE = 'Demasiados intentos fallidos. Espera un minuto y vuelve a intentar.';

    public function test_el_login_del_panel_se_bloquea_tras_cinco_intentos_fallidos(): void
    {
        $datos = ['email' => 'nadie@sistema.com', 'password' => 'incorrecta'];

        for ($i = 1; $i <= 5; $i++) {
            $this->from('/login')
                ->post('/login', $datos)
                ->assertRedirect('/login')
                ->assertSessionHasErrors(['email' => 'Las credenciales no coinciden.']);
        }

        $this->from('/login')
            ->post('/login', $datos)
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => self::MENSAJE]);
    }

    public function test_el_login_de_la_tienda_se_bloquea_tras_cinco_intentos_fallidos(): void
    {
        $datos = ['email' => 'nadie@correo.com', 'password' => 'incorrecta'];

        for ($i = 1; $i <= 5; $i++) {
            $this->from('/iniciar-sesion')
                ->post('/iniciar-sesion', $datos)
                ->assertRedirect('/iniciar-sesion')
                ->assertSessionHasErrors(['email' => 'Las credenciales no coinciden.']);
        }

        $this->from('/iniciar-sesion')
            ->post('/iniciar-sesion', $datos)
            ->assertRedirect('/iniciar-sesion')
            ->assertSessionHasErrors(['email' => self::MENSAJE]);
    }

    public function test_los_limites_del_panel_y_de_la_tienda_no_se_mezclan(): void
    {
        $datos = ['email' => 'nadie@sistema.com', 'password' => 'incorrecta'];

        for ($i = 1; $i <= 6; $i++) {
            $this->post('/login', $datos);
        }

        // El panel ya está bloqueado, pero la tienda sigue respondiendo normal.
        $this->from('/iniciar-sesion')
            ->post('/iniciar-sesion', ['email' => 'otro@correo.com', 'password' => 'incorrecta'])
            ->assertRedirect('/iniciar-sesion')
            ->assertSessionHasErrors(['email' => 'Las credenciales no coinciden.']);
    }

    public function test_unos_pocos_intentos_no_activan_el_bloqueo(): void
    {
        $this->from('/login')
            ->post('/login', ['email' => 'nadie@sistema.com', 'password' => 'mal'])
            ->assertSessionHasErrors(['email' => 'Las credenciales no coinciden.']);

        $this->from('/login')
            ->post('/login', ['email' => 'nadie@sistema.com', 'password' => 'otra'])
            ->assertSessionHasErrors(['email' => 'Las credenciales no coinciden.']);
    }
}
