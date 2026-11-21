# Plan — Venta Fraccionada y Unidades de Venta

> **Estado:** implementación en curso. Fases 1–4 y parte de la 5 completadas (catálogos globales, presentaciones por producto, ventas/atenciones/ingresos fraccionados, kardex/reportes y carrito público). Pendiente: presentaciones en combos, código de barras y ajustes finos de reportes.
> **Fecha:** 18/11/2026.
> **Objetivo:** permitir vender productos por fracciones o presentaciones (caja, blíster, tableta, ml, gramo, etc.) manteniendo stock, kardex, caja, reportes y carrito coherentes.

---

## 0. Estado de implementación

| Fase | Estado | Evidencia |
|---|---|---|
| 1. Esquema, modelos y catálogos globales | ✅ Implementado | `unidad_medida`, `presentacion`, `producto_presentacion`; CRUD en `/admin/unidades-medida` y `/admin/presentaciones` |
| 2. Asignación de presentaciones al producto | ✅ Implementado | Formulario de producto con unidad base, `stock_minimo` y repeater de presentaciones |
| 3. Ventas, atenciones e ingresos fraccionados | ✅ Implementado | `InventarioService`, selects producto/presentación, cantidades decimales, kardex y caja |
| 4. Kardex, reportes y dashboard | ✅ Implementado (base) | Kardex con presentación; reportes y top productos usan `cantidad_base`; stock crítico usa `stock_minimo` |
| 5. Catálogo público y carrito | ✅ Implementado (base) | Selector de presentación, cantidad decimal, carrito con presentación y PDF/WhatsApp |
| 6. Combos con presentación | ⏳ Pendiente | Los combos siguen usando la presentación base |
| 7. Código de barras y pulido | ⏳ Pendiente | Columna `codigo_barras` preparada, sin lector |

**Pruebas automatizadas al cierre de esta revisión:** 122 pruebas aprobadas (423 aserciones), incluyendo unitarias de cantidades, catálogos, asignación a productos, ventas fraccionadas, carrito y seeders.

> **Migraciones:** se modificaron las migraciones originales porque no hay datos productivos. Ejecutar `php artisan migrate:fresh --seed`.

---

## 0.1. Decisiones implementadas

- Catálogos **globales** administrados por usuarios: `unidad_medida` y `presentacion`.
- Cada unidad base tiene una **presentación base** (`unidad_medida.presentacion_base_id`) que se usa automáticamente al crear productos.
- Cada producto tiene **una unidad base** y **una presentación base implícita** (factor 1, precio = `producto.precio_venta`).
- Las presentaciones adicionales se asignan al producto con **factor, precio, paso y estado**.
- El stock se guarda siempre en **unidad base**, con `decimal(12,3)`.
- Las cantidades de detalle y kardex usan `decimal(12,3)`; los precios unitarios `decimal(12,4)`.
- Los detalles usan **ID propio** (`id_detalle_venta`, `id_detalle_ingreso`, `id_atencion_producto`), lo que permite varias presentaciones del mismo producto en un documento.
- Se centralizó el movimiento de stock en `App\Services\InventarioService` con bloqueo `lockForUpdate()`.
- Se agregó `stock_minimo` por producto y se reemplazó el umbral fijo de 5.


---

## 1. Resumen ejecutivo

Hoy el sistema trabaja con **cantidades enteras** y **un solo precio por producto**. Para vender fraccionado se necesita separar tres conceptos que actualmente están mezclados:

1. **Unidad base** del producto (la unidad mínima en la que se controla el stock): tableta, ml, gramo, unidad.
2. **Presentaciones de venta** (cómo se ofrece al cliente): caja x 100, blíster x 10, tableta suelta, frasco de 120 ml.
3. **Cantidad vendida** en la presentación elegida, convertida a unidad base para descontar stock.

**Recomendación principal:** implementar un modelo de **presentaciones múltiples con factor de conversión**, guardando todo el stock en la **unidad base**. No alcanza con permitir decimales en la cantidad actual: una farmacia necesita vender una caja, un blíster o una tableta con precios distintos y descontar correctamente el inventario.

Ejemplo:

| Producto | Unidad base | Presentación | Factor | Precio | Stock |
|---|---|---|---|---|---|
| Ibuprofeno 400 mg | Tableta (TAB) | Caja x 100 | 100 | S/ 50.00 | 250 TAB |
| Ibuprofeno 400 mg | Tableta (TAB) | Blíster x 10 | 10 | S/ 6.00 | 250 TAB |
| Ibuprofeno 400 mg | Tableta (TAB) | Tableta | 1 | S/ 0.80 | 250 TAB |

Vender `1 caja + 5 tabletas` descuenta `105 TAB` y deja `145 TAB`.

---

## 2. Situación actual y limitaciones

