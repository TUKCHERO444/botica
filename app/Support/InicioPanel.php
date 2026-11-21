<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Calcula a qué página del panel mandar al usuario después de iniciar sesión.
 *
 * No todos los roles tienen el permiso "dashboard" (por ejemplo Vendedor solo
 * tiene ventas y atenciones). Mandarlos siempre a /admin/dashboard provoca un
 * 403 justo después del login, así que se usa el primer módulo permitido.
 */
class InicioPanel
{
    /**
     * Permiso => ruta de su listado. El orden define la prioridad.
     *
     * @var array<string, string>
     */
    private const DESTINOS = [
        'dashboard' => 'admin.dashboard',
        'ventas' => 'admin.ventas.index',
        'atenciones' => 'admin.atenciones.index',
        'caja' => 'admin.caja.index',
        'productos' => 'admin.productos.index',
        'ingresos' => 'admin.ingresos.index',
        'inventario-inicial' => 'admin.inventario-inicial.index',
        'ajustes' => 'admin.ajustes.index',
        'devoluciones' => 'admin.devoluciones.index',
        'conciliaciones' => 'admin.conciliaciones.index',
        'kardex' => 'admin.kardex.index',
        'lotes' => 'admin.lotes.index',
        'clientes' => 'admin.clientes.index',
        'proveedores' => 'admin.proveedores.index',
        'servicios' => 'admin.servicios.index',
        'categorias' => 'admin.categorias.index',
        'marcas' => 'admin.marcas.index',
        'unidades-medida' => 'admin.unidades-medida.index',
        'presentaciones' => 'admin.presentaciones.index',
        'metodos-pago' => 'admin.metodos-pago.index',
        'usuarios' => 'admin.usuarios.index',
        'roles' => 'admin.roles.index',
        'sliders' => 'admin.sliders.index',
        'portada.header-text' => 'admin.header-text.index',
        'portada.banners' => 'admin.banners.index',
        'portada.categorias' => 'admin.portada-categorias.index',
        'portada.promociones' => 'admin.promociones.index',
        'combos' => 'admin.combos.index',
        'reportes' => 'admin.reportes.index',
        'auditoria' => 'admin.auditoria.index',
    ];

    /**
     * Primer destino del panel al que el usuario tiene acceso.
     */
    public static function para(?Authenticatable $usuario): string
    {
        if (! $usuario || ! method_exists($usuario, 'can')) {
            return route('login');
        }

        foreach (self::DESTINOS as $permiso => $ruta) {
            if (Route::has($ruta) && $usuario->can($permiso)) {
                return route($ruta);
            }
        }

        // Sin permisos del panel: la tienda evita un 403 sin salida.
        return route('home');
    }

    /**
     * Destino tras autenticarse. Respeta la URL que el usuario intentó abrir
     * antes del login solo si su rol realmente puede entrar (por ejemplo, un
     * Vendedor no debe volver a /admin/dashboard y comerse un 403).
     */
    public static function destino(Request $request, Authenticatable $usuario): string
    {
        $intendida = $request->session()->pull('url.intended');

        if (is_string($intendida) && $intendida !== '' && self::puedeVisitar($intendida, $usuario)) {
            return $intendida;
        }

        return self::para($usuario);
    }

    private static function puedeVisitar(string $url, Authenticatable $usuario): bool
    {
        try {
            $ruta = Route::getRoutes()->match(Request::create($url, 'GET'));
        } catch (NotFoundHttpException) {
            return false;
        }

        foreach ($ruta->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'permission:')) {
                $permisos = explode('|', substr($middleware, strlen('permission:')));

                return $usuario->canAny($permisos);
            }
        }

        return true;
    }
}
