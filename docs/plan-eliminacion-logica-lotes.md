# Plan de Implementación — Eliminación de la Lógica de Ingresos por Lotes

> **Documento histórico.** Describe la eliminación de la lógica de lotes anterior (commit `ade7c19`). Esa decisión fue reevaluada y reemplazada por el rediseño de [`plan-lotes-fefo-v2.md`](plan-lotes-fefo-v2.md), ya implementado. No usar este documento como especificación vigente.

## 1. Objetivo

Eliminar del código del proyecto toda la lógica de **ingreso/registro de productos basada en lotes** (FEFO), considerada errónea, y restaurar la lógica anterior de creación de productos/ingresos donde el stock se maneja de forma directa:

- Un **ingreso** incrementa `producto.stock` y mantiene `inventario = stock`.
- Una **venta** o **atención** decrementa `producto.stock`.
- La trazabilidad se mantiene vía `kardex_movimientos` (sin cambios).

Los documentos de `docs/` que describen la lógica de lotes **se conservan intactos como referencia histórica** (`docs/plan-control-vencimientos-lote.md`, `docs/plan-kardex.md`, `docs/kardex.txt`, `docs/modulo-reportes.md`, `docs/feature_reniec_integracion.md`).

## 2. Alcance del borrado

### 2.1. Migraciones (se eliminan los 4 archivos)

- `database/migrations/2026_06_04_000003_create_lote_producto_table.php`
- `database/migrations/2026_06_04_000004_add_lote_fecha_vencimiento_to_detalle_ingreso.php`
- `database/migrations/2026_06_04_000005_create_detalle_venta_lote_table.php`
- `database/migrations/2026_06_04_000006_create_atencion_producto_lote_table.php`

Con `php artisan migrate:fresh` el esquema vuelve al estado previo: `detalle_ingreso` queda sin las columnas `lote` y `fecha_vencimiento`, y las tablas `lote_producto`, `detalle_venta_lote` y `atencion_producto_lote` dejan de existir.

> **Nota:** tras aplicar estos cambios se debe ejecutar `php artisan migrate:fresh --seed` para limpiar bases de datos existentes que aún contengan tablas/columnas huérfanas.

### 2.2. Modelos

- Se eliminan: `app/Models/LoteProducto.php`, `app/Models/DetalleVentaLote.php`, `app/Models/AtencionProductoLote.php`.
- `app/Models/Producto.php`:
  - Se eliminan las relaciones `lotes()`, `lotesDisponibles()` y los accesores `proximo_vencimiento`, `stock_vencido`.
  - En la relación `ingresos()` el pivote queda `withPivot('cantidad')` (sin `lote` ni `fecha_vencimiento`).
- `app/Models/Ingreso.php`:
  - Se elimina la relación `lotes()` y los campos `lote`/`fecha_vencimiento` del pivote (`withPivot('cantidad')`).
- `app/Models/Venta.php` y `app/Models/Atencion.php`:
  - Se elimina la relación `lotesConsumidos()`.

### 2.3. Helpers y comandos

- Se elimina `app/Helpers/StockHelper.php` (consumo FEFO por lote y reversiones de lote).
- Se elimina `app/Console/Commands/StockSync.php` (comando `stock:sync`) y su registro en `app/Console/Kernel.php`.

### 2.4. Controladores

- **`IngresoController`** (`store` / `update`):
  - Se elimina `LoteProducto::create(...)` por detalle.
  - El attach a `detalle_ingreso` queda solo con `cantidad`.
  - Se conserva: `increment('stock')`, `inventario = stock` y `KardexHelper::registrar(...)`.
  - En `update` y `destroy` se elimina `$ingreso->lotes()->delete()`.
- **`VentaController`** (`store` / `update` / `destroy`):
  - `StockHelper::consumir($producto, $cantidad, $venta, 'venta')` → decremento directo:
    `$producto->decrement('stock', $cantidad)` + `KardexHelper::registrar(...)` con `-'cantidad'` y observación "Venta registrada".
  - `StockHelper::revertir($venta, 'venta')` → restauración directa del stock por cada detalle previo + `KardexHelper::eliminarPorReferencia('Venta', $venta->id_venta)`.
- **`AtencionController`** (`store` / `update` / `destroy`):
  - Mismo cambio que ventas: decremento/incremento directo de `producto.stock` + kardex, sin lotes.
- **`AlertaVencimientoController`**: se elimina (módulo de alertas de vencimientos).

### 2.5. Requests (validación)