### 2.1. Base de datos

| Tabla / columna | Estado actual | Problema |
|---|---|---|
| `producto.stock`, `producto.inventario` | `integer` | No admite fracciones ni ml/g |
| `producto` | Un solo `precio_venta` | No distingue caja/blíster/tableta |
| `detalle_venta.cantidad` | `integer` | No admite `0.5` ni `10.5` |
| `detalle_venta` | PK compuesta `(venta, producto)` | Impide dos presentaciones del mismo producto en una venta |
| `detalle_ingreso.cantidad` | `integer` | No permite ingresar “1 caja = 100 tabletas” |
| `atencion_producto.cantidad` | `integer` | Mismo problema en atenciones |
| `kardex_movimientos.cantidad` | `integer` | No registra movimientos fraccionados |
| `carrito_item.cantidad` | `integer` | No permite fracciones ni presentaciones |
| `combo_producto.cantidad` | `integer` | Los combos no pueden expresar presentaciones |
| `KardexHelper::registrar()` | `int $cantidad` | Firma incompatible con decimales |

### 2.2. Lógica y UI

- `StoreVentaRequest`, `UpdateVentaRequest`, `StoreAtencionRequest`, `StoreIngresoRequest` exigen `integer|min:1`.
- Los formularios de venta/atención usan `parseInt()` y `min="1"`.
- El formulario de producto solo pide `stock` e `inventario` enteros y un único `precio_venta`.
- El catálogo público muestra un único precio y el detalle no permite elegir presentación.
- El carrito agrega solo `item_id` y cantidad 1; no envía presentación.
- El dashboard y los reportes suman `detalle_venta.cantidad`; si se mezclan cajas y tabletas, el resultado deja de ser comparable.
- El reporte de inventario valoriza `stock * precio_compra`; si el costo es por caja y el stock por tableta, se multiplica por error.

### 2.3. Restricciones estructurales importantes

- **PK compuestas**: `detalle_venta`, `detalle_ingreso` y `atencion_producto` tienen PK `(documento, producto)`. Para vender el mismo producto en dos presentaciones hay que incluir la presentación en la PK o agregar un ID propio.
- **Stock no bloqueado**: las validaciones se hacen antes de la transacción y sin `lockForUpdate()`. Con fracciones y precios por presentación conviene centralizar el consumo de stock en un servicio transaccional.
- **SQLite en tests**: alterar PK compuestas en SQLite es limitado. La estrategia de migración depende de si hay datos productivos.

---

## 3. Modelo implementado

### 3.1. Conceptos

```
PRODUCTO
  unidad_base: TAB
  stock: 250.000 TAB
  stock_minimo: 30.000 TAB
  costo_base: S/ 0.20 por TAB
  presentaciones:
    - Caja x 100   factor 100   precio S/ 50.00   paso 1
    - Blíster x 10 factor 10    precio S/ 6.00    paso 1
    - Tableta      factor 1     precio S/ 0.80    paso 0.5  (si se permite media tableta)
```

Regla: **todo movimiento de stock se expresa en unidad base**. La presentación solo existe para vender, mostrar y convertir.

### 3.2. Tablas nuevas

#### `unidad_medida`

Catálogo controlado para evitar texto libre inconsistente.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `codigo` | varchar(10) unique | `UND`, `TAB`, `CAP`, `ML`, `G`, `MG` |
| `nombre` | varchar(50) | Unidad, Tableta, Cápsula, Mililitro, Gramo |
| `permite_fraccion` | boolean | Default de la unidad |
| `presentacion_base_id` | bigint FK nullable | Presentación global usada como base de los productos |
| `activo` | boolean | |

Seed inicial: `UND`, `TAB`, `CAP`, `ML`, `G`, `MG`.

#### `producto_presentacion`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `producto_id` | int FK | `producto.id_producto` |
| `nombre` | varchar(100) | “Caja x 100”, “Blíster x 10”, “Tableta” |
| `factor` | decimal(12,3) | Unidades base que representa |
| `precio_venta` | decimal(12,4) | Precio por presentación |
| `paso` | decimal(12,3) | Incremento mínimo de venta (1, 0.5, 5 ml) |
| `es_base` | boolean | Exactamente una por producto, `factor = 1` |
| `activo` | boolean | |
| `codigo_barras` | varchar(50) nullable | Futuro lector |
| `orden` | integer | |

Índices/restricciones:

- `unique(producto_id, nombre)`.
- `index(producto_id, activo)`.
- `factor > 0` (validación de aplicación).
- Una sola presentación base por producto.

### 3.3. Cambios en tablas existentes

#### `producto`

