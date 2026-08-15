# Plan de Implementación — Control de Vencimientos por Lote

## 1. Objetivo

Implementar un sistema de trazabilidad por lotes y control de fechas de vencimiento para productos farmacéuticos, permitiendo registrar el número de lote y fecha de vencimiento en cada ingreso de inventario, consumir stock bajo el principio FEFO (*First Expiry, First Out*), generar alertas visuales de próximos vencimientos, y mantener la capacidad de reversión completa en ventas y atenciones.

---

## 2. Stack y contexto

| Aspecto | Detalle |
|---------|---------|
| Framework | Laravel 12 |
| Frontend | Blade SPA (AJAX) con Tailwind v4 |
| Auth/RBAC | Spatie Laravel Permission |
| DB | MySQL |
| Patrón existente | Controladores con traits `CacheaPanel` + `Audita` |
| Módulos afectados | Ingresos, Ventas, Atenciones, Productos |
| Sidebar | Definido en `resources/views/layouts/admin.blade.php` |

---

## 3. Casos de negocio cubiertos

### 3.1. Registro de lote y vencimiento en ingreso de productos

**Actor:** Usuario con permiso `ingresos`

**Flujo:**
1. El usuario abre el formulario "Nuevo ingreso" vía modal.
2. Selecciona proveedor y fecha.
3. Por cada producto agregado, ingresa:
   - **Producto** (select con búsqueda)
   - **N° de lote** (texto — código del fabricante, ej. `LOT2405A`)
   - **Fecha de vencimiento** (date picker — fecha en que expira ese lote)
   - **Cantidad recibida** (número entero)
4. Al guardar:
   - Se crea el registro `ingreso` con su correlativo.
   - Se crea un registro en `detalle_ingreso` con `lote` y `fecha_vencimiento`.
   - Se crea (o actualiza) un registro en `lote_producto` con el stock actual de ese lote.
   - Se incrementa `producto.stock` con la cantidad total (como hasta ahora).

**Regla de negocio:** Un mismo `codigo_lote` + `producto_id_producto` + `fecha_vencimiento` puede aparecer en múltiples ingresos. Cada ingreso crea su propio registro en `lote_producto` (no se fusionan), lo que permite trazabilidad individual hasta la orden de compra.

### 3.2. Consumo de stock por lote (FEFO) en ventas

**Actor:** Usuario con permiso `ventas`

**Flujo:**
1. El usuario registra una venta con productos y cantidades.
2. Al guardar, el sistema:
   - Identifica todos los lotes activos del producto (`lote_producto.cantidad > 0`).
   - Los ordena por `fecha_vencimiento ASC, id_lote ASC` (primero el que vence antes; si hay empate, el más antiguo).
   - Consume la cantidad vendida comenzando por el lote más próximo a vencer.
   - Registra el detalle del consumo en `detalle_venta_lote` (qué venta, qué lote, qué cantidad).
   - Decrementa `lote_producto.cantidad` en cada lote afectado.
   - Decrementa `producto.stock` con la cantidad total vendida.

**Regla de negocio:** Si un lote tiene `cantidad = 0` después del consumo, queda como registro histórico pero ya no se considera disponible para futuras ventas. No se elimina físicamente para mantener la trazabilidad.

### 3.3. Consumo de stock por lote (FEFO) en atenciones

Idéntico al flujo de ventas (3.2), pero registrando el consumo en `atencion_producto_lote`.

### 3.4. Reversión de consumo al editar/eliminar ventas o atenciones

**Actor:** Usuario con permiso `ventas` o `atenciones`

**Flujo:**
1. El usuario edita o elimina una venta/atención existente.
2. El sistema:
   - Consulta los registros en `detalle_venta_lote` o `atencion_producto_lote` asociados.
   - Incrementa `lote_producto.cantidad` en cada lote afectado (restaurando el stock del lote).
   - Elimina los registros de detalle de lote.
   - Re-aplica el nuevo consumo (en caso de edición) o simplemente revierte (en caso de eliminación).
   - Ajusta `producto.stock` en consecuencia.

**Regla de negocio:** La reversión es exacta porque se guardó qué lote específico se consumió en cada transacción. No hay aproximaciones ni asignación a lotes "genéricos".

### 3.5. Alertas de próximos vencimientos

**Actor:** Usuario con permiso `productos`

**Flujo:**
1. El usuario navega al panel "Alertas de vencimientos" desde el sidebar.
2. Visualiza una tabla con todos los lotes activos, clasificados por estado:

| Estado | Condición | Color |
|--------|-----------|-------|
| Vigente | `fecha_vencimiento > today + 30 days` | Verde |
| Próximo a vencer | `today + 7 < fecha_vencimiento <= today + 30` | Amarillo |
| Crítico | `today < fecha_vencimiento <= today + 7` | Naranja |
| Vencido | `fecha_vencimiento <= today` | Rojo |

3. Cada fila muestra: producto, código de lote, cantidad disponible, fecha de vencimiento, días restantes y estado.
4. Los lotes vencidos aparecen con opción de "dar de baja" (marcar cantidad = 0) para limpiar el inventario.

**Ubicación en el sidebar:** Sección "Inventario", entre "Marcas" e "Ingresos".

---

## 4. Implicaciones sobre el proceso actual

### 4.1. Impacto en Ingreso de productos (cambio controlado)

**Antes:**
- `detalle_ingreso` solo almacenaba `cantidad`.
- Se disparaba `producto.increment('stock', cantidad)`.

**Después:**
- `detalle_ingreso` almacena `cantidad`, `lote` y `fecha_vencimiento`.
- Se dispara `producto.increment('stock', cantidad)` (igual que antes).
- **Además** se crea un registro en `lote_producto`.
- **Cambio en UI:** Cada fila de producto en el formulario agrega 2 campos nuevos (lote y fecha de vencimiento).

