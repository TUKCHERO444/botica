<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Public\CarritoController;
use App\Http\Controllers\Public\ComboController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ProductoCatalogoController;
use App\Models\Promocion;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/combos/{combo}', [ComboController::class, 'show'])->name('public.combos.show');

Route::post('/promociones/{promocion}/click', function (Promocion $promocion) {
    $promocion->increment('clicks');
    return response()->json(['success' => true]);
})->name('public.promociones.click');

Route::get('/productos', [ProductoCatalogoController::class, 'index'])->name('public.productos.index');
Route::get('/productos/buscar', [ProductoCatalogoController::class, 'buscar'])->name('public.productos.buscar');
Route::get('/productos/{producto}', [ProductoCatalogoController::class, 'show'])->name('public.productos.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('guest:clientes')->group(function () {
    Route::get('/registro', [\App\Http\Controllers\Public\RegistroController::class, 'showForm'])->name('public.registro');
    Route::post('/registro', [\App\Http\Controllers\Public\RegistroController::class, 'register']);
    Route::get('/iniciar-sesion', [\App\Http\Controllers\Public\SesionController::class, 'showForm'])->name('public.login');
    Route::post('/iniciar-sesion', [\App\Http\Controllers\Public\SesionController::class, 'login']);
});

Route::middleware('auth:clientes')->group(function () {
    Route::post('/cerrar-sesion', [\App\Http\Controllers\Public\SesionController::class, 'logout'])->name('public.logout');
    Route::get('/mi-perfil', [\App\Http\Controllers\Public\PerfilController::class, 'show'])->name('public.perfil');
    Route::put('/mi-perfil', [\App\Http\Controllers\Public\PerfilController::class, 'updateInfo'])->name('public.perfil.update');
    Route::put('/mi-perfil/password', [\App\Http\Controllers\Public\PerfilController::class, 'updatePassword'])->name('public.perfil.password');

    Route::prefix('carrito')->name('public.carrito.')->group(function () {
        Route::get('/', [CarritoController::class, 'index'])->name('index');
        Route::post('/', [CarritoController::class, 'store'])->name('store');
        Route::put('/{item}', [CarritoController::class, 'update'])->name('update');
        Route::delete('/{item}', [CarritoController::class, 'destroy'])->name('destroy');
        Route::get('/pdf', [CarritoController::class, 'pdf'])->name('pdf');
        Route::get('/whatsapp', [CarritoController::class, 'whatsapp'])->name('whatsapp');
    });

    Route::get('/carrito-count', [CarritoController::class, 'count'])->name('public.carrito.count');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::redirect('/dashboard', '/admin/dashboard');
});

require __DIR__.'/admin.php';