| Columna | Cambio |
|---|---|
| `unidad_base_id` | Nueva FK a `unidad_medida` |
| `stock` | `integer` → `decimal(12,3)` |
| `inventario` | `integer` → `decimal(12,3)` |
| `stock_minimo` | Nueva `decimal(12,3)` default `5.000` |
| `precio_compra` | `decimal(10,2)` → `decimal(12,4)` (costo por unidad base) |
| `precio_venta` | `decimal(10,2)` → `decimal(12,4)` (precio de la presentación base; compatibilidad) |

> `precio_venta` del producto se mantiene sincronizado con la presentación base para no romper catálogo/listados mientras se migra. La fuente de verdad para vender es `producto_presentacion.precio_venta`.

#### `detalle_venta`

| Columna | Cambio |
|---|---|
| `producto_presentacion_id` | Nueva FK a `producto_presentacion` |
| `cantidad` | `integer` → `decimal(12,3)` (en la presentación) |
| `factor` | Nueva `decimal(12,3)` (snapshot) |
| `cantidad_base` | Nueva `decimal(12,3)` |
| `precio_unitario` | Nueva `decimal(12,4)` (snapshot) |
| `sub_total` | Se mantiene `decimal(10,2)` |

PK implementada: `id_detalle_venta` (ID propio). Columnas: `producto_presentacion_id`, `cantidad`, `factor`, `cantidad_base`, `precio_unitario`, `sub_total`.

#### `detalle_ingreso`

Mismo patrón: `id_detalle_ingreso` propio, `producto_presentacion_id`, `cantidad` decimal, `factor`, `cantidad_base`.

#### `atencion_producto`

Mismo patrón: `id_atencion_producto` propio, `producto_presentacion_id`, `cantidad` decimal, `factor`, `cantidad_base`, `precio_unitario`.

#### `kardex_movimientos`

| Columna | Cambio |
|---|---|
| `cantidad` | `integer` → `decimal(12,3)` (unidad base) |
| `stock_anterior` | `integer` → `decimal(12,3)` |
| `stock_nuevo` | `integer` → `decimal(12,3)` |
| `producto_presentacion_id` | Nueva FK nullable |
| `cantidad_presentacion` | Nueva `decimal(12,3)` nullable |
| `unidad_presentacion` | Nueva `varchar(30)` nullable (snapshot) |
| `factor` | Nueva `decimal(12,3)` nullable |

El kardex sigue siendo la fuente de trazabilidad y **siempre** registra la cantidad base; los campos de presentación son informativos.

#### `carrito_item`

| Columna | Cambio |
|---|---|
| `producto_presentacion_id` | Nueva FK nullable (null para combos) |
| `cantidad` | `integer` → `decimal(12,3)` |
| `precio_unitario` | Nueva `decimal(12,4)` (snapshot al agregar) |
| Único | `(carrito_id_carrito, item_type, item_id, producto_presentacion_id)` |

Para combos, `producto_presentacion_id` queda null; la unicidad se controla en aplicación con `whereNull`.

#### `combo_producto`

| Columna | Cambio |
|---|---|
| `producto_presentacion_id` | Nueva FK nullable |
| `cantidad` | `integer` → `decimal(12,3)` |

Los combos pueden seguir usando la presentación base en la primera fase. La segunda fase permite elegir presentación y muestra el precio correcto.

---

## 4. Reglas de negocio y cálculos

### 4.1. Conversión

```php
$cantidadBase = bcmul((string) $cantidad, (string) $factor, 3);
$subtotal = round((float) bcmul((string) $cantidad, (string) $precioUnitario, 4), 2);
```

- Cantidades: escala **3** (0.001).
- Precios unitarios: escala **4**.
- Totales: escala **2**.
- No usar `float` para stock ni validaciones; usar `bcmath` en backend.
- JS puede usar `parseFloat` solo para mostrar y calcular el preview.

### 4.2. Validación de cantidad

- `cantidad > 0`.
- Debe ser múltiplo de `presentacion.paso`:
  - Tableta con paso `0.5` → permite `0.5`, `1`, `1.5`.
  - Caja con paso `1` → no permite `1.5`.
- La presentación debe pertenecer al producto y estar activa.
- `cantidad_base <= stock disponible`.
- En edición: `disponible = stock + cantidad_base_anterior` del documento.

### 4.3. Precio

- El precio se toma de `producto_presentacion.precio_venta` en el servidor.
- No confiar en el precio enviado por el navegador.
- Si se necesita precio manual/descuento, agregar `precio_manual` con permiso y auditoría; no reutilizar el precio del formulario sin validación.
- `sub_total` se calcula por línea y se redondea a 2 decimales; el total del documento es la suma de subtotales (no recalcular desde el total).

### 4.4. Stock y kardex

- Venta/atención: `stock -= cantidad_base`.
- Ingreso: `stock += cantidad_base`; `inventario = stock` al registrar/editar el ingreso (misma semántica actual).
- Edición: revertir cantidades base anteriores, eliminar kardex de la referencia y volver a consumir.
- Eliminación: restaurar cantidades base y eliminar kardex/caja.
- `KardexHelper::registrar()` debe aceptar cantidad base decimal y datos opcionales de presentación:

```php
KardexHelper::registrar(
    producto: $producto,
    tipoMovimiento: 'VENTA',
    referenciaTipo: 'Venta',
    referenciaId: $venta->id_venta,
    referenciaCodigo: $venta->correlativo,
    cantidadBase: '-105.000',
    presentacion: $presentacion,       // opcional
    cantidadPresentacion: '1.000',
    observacion: 'Venta registrada'
);
```

### 4.5. Concurrencia

Crear un `InventarioService` que:

1. Abra una transacción.
2. Bloquee los productos con `lockForUpdate()` en orden de ID.
3. Valide stock con `bccomp()`.
4. Descuente/incremente.
5. Escriba el kardex.

Esto corrige también una debilidad actual: la validación del FormRequest no bloquea el stock y dos cajas podrían vender la última unidad a la vez.

---

## 5. Cambios por capa

### 5.1. Migraciones

1. `create_unidad_medida_table`.
2. `create_producto_presentacion_table`.
3. `alter_producto_add_unidad_base_stock_decimal`.
4. `alter_detalle_venta_add_presentacion`.
5. `alter_detalle_ingreso_add_presentacion`.
6. `alter_atencion_producto_add_presentacion`.
7. `alter_kardex_add_presentacion`.
8. `alter_carrito_item_add_presentacion`.
9. `alter_combo_producto_add_presentacion`.
10. Backfill de presentaciones base y datos históricos.

**Estrategia según datos:**

- **Sin datos productivos (recomendado si el repo sigue en desarrollo):** reescribir las migraciones originales con el esquema nuevo y ejecutar `migrate:fresh --seed`. Es la ruta más limpia y evita migraciones de PK complejas en SQLite.
- **Con datos productivos:** migraciones aditivas + comando de backfill + ventana de mantenimiento. No borrar columnas antiguas hasta validar. Para SQLite/tests, usar el esquema reescrito; para MySQL, `ALTER TABLE ... DROP PRIMARY KEY, ADD PRIMARY KEY (...)`.

### 5.2. Modelos

- Nuevos: `UnidadMedida`, `ProductoPresentacion`.
- `Producto`: `unidadBase()`, `presentaciones()`, `presentacionBase()`, accessor `precio_desde`, `stock_en_unidad`.
- `Venta`, `Atencion`, `Ingreso`: pivotes con `producto_presentacion_id`, `cantidad`, `factor`, `cantidad_base`, `precio_unitario`.
- `KardexMovimiento`: casts decimal y relación `presentacion()`.
- `CarritoItem`: `presentacion()`, `precio_unitario`, `subtotal` basado en snapshot.
- `Combo`: pivote con presentación.

### 5.3. Servicios/helpers

- `CantidadHelper` (bcmath): convertir, comparar, sumar, validar paso.
- `InventarioService`: consumir, revertir, ingresar, con locks y kardex.
- Refactorizar `VentaController`, `AtencionController` e `IngresoController` para usarlo. Evita duplicar la lógica y facilita pruebas.

### 5.4. Requests

- `StoreProductoRequest` / `UpdateProductoRequest`: `unidad_base_id`, `stock`/`inventario` decimales, `stock_minimo`, arreglo `presentaciones` (mínimo una base).
- `StoreVentaRequest` / `UpdateVentaRequest`: `producto_presentacion_id`, `cantidad` decimal, `precio` (solo si se permite override), validación de paso y stock base.
- `StoreAtencionRequest` / `UpdateAtencionRequest`: igual.
- `StoreIngresoRequest` / `UpdateIngresoRequest`: `producto_presentacion_id`, `cantidad` decimal.
- `StoreComboRequest` / `UpdateComboRequest`: presentación opcional y cantidad decimal.

### 5.5. Controladores

- `ProductoController`: guardar/actualizar presentaciones en transacción; sincronizar `producto.precio_venta` con la base; validar una sola base.
- `VentaController`: total desde presentaciones; descuento base; kardex con presentación; restauración en edición/eliminación.
- `AtencionController`: igual.
- `IngresoController`: convertir presentación a base; actualizar `inventario`.
- `CarritoController`: recibir `producto_presentacion_id`, `cantidad`; validar presentación activa; guardar snapshot de precio; devolver subtotal/total.
- `ComboController` (admin y público): precio de componentes según presentación.
- `DashboardController` y `ReporteService`: sumar `cantidad_base`, no `cantidad`.

### 5.6. Vistas y JS

**Admin producto**