- `StoreIngresoRequest.php` / `UpdateIngresoRequest.php`: se eliminan las reglas y mensajes de `productos.*.lote` y `productos.*.fecha_vencimiento`.
- `StoreVentaRequest.php` / `UpdateVentaRequest.php` / `StoreAtencionRequest.php` / `UpdateAtencionRequest.php`: se eliminan las validaciones contra `LoteProducto` (el check contra `producto.stock`, que ya existía, se conserva).

### 2.6. Alertas y reportes

- Se eliminan: `resources/views/admin/alertas/index.blade.php` y `full.blade.php`, `resources/views/admin/reportes/pdf/vencimientos.blade.php`.
- `routes/admin.php`: se elimina el import de `AlertaVencimientoController` y el grupo de rutas `admin/alertas/vencimientos`.
- `resources/views/layouts/admin.blade.php`: se elimina el enlace del sidebar "Alertas vencimientos".
- `app/Services/ReporteService.php`: se elimina el método `vencimientos()` y el import de `LoteProducto`.
- `app/Http/Controllers/Admin/ReporteController.php`: se elimina el caso `vencimientos` del `match` y de `getTitulo()`.
- `resources/views/admin/reportes/index.blade.php`: se elimina la opción "Vencimientos" y sus filtros contextuales.
- `resources/views/admin/reportes/pdf/ingresos.blade.php`: se eliminan los badges de "Lote" y "Vence".

### 2.7. Vistas de ingreso

- `resources/views/admin/ingresos/create.blade.php` y `edit.blade.php`: se eliminan los campos "N° Lote" y "Vencimiento" (inputs y ajuste del layout de la fila).
- `resources/views/admin/ingresos/show.blade.php`: se eliminan las columnas "Lote" y "Vencimiento" de la tabla.
- `resources/js/admin/ingresos/ingreso-form.js`: sin lógica de lotes (solo revisión de índices de fila tras quitar los campos).

## 3. Lógica de datos restaurada

### 3.1. Ingreso de productos (creación)

1. Se crea el `ingreso` (correlativo, proveedor, fecha, usuario).
2. Por cada producto se inserta en `detalle_ingreso` con `ingreso_id_ingreso`, `producto_id_producto` y `cantidad`.
3. Se incrementa `producto.stock` y se actualiza `inventario = stock`.
4. Se registra el movimiento en `kardex_movimientos` (tipo `INGRESO`).

### 3.2. Ventas y atenciones

1. Se inserta el detalle en `detalle_venta` o `atencion_producto`.
2. Se decrementa `producto.stock`.
3. Se registra el movimiento en `kardex_movimientos` (tipo `VENTA` / `ATENCION`).
4. Al editar o eliminar la operación se **restaura** el stock (incremento) y se eliminan/regeneran los movimientos de kardex asociados.

## 4. Seeders, factories y tests

- `database/seeders/TablasBaseSeeder.php` (secciones 7–9):
  - Se elimina la creación de `lote_producto`, el consumo FIFO por lotes y los inserts en `detalle_venta_lote` / `atencion_producto_lote`.
  - `detalle_ingreso` se inserta solo con `cantidad`.
  - Al final, por cada producto: `inventario = total ingresado` y `stock = ingresado − consumido`.
  - Los movimientos de kardex y la caja se mantienen como hasta ahora.
- `database/factories/UserFactory.php`, `DatabaseSeeder.php` y `PermissionSeeder.php`: sin referencias a lotes, sin cambios.
- `tests/Feature/VentaConReniecTest.php` y `tests/Feature/AtencionConReniecTest.php`:
  - Se deja de crear `LoteProducto`.
  - El stock para la prueba se asigna directamente al producto (ej. `$producto->increment('stock', ...)`), conservando la cobertura de RENIEC.

## 5. Documentación

- **AGENTS.md** se actualiza: se eliminan las referencias a "stock derivado de lotes", `php artisan stock:sync` y las tablas `lote_producto` / `detalle_venta_lote` / `atencion_producto_lote`.
- Los `docs/` que hablan de la lógica de lotes **se conservan como referencia histórica**, sin modificaciones.

## 6. Verificación

1. `php artisan test` — toda la suite debe pasar.
2. `./vendor/bin/pint` — formateo del código.
3. `php artisan migrate:fresh --seed` — el esquema y los datos de prueba se reconstruyen sin lotes.
4. Prueba manual en el panel: crear/editar/eliminar ingreso, venta y atención validando `producto.stock` e `inventario`, y consultando el Kardex.