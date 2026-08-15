# Plan de Implementación — Módulo Kardex de Inventario

## 1. Objetivo

Implementar un módulo de Kardex de Inventario que registre automáticamente todos los movimientos de stock (INGRESO, VENTA, ATENCIÓN) generados por las operaciones del sistema, proporcionando trazabilidad completa, auditoría y una interfaz de consulta con filtros, sin modificar la lógica actual de stock ni agregar una fuente de verdad independiente.

---

## 2. Stack y contexto

| Aspecto | Detalle |
|---------|---------|
| Framework | Laravel 12 |
| Frontend | Blade SPA (AJAX) con Tailwind v4 |
| Auth/RBAC | Spatie Laravel Permission |
| DB | MySQL |
| Patrón existente | Controladores con traits `CacheaPanel` + `Audita` + `StockHelper` |
| Módulos afectados | Ingresos, Ventas, Atenciones, Productos (solo lectura) |
| Sidebar | Definido en `resources/views/layouts/admin.blade.php` |

---

## 3. Arquitectura del módulo

### 3.1. Componentes

```
database/migrations/
  └── YYYY_MM_DD_HHMMSS_create_kardex_movimientos_table.php

app/Models/
  └── KardexMovimiento.php

app/Helpers/
  └── KardexHelper.php

app/Http/Controllers/Admin/
  ├── KardexController.php          (nuevo — solo lectura)
  ├── IngresoController.php          (modificado)
  ├── VentaController.php            (modificado — vía StockHelper)
  └── AtencionController.php         (modificado — vía StockHelper)

app/Helpers/
  └── StockHelper.php                (modificado — consumir + revertir)

resources/views/admin/kardex/
  ├── index.blade.php                (partial — listado con filtros)
  ├── producto.blade.php             (partial — movimientos por producto)
  └── full.blade.php                 (vista completa con layout)

resources/views/admin/productos/
  └── index.blade.php                (modificado — botón "Ver Kardex")

resources/views/layouts/
  └── admin.blade.php                (modificado — link sidebar)

routes/
  └── admin.php                      (modificado — grupo de rutas kardex)

database/seeders/
  └── PermissionSeeder.php           (modificado — permiso kardex)
```

### 3.2. Diagrama de flujo

```
OPERACIÓN DEL SISTEMA                   KARDEX
─────────────────────────               ──────────────────────────

INGRESO (store)
  ├── Producto.increment('stock')
  ├── LoteProducto.create(...)
  └── KardexHelper::registrar(           ← NUEVO
         tipo: INGRESO,
         referencia: Ingreso,
         cantidad: +N,
         stock_anterior, stock_nuevo
       )

INGRESO (update)
  ├── Revertir stock anterior
  ├── KardexHelper::eliminarPorReferencia()   ← NUEVO
  ├── Aplicar nuevo stock
  ├── Nuevos LoteProducto
  └── KardexHelper::registrar()               ← NUEVO

INGRESO (destroy)
  ├── Producto.decrement('stock')
  ├── LoteProducto.delete()
  ├── KardexHelper::eliminarPorReferencia()   ← NUEVO
  └── Ingreso.delete()

VENTA / ATENCIÓN (store)
  └── StockHelper::consumir()
        ├── LoteProducto.decrement() (FEFO)
        ├── DetalleVentaLote / AtencionProductoLote.create()
        ├── Producto.decrement('stock')
        └── KardexHelper::registrar()         ← NUEVO (dentro de consumir)

VENTA / ATENCIÓN (update)
  ├── StockHelper::revertir()
  │     ├── LoteProducto.increment()
  │     ├── DetalleVentaLote / AtencionProductoLote.delete()
  │     ├── Producto.increment('stock')
  │     └── KardexHelper::eliminarPorReferencia()   ← NUEVO
  └── StockHelper::consumir()
        └── KardexHelper::registrar()               ← NUEVO

VENTA / ATENCIÓN (destroy)
  └── StockHelper::revertir()
        └── KardexHelper::eliminarPorReferencia()   ← NUEVO

USUARIO (interfaz)
  ├── Sidebar → Kardex
  │     └── KardexController@index
  │           ├── Filtros: producto, tipo, usuario, fechas
  │           └── Tabla: Fecha | Producto | Tipo | Ref | Cantidad | Stock Ant | Stock Nuevo | Usuario
  │
  └── Productos → [Ver Kardex] (por fila)
        └── KardexController@porProducto
              └── Misma tabla filtrada por producto_id
```