- Select de unidad base.
- Repeater de presentaciones: nombre, factor, precio, paso, base, activo, código de barras.
- Ayuda visual: “1 caja = 100 tabletas”.
- Stock e inventario etiquetados en unidad base; `stock_minimo`.
- Validación en vivo de factor/precio.

**Admin venta/atención/ingreso**

- Dos selects: producto y presentación.
- Cantidad con `step` según presentación.
- Precio unitario readonly desde la presentación.
- Stock disponible en unidad base y equivalencia.
- Subtotal por línea y total.
- Endpoint AJAX `GET /admin/productos/{producto}/presentaciones` para poblar el select (o datos embebidos si el catálogo es pequeño).
- Al editar, reconstruir líneas con presentación y cantidad.

**Público**

- Card: “Desde S/ X” si hay varias presentaciones.
- Detalle: selector de presentación, precio y stock dinámicos, cantidad con paso.
- Carrito: guardar presentación, mostrar “Caja x 100” y cantidad; permitir entrada decimal; PDF/WhatsApp con presentación.
- Búsqueda: precio mínimo de presentaciones activas.

**Kardex/reportes**

- Mostrar cantidad base y, entre paréntesis, la presentación (“-100 TAB (1 caja)”).
- Reporte de inventario con unidad base y valorización por costo base.
- Top productos: sumar `cantidad_base` y mostrar unidad; idealmente añadir un ranking por **monto vendido** para no comparar tabletas con ml.

### 5.7. Notas de implementación

- **Pivotes Eloquent:** aunque el detalle tiene ID propio, el `attach()` debe enviar la presentación en el arreglo de datos:

```php
$venta->productos()->attach($productoId, [
    'producto_presentacion_id' => $presentacionId,
    'cantidad' => $cantidad,
    'factor' => $factor,
    'cantidad_base' => $cantidadBase,
    'precio_unitario' => $precio,
    'sub_total' => $subTotal,
]);
```

- Agregar `withPivot('producto_presentacion_id', 'cantidad', 'factor', 'cantidad_base', 'precio_unitario')` en las relaciones.
- **Edición:** no usar un mapa por `producto_id`; sumar las cantidades base anteriores por producto (o por producto+presentación) para calcular el disponible.
- **Duplicados:** validar que no se repita la misma combinación producto+presentación en una venta; si se permite, la PK debe incluir un ID propio. Para el MVP, consolidar líneas duplicadas.
- **`sync()` con combos:** si se usa `sync()`, la clave debe incluir la presentación; de lo contrario se pierden líneas del mismo producto.
- **Compatibilidad:** mientras exista código que lea `producto.precio_venta`, sincronizarlo con la presentación base al guardar el producto.

---

## 6. Migración y backfill

### 6.1. Backfill mínimo

Para cada producto existente:

1. Crear presentación base:
   - `nombre = 'Unidad'` o el nombre de la unidad.
   - `factor = 1`.
   - `precio_venta = producto.precio_venta`.
   - `paso = 1`.
   - `es_base = true`.
2. Asignar `unidad_base_id = UND` por defecto. **Revisar manualmente** productos líquidos/polvos para cambiarlos a ML/G.
3. `stock` e `inventario` se conservan con la misma cifra, ahora decimal.

Para detalles históricos:

- `producto_presentacion_id` = presentación base del producto.
- `factor = 1`.
- `cantidad_base = cantidad`.
- `precio_unitario = sub_total / cantidad` (si `cantidad > 0`).

Para kardex histórico:

- `cantidad`, `stock_anterior`, `stock_nuevo` se conservan.
- `cantidad_presentacion = cantidad`.
- `unidad_presentacion = 'UND'`.
- `factor = 1`.

### 6.2. Comando de verificación

Crear `php artisan inventario:auditar` que compare, por producto:

- Último `stock_nuevo` del kardex contra `producto.stock`.
- Suma de `cantidad_base` de detalles contra movimientos.
- Diferencias y productos sin presentación base.

Ejecutarlo antes y después de la migración.

---

## 7. Pruebas y QA

### 7.1. Unitarias

- Conversión `cantidad * factor` con 0.5, 10.5, 100.
- Validación de paso (0.5, 1, 5).
- Redondeo de subtotales y total.
- Comparación de stock con `bccomp`.

### 7.2. Feature

1. Vender 1 caja descuenta 100 TAB.
2. Vender 5 tabletas descuenta 5 TAB.
3. Vender 1 caja + 5 tabletas en la misma venta descuenta 105 TAB.
4. Intentar 3 cajas con stock 250 → 422.
5. Vender 0.5 tableta si el paso lo permite → stock 249.5.
6. Editar venta de 1 caja a 2 blísteres → stock correcto y kardex regenerado.
7. Eliminar venta → restaura 105 TAB.
8. Ingreso de 2 cajas → stock +200 TAB e inventario actualizado.
9. Kardex muestra cantidad base y presentación.
10. Caja registra el total correcto con precios por presentación.
11. Carrito con presentación y cantidad decimal; PDF/WhatsApp.
12. Reporte top productos suma base.
13. Producto sin presentaciones → se crea base automáticamente.

### 7.3. Regresión

- Ventas/atenciones/ingresos existentes con cantidades enteras.
- Caja, reportes, dashboard, combos y catálogo público.
- Migración sobre copia de la base actual y `inventario:auditar` sin diferencias.

---

## 8. Fases de implementación

| Fase | Entregable | Estimación |
|---|---|---|
| 0 | Definir unidades, presentaciones y política de precios con el negocio | 2–3 días |
| 1 | Esquema, modelos, catálogo de unidades y backfill | 4–6 días |
| 2 | Admin de productos con presentaciones | 3–4 días |
| 3 | Ventas y atenciones con presentación/cantidad decimal + servicio de inventario | 6–8 días |
| 4 | Ingresos con presentación y conversión a base | 2–3 días |
| 5 | Catálogo público, detalle y carrito | 4–6 días |
| 6 | Kardex, reportes, dashboard y combos | 3–5 días |
| 7 | Pruebas, migración, capacitación y piloto | 4–6 días |

**Total aproximado:** 4–6 semanas con un desarrollador, dependiendo de si se implementan combos y código de barras.

### Piloto recomendado

1. Elegir 10 productos: 3 de tableta, 3 líquidos, 2 cremas, 2 de venta por unidad.
2. Cargar presentaciones y precios.
3. Operar una caja durante una semana con la bandera `ventas.fraccionada` activa.
4. Comparar kardex, stock físico y caja diariamente.
5. Recién después habilitar el resto del catálogo.

---

## 9. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Mezclar stock base con stock por presentación | Una sola regla: `stock` siempre en unidad base |
| Errores de redondeo | `bcmath`, escala 3 para cantidades, 2 para totales |
| Precio manipulado desde el navegador | El servidor toma el precio de la presentación |
| Vender más que el stock por concurrencia | `lockForUpdate()` + validación dentro de la transacción |
| PK compuesta impide dos presentaciones | Se reemplazó por ID propio en los detalles (`id_detalle_venta`, etc.) |
| Reportes comparan cajas con tabletas | Sumar `cantidad_base` y mostrar unidad; añadir ranking por monto |
| Costo por caja usado como costo por tableta | Etiquetar `precio_compra` como costo por unidad base y validar |
| Migración con datos productivos | Backfill, auditoría, respaldo y ventana de mantenimiento |
| Curva de adopción del personal | Etiquetas claras, valores por defecto y piloto |

---

## 10. Decisiones a confirmar antes de implementar

1. **¿Hay datos productivos?** Si no, se reescriben las migraciones y se usa `migrate:fresh`; si sí, se hace migración aditiva con backfill.
2. **¿Qué granularidad se necesita?** ¿Media tableta, ml, gramos, o solo fracciones de caja/blíster? Esto define `paso` y precisión.
3. **¿El precio es por presentación o se calcula desde la unidad base?** Se recomienda precio explícito por presentación.
4. **¿Los combos deben elegir presentación en la primera fase?** Se recomienda dejarlo para la fase 6.
5. **¿Se necesita código de barras por presentación?** La columna queda preparada, pero el lector es un proyecto aparte.
6. **¿Devoluciones/anulaciones parciales?** Hoy no existen; si se necesitan, diseñarlas junto con el kardex.

---

## 11. Recomendaciones finales

1. **No implementar solo “cantidad decimal”**: sin presentaciones, una caja y una tableta seguirían compitiendo por el mismo precio.
2. **Unidad base única por producto** y stock siempre en esa unidad.
3. **Precio explícito por presentación**, no derivado por división.
4. **Servicio de inventario transaccional** con bloqueo de stock; es la mejor inversión técnica del proyecto.
5. **`stock_minimo` por producto** en unidad base, en lugar del umbral fijo de 5.
6. **Ranking de productos por monto** además de por unidades, para que los reportes sigan siendo útiles con unidades heterogéneas.
7. **Bandera de funcionalidad y piloto** antes de habilitar toda la farmacia.
8. **Auditoría de inventario** antes/después de migrar.

---

## 12. Alternativa de menor alcance (solo si aplica)

Si el negocio **solo necesita fraccionar la misma unidad** (por ejemplo, vender 10.5 ml de un frasco o 0.5 tabletas) y **no** necesita precios distintos por caja/blíster, se puede hacer un MVP más pequeño:

- Agregar `producto.unidad_medida` (texto o catálogo simple).
- Cambiar `stock`, `inventario`, cantidades de detalle y kardex a `decimal(12,3)`.
- Añadir `paso` por producto (0.5, 1, 5).
- Ajustar formularios, validaciones y reportes para decimales.
- Mantener un único `precio_venta` por unidad base.