**Ruptura:** Mínima. El flujo general del controlador (`IngresoController::store()`) mantiene su estructura. Solo se agregan líneas después del `attach()` y no se modifica la lógica de stock existente. El formulario se adapta sin cambiar su mecánica de clonado de filas.

### 4.2. Impacto en Venta de productos (cambio localizado)

**Antes:**
```php
Producto::where('id_producto', $p['producto_id_producto'])
    ->decrement('stock', $p['cantidad']);
```

**Después:**
```php
StockHelper::consumir($producto, $p['cantidad'], $venta, 'venta');
```

**Ruptura:** Media. El cambio es en una línea por método (`store`, `update`, `destroy`), pero la lógica subyacente cambia de un simple decremento a un algoritmo FEFO con registro en tabla de detalle. El stock final (`producto.stock`) es el mismo. Las reversiones ahora son exactas en lugar de aproximadas.

### 4.3. Impacto en Atenciones (mismo patrón que ventas)

Idéntico al punto 4.2, pero aplicado a `AtencionController`.

### 4.4. Impacto en base de datos (expansivo pero no destructivo)

Se agregan:
- 1 tabla nueva (`lote_producto`) para stock por lote.
- 2 columnas nuevas en `detalle_ingreso`.
- 1 tabla nueva (`detalle_venta_lote`) para tracking de consumo.
- 1 tabla nueva (`atencion_producto_lote`) para tracking de consumo.

**No se modifican** tablas existentes en su estructura (solo se agregan columnas NULLables). Todas las migraciones son aditivas. No hay drop de columnas ni cambios en tipos de datos.

### 4.5. Impacto en seeders y datos de demostración

Los seeders existentes crean ingresos de prueba sin datos de lote. Las columnas `lote` y `fecha_vencimiento` son NULLables, por lo que los registros existentes no se rompen. Se pueden agregar lotes de demostración en un nuevo seeder, pero no es obligatorio para la migración.

---

## 5. Diseño técnico

### 5.1. Arquitectura del módulo

```
database/migrations/
  ├── XXXX_XX_XX_XXXXXX_create_lote_producto_table.php
  ├── XXXX_XX_XX_XXXXXX_add_lote_fecha_vencimiento_to_detalle_ingreso_table.php
  ├── XXXX_XX_XX_XXXXXX_create_detalle_venta_lote_table.php
  └── XXXX_XX_XX_XXXXXX_create_atencion_producto_lote_table.php

app/Models/
  └── LoteProducto.php

app/Helpers/
  └── StockHelper.php

app/Http/Controllers/Admin/
  ├── IngresoController.php           (modificado)
  ├── VentaController.php             (modificado)
  ├── AtencionController.php          (modificado)
  └── AlertaVencimientoController.php (nuevo)

app/Http/Requests/Admin/
  ├── StoreIngresoRequest.php         (modificado)
  └── UpdateIngresoRequest.php        (modificado)

resources/views/admin/
  ├── ingresos/
  │   ├── create.blade.php            (modificado)
  │   ├── edit.blade.php              (modificado)
  │   └── show.blade.php              (modificado)
  └── alertas/
      ├── index.blade.php             (nuevo)
      └── full.blade.php              (nuevo)

resources/views/layouts/
  └── admin.blade.php                 (modificado — link sidebar)

routes/
  └── admin.php                       (modificado — ruta alertas)
```

### 5.2. Diagrama de base de datos

```
┌─────────────────────────┐       ┌───────────────────────────────┐
│       producto          │       │       lote_producto           │
├─────────────────────────┤       ├───────────────────────────────┤
│ id_producto (PK)        │──┐    │ id_lote (PK)                  │
│ nombre                  │  │    │ producto_id_producto (FK) ────┘
│ stock                   │  └───>│ codigo_lote VARCHAR(50)       │
│ ...                     │       │ fecha_vencimiento DATE        │
└─────────────────────────┘       │ cantidad INTEGER              │
                                  │ ingreso_id_ingreso (FK) ──┐   │
┌─────────────────────────┐       └───────────────────────────┼───┘
│     detalle_ingreso     │                                    │
├─────────────────────────┤       ┌─────────────────────────┐  │
│ ingreso_id_ingreso (FK) │──┐    │       ingreso           │  │
│ producto_id_producto(FK)│  │    ├─────────────────────────┤  │
│ cantidad                │  │    │ id_ingreso (PK)         │──┘
│ lote          (NUEVO)   │  └───>│ correlativo             │
│ fecha_vencimiento(NUEVO)│       │ proveedor_id_proveedor   │
└─────────────────────────┘       │ ...                     │
                                  └─────────────────────────┘
┌─────────────────────────┐       ┌─────────────────────────┐
│   detalle_venta_lote    │       │         venta           │
├─────────────────────────┤       ├─────────────────────────┤
│ id (PK)                 │       │ id_venta (PK)           │
│ venta_id_venta (FK)     │──┐    │ correlativo             │
│ producto_id_producto(FK)│  └───>│ ...                     │
│ lote_id_lote (FK)       │──┐    └─────────────────────────┘
│ cantidad                │  │    ┌─────────────────────────┐
└─────────────────────────┘  │    │       atencion          │
                             │    ├─────────────────────────┤
┌─────────────────────────┐  │    │ id_atencion (PK)        │
│ atencion_producto_lote  │  │    │ ...                     │
├─────────────────────────┤  │    └─────────────────────────┘
│ id (PK)                 │  │
│ atencion_id_atencion(FK)│──┘
│ producto_id_producto(FK)│
│ lote_id_lote (FK)       │──┘
│ cantidad                │
└─────────────────────────┘
```