---

## 4. Diseño de base de datos

### Tabla `kardex_movimientos`

```sql
CREATE TABLE kardex_movimientos (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    producto_id       BIGINT UNSIGNED NOT NULL,
    usuario_id        BIGINT UNSIGNED NOT NULL,
    tipo_movimiento   ENUM('INGRESO', 'VENTA', 'ATENCION') NOT NULL,

    -- Referencia polimórfica a la operación origen
    referencia_tipo   VARCHAR(50) NOT NULL,        -- 'Ingreso', 'Venta', 'Atencion'
    referencia_id     BIGINT UNSIGNED NOT NULL,     -- ID de la entidad origen
    referencia_codigo VARCHAR(50) NOT NULL,         -- Código visible: ING-000001, V-000123, etc.

    -- Datos del movimiento
    cantidad          INTEGER NOT NULL,             -- +50 o -2
    stock_anterior    INTEGER NOT NULL,             -- Stock antes del movimiento
    stock_nuevo       INTEGER NOT NULL,             -- Stock después del movimiento
    observacion       TEXT NULL,

    created_at        TIMESTAMP NULL,
    updated_at        TIMESTAMP NULL,

    FOREIGN KEY (producto_id) REFERENCES producto(id_producto) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX kardex_producto_idx (producto_id),
    INDEX kardex_tipo_idx (tipo_movimiento),
    INDEX kardex_referencia_idx (referencia_tipo, referencia_id),
    INDEX kardex_created_at_idx (created_at)
);
```

### Descripción de campos

| Columna | Propósito |
|---------|-----------|
| `producto_id` | FK al producto afectado |
| `usuario_id` | FK al usuario que realizó la operación original |
| `tipo_movimiento` | `INGRESO`, `VENTA` o `ATENCION` |
| `referencia_tipo` | Nombre del modelo origen: `Ingreso`, `Venta`, `Atencion` |
| `referencia_id` | ID de la entidad origen (ej. `id_ingreso = 15`) |
| `referencia_codigo` | Código visible de la operación (ej. `ING-000001`, `V-000123`) |
| `cantidad` | Movimiento realizado: positivo para ingresos, negativo para egresos |
| `stock_anterior` | Stock del producto antes del movimiento |
| `stock_nuevo` | Stock del producto después del movimiento |
| `observacion` | Texto opcional (ej. "Ingreso de inventario", "Venta registrada") |

---

## 5. Modelo Eloquent

