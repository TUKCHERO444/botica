<?php

use App\Http\Controllers\Admin\AlertaVencimientoController;
use App\Http\Controllers\Admin\AtencionController;
use App\Http\Controllers\Admin\PortadaCategoriaController;
use App\Http\Controllers\Admin\CajaController;
use App\Http\Controllers\Admin\CategoriaController;
use App\Http\Controllers\Admin\ClienteController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\IngresoController;
use App\Http\Controllers\Admin\KardexController;
use App\Http\Controllers\Admin\MarcaController;
use App\Http\Controllers\Admin\ProductoController;
use App\Http\Controllers\Admin\ProveedorController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ServicioController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\ComboController;
use App\Http\Controllers\Admin\HeaderTextController;
use App\Http\Controllers\Admin\PromocionController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VentaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard')
        ->name('admin.dashboard');

    Route::get('/clientes/search', [ClienteController::class, 'search'])
        ->middleware('permission:clientes')
        ->name('admin.clientes.search');
    Route::post('/clientes/generico', [ClienteController::class, 'generico'])
        ->middleware('permission:clientes')
        ->name('admin.clientes.generico');

    Route::get('categorias/search', [CategoriaController::class, 'search'])
        ->middleware('permission:categorias')->name('admin.categorias.search');

    Route::patch('categorias/{categoria}/toggle-status', [CategoriaController::class, 'toggleStatus'])
        ->middleware('permission:categorias')->name('admin.categorias.toggleStatus');

    Route::resource('categorias', CategoriaController::class)->names([
        'index'   => 'admin.categorias.index', 'create'  => 'admin.categorias.create',
        'store'   => 'admin.categorias.store', 'edit'    => 'admin.categorias.edit',
        'update'  => 'admin.categorias.update', 'destroy' => 'admin.categorias.destroy',
    ])->except(['show'])->middleware('permission:categorias');

    Route::get('marcas/search', [MarcaController::class, 'search'])
        ->middleware('permission:marcas')->name('admin.marcas.search');

    Route::patch('marcas/{marca}/toggle-status', [MarcaController::class, 'toggleStatus'])
        ->middleware('permission:marcas')->name('admin.marcas.toggleStatus');

    Route::resource('marcas', MarcaController::class)->names([
        'index'   => 'admin.marcas.index', 'create'  => 'admin.marcas.create',
        'store'   => 'admin.marcas.store', 'edit'    => 'admin.marcas.edit',
        'update'  => 'admin.marcas.update', 'destroy' => 'admin.marcas.destroy',
    ])->except(['show'])->middleware('permission:marcas');

    Route::patch('proveedores/{proveedor}/toggle-status', [ProveedorController::class, 'toggleStatus'])
        ->middleware('permission:proveedores')->name('admin.proveedores.toggleStatus');

    Route::resource('proveedores', ProveedorController::class)->names([
        'index'   => 'admin.proveedores.index', 'create'  => 'admin.proveedores.create',
        'store'   => 'admin.proveedores.store', 'edit'    => 'admin.proveedores.edit',
        'update'  => 'admin.proveedores.update', 'destroy' => 'admin.proveedores.destroy',
    ])->middleware('permission:proveedores');

    Route::post('/reniec/consultar', [\App\Http\Controllers\Admin\ReniecController::class, 'checkDni'])
        ->middleware('permission:clientes')
        ->name('admin.reniec.consultar');

    Route::post('/sunat/consultar', [\App\Http\Controllers\Admin\SunatController::class, 'checkRuc'])
        ->middleware('permission:proveedores')
        ->name('admin.sunat.consultar');

    Route::resource('clientes', ClienteController::class)->names([
        'index'   => 'admin.clientes.index', 'create'  => 'admin.clientes.create',
        'store'   => 'admin.clientes.store', 'edit'    => 'admin.clientes.edit',
        'update'  => 'admin.clientes.update', 'destroy' => 'admin.clientes.destroy',
    ])->middleware('permission:clientes');

    Route::middleware('permission:productos')
        ->prefix('alertas')
        ->name('admin.alertas.')
        ->group(function () {
            Route::get('/vencimientos', [AlertaVencimientoController::class, 'index'])
                ->name('vencimientos');
        });

    Route::resource('servicios', ServicioController::class)->names([
        'index'   => 'admin.servicios.index', 'create'  => 'admin.servicios.create',
        'store'   => 'admin.servicios.store', 'edit'    => 'admin.servicios.edit',
        'update'  => 'admin.servicios.update', 'destroy' => 'admin.servicios.destroy',
    ])->middleware('permission:servicios');

    Route::get('productos/search', [ProductoController::class, 'search'])
        ->middleware('permission:productos')->name('admin.productos.search');

    Route::patch('productos/{producto}/toggle-status', [ProductoController::class, 'toggleStatus'])
        ->middleware('permission:productos')->name('admin.productos.toggleStatus');

    Route::resource('productos', ProductoController::class)->names([
        'index'   => 'admin.productos.index', 'create'  => 'admin.productos.create',
        'store'   => 'admin.productos.store', 'edit'    => 'admin.productos.edit',
        'update'  => 'admin.productos.update', 'destroy' => 'admin.productos.destroy',
    ])->except(['show'])->middleware('permission:productos');

    Route::resource('ventas', VentaController::class)->names([
        'index'   => 'admin.ventas.index', 'create'  => 'admin.ventas.create',
        'store'   => 'admin.ventas.store', 'show'    => 'admin.ventas.show',
        'edit'    => 'admin.ventas.edit', 'update'  => 'admin.ventas.update',
        'destroy' => 'admin.ventas.destroy',
    ])->middleware('permission:ventas');

    Route::resource('atenciones', AtencionController::class)->names([
        'index'   => 'admin.atenciones.index', 'create'  => 'admin.atenciones.create',
        'store'   => 'admin.atenciones.store', 'show'    => 'admin.atenciones.show',
        'edit'    => 'admin.atenciones.edit', 'update'  => 'admin.atenciones.update',
        'destroy' => 'admin.atenciones.destroy',
    ])->middleware('permission:atenciones');

    Route::resource('ingresos', IngresoController::class)->names([
        'index'   => 'admin.ingresos.index', 'create'  => 'admin.ingresos.create',
        'store'   => 'admin.ingresos.store', 'show'    => 'admin.ingresos.show',
        'edit'    => 'admin.ingresos.edit', 'update'  => 'admin.ingresos.update',
        'destroy' => 'admin.ingresos.destroy',
    ])->middleware('permission:ingresos');

    Route::middleware('permission:kardex')
        ->prefix('kardex')
        ->name('admin.kardex.')
        ->group(function () {
            Route::get('/', [KardexController::class, 'index'])->name('index');
            Route::get('/producto/{producto}', [KardexController::class, 'porProducto'])->name('producto');
        });

    Route::patch('usuarios/{usuario}/toggle-status', [UserController::class, 'toggleStatus'])
        ->middleware('permission:usuarios')->name('admin.usuarios.toggleStatus');

    Route::resource('usuarios', UserController::class)->names([
        'index'   => 'admin.usuarios.index', 'create'  => 'admin.usuarios.create',
        'store'   => 'admin.usuarios.store', 'edit'    => 'admin.usuarios.edit',
        'update'  => 'admin.usuarios.update', 'destroy' => 'admin.usuarios.destroy',
    ])->middleware('permission:usuarios');

    Route::prefix('caja')->name('admin.caja.')->group(function () {
        Route::get('/', [CajaController::class, 'index'])->name('index');
        Route::get('/apertura', [CajaController::class, 'formApertura'])->name('formApertura');
        Route::post('/apertura', [CajaController::class, 'apertura'])->name('apertura');
        Route::get('/egreso', [CajaController::class, 'formEgreso'])->name('formEgreso');
        Route::post('/egreso', [CajaController::class, 'egreso'])->name('egreso');
        Route::post('/cierre', [CajaController::class, 'cierre'])->name('cierre');
        Route::get('/historial', [CajaController::class, 'historial'])->name('historial');
        Route::get('/historial/{caja}', [CajaController::class, 'historialShow'])->name('historialShow');
    })->middleware('permission:caja');

    Route::middleware('permission:sliders')
        ->prefix('sliders')
        ->name('admin.sliders.')
        ->group(function () {
            Route::get('/', [SliderController::class, 'index'])->name('index');
            Route::get('/productos/{tipo}', [SliderController::class, 'listar'])->name('listar');
            Route::post('/asignar/{tipo}', [SliderController::class, 'asignar'])->name('asignar');
            Route::delete('/quitar/{tipo}/{producto}', [SliderController::class, 'quitar'])->name('quitar');
            Route::post('/reordenar/{tipo}', [SliderController::class, 'reordenar'])->name('reordenar');
            Route::get('/buscar-productos', [SliderController::class, 'buscarProductos'])->name('buscar');
        });

    Route::middleware('permission:portada.header-text')
        ->prefix('header-text')
        ->name('admin.header-text.')
        ->group(function () {
            Route::get('/', [HeaderTextController::class, 'index'])->name('index');
            Route::get('/create', [HeaderTextController::class, 'create'])->name('create');
            Route::post('/', [HeaderTextController::class, 'store'])->name('store');
            Route::get('/{headerText}/edit', [HeaderTextController::class, 'edit'])->name('edit');
            Route::put('/{headerText}', [HeaderTextController::class, 'update'])->name('update');
            Route::delete('/{headerText}', [HeaderTextController::class, 'destroy'])->name('destroy');
            Route::post('/reordenar', [HeaderTextController::class, 'reordenar'])->name('reordenar');
        });

    Route::middleware('permission:portada.banners')
        ->prefix('banners')
        ->name('admin.banners.')
        ->group(function () {
            Route::get('/', [BannerController::class, 'index'])->name('index');
            Route::get('/create', [BannerController::class, 'create'])->name('create');
            Route::post('/', [BannerController::class, 'store'])->name('store');
            Route::get('/{banner}/edit', [BannerController::class, 'edit'])->name('edit');
            Route::put('/{banner}', [BannerController::class, 'update'])->name('update');
            Route::delete('/{banner}', [BannerController::class, 'destroy'])->name('destroy');
            Route::post('/reordenar', [BannerController::class, 'reordenar'])->name('reordenar');
        });

    Route::middleware('permission:portada.categorias')
        ->prefix('portada-categorias')
        ->name('admin.portada-categorias.')
        ->group(function () {
            Route::get('/', [PortadaCategoriaController::class, 'index'])->name('index');
            Route::post('/toggle/{categoria}', [PortadaCategoriaController::class, 'toggle'])->name('toggle');
            Route::post('/reordenar', [PortadaCategoriaController::class, 'reordenar'])->name('reordenar');
        });

    Route::middleware('permission:portada.promociones')
        ->prefix('promociones')
        ->name('admin.promociones.')
        ->group(function () {
            Route::get('/', [PromocionController::class, 'index'])->name('index');
            Route::get('/create', [PromocionController::class, 'create'])->name('create');
            Route::post('/', [PromocionController::class, 'store'])->name('store');
            Route::get('/{promocion}/edit', [PromocionController::class, 'edit'])->name('edit');
            Route::put('/{promocion}', [PromocionController::class, 'update'])->name('update');
            Route::delete('/{promocion}', [PromocionController::class, 'destroy'])->name('destroy');
            Route::post('/reordenar', [PromocionController::class, 'reordenar'])->name('reordenar');
            Route::get('/buscar-entidad', [PromocionController::class, 'buscarEntidad'])->name('buscar-entidad');
        });

    Route::middleware('permission:combos')
        ->prefix('combos')
        ->name('admin.combos.')
        ->group(function () {
            Route::get('/', [ComboController::class, 'index'])->name('index');
            Route::get('/create', [ComboController::class, 'create'])->name('create');
            Route::post('/', [ComboController::class, 'store'])->name('store');
            Route::get('/{combo}/edit', [ComboController::class, 'edit'])->name('edit');
            Route::put('/{combo}', [ComboController::class, 'update'])->name('update');
            Route::delete('/{combo}', [ComboController::class, 'destroy'])->name('destroy');
            Route::get('/buscar-productos', [ComboController::class, 'buscarProductos'])->name('buscar-productos');
            Route::get('/buscar-combos', [ComboController::class, 'buscarCombos'])->name('buscar-combos');
        });

    Route::resource('roles', RoleController::class)->names([
        'index'   => 'admin.roles.index', 'create'  => 'admin.roles.create',
        'store'   => 'admin.roles.store', 'edit'    => 'admin.roles.edit',
        'update'  => 'admin.roles.update', 'destroy' => 'admin.roles.destroy',
    ])->middleware('permission:roles');

    Route::middleware('permission:auditoria')
        ->prefix('auditoria')
        ->name('admin.auditoria.')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AuditoriaController::class, 'index'])->name('index');
            Route::get('/{auditorium}', [\App\Http\Controllers\Admin\AuditoriaController::class, 'show'])->name('show');
        });

    Route::middleware('permission:reportes')
        ->prefix('reportes')
        ->name('admin.reportes.')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ReporteController::class, 'index'])->name('index');
            Route::get('/generate', [\App\Http\Controllers\Admin\ReporteController::class, 'generate'])->name('generate');
            Route::get('/preview', [\App\Http\Controllers\Admin\ReporteController::class, 'preview'])->name('preview');
        });
});