### 5.3. Migraciones

#### 5.3.1. `create_lote_producto_table`

```sql
CREATE TABLE lote_producto (
    id_lote               INTEGER AUTO_INCREMENT PRIMARY KEY,
    producto_id_producto   INTEGER NOT NULL,
    codigo_lote           VARCHAR(50) NOT NULL,
    fecha_vencimiento     DATE NOT NULL,
    cantidad              INTEGER NOT NULL DEFAULT 0,
    ingreso_id_ingreso    INTEGER NULL,

    FOREIGN KEY (producto_id_producto, 'lote_producto_producto_FK')
        REFERENCES producto(id_producto),
    FOREIGN KEY (ingreso_id_ingreso, 'lote_producto_ingreso_FK')
        REFERENCES ingreso(id_ingreso),

    INDEX lote_producto_vencimiento_idx (fecha_vencimiento),
    INDEX lote_producto_producto_lote_idx (producto_id_producto, codigo_lote)
);
```

#### 5.3.2. `add_lote_fecha_vencimiento_to_detalle_ingreso`

```sql
ALTER TABLE detalle_ingreso
    ADD COLUMN lote VARCHAR(50) NULL AFTER cantidad,
    ADD COLUMN fecha_vencimiento DATE NULL AFTER lote;
```

#### 5.3.3. `create_detalle_venta_lote`

```sql
CREATE TABLE detalle_venta_lote (
    id                    INTEGER AUTO_INCREMENT PRIMARY KEY,
    venta_id_venta        INTEGER NOT NULL,
    producto_id_producto  INTEGER NOT NULL,
    lote_id_lote          INTEGER NOT NULL,
    cantidad              INTEGER NOT NULL,

    FOREIGN KEY (venta_id_venta, 'detalle_venta_lote_venta_FK')
        REFERENCES venta(id_venta),
    FOREIGN KEY (producto_id_producto, 'detalle_venta_lote_producto_FK')
        REFERENCES producto(id_producto),
    FOREIGN KEY (lote_id_lote, 'detalle_venta_lote_lote_FK')
        REFERENCES lote_producto(id_lote),

    INDEX detalle_venta_lote_venta_idx (venta_id_venta)
);
```

#### 5.3.4. `create_atencion_producto_lote`

```sql
CREATE TABLE atencion_producto_lote (
    id                      INTEGER AUTO_INCREMENT PRIMARY KEY,
    atencion_id_atencion    INTEGER NOT NULL,
    producto_id_producto    INTEGER NOT NULL,
    lote_id_lote            INTEGER NOT NULL,
    cantidad                INTEGER NOT NULL,

    FOREIGN KEY (atencion_id_atencion, 'atencion_producto_lote_atencion_FK')
        REFERENCES atencion(id_atencion),
    FOREIGN KEY (producto_id_producto, 'atencion_producto_lote_producto_FK')
        REFERENCES producto(id_producto),
    FOREIGN KEY (lote_id_lote, 'atencion_producto_lote_lote_FK')
        REFERENCES lote_producto(id_lote),

    INDEX atencion_producto_lote_atencion_idx (atencion_id_atencion)
);
```

### 5.4. Modelo Eloquent

#### `LoteProducto.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoteProducto extends Model
{
    protected $table = 'lote_producto';
    protected $primaryKey = 'id_lote';
    public $timestamps = false;

    protected $fillable = [
        'producto_id_producto',
        'codigo_lote',
        'fecha_vencimiento',
        'cantidad',
        'ingreso_id_ingreso',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id_producto', 'id_producto');
    }

    public function ingreso()
    {
        return $this->belongsTo(Ingreso::class, 'ingreso_id_ingreso', 'id_ingreso');
    }

    public function scopeDisponible($query)
    {
        return $query->where('cantidad', '>', 0);
    }

    public function scopeVigente($query)
    {
        return $query->where('fecha_vencimiento', '>', now());
    }

    public function scopeVencido($query)
    {
        return $query->where('fecha_vencimiento', '<=', now());
    }

    public function scopeProximoAVencer($query, int $dias = 30)
    {
        return $query->whereBetween('fecha_vencimiento', [now(), now()->addDays($dias)]);
    }
}
```

### 5.5. Helper `StockHelper`

```php
<?php

namespace App\Helpers;

use App\Models\LoteProducto;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class StockHelper
{
    /**
     * Consume stock de un producto usando FEFO (First Expiry, First Out).
     *
     * @param Producto $producto  Producto del cual consumir.
     * @param int      $cantidad  Cantidad a consumir.
     * @param mixed    $referencia Modelo relacionado (Venta o Atencion).
     * @param string   $tipo      'venta' o 'atencion'.
     * @return array Registros de consumo creados.
     */
    public static function consumir(Producto $producto, int $cantidad, $referencia, string $tipo): array
    {
        $lotes = LoteProducto::where('producto_id_producto', $producto->id_producto)
            ->where('cantidad', '>', 0)
            ->orderBy('fecha_vencimiento')
            ->orderBy('id_lote')
            ->get();

        $porConsumir = $cantidad;
        $consumos = [];

        DB::transaction(function () use ($lotes, &$porConsumir, &$consumos, $referencia, $tipo, $producto) {
            foreach ($lotes as $lote) {
                if ($porConsumir <= 0) break;

                $aConsumir = min($lote->cantidad, $porConsumir);
                $lote->decrement('cantidad', $aConsumir);
                $porConsumir -= $aConsumir;

                if ($tipo === 'venta') {
                    $referencia->lotesConsumidos()->create([
                        'venta_id_venta'       => $referencia->id_venta,
                        'producto_id_producto' => $producto->id_producto,
                        'lote_id_lote'         => $lote->id_lote,
                        'cantidad'             => $aConsumir,
                    ]);
                } elseif ($tipo === 'atencion') {
                    $referencia->lotesConsumidos()->create([
                        'atencion_id_atencion'   => $referencia->id_atencion,
                        'producto_id_producto'   => $producto->id_producto,
                        'lote_id_lote'           => $lote->id_lote,
                        'cantidad'               => $aConsumir,
                    ]);
                }

                $consumos[] = [
                    'lote'     => $lote,
                    'cantidad' => $aConsumir,
                ];
            }

            $producto->decrement('stock', $cantidad - $porConsumir);
        });

        return $consumos;
    }

    /**
     * Revierte el consumo de stock de una venta o atención.
     *
     * @param mixed  $referencia Modelo relacionado (Venta o Atencion).
     * @param string $tipo       'venta' o 'atencion'.
     */
    public static function revertir($referencia, string $tipo): void
    {
        DB::transaction(function () use ($referencia, $tipo) {
            $loteConsumidos = $tipo === 'venta'
                ? $referencia->lotesConsumidos
                : $referencia->lotesConsumidos;

            $productosAjustados = [];

            foreach ($loteConsumidos as $consumo) {
                LoteProducto::where('id_lote', $consumo->lote_id_lote)
                    ->increment('cantidad', $consumo->cantidad);

                $productosAjustados[$consumo->producto_id_producto] =
                    ($productosAjustados[$consumo->producto_id_producto] ?? 0) + $consumo->cantidad;
            }

            foreach ($productosAjustados as $productoId => $cantidad) {
                Producto::where('id_producto', $productoId)
                    ->increment('stock', $cantidad);
            }

            $loteConsumidos->each->delete();
        });
    }
}
```

### 5.6. Modificaciones a modelos existentes

#### `Producto.php` — relación lotes

```php
public function lotes()
{
    return $this->hasMany(LoteProducto::class, 'producto_id_producto', 'id_producto');
}

public function lotesDisponibles()
{
    return $this->lotes()->where('cantidad', '>', 0)->orderBy('fecha_vencimiento');
}

public function getProximoVencimientoAttribute(): ?string
{
    $lote = $this->lotesDisponibles()->first();
    return $lote?->fecha_vencimiento?->format('Y-m-d');
}

public function getStockVencidoAttribute(): int
{
    return (int) $this->lotes()->vencido()->sum('cantidad');
}
```

#### `Ingreso.php` — pivot extendido

```php
public function productos()
{
    return $this->belongsToMany(Producto::class, 'detalle_ingreso',
        'ingreso_id_ingreso', 'producto_id_producto')
        ->withPivot('cantidad', 'lote', 'fecha_vencimiento');
}

public function lotes()
{
    return $this->hasMany(LoteProducto::class, 'ingreso_id_ingreso', 'id_ingreso');
}
```

#### `Venta.php` — relación lotes consumidos

```php
public function lotesConsumidos()
{
    return $this->hasMany(DetalleVentaLote::class, 'venta_id_venta', 'id_venta');
}
```

#### `Atencion.php` — relación lotes consumidos

```php
public function lotesConsumidos()
{
    return $this->hasMany(AtencionProductoLote::class, 'atencion_id_atencion', 'id_atencion');
}
```

### 5.7. Controlador: `IngresoController`

#### `store()` — cambios (líneas 78-87)

```php
foreach ($data['productos'] as $p) {
    $ingreso->productos()->attach($p['producto_id_producto'], [
        'cantidad'          => $p['cantidad'],
        'lote'              => $p['lote'],
        'fecha_vencimiento' => $p['fecha_vencimiento'],
    ]);

    LoteProducto::create([
        'producto_id_producto' => $p['producto_id_producto'],
        'codigo_lote'          => $p['lote'],
        'fecha_vencimiento'    => $p['fecha_vencimiento'],
        'cantidad'             => $p['cantidad'],
        'ingreso_id_ingreso'   => $ingreso->id_ingreso,
    ]);

    Producto::where('id_producto', $p['producto_id_producto'])
        ->increment('stock', $p['cantidad']);
    Producto::where('id_producto', $p['producto_id_producto'])
        ->update(['inventario' => DB::raw('stock')]);
}
```

#### `update()` — cambios (líneas 123-153)

```php
// Al inicio del transaction: eliminar lotes anteriores
$ingreso->lotes()->delete();

// En el foreach de nuevos productos: crear LoteProducto como en store
```

#### `destroy()` — cambios (líneas 172-180)

```php
// Antes de detach: eliminar lotes
$ingreso->lotes()->delete();
```

### 5.8. Controlador: `VentaController`

#### `store()` — cambio (línea 99)

```php
// ANTES:
Producto::where('id_producto', $p['producto_id_producto'])
    ->decrement('stock', $p['cantidad']);

// DESPUÉS:
$producto = Producto::findOrFail($p['producto_id_producto']);
StockHelper::consumir($producto, $p['cantidad'], $venta, 'venta');
```

#### `update()` — cambio (líneas 149-171)

```php
// ANTES del foreach de nuevos productos:
StockHelper::revertir($venta, 'venta');
// Omitir el foreach de reversión de stock (ya no es necesario)

// En el foreach de nuevos productos:
StockHelper::consumir($producto, $p['cantidad'], $venta, 'venta');
```

#### `destroy()` — cambio (líneas 213-216)

```php
// ANTES:
foreach ($venta->productos as $p) {
    Producto::where('id_producto', $p->id_producto)
        ->increment('stock', $p->pivot->cantidad);
}