**Ventajas:** 1–2 semanas, menos cambios, sin tabla de presentaciones.
**Límites:** no permite vender “caja x 100 a S/ 50” y “tableta a S/ 0.80” con precios independientes; el precio de la caja sería `100 × precio tableta`. Si la farmacia vende cajas, blísters y unidades con precios comerciales distintos, el modelo completo de presentaciones es el correcto.

---

## 13. Roadmap de tareas y pruebas manuales

### 13.1. Checklist de implementación

| # | Tarea | Estado |
|---|---|---|
| 1 | Migraciones de `presentacion`, `unidad_medida`, `producto_presentacion` | ✅ |
| 2 | Stock, inventario, cantidades y kardex decimales | ✅ |
| 3 | CRUD de unidades de medida y presentaciones | ✅ |
| 4 | Permisos `unidades-medida` y `presentaciones` | ✅ |
| 5 | Unidad base y `stock_minimo` en producto | ✅ |
| 6 | Repeater de presentaciones en el producto | ✅ |
| 7 | Selector producto/presentación en ventas, atenciones e ingresos | ✅ |
| 8 | Servicio transaccional de inventario con locks | ✅ |
| 9 | Kardex con presentación y cantidades base | ✅ |
| 10 | Reportes/dashboard usando `cantidad_base` y `stock_minimo` | ✅ |
| 11 | Catálogo público con “Desde” y selector de presentación | ✅ |
| 12 | Carrito con presentación, cantidad decimal, PDF/WhatsApp | ✅ |
| 13 | Pruebas unitarias y feature | ✅ (122) |
| 14 | Combos con presentación | ⏳ |
| 15 | Código de barras por presentación | ⏳ |
| 16 | Ranking de productos por monto | ⏳ |

### 13.2. Prueba manual — Catálogos globales

1. Iniciar sesión como Administrador.
2. Ir a **Inventario → Unidades de medida**.
3. Crear `ML` / “Mililitro”, marcar “Permite fraccionadas” y elegir “Mililitro” como presentación base.
4. Verificar que aparece en la tabla y que el formulario de producto la ofrece.
5. Ir a **Inventario → Presentaciones** y crear “Caja” y “Blíster”.
6. Intentar crear “Caja” dos veces: debe mostrar error de nombre duplicado.
7. Editar “Caja”, desactivarla y verificar que deja de aparecer en el alta de productos.
8. Intentar eliminar una presentación asignada a un producto: debe rechazarse con mensaje.
9. Entrar con un usuario sin permiso `unidades-medida`: no debe ver el menú y debe recibir 403 al abrir la URL.

**Resultado esperado:** catálogos administrables, sin duplicados y protegidos por permisos.

### 13.3. Prueba manual — Producto y presentaciones

1. Crear producto “Ibuprofeno 400mg”:
   - Unidad base: Tableta (`TAB`).
   - Stock: `250`, inventario: `250`, stock mínimo: `20`.
   - Precio venta base: `0.80`.
2. Agregar presentaciones:
   - Blíster, factor `10`, precio `6.00`, paso `1`.
   - Caja, factor `100`, precio `50.00`, paso `1`.
3. Guardar y volver a editar.
4. Verificar:
   - Presentación base creada automáticamente (Tableta, factor 1, S/ 0.80).
   - Stock mostrado como `250 TAB`.
   - Las dos presentaciones adicionales aparecen.
5. Intentar agregar la presentación base (Tableta) en el repeater: debe dar error.
6. Intentar factor `0` o paso mayor al factor: debe dar error.
7. Cambiar el precio base a `0.90`: la presentación base debe actualizarse.
8. Quitar “Caja” y guardar: debe quedar inactiva, no desaparecer si tiene movimientos.
9. En el catálogo público, el producto debe mostrar “Desde S/ 0.80” y el detalle debe ofrecer Blíster/Caja.

**Resultado esperado:** una base obligatoria, presentaciones asignadas con factor/precio/paso y stock en unidad base.

### 13.4. Prueba manual — Ingreso

1. Ir a **Inventario → Ingresos → Nuevo ingreso**.
2. Seleccionar proveedor y fecha.
3. Seleccionar “Ibuprofeno — Caja x 100”, cantidad `2`.
4. Guardar.
5. Verificar:
   - Stock: `250 + 200 = 450 TAB`.
   - Inventario: `450`.
   - Kardex: movimiento `INGRESO`, cantidad `+200`, presentación “Caja x 100”.
6. Editar el ingreso a cantidad `1`: stock debe quedar `350`.
7. Eliminar el ingreso: stock debe volver a `250` y desaparecer el kardex asociado.

**Resultado esperado:** la cantidad de la presentación se convierte a unidad base y el kardex conserva la presentación.

### 13.5. Prueba manual — Venta fraccionada

1. Abrir caja con S/ 100.
2. Ir a **Operaciones → Ventas → Nueva venta**.
3. Agregar:
   - Ibuprofeno — Caja x 100, cantidad `1`.
   - Ibuprofeno — Tableta, cantidad `5`.
4. Verificar total `S/ 54.00` (50 + 4).
5. Guardar.
6. Verificar:
   - Stock `250 - 105 = 145 TAB`.
   - Kardex con `-100` (Caja) y `-5` (Tableta).
   - Caja con ingreso `S/ 54.00`.
7. Intentar vender `3` cajas con stock `145`: debe rechazar por stock insuficiente.
8. Editar la venta a `2` blísteres: stock debe quedar `230 TAB`, total `S/ 12.00`, caja `S/ 12.00`.
9. Eliminar la venta: stock debe volver a `250`, caja a `0` y kardex vacío.
10. Si la presentación Tableta tiene paso `0.5`, vender `0.5`: stock `249.500`, total `S/ 0.40`.

**Resultado esperado:** una venta puede mezclar presentaciones del mismo producto, el stock se descuenta en base y la caja usa el total de las presentaciones.

### 13.6. Prueba manual — Atención

1. Ir a **Operaciones → Atenciones → Nueva atención**.
2. Agregar un servicio y una presentación de producto (por ejemplo, 1 Caja).
3. Guardar y verificar stock, kardex `ATENCION` y caja (producto + servicio).
4. Editar la cantidad de la presentación y verificar recálculo.
5. Eliminar la atención y verificar restauración de stock y caja.

### 13.7. Prueba manual — Catálogo y carrito público

1. Abrir el detalle del producto en la tienda.
2. Verificar el selector de presentación y que el precio cambia al elegir Caja/Blíster/Tableta.
3. Agregar `2` blísteres al carrito.
4. Verificar en `/carrito`:
   - Nombre “Ibuprofeno — Blíster x 10”.
   - Cantidad `2`, subtotal `S/ 12.00`.
5. Cambiar cantidad con `+`/`-`; en una presentación con paso `0.5`, verificar que avanza de `0.5` en `0.5`.
6. Descargar PDF y cotizar por WhatsApp: debe incluir presentación y cantidad.
7. Verificar el badge del carrito.
8. Sin sesión, “Añadir al carrito” debe redirigir a login.

**Resultado esperado:** el cliente elige presentación y cantidad; el carrito conserva el precio y la presentación.

### 13.8. Prueba manual — Kardex y reportes

1. Ir a **Inventario → Kardex**.
2. Verificar columna “Presentación”, cantidades con decimales y stock anterior/nuevo.
3. Filtrar por producto, tipo y fechas.
4. Generar **Reporte de inventario**: stock con unidad, stock mínimo y valorización.
5. Generar **Reporte de ventas** y **Atenciones**: presentación y unidad en el detalle.
6. Revisar el dashboard: “Stock bajo” debe usar `stock_minimo` y los productos en riesgo deben mostrar stock/stock mínimo.
7. Verificar que el top de productos usa `cantidad_base` (no mezcla cajas con tabletas).

### 13.9. Prueba manual — Seeders y migración

1. Ejecutar `php artisan migrate:fresh --seed`.
2. Iniciar sesión con `admin@sistema.com` / `admin123`.
3. Verificar:
   - 5 unidades de medida y 11 presentaciones globales.
   - 10 productos, todos con presentación base.
   - Paracetamol, Ibuprofeno y Amoxicilina con Caja y Blíster adicionales.
4. Abrir una venta y confirmar que los selects muestran presentaciones.
5. Ejecutar `php artisan test --compact`: deben pasar las 122 pruebas.

### 13.10. Pruebas automatizadas por tarea

| Tarea | Archivo |
|---|---|
| Conversión, comparación, paso y redondeo | `tests/Unit/CantidadHelperTest.php` |
| CRUD de unidades/presentaciones y protección de borrado | `tests/Feature/CatalogoUnidadesPresentacionesTest.php` |
| Asignación de presentaciones al producto | `tests/Feature/ProductoPresentacionesTest.php` |
| Venta fraccionada, stock, kardex, caja, ingreso | `tests/Feature/VentaFraccionadaTest.php` |
| Carrito con presentación y cantidad decimal | `tests/Feature/CarritoFraccionadoTest.php` |
| Seeders y presentaciones base | `tests/Feature/DatabaseSeederTest.php` |

---

*Plan implementado parcialmente. Los pendientes (combos, código de barras y ranking por monto) deben reflejarse aquí y en `funcionalidades-del-sistema.md` al completarse.*
