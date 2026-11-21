<?php

namespace App\Providers;

use App\Models\Atencion;
use App\Models\Combo;
use App\Models\ConfiguracionSistema;
use App\Models\HeaderText;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->limitesDeLogin();

        Route::model('proveedore', Proveedor::class);
        Route::model('atencione', Atencion::class);

        Relation::morphMap([
            'producto' => Producto::class,
            'combo' => Combo::class,
        ]);

        View::composer('layouts.public', function ($view) {
            $count = 0;
            if (Auth::guard('clientes')->check()) {
                $carrito = Auth::guard('clientes')->user()->carrito;
                $count = $carrito?->items()->sum('cantidad') ?? 0;
            }
            $view->with('cartCount', $count);

            $headerTexts = HeaderText::where('activo', true)->orderBy('orden')->get();
            $view->with('headerTexts', $headerTexts);
        });

        View::composer(
            ['layouts.admin', 'admin.tickets.base', 'admin.reportes.pdf.partials.negocio'],
            function ($view) {
                $view->with('nombreNegocio', ConfiguracionSistema::nombreNegocio());
            }
        );
    }

    /**
     * Limita los intentos de login para frenar ataques de fuerza bruta.
     * Los contadores del panel y de la tienda son independientes.
     */
    private function limitesDeLogin(): void
    {
        RateLimiter::for('login-admin', function (Request $request) {
            return [
                Limit::perMinute(5)
                    ->by('admin:'.$this->claveDeLogin($request))
                    ->response(fn () => $this->demasiadosIntentos()),
                Limit::perMinute(20)
                    ->by('admin-ip:'.$request->ip())
                    ->response(fn () => $this->demasiadosIntentos()),
            ];
        });

        RateLimiter::for('login-cliente', function (Request $request) {
            return [
                Limit::perMinute(5)
                    ->by('cliente:'.$this->claveDeLogin($request))
                    ->response(fn () => $this->demasiadosIntentos()),
                Limit::perMinute(20)
                    ->by('cliente-ip:'.$request->ip())
                    ->response(fn () => $this->demasiadosIntentos()),
            ];
        });
    }

    private function claveDeLogin(Request $request): string
    {
        return Str::lower((string) $request->input('email')).'|'.$request->ip();
    }

    private function demasiadosIntentos()
    {
        return back()
            ->withErrors([
                'email' => 'Demasiados intentos fallidos. Espera un minuto y vuelve a intentar.',
            ])
            ->onlyInput('email');
    }
}