// DESPUÉS:
StockHelper::revertir($venta, 'venta');
```

### 5.9. Controlador: `AlertaVencimientoController` (nuevo)

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoteProducto;
use App\Models\Producto;

class AlertaVencimientoController extends Controller
{
    public function index()
    {
        $hoy = now()->startOfDay();

        $vencidos = LoteProducto::with('producto')
            ->where('cantidad', '>', 0)
            ->where('fecha_vencimiento', '<=', $hoy)
            ->orderBy('fecha_vencimiento')
            ->get();

        $criticos = LoteProducto::with('producto')
            ->where('cantidad', '>', 0)
            ->whereBetween('fecha_vencimiento', [$hoy->copy()->addDay(), $hoy->copy()->addDays(7)])
            ->orderBy('fecha_vencimiento')
            ->get();

        $proximos = LoteProducto::with('producto')
            ->where('cantidad', '>', 0)
            ->whereBetween('fecha_vencimiento', [$hoy->copy()->addDays(8), $hoy->copy()->addDays(30)])
            ->orderBy('fecha_vencimiento')
            ->get();

        $vigentes = LoteProducto::with('producto')
            ->where('cantidad', '>', 0)
            ->where('fecha_vencimiento', '>', $hoy->copy()->addDays(30))
            ->orderBy('fecha_vencimiento')
            ->get();

        if (request()->ajax()) {
            return view('admin.alertas.index', compact('vencidos', 'criticos', 'proximos', 'vigentes', 'hoy'));
        }

        return view('admin.alertas.full', compact('vencidos', 'criticos', 'proximos', 'vigentes', 'hoy'));
    }
}
```

### 5.10. Vistas

#### `alertas/index.blade.php` — tabla de alertas (nuevo)

```
┌──────────────────────────────────────────────────────────────────────────┐
│  Alertas de Vencimientos                                                 │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ─── Vencidos (3) ───                                                    │
│  ┌──────────┬────────────┬──────┬──────────────┬──────┬──────────┬─────┐│
│  │ Producto │ Lote       │ Und  │ Vencimiento  │ Días │ Estado   │     ││
│  ├──────────┼────────────┼──────┼──────────────┼──────┼──────────┼─────┤│
│  │ Amox...  │ LOT2401A   │ 10   │ 01/01/2026   │ -154 │ Vencido  │[Baja]│
│  │ Para...  │ LOT2312B   │ 5    │ 15/12/2025   │ -171 │ Vencido  │[Baja]│
│  └──────────┴────────────┴──────┴──────────────┴──────┴──────────┴─────┘│
│                                                                          │
│  ─── Críticos (≤7 días) (1) ───                                          │
│  ┌──────────┬────────────┬──────┬──────────────┬──────┬──────────┐      │
│  │ Ibu...   │ LOT2405B   │ 20   │ 08/06/2026   │ 4    │ Crítico  │      │
│  └──────────┴────────────┴──────┴──────────────┴──────┴──────────┘      │
│                                                                          │
│  ─── Próximos a vencer (≤30 días) (4) ───                                │
│  ┌──────────┬────────────┬──────┬──────────────┬──────┬──────────┐      │
│  │ ...      │ ...        │ ...  │ ...          │ ...  │ ...      │      │
│  └──────────┴────────────┴──────┴──────────────┴──────┴──────────┘      │
│                                                                          │
│  ─── Vigentes (12) ───                                                   │
│  ┌──────────┬────────────┬──────┬──────────────┬──────┬──────────┐      │
│  │ ...      │ ...        │ ...  │ ...          │ ...  │ Vigente  │      │
│  └──────────┴────────────┴──────┴──────────────┴──────┴──────────┘      │
└──────────────────────────────────────────────────────────────────────────┘
```

#### `ingresos/show.blade.php` — columnas agregadas

```
<th class="...">Producto</th>
<th class="...">Lote</th>             ← NUEVO
<th class="...">Vencimiento</th>      ← NUEVO
<th class="... text-center">Cantidad</th>

...

<td class="...">{{ $p->nombre }}</td>
<td class="... font-mono">{{ $p->pivot->lote ?? '—' }}</td>
<td class="...">{{ $p->pivot->fecha_vencimiento ? \Carbon\Carbon::parse($p->pivot->fecha_vencimiento)->format('d/m/Y') : '—' }}</td>
<td class="... text-center font-medium">{{ $p->pivot->cantidad }}</td>
```

### 5.11. Form Requests

#### `StoreIngresoRequest.php` y `UpdateIngresoRequest.php`

```php
'productos.*.lote' => 'required|string|max:50',
'productos.*.fecha_vencimiento' => 'required|date',
```

### 5.12. Sidebar — nuevo enlace

En `resources/views/layouts/admin.blade.php`, dentro del bloque "Inventario", entre Marcas e Ingresos:

```blade
<a href="#" data-panel="/admin/alertas/vencimientos"
   class="flex items-center gap-3 px-3 py-2 rounded-lg text-emerald-100 hover:bg-emerald-700 hover:text-white transition text-sm font-medium">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    Alertas de vencimientos
</a>
```

### 5.13. Rutas

En `routes/admin.php`, dentro del grupo `auth` y después de los recursos de producto:

```php
Route::middleware('permission:productos')
    ->prefix('alertas')
    ->name('admin.alertas.')
    ->group(function () {
        Route::get('/vencimientos', [AlertaVencimientoController::class, 'index'])
            ->name('vencimientos');
    });
```

---

## 6. Consideraciones adicionales

### 6.1. Validación de fecha de vencimiento

La fecha de vencimiento debe ser futura al momento del ingreso. Se recomienda agregar la regla:

```php
'productos.*.fecha_vencimiento' => 'required|date|after:today',
```

Sin embargo, para permitir ingresos retrospectivos (regularización de inventario), se puede omitir `after:today` y permitir cualquier fecha, dejando la validación de vigencia al módulo de alertas.

### 6.2. Lotes vencidos en stock

El sistema no bloquea automáticamente la venta de lotes vencidos. La responsabilidad de dar de baja lotes vencidos recae en el usuario a través del panel de alertas. Esto es deliberado: en farmacia real, un lote "vencido" puede ser devuelto al proveedor o destruido con acta, y el sistema debe reflejar ese proceso manual.

### 6.3. Productos sin lote

Las columnas `lote` y `fecha_vencimiento` en `detalle_ingreso` son NULLables, lo que permite que ingresos existentes (creados antes de esta migración) sigan funcionando sin datos de lote. Visualmente se mostrará "—" en lugar del lote.

Sin embargo, para los nuevos ingresos, ambos campos son **requeridos** a nivel de validación (`FormRequest`).

### 6.4. Relación con el módulo de caja

No hay impacto directo en el módulo de caja. Los movimientos de caja se siguen generando con el total de la venta/atención, independientemente de los lotes involucrados.

### 6.5. Impacto en búsqueda y filtros de productos

La API pública de catálogo (`/productos`) podría beneficiarse de mostrar la próxima fecha de vencimiento o alertar al cliente si el producto está próximo a vencer. Esto queda fuera del alcance de esta implementación y se puede abordar en una fase posterior.

### 6.6. Rendimiento

- Índices en `lote_producto.fecha_vencimiento` y `lote_producto.producto_id_producto` para consultas FEFO rápidas.
- Índice en `detalle_venta_lote.venta_id_venta` para reversiones rápidas.
- La vista de alertas consulta lotes con `cantidad > 0` (parcial), lo que puede optimizarse con índices compuestos si el volumen de datos crece.

---

## 7. Orden de implementación

| Fase | Tarea | Archivos | Dependencias |
|------|-------|----------|-------------|
| 1 | Crear migración `lote_producto` | `XXXX_create_lote_producto_table.php` | — |
| 2 | Crear migración `add_lote_fecha_vencimiento_to_detalle_ingreso` | `XXXX_add_lote_fecha_vencimiento_to_detalle_ingreso.php` | — |
| 3 | Crear migración `detalle_venta_lote` | `XXXX_create_detalle_venta_lote_table.php` | Fase 1 |
| 4 | Crear migración `atencion_producto_lote` | `XXXX_create_atencion_producto_lote_table.php` | Fase 1 |
| 5 | Ejecutar migraciones | `php artisan migrate` | Fases 1-4 |
| 6 | Crear modelo `LoteProducto` | `app/Models/LoteProducto.php` | Fase 5 |
| 7 | Crear modelos `DetalleVentaLote` y `AtencionProductoLote` | `app/Models/DetalleVentaLote.php`, `app/Models/AtencionProductoLote.php` | Fase 5 |
| 8 | Modificar modelo `Ingreso` (withPivot extendido + relación lotes) | `app/Models/Ingreso.php` | Fase 6 |
| 9 | Modificar modelo `Producto` (relación lotes) | `app/Models/Producto.php` | Fase 6 |
| 10 | Modificar modelo `Venta` (relación lotesConsumidos) | `app/Models/Venta.php` | Fase 7 |
| 11 | Modificar modelo `Atencion` (relación lotesConsumidos) | `app/Models/Atencion.php` | Fase 7 |
| 12 | Crear `StockHelper` | `app/Helpers/StockHelper.php` | Fase 6 |
| 13 | Modificar `StoreIngresoRequest` | `app/Http/Requests/Admin/StoreIngresoRequest.php` | — |
| 14 | Modificar `UpdateIngresoRequest` | `app/Http/Requests/Admin/UpdateIngresoRequest.php` | — |
| 15 | Modificar `IngresoController::store()` | `app/Http/Controllers/Admin/IngresoController.php` | Fases 6, 12, 13 |
| 16 | Modificar `IngresoController::update()` | mismo archivo | Fases 6, 12, 14 |
| 17 | Modificar `IngresoController::destroy()` | mismo archivo | Fase 6 |
| 18 | Modificar `VentaController::store()` | `app/Http/Controllers/Admin/VentaController.php` | Fase 12 |
| 19 | Modificar `VentaController::update()` | mismo archivo | Fase 12 |
| 20 | Modificar `VentaController::destroy()` | mismo archivo | Fase 12 |
| 21 | Modificar `AtencionController::store()` | `app/Http/Controllers/Admin/AtencionController.php` | Fase 12 |
| 22 | Modificar `AtencionController::update()` | mismo archivo | Fase 12 |
| 23 | Modificar `AtencionController::destroy()` | mismo archivo | Fase 12 |
| 24 | Modificar `create.blade.php` (ingresos) | `resources/views/admin/ingresos/create.blade.php` | — |
| 25 | Modificar `edit.blade.php` (ingresos) | `resources/views/admin/ingresos/edit.blade.php` | — |
| 26 | Modificar `show.blade.php` (ingresos) | `resources/views/admin/ingresos/show.blade.php` | — |
| 27 | Crear `AlertaVencimientoController` | `app/Http/Controllers/Admin/AlertaVencimientoController.php` | Fase 6 |
| 28 | Crear vista `alertas/index.blade.php` | `resources/views/admin/alertas/index.blade.php` | Fase 27 |
| 29 | Crear vista `alertas/full.blade.php` | `resources/views/admin/alertas/full.blade.php` | Fase 28 |
| 30 | Modificar sidebar | `resources/views/layouts/admin.blade.php` | — |
| 31 | Agregar ruta de alertas | `routes/admin.php` | Fase 27 |
| 32 | Compilar assets y verificar | `npm run build` | Fases 24-26 |

---

## 8. Tests unitarios y de integración

### 8.1. Test de creación de lote en ingreso

