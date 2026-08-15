<?php

namespace App\Providers;

use App\Models\Atencion;
use App\Models\Combo;
use App\Models\HeaderText;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::model('proveedore', Proveedor::class);
        Route::model('atencione', Atencion::class);

        Relation::morphMap([
            'producto' => Producto::class,
            'combo'    => Combo::class,
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
    }
}