### `KardexMovimiento.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KardexMovimiento extends Model
{
    protected $table = 'kardex_movimientos';

    protected $fillable = [
        'producto_id',
        'usuario_id',
        'tipo_movimiento',
        'referencia_tipo',
        'referencia_id',
        'referencia_codigo',
        'cantidad',
        'stock_anterior',
        'stock_nuevo',
        'observacion',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'stock_anterior' => 'integer',
        'stock_nuevo' => 'integer',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id', 'id_producto');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
```

---

## 6. Helper `KardexHelper`

Se crea en `app/Helpers/KardexHelper.php` siguiendo el mismo patrón que `StockHelper`. Centraliza la creación y eliminación de movimientos Kardex para mantener el registro de trazabilidad consistente desde todos los puntos de integración.

```php
<?php

namespace App\Helpers;

use App\Models\KardexMovimiento;
use App\Models\Producto;

class KardexHelper
{
    /**
     * Registrar un movimiento en el Kardex.
     */
    public static function registrar(
        Producto $producto,
        string $tipoMovimiento,    // INGRESO|VENTA|ATENCION
        string $referenciaTipo,    // Ingreso|Venta|Atencion
        int $referenciaId,
        string $referenciaCodigo,
        int $cantidad,             // +50 o -2
        ?string $observacion = null
    ): KardexMovimiento {
        $stockAnterior = $producto->stock - $cantidad;
        $stockNuevo = $producto->stock;

        return KardexMovimiento::create([
            'producto_id'       => $producto->id_producto,
            'usuario_id'        => auth()->id(),
            'tipo_movimiento'   => $tipoMovimiento,
            'referencia_tipo'   => $referenciaTipo,
            'referencia_id'     => $referenciaId,
            'referencia_codigo' => $referenciaCodigo,
            'cantidad'          => $cantidad,
            'stock_anterior'    => $stockAnterior,
            'stock_nuevo'       => $stockNuevo,
            'observacion'       => $observacion,
        ]);
    }

    /**
     * Eliminar todos los movimientos Kardex asociados a una referencia.
     * Útil en ediciones y eliminaciones de operaciones.
     */
    public static function eliminarPorReferencia(string $referenciaTipo, int $referenciaId): void
    {
        KardexMovimiento::where('referencia_tipo', $referenciaTipo)
            ->where('referencia_id', $referenciaId)
            ->delete();
    }
}
```

### Uso en los puntos de integración

```php
// Después de incrementar stock en INGRESO:
KardexHelper::registrar(
    $producto,
    'INGRESO',
    'Ingreso',
    $ingreso->id_ingreso,
    $ingreso->correlativo,
    $p['cantidad'],                    // valor positivo
    'Ingreso de inventario'
);

// Dentro de StockHelper::consumir(), después de decrementar stock:
KardexHelper::registrar(
    $producto,
    strtoupper($tipo),                 // VENTA o ATENCION
    ucfirst($tipo),                    // Venta o Atencion
    $referencia->id,
    $refCodigo,
    -$efectivamenteConsumido,          // valor negativo
    $tipo === 'venta' ? 'Venta registrada' : 'Atención registrada'
);

// Dentro de StockHelper::revertir(), antes de retornar:
KardexHelper::eliminarPorReferencia(ucfirst($tipo), $referencia->id);

// En IngresoController::update(), antes de revertir stock:
KardexHelper::eliminarPorReferencia('Ingreso', $ingreso->id_ingreso);

// En IngresoController::destroy(), después de revertir stock:
KardexHelper::eliminarPorReferencia('Ingreso', $ingreso->id_ingreso);
```

---

## 7. Integración en `StockHelper`

### `StockHelper::consumir()` — Agregar registro Kardex

Dentro del `DB::transaction`, después de `$producto->decrement('stock', $efectivamenteConsumido)`:

```php
$refCodigo = $tipo === 'venta'
    ? $referencia->correlativo
    : '#' . $referencia->id_atencion;

KardexHelper::registrar(
    $producto,
    strtoupper($tipo),
    ucfirst($tipo),
    $referencia->id,
    $refCodigo,
    -$efectivamenteConsumido,
    $tipo === 'venta' ? 'Venta registrada' : 'Atención registrada'
);
```

### `StockHelper::revertir()` — Eliminar movimientos Kardex

Al final del `DB::transaction`, después de eliminar los registros de consumo:

```php
KardexHelper::eliminarPorReferencia(ucfirst($tipo), $referencia->id);
```

---

## 8. Integración en `IngresoController`

### `store()` — Después de incrementar stock (línea 95)

Agregar dentro del `foreach` de productos, después de `Producto::increment('stock')`:

```php
$producto = Producto::find($p['producto_id_producto']);
KardexHelper::registrar(
    $producto,
    'INGRESO',
    'Ingreso',
    $ingreso->id_ingreso,
    $ingreso->correlativo,
    $p['cantidad'],
    'Ingreso de inventario'
);
```

### `update()` — Antes de revertir stock anterior (línea 134) y después de reincrementar (línea 165)

Al inicio del `DB::transaction`:
```php
KardexHelper::eliminarPorReferencia('Ingreso', $ingreso->id_ingreso);
```

Dentro del `foreach` de nuevos productos, después de `Producto::increment('stock')`:
```php
KardexHelper::registrar(
    $producto, 'INGRESO', 'Ingreso',
    $ingreso->id_ingreso, $ingreso->correlativo,
    $p['cantidad'], 'Ingreso de inventario'
);
```

### `destroy()` — Después de decrementar stock (línea 197)

Dentro del `DB::transaction`, antes de `$ingreso->delete()`:
```php
KardexHelper::eliminarPorReferencia('Ingreso', $ingreso->id_ingreso);
```

---

## 9. Controlador `KardexController` (solo lectura)

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KardexMovimiento;
use App\Models\Producto;
use App\Models\User;

class KardexController extends Controller
{
    public function index()
    {
        $query = KardexMovimiento::with('producto', 'usuario')
            ->orderBy('created_at', 'desc');

        // Filtros
        if ($productoId = request('producto_id')) {
            $query->where('producto_id', $productoId);
        }
        if ($tipo = request('tipo_movimiento')) {
            $query->where('tipo_movimiento', $tipo);
        }
        if ($usuarioId = request('usuario_id')) {
            $query->where('usuario_id', $usuarioId);
        }
        if ($desde = request('fecha_desde')) {
            $query->whereDate('created_at', '>=', $desde);
        }
        if ($hasta = request('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $hasta);
        }

        $movimientos = $query->paginate(50);
        $productos = Producto::where('estado', 1)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();

        if (request()->ajax()) {
            return view('admin.kardex.index', compact('movimientos', 'productos', 'usuarios'));
        }

        return view('admin.kardex.full', compact('movimientos', 'productos', 'usuarios'));
    }

    public function porProducto(Producto $producto)
    {
        $movimientos = KardexMovimiento::with('usuario')
            ->where('producto_id', $producto->id_producto)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        if (request()->ajax()) {
            return view('admin.kardex.producto', compact('movimientos', 'producto'));
        }

        return redirect()->route('admin.kardex.index');
    }
}
```

---

## 10. Vistas

### `kardex/index.blade.php` — Listado con filtros

```
┌──────────────────────────────────────────────────────────────────────┐
│  Kardex de Inventario                                                │
├──────────────────────────────────────────────────────────────────────┤
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────────┐│
│  │ Producto▼│ │  Tipo ▼  │ │ Usuario ▼│ │ Desde ►  │ │ Hasta ►    ││
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └────────────┘│
│                                                                      │
│  ┌──────┬──────────┬──────────┬───────────┬─────────┬──────────┬───┐│
│  │Fecha │ Producto │ Tipo     │ Referencia│ Cantid. │ St. A→N  │Usr││
│  ├──────┼──────────┼──────────┼───────────┼─────────┼──────────┼───┤│
│  │04/06 │ Paracet. │ INGRESO  │ ING-00001 │  +50    │ 0 → 50   │Adm││
│  │04/06 │ Paracet. │ VENTA    │ V-000123  │  -2     │ 50 → 48  │Adm││
│  │04/06 │ Ibupro.  │ ATENCION │ A-000015  │  -1     │ 30 → 29  │Adm││
│  └──────┴──────────┴──────────┴───────────┴─────────┴──────────┴───┘│
│                                                                      │
│  Páginas: ◀ 1 2 3 ... ▶                                             │
└──────────────────────────────────────────────────────────────────────┘
```

**Filtros:**
- **Producto**: `<select>` con todos los productos activos + "Todos"
- **Tipo**: `<select>` con INGRESO, VENTA, ATENCION + "Todos"
- **Usuario**: `<select>` con usuarios del sistema + "Todos"
- **Fecha desde / hasta**: inputs `type="date"`

**Columnas de la tabla (orden descendente por created_at):**
1. Fecha y hora del movimiento
2. Nombre del producto
3. Tipo de movimiento (badge verde/azul/naranja según INGRESO/VENTA/ATENCION)
4. Referencia (código clickeable si el usuario tiene permiso del módulo)
5. Cantidad (verde si positivo, rojo si negativo, con signo +/−)
6. Stock anterior → Stock nuevo
7. Usuario que realizó la operación

### `kardex/producto.blade.php` — Movimientos por producto

```
┌──────────────────────────────────────────────────────────────────────┐
│  Kardex — Paracetamol 500mg                              [Cerrar]   │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  Stock actual: 48                                                    │
│                                                                      │
│  ┌──────┬──────────┬───────────┬─────────┬──────────┬───────┐       │
│  │Fecha │ Tipo     │ Referencia│ Cantid. │ St. A→N  │ Usr   │       │
│  ├──────┼──────────┼───────────┼─────────┼──────────┼───────┤       │
│  │04/06 │ INGRESO  │ ING-00001 │  +50    │ 0 → 50   │ Admin │       │
│  │04/06 │ VENTA    │ V-000123  │  -2     │ 50 → 48  │ Admin │       │
│  └──────┴──────────┴───────────┴─────────┴──────────┴───────┘       │
└──────────────────────────────────────────────────────────────────────┘
```

Se abre como modal desde el botón "Ver Kardex" en la tabla de productos.

### `kardex/full.blade.php`

```blade
@extends('layouts.admin')

@section('title', 'Kardex de Inventario - Botica')

@section('content')
    @include('admin.kardex.index')
@endsection
```

---

## 11. Sidebar — Nuevo enlace

En `resources/views/layouts/admin.blade.php`, dentro del bloque **Inventario**, después de "Ingresos":

```blade
@can('kardex')
<a href="#" data-panel="/admin/kardex"
   class="flex items-center gap-3 px-3 py-2 rounded-lg text-emerald-100 hover:bg-emerald-700 hover:text-white transition text-sm font-medium">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>
    Kardex
</a>
@endcan
```

---

## 12. Botón "Ver Kardex" en Productos

En `resources/views/admin/productos/index.blade.php`, dentro del `<div class="flex items-center justify-end gap-2">` de cada fila, antes del botón de editar:

```blade
<button type="button" data-modal="{{ route('admin.kardex.producto', $p->id_producto) }}"
    class="text-emerald-600 hover:text-emerald-800 p-1.5 hover:bg-emerald-50 rounded-lg transition"
    title="Ver Kardex">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>
</button>
```

---

## 13. Rutas

En `routes/admin.php`, después del grupo de `ingresos`:

```php
use App\Http\Controllers\Admin\KardexController;

Route::middleware('permission:kardex')
    ->prefix('kardex')
    ->name('admin.kardex.')
    ->group(function () {
        Route::get('/', [KardexController::class, 'index'])->name('index');
        Route::get('/producto/{producto}', [KardexController::class, 'porProducto'])->name('producto');
    });
```

---

## 14. Permiso

En `database/seeders/PermissionSeeder.php`, agregar `'kardex'` al array `$modulos`:

```php
$modulos = [
    'dashboard',
    'productos',
    'categorias',
    'marcas',
    'proveedores',
    'clientes',
    'ventas',
    'servicios',
    'atenciones',
    'ingresos',
    'kardex',         // ← NUEVO
    'usuarios',
    'roles',
    'caja',
    'sliders',
    'auditoria',
];
```

Luego asignar el permiso al rol Administrador en `RoleSeeder.php` (si existe).

---

## 15. Consideraciones adicionales

### 15.1. Inmutabilidad del Kardex

Los movimientos Kardex **no pueden editarse ni eliminarse manualmente**. Solo se generan o eliminan automáticamente como consecuencia de operaciones válidas del sistema (ingresos, ventas, atenciones). El controlador `KardexController` solo expone métodos GET.

### 15.2. No altera la lógica de stock actual

El Kardex es puramente de registro. No modifica `producto.stock`, no afecta `StockHelper::consumir()` ni `StockHelper::revertir()`, y no interfiere con la lógica FEFO de consumo por lotes.

### 15.3. Consistencia en ediciones

Cuando se edita una operación:
1. Se **eliminan** todos los movimientos Kardex de la versión anterior.
2. Se **recalcula** el stock (el sistema ya lo hace).
3. Se **generan** nuevos movimientos Kardex con los valores actualizados.

Esto garantiza que el Kardex siempre refleje el estado actual de las operaciones.

### 15.4. Sin huérfanos al eliminar

Cuando se elimina una operación, el `StockHelper::revertir()` ya elimina los movimientos Kardex asociados, garantizando que no queden registros huérfanos.

### 15.5. Stock anterior y stock nuevo

El `stock_anterior` se calcula como `($producto->stock - $cantidad)` antes de la modificación, y `stock_nuevo` es `$producto->stock` después. Esto es conceptualmente correcto porque al momento de llamar a `KardexHelper::registrar()`, `$producto->stock` ya refleja el nuevo valor (o se pasa antes del cambio en el helper).

En `KardexHelper::registrar()`:
```php
$stockAnterior = $producto->stock - $cantidad;  // stock antes del cambio
$stockNuevo = $producto->stock;                  // stock después del cambio
```

### 15.6. Sin JS adicional

El módulo Kardex no requiere JavaScript propio porque utiliza los mecanismos existentes del panel SPA:
- `data-modal` para abrir modales (vista por producto)
- Navegación AJAX estándar del panel (sidebar → `data-panel`)
- Paginación de Laravel (renderizada como HTML, funciona con AJAX)
- Filtros se envían como query params en la misma URL del panel

### 15.7. Rendimiento

- Índices en `kardex_movimientos.producto_id`, `tipo_movimiento`, `referencia_tipo+referencia_id` y `created_at`.
- La tabla puede crecer rápidamente en farmacias con alto volumen de ventas. La paginación (50 por página) y los filtros mitigan el impacto en la interfaz.
- No hay JOINS costosos: solo `producto` (por nombre) y `users` (por nombre).

---

## 16. Restricciones

| Regla | Descripción |
|-------|-------------|
| Solo lectura | Los movimientos Kardex no pueden editarse ni eliminarse manualmente |
| Generación automática | Solo se crean desde operaciones válidas del sistema (Ingresos, Ventas, Atenciones) |
| Sin efecto en stock | El Kardex no altera la lógica actual de stock ni es fuente de verdad |
| Consistencia referencial | Al editar/eliminar una operación, los movimientos Kardex se regeneran/eliminan en el mismo transaction |
| Trazabilidad completa | Todos los movimientos de stock quedan registrados con quién, cuándo y por qué |

---

## 17. Resultado esperado

El usuario podrá seleccionar cualquier producto y conocer exactamente:
- Cuándo ingresó stock.
- Cuándo se vendió.
- Cuándo se utilizó en una atención.
- Quién realizó la operación.
- Cuál era el stock antes del movimiento.
- Cuál fue el stock después del movimiento.

Esto permitirá auditoría, trazabilidad y control total del inventario sin modificar la lógica existente del sistema.

---

## 18. Orden de implementación

| Fase | Tarea | Archivos | Dependencias |
|------|-------|----------|-------------|
| 1 | Crear migración `kardex_movimientos` | `database/migrations/*_create_kardex_movimientos_table.php` | — |
| 2 | Crear modelo `KardexMovimiento` | `app/Models/KardexMovimiento.php` | Fase 1 |
| 3 | Crear helper `KardexHelper` | `app/Helpers/KardexHelper.php` | Fase 2 |
| 4 | Modificar `StockHelper::consumir()` | `app/Helpers/StockHelper.php` | Fase 3 |
| 5 | Modificar `StockHelper::revertir()` | `app/Helpers/StockHelper.php` | Fase 3 |
| 6 | Modificar `IngresoController::store()` | `app/Http/Controllers/Admin/IngresoController.php` | Fase 3 |
| 7 | Modificar `IngresoController::update()` | mismo archivo | Fase 3 |
| 8 | Modificar `IngresoController::destroy()` | mismo archivo | Fase 3 |
| 9 | Agregar permiso `kardex` | `database/seeders/PermissionSeeder.php` | — |
| 10 | Agregar ruta de kardex | `routes/admin.php` | — |
| 11 | Crear `KardexController` | `app/Http/Controllers/Admin/KardexController.php` | Fase 2 |
| 12 | Crear vista `kardex/index.blade.php` | `resources/views/admin/kardex/index.blade.php` | Fase 11 |
| 13 | Crear vista `kardex/producto.blade.php` | `resources/views/admin/kardex/producto.blade.php` | Fase 11 |
| 14 | Crear vista `kardex/full.blade.php` | `resources/views/admin/kardex/full.blade.php` | Fase 12 |
| 15 | Modificar sidebar | `resources/views/layouts/admin.blade.php` | — |
| 16 | Agregar botón "Ver Kardex" en productos | `resources/views/admin/productos/index.blade.php` | — |
| 17 | Ejecutar migraciones + seeders | `php artisan migrate`, `php artisan db:seed --class=PermissionSeeder` | Fases 1, 9 |
| 18 | Verificación completa | Probar flujo: crear ingreso → ver kardex → crear venta → ver kardex → editar → ver kardex → eliminar → ver kardex | Todo lo anterior |

---

## 19. Inventario completo de archivos

| Tipo | Archivo | Acción |
|------|---------|--------|
| Migración | `database/migrations/*_create_kardex_movimientos_table.php` | **CREAR** |
| Modelo | `app/Models/KardexMovimiento.php` | **CREAR** |
| Helper | `app/Helpers/KardexHelper.php` | **CREAR** |
| Controlador | `app/Http/Controllers/Admin/KardexController.php` | **CREAR** |
| Vista | `resources/views/admin/kardex/index.blade.php` | **CREAR** |
| Vista | `resources/views/admin/kardex/producto.blade.php` | **CREAR** |
| Vista | `resources/views/admin/kardex/full.blade.php` | **CREAR** |
| Helper | `app/Helpers/StockHelper.php` | **MODIFICAR** |
| Controlador | `app/Http/Controllers/Admin/IngresoController.php` | **MODIFICAR** |
| Layout | `resources/views/layouts/admin.blade.php` | **MODIFICAR** |
| Vista | `resources/views/admin/productos/index.blade.php` | **MODIFICAR** |
| Rutas | `routes/admin.php` | **MODIFICAR** |
| Seeder | `database/seeders/PermissionSeeder.php` | **MODIFICAR** |

---

## 20. Verificación

1. `php artisan migrate` → crear tabla `kardex_movimientos`
2. `php artisan db:seed --class=PermissionSeeder` → nuevo permiso `kardex`
3. Navegar al panel → sidebar debe mostrar "Kardex" en sección Inventario
4. Crear un ingreso con 2 productos → verificar en BD que se crearon movimientos Kardex (tipo=INGRESO, cantidad=+N)
5. Crear una venta de 1 producto → verificar movimiento Kardex (tipo=VENTA, cantidad=-N)
6. Crear una atención con 1 producto → verificar movimiento Kardex (tipo=ATENCION, cantidad=-N)
7. Abrir "Kardex" desde el sidebar → ver los 4 movimientos en orden descendente
8. Filtrar por producto → solo movimientos de ese producto
9. Filtrar por tipo → solo movimientos de ese tipo
10. Hacer clic en "Ver Kardex" en un producto de la tabla de productos → modal con movimientos de ese producto
11. Editar un ingreso cambiando cantidades → verificar que los movimientos viejos se eliminaron y los nuevos se crearon con valores correctos
12. Eliminar un ingreso → verificar que los movimientos Kardex asociados se eliminaron
13. Verificar que no hay registros huérfanos en `kardex_movimientos` después de eliminar una operación
14. Intentar acceder a `/admin/kardex/create` (POST) → debe dar 404 (no definida)