```php
/** @test */
public function crea_lote_producto_al_registrar_ingreso()
{
    $producto = Producto::factory()->create(['stock' => 0]);
    $proveedor = Proveedor::factory()->create();

    $response = $this->actingAs($this->admin)
        ->post(route('admin.ingresos.store'), [
            'proveedor_id_proveedor' => $proveedor->id_proveedor,
            'fecha' => now()->toDateString(),
            'productos' => [
                [
                    'producto_id_producto' => $producto->id_producto,
                    'cantidad' => 10,
                    'lote' => 'LOT-TEST-001',
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                ],
            ],
        ]);

    $this->assertDatabaseHas('lote_producto', [
        'producto_id_producto' => $producto->id_producto,
        'codigo_lote' => 'LOT-TEST-001',
        'cantidad' => 10,
    ]);

    $this->assertEquals(10, $producto->fresh()->stock);
}
```

### 8.2. Test de consumo FEFO en venta

```php
/** @test */
public function consume_lote_mas_proximo_a_vencer_primero()
{
    $producto = Producto::factory()->create(['stock' => 30]);

    // Lote A: vence en 60 días
    LoteProducto::factory()->create([
        'producto_id_producto' => $producto->id_producto,
        'codigo_lote' => 'LOTE-A',
        'fecha_vencimiento' => now()->addDays(60),
        'cantidad' => 20,
    ]);

    // Lote B: vence en 30 días (debe consumirse primero)
    LoteProducto::factory()->create([
        'producto_id_producto' => $producto->id_producto,
        'codigo_lote' => 'LOTE-B',
        'fecha_vencimiento' => now()->addDays(30),
        'cantidad' => 10,
    ]);

    $venta = Venta::factory()->create();

    StockHelper::consumir($producto, 15, $venta, 'venta');

    // Lote B (10 unidades) se consumió completamente
    $this->assertEquals(0, LoteProducto::where('codigo_lote', 'LOTE-B')->first()->cantidad);
    // Lote A perdió 5 unidades
    $this->assertEquals(15, LoteProducto::where('codigo_lote', 'LOTE-A')->first()->cantidad);
    // Stock total bajó de 30 a 15
    $this->assertEquals(15, $producto->fresh()->stock);
}
```

### 8.3. Test de reversión exacta en eliminación de venta

```php
/** @test */
public function revierte_consumo_exacto_al_eliminar_venta()
{
    $producto = Producto::factory()->create(['stock' => 50]);

    $lote1 = LoteProducto::factory()->create([
        'producto_id_producto' => $producto->id_producto,
        'codigo_lote' => 'LOTE-X',
        'fecha_vencimiento' => now()->addDays(30),
        'cantidad' => 20,
    ]);

    $lote2 = LoteProducto::factory()->create([
        'producto_id_producto' => $producto->id_producto,
        'codigo_lote' => 'LOTE-Y',
        'fecha_vencimiento' => now()->addDays(60),
        'cantidad' => 30,
    ]);

    $venta = Venta::factory()->create();
    StockHelper::consumir($producto, 25, $venta, 'venta');

    // Verificar consumo
    $this->assertEquals(0, $lote1->fresh()->cantidad); // LOTE-X: 20 consumidos
    $this->assertEquals(25, $lote2->fresh()->cantidad); // LOTE-Y: 5 consumidos

    // Revertir
    StockHelper::revertir($venta, 'venta');

    // Verificar restauración
    $this->assertEquals(20, $lote1->fresh()->cantidad);
    $this->assertEquals(30, $lote2->fresh()->cantidad);
    $this->assertEquals(50, $producto->fresh()->stock);

    // Verificar que los registros de detalle se eliminaron
    $this->assertDatabaseMissing('detalle_venta_lote', [
        'venta_id_venta' => $venta->id_venta,
    ]);
}
```

### 8.4. Test de alertas — lotes vencidos

```php
/** @test */
public function muestra_lotes_vencidos_en_alerta()
{
    LoteProducto::factory()->create([
        'producto_id_producto' => Producto::factory()->create()->id_producto,
        'cantidad' => 5,
        'fecha_vencimiento' => now()->subDays(10), // vencido
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.alertas.vencimientos'));

    $response->assertSee('Vencido');
    $response->assertSee('5');
}
```

### 8.5. Test de validación — lote requerido en ingreso

```php
/** @test */
public function lote_y_fecha_vencimiento_son_requeridos_en_ingreso()
{
    $producto = Producto::factory()->create();
    $proveedor = Proveedor::factory()->create();

    $response = $this->actingAs($this->admin)
        ->post(route('admin.ingresos.store'), [
            'proveedor_id_proveedor' => $proveedor->id_proveedor,
            'productos' => [
                [
                    'producto_id_producto' => $producto->id_producto,
                    'cantidad' => 5,
                    // Sin lote ni fecha_vencimiento
                ],
            ],
        ]);

    $response->assertSessionHasErrors([
        'productos.0.lote',
        'productos.0.fecha_vencimiento',
    ]);
}
```

### 8.6. Test de integración — edición de ingreso actualiza lotes

