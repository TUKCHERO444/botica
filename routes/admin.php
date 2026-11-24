<?php

use App\Http\Controllers\Admin\AjusteInventarioController;
use App\Http\Controllers\Admin\AtencionController;
use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\CajaController;
use App\Http\Controllers\Admin\CategoriaController;
use App\Http\Controllers\Admin\ClienteController;
use App\Http\Controllers\Admin\ComboController;
use App\Http\Controllers\Admin\ConciliacionInventarioController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DatosSistemaController;
use App\Http\Controllers\Admin\DevolucionProveedorController;
use App\Http\Controllers\Admin\HeaderTextController;
use App\Http\Controllers\Admin\IngresoController;
use App\Http\Controllers\Admin\InventarioInicialController;
use App\Http\Controllers\Admin\KardexController;
use App\Http\Controllers\Admin\LoteController;
use App\Http\Controllers\Admin\MarcaController;
use App\Http\Controllers\Admin\MetodoPagoController;
use App\Http\Controllers\Admin\PortadaCategoriaController;
use App\Http\Controllers\Admin\PresentacionController;
use App\Http\Controllers\Admin\ProductoController;
use App\Http\Controllers\Admin\PromocionController;
use App\Http\Controllers\Admin\ProveedorController;
use App\Http\Controllers\Admin\ReniecController;
use App\Http\Controllers\Admin\ReporteController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ServicioController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Admin\SunatController;
use App\Http\Controllers\Admin\UnidadMedidaController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VentaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard')
        ->name('admin.dashboard');

    Route::get('/clientes/search', [ClienteController::class, 'search'])
        ->middleware('permission:clientes|ventas|atenciones')
        ->name('admin.clientes.search');
    Route::post('/clientes/verificar-dni', [ClienteController::class, 'verificarDni'])
        ->middleware('permission:clientes|ventas|atenciones')
        ->name('admin.clientes.verificarDni');
    Route::post('/clientes/generico', [ClienteController::class, 'generico'])
        ->middleware('permission:clientes|ventas|atenciones')
        ->name('admin.clientes.generico');

    Route::get('categorias/search', [CategoriaController::class, 'search'])
        ->middleware('permission:categorias')->name('admin.categorias.search');

    Route::patch('categorias/{categoria}/toggle-status', [CategoriaController::class, 'toggleStatus'])
        ->middleware('permission:categorias')->name('admin.categorias.toggleStatus');

    Route::resource('categorias', CategoriaController::class)->names([
        'index' => 'admin.categorias.index', 'create' => 'admin.categorias.create',
        'store' => 'admin.categorias.store', 'edit' => 'admin.categorias.edit',
        'update' => 'admin.categorias.update', 'destroy' => 'admin.categorias.destroy',
    ])->except(['show'])->middleware('permission:categorias');

    Route::get('marcas/search', [MarcaController::class, 'search'])
        ->middleware('permission:marcas')->name('admin.marcas.search');

    Route::patch('marcas/{marca}/toggle-status', [MarcaController::class, 'toggleStatus'])
        ->middleware('permission:marcas')->name('admin.marcas.toggleStatus');

    Route::resource('marcas', MarcaController::class)->names([
        'index' => 'admin.marcas.index', 'create' => 'admin.marcas.create',
        'store' => 'admin.marcas.store', 'edit' => 'admin.marcas.edit',
        'update' => 'admin.marcas.update', 'destroy' => 'admin.marcas.destroy',
    ])->except(['show'])->middleware('permission:marcas');

    Route::resource('unidades-medida', UnidadMedidaController::class)
        ->names([
            'index' => 'admin.unidades-medida.index',
            'create' => 'admin.unidades-medida.create',
            'store' => 'admin.unidades-medida.store',
            'edit' => 'admin.unidades-medida.edit',
            'update' => 'admin.unidades-medida.update',
            'destroy' => 'admin.unidades-medida.destroy',
        ])
        ->parameters(['unidades-medida' => 'unidadMedida'])
        ->except(['show'])
        ->middleware('permission:unidades-medida');

    Route::resource('presentaciones', PresentacionController::class)
        ->names([
            'index' => 'admin.presentaciones.index',
            'create' => 'admin.presentaciones.create',
            'store' => 'admin.presentaciones.store',
            'edit' => 'admin.presentaciones.edit',
            'update' => 'admin.presentaciones.update',
            'destroy' => 'admin.presentaciones.destroy',
        ])
        ->parameters(['presentaciones' => 'presentacion'])
        ->except(['show'])
        ->middleware('permission:presentaciones');

    Route::patch('proveedores/{proveedor}/toggle-status', [ProveedorController::class, 'toggleStatus'])
        ->middleware('permission:proveedores')->name('admin.proveedores.toggleStatus');

    Route::post('proveedores/verificar-ruc', [ProveedorController::class, 'verificarRuc'])
        ->middleware('permission:proveedores')->name('admin.proveedores.verificarRuc');

    Route::resource('proveedores', ProveedorController::class)->names([
        'index' => 'admin.proveedores.index', 'create' => 'admin.proveedores.create',
        'store' => 'admin.proveedores.store', 'edit' => 'admin.proveedores.edit',
        'update' => 'admin.proveedores.update', 'destroy' => 'admin.proveedores.destroy',
    ])->middleware('permission:proveedores');

    Route::post('/reniec/consultar', [ReniecController::class, 'checkDni'])
        ->middleware('permission:clientes|ventas|atenciones')
        ->name('admin.reniec.consultar');

    Route::post('/sunat/consultar', [SunatController::class, 'checkRuc'])
        ->middleware('permission:proveedores')
        ->name('admin.sunat.consultar');

    Route::resource('clientes', ClienteController::class)->names([
        'index' => 'admin.clientes.index', 'create' => 'admin.clientes.create',
        'store' => 'admin.clientes.store', 'edit' => 'admin.clientes.edit',
        'update' => 'admin.clientes.update', 'destroy' => 'admin.clientes.destroy',
    ])->middleware('permission:clientes');

    Route::resource('servicios', ServicioController::class)->names([
        'index' => 'admin.servicios.index', 'create' => 'admin.servicios.create',
        'store' => 'admin.servicios.store', 'edit' => 'admin.servicios.edit',
        'update' => 'admin.servicios.update', 'destroy' => 'admin.servicios.destroy',
    ])->middleware('permission:servicios');

    Route::patch('metodos-pago/{metodoPago}/toggle-status', [MetodoPagoController::class, 'toggleStatus'])
        ->middleware('permission:metodos-pago')->name('admin.metodos-pago.toggleStatus');

    Route::resource('metodos-pago', MetodoPagoController::class)
        ->parameters(['metodos-pago' => 'metodoPago'])
        ->names([
            'index' => 'admin.metodos-pago.index',
            'create' => 'admin.metodos-pago.create',
            'store' => 'admin.metodos-pago.store',
            'edit' => 'admin.metodos-pago.edit',
            'update' => 'admin.metodos-pago.update',
            'destroy' => 'admin.metodos-pago.destroy',
        ])
        ->except(['show'])
        ->middleware('permission:metodos-pago');

    Route::get('productos/search', [ProductoController::class, 'search'])
        ->middleware('permission:productos')->name('admin.productos.search');

    Route::patch('productos/{producto}/toggle-status', [ProductoController::class, 'toggleStatus'])
        ->middleware('permission:productos')->name('admin.productos.toggleStatus');

    Route::resource('productos', ProductoController::class)->names([
        'index' => 'admin.productos.index', 'create' => 'admin.productos.create',
        'store' => 'admin.productos.store', 'edit' => 'admin.productos.edit',
        'update' => 'admin.productos.update', 'destroy' => 'admin.productos.destroy',
    ])->except(['show'])->middleware('permission:productos');

    Route::get('ventas/presentaciones/search', [VentaController::class, 'searchPresentaciones'])
        ->middleware('permission:ventas')
        ->name('admin.ventas.presentaciones.search');

    Route::get('ventas/{venta}/ticket', [VentaController::class, 'ticket'])
        ->middleware('permission:ventas')
        ->name('admin.ventas.ticket');

    Route::get('ventas/{venta}/anular', [VentaController::class, 'formAnular'])
        ->middleware('permission:ventas')
        ->name('admin.ventas.formAnular');

    Route::post('ventas/{venta}/anular', [VentaController::class, 'anular'])
        ->middleware('permission:ventas')
        ->name('admin.ventas.anular');

    Route::get('ventas/historial', [VentaController::class, 'historial'])
        ->middleware('permission:ventas')
        ->name('admin.ventas.historial');

    Route::resource('ventas', VentaController::class)->names([
        'index' => 'admin.ventas.index', 'create' => 'admin.ventas.create',
        'store' => 'admin.ventas.store', 'show' => 'admin.ventas.show',
        'edit' => 'admin.ventas.edit', 'update' => 'admin.ventas.update',
        'destroy' => 'admin.ventas.destroy',
    ])->middleware('permission:ventas');

    Route::get('atenciones/{atencione}/ticket', [AtencionController::class, 'ticket'])
        ->middleware('permission:atenciones')
        ->name('admin.atenciones.ticket');

    Route::get('atenciones/{atencione}/anular', [AtencionController::class, 'formAnular'])
        ->middleware('permission:atenciones')
        ->name('admin.atenciones.formAnular');

    Route::post('atenciones/{atencione}/anular', [AtencionController::class, 'anular'])
        ->middleware('permission:atenciones')
        ->name('admin.atenciones.anular');

    Route::get('atenciones/historial', [AtencionController::class, 'historial'])
        ->middleware('permission:atenciones')
        ->name('admin.atenciones.historial');

    Route::resource('atenciones', AtencionController::class)->names([
        'index' => 'admin.atenciones.index', 'create' => 'admin.atenciones.create',
        'store' => 'admin.atenciones.store', 'show' => 'admin.atenciones.show',
        'edit' => 'admin.atenciones.edit', 'update' => 'admin.atenciones.update',
        'destroy' => 'admin.atenciones.destroy',
    ])->middleware('permission:atenciones');

    Route::get('ingresos/presentaciones/search', [IngresoController::class, 'searchPresentaciones'])
        ->middleware('permission:ingresos')
        ->name('admin.ingresos.presentaciones.search');

    Route::resource('ingresos', IngresoController::class)->names([
        'index' => 'admin.ingresos.index', 'create' => 'admin.ingresos.create',
        'store' => 'admin.ingresos.store', 'show' => 'admin.ingresos.show',
        'edit' => 'admin.ingresos.edit', 'update' => 'admin.ingresos.update',
        'destroy' => 'admin.ingresos.destroy',
    ])->middleware('permission:ingresos');

    Route::middleware('permission:ingresos')
        ->prefix('ingresos')
        ->name('admin.ingresos.')
        ->group(function () {
            Route::get('{ingreso}/recibir', [IngresoController::class, 'formRecibir'])->name('formRecibir');
            Route::post('{ingreso}/recibir', [IngresoController::class, 'recibir'])->name('recibir');
            Route::get('{ingreso}/anular', [IngresoController::class, 'formAnular'])->name('formAnular');
            Route::post('{ingreso}/anular', [IngresoController::class, 'anular'])->name('anular');
        });

    Route::resource('devoluciones', DevolucionProveedorController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->parameters(['devoluciones' => 'devolucion'])
        ->names([
            'index' => 'admin.devoluciones.index',
            'create' => 'admin.devoluciones.create',
            'store' => 'admin.devoluciones.store',
            'show' => 'admin.devoluciones.show',
        ])
        ->middleware('permission:devoluciones');

    Route::middleware('permission:devoluciones')
        ->prefix('devoluciones')
        ->name('admin.devoluciones.')
        ->group(function () {
            Route::get('{devolucion}/anular', [DevolucionProveedorController::class, 'formAnular'])->name('formAnular');
            Route::post('{devolucion}/anular', [DevolucionProveedorController::class, 'anular'])->name('anular');
        });

    Route::get('ajustes/lotes/search', [AjusteInventarioController::class, 'searchLotes'])
        ->middleware('permission:ajustes')
        ->name('admin.ajustes.lotes.search');

    Route::resource('ajustes', AjusteInventarioController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->names([
            'index' => 'admin.ajustes.index',
            'create' => 'admin.ajustes.create',
            'store' => 'admin.ajustes.store',
            'show' => 'admin.ajustes.show',
        ])
        ->middleware('permission:ajustes');

    Route::middleware('permission:inventario-inicial')
        ->prefix('inventario-inicial')
        ->name('admin.inventario-inicial.')
        ->group(function () {
            Route::get('/', [InventarioInicialController::class, 'index'])->name('index');
            Route::get('/create', [InventarioInicialController::class, 'create'])->name('create');
            Route::get('/presentaciones/search', [InventarioInicialController::class, 'searchPresentaciones'])
                ->name('presentaciones.search');
            Route::post('/', [InventarioInicialController::class, 'store'])->name('store');
            Route::get('/plantilla', [InventarioInicialController::class, 'plantilla'])->name('plantilla');
            Route::get('/exportar-productos', [InventarioInicialController::class, 'exportarProductos'])
                ->name('exportarProductos');
            Route::post('/importar', [InventarioInicialController::class, 'importar'])->name('importar');
            Route::get('/{inventario}/edit', [InventarioInicialController::class, 'edit'])->name('edit');
            Route::put('/{inventario}', [InventarioInicialController::class, 'update'])->name('update');
            Route::get('/{inventario}', [InventarioInicialController::class, 'show'])->name('show');
            Route::post('/{inventario}/confirmar', [InventarioInicialController::class, 'confirmar'])
                ->name('confirmar');
            Route::delete('/{inventario}', [InventarioInicialController::class, 'destroy'])->name('destroy');
        });

    Route::middleware('permission:conciliaciones')
        ->prefix('conciliaciones')
        ->name('admin.conciliaciones.')
        ->group(function () {
            Route::get('/', [ConciliacionInventarioController::class, 'index'])->name('index');
            Route::post('/ejecutar', [ConciliacionInventarioController::class, 'ejecutar'])->name('ejecutar');
            Route::get('/{conciliacion}', [ConciliacionInventarioController::class, 'show'])->name('show');
        });

    Route::middleware('permission:kardex')
        ->prefix('kardex')
        ->name('admin.kardex.')
        ->group(function () {
            Route::get('/', [KardexController::class, 'index'])->name('index');
            Route::get('/producto/{producto}', [KardexController::class, 'porProducto'])->name('producto');
        });

    Route::middleware('permission:lotes')
        ->prefix('lotes')
        ->name('admin.lotes.')
        ->group(function () {
            Route::get('/', [LoteController::class, 'index'])->name('index');
            Route::delete('/{productoLote}', [LoteController::class, 'baja'])->name('baja');
        });

    Route::patch('usuarios/{usuario}/toggle-status', [UserController::class, 'toggleStatus'])
        ->middleware('permission:usuarios')->name('admin.usuarios.toggleStatus');

    Route::resource('usuarios', UserController::class)->names([
        'index' => 'admin.usuarios.index', 'create' => 'admin.usuarios.create',
        'store' => 'admin.usuarios.store', 'edit' => 'admin.usuarios.edit',
        'update' => 'admin.usuarios.update', 'destroy' => 'admin.usuarios.destroy',
    ])->middleware('permission:usuarios');

    Route::middleware('permission:caja')
        ->prefix('caja')
        ->name('admin.caja.')
        ->group(function () {
            Route::get('/', [CajaController::class, 'index'])->name('index');
            Route::get('/apertura', [CajaController::class, 'formApertura'])->name('formApertura');
            Route::post('/apertura', [CajaController::class, 'apertura'])->name('apertura');
            Route::get('/ingreso', [CajaController::class, 'formIngreso'])->name('formIngreso');
            Route::post('/ingreso', [CajaController::class, 'ingreso'])->name('ingreso');
            Route::get('/egreso', [CajaController::class, 'formEgreso'])->name('formEgreso');
            Route::post('/egreso', [CajaController::class, 'egreso'])->name('egreso');
            Route::get('/cierre', [CajaController::class, 'formCierre'])->name('formCierre');
            Route::post('/cierre', [CajaController::class, 'cierre'])->name('cierre');
            Route::get('/historial', [CajaController::class, 'historial'])->name('historial');
            Route::get('/historial/{caja}', [CajaController::class, 'historialShow'])->name('historialShow');
        });

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
        'index' => 'admin.roles.index', 'create' => 'admin.roles.create',
        'store' => 'admin.roles.store', 'edit' => 'admin.roles.edit',
        'update' => 'admin.roles.update', 'destroy' => 'admin.roles.destroy',
    ])->middleware('permission:roles');

    Route::middleware('permission:auditoria')
        ->prefix('auditoria')
        ->name('admin.auditoria.')
        ->group(function () {
            Route::get('/', [AuditoriaController::class, 'index'])->name('index');
            Route::get('/{auditorium}', [AuditoriaController::class, 'show'])->name('show');
        });

    Route::middleware('permission:reportes')
        ->prefix('reportes')
        ->name('admin.reportes.')
        ->group(function () {
            Route::get('/', [ReporteController::class, 'index'])->name('index');
            Route::get('/generate', [ReporteController::class, 'generate'])->name('generate');
            Route::get('/preview', [ReporteController::class, 'preview'])->name('preview');
        });

    Route::middleware('permission:datos-sistema')
        ->prefix('datos-sistema')
        ->name('admin.datos-sistema.')
        ->group(function () {
            Route::get('/', [DatosSistemaController::class, 'index'])->name('index');
            Route::put('/', [DatosSistemaController::class, 'update'])->name('update');
        });
});