```php
/** @test */
public function editar_ingreso_actualiza_lotes_correctamente()
{
    $producto = Producto::factory()->create(['stock' => 10]);
    $proveedor = Proveedor::factory()->create();

    $ingreso = Ingreso::factory()->create([
        'proveedor_id_proveedor' => $proveedor->id_proveedor,
    ]);

    $ingreso->productos()->attach($producto->id_producto, [
        'cantidad' => 10,
        'lote' => 'LOTE-OLD',
        'fecha_vencimiento' => now()->addYear(),
    ]);

    LoteProducto::factory()->create([
        'producto_id_producto' => $producto->id_producto,
        'codigo_lote' => 'LOTE-OLD',
        'fecha_vencimiento' => now()->addYear(),
        'cantidad' => 10,
        'ingreso_id_ingreso' => $ingreso->id_ingreso,
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.ingresos.update', $ingreso), [
            'proveedor_id_proveedor' => $proveedor->id_proveedor,
            'fecha' => now()->toDateString(),
            'productos' => [
                [
                    'producto_id_producto' => $producto->id_producto,
                    'cantidad' => 20,
                    'lote' => 'LOTE-NEW',
                    'fecha_vencimiento' => now()->addMonths(18)->toDateString(),
                ],
            ],
        ]);

    // Lote anterior debe haber desaparecido
    $this->assertDatabaseMissing('lote_producto', ['codigo_lote' => 'LOTE-OLD']);
    // Nuevo lote debe existir con cantidad actualizada
    $this->assertDatabaseHas('lote_producto', [
        'codigo_lote' => 'LOTE-NEW',
        'cantidad' => 20,
    ]);
    // Stock debe reflejar la nueva cantidad
    $this->assertEquals(20, $producto->fresh()->stock);
}
```

### 8.7. Test de cobertura — sin lotes no afecta productos existentes

```php
/** @test */
public function productos_sin_lote_siguen_funcionando()
{
    $producto = Producto::factory()->create(['stock' => 5]);
    $proveedor = Proveedor::factory()->create();

    // Ingreso sin lote (simula datos pre-migración)
    $ingreso = Ingreso::factory()->create([
        'proveedor_id_proveedor' => $proveedor->id_proveedor,
    ]);
    $ingreso->productos()->attach($producto->id_producto, [
        'cantidad' => 5,
        'lote' => null,
        'fecha_vencimiento' => null,
    ]);

    // Vender el producto (debe funcionar aunque no tenga lotes)
    $cliente = Cliente::factory()->create();
    $response = $this->actingAs($this->admin)
        ->post(route('admin.ventas.store'), [
            'cliente_id_cliente' => $cliente->id_cliente,
            'productos' => [
                [
                    'producto_id_producto' => $producto->id_producto,
                    'cantidad' => 3,
                    'precio' => 10.00,
                ],
            ],
        ]);

    $response->assertOk();
    $this->assertEquals(2, $producto->fresh()->stock);
}
```

---

## 9. Verificación

1. `php artisan migrate` → crear tablas `lote_producto`, `detalle_venta_lote`, `atencion_producto_lote`, columnas en `detalle_ingreso`.
2. Crear un ingreso con 3 productos, cada uno con lote y fecha de vencimiento diferente → verificar en BD que se crearon registros en `detalle_ingreso` (con lote/vencimiento) y `lote_producto`.
3. Verificar que `producto.stock` = suma de `lote_producto.cantidad` para ese producto.
4. Crear una venta que consuma unidades de múltiples lotes → verificar que se consumió primero el lote con fecha de vencimiento más próxima.
5. Verificar en `detalle_venta_lote` que se registró qué lote y qué cantidad se consumió.
6. Editar la venta reduciendo la cantidad → verificar que el lote más próximo recuperó unidades y el detalle se actualizó.
7. Eliminar la venta → verificar que todos los lotes recuperaron su cantidad original.
8. Navegar a "Alertas de vencimientos" → verificar lotes vencidos (rojo), críticos (naranja), próximos (amarillo), vigentes (verde).
9. Dar de baja un lote vencido → verificar que su cantidad pasa a 0.
10. Ejecutar tests: `php artisan test --filter=Lote` o el nombre del TestCase.

---

## 10. Resumen de archivos

| Tipo | Archivo | Acción |
|------|---------|--------|
| Migración | `database/migrations/XXXX_create_lote_producto_table.php` | CREAR |
| Migración | `database/migrations/XXXX_add_lote_fecha_vencimiento_to_detalle_ingreso.php` | CREAR |
| Migración | `database/migrations/XXXX_create_detalle_venta_lote_table.php` | CREAR |
| Migración | `database/migrations/XXXX_create_atencion_producto_lote_table.php` | CREAR |
| Modelo | `app/Models/LoteProducto.php` | CREAR |
| Modelo | `app/Models/DetalleVentaLote.php` | CREAR |
| Modelo | `app/Models/AtencionProductoLote.php` | CREAR |
| Helper | `app/Helpers/StockHelper.php` | CREAR |
| Controlador | `app/Http/Controllers/Admin/AlertaVencimientoController.php` | CREAR |
| Vista | `resources/views/admin/alertas/index.blade.php` | CREAR |
| Vista | `resources/views/admin/alertas/full.blade.php` | CREAR |
| Modelo | `app/Models/Ingreso.php` | MODIFICAR |
| Modelo | `app/Models/Producto.php` | MODIFICAR |
| Modelo | `app/Models/Venta.php` | MODIFICAR |
| Modelo | `app/Models/Atencion.php` | MODIFICAR |
| Request | `app/Http/Requests/Admin/StoreIngresoRequest.php` | MODIFICAR |
| Request | `app/Http/Requests/Admin/UpdateIngresoRequest.php` | MODIFICAR |
| Controlador | `app/Http/Controllers/Admin/IngresoController.php` | MODIFICAR |
| Controlador | `app/Http/Controllers/Admin/VentaController.php` | MODIFICAR |
| Controlador | `app/Http/Controllers/Admin/AtencionController.php` | MODIFICAR |
| Vista | `resources/views/admin/ingresos/create.blade.php` | MODIFICAR |
| Vista | `resources/views/admin/ingresos/edit.blade.php` | MODIFICAR |
| Vista | `resources/views/admin/ingresos/show.blade.php` | MODIFICAR |
| Layout | `resources/views/layouts/admin.blade.php` | MODIFICAR |
| Rutas | `routes/admin.php` | MODIFICAR |
