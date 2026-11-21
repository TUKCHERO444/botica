# Plan v2 — Lotes, vencimientos y FEFO sobre el sistema actual

> **Estado:** implementación parcial (núcleo operativo). Implementados: esquema de lotes, capas y consumos; ingreso con lote/vencimiento/costo; FEFO en ventas/atenciones; reversión exacta; bloqueo de edición de ingresos consumidos; alertas/bajas; seeder de lotes.
> **Pendiente:** reportes de costo/margen por lote, bandera de adopción gradual, backfill para entornos con datos y reportes avanzados de vencimientos.
> **Fecha:** 19/11/2026.
> **Base:** sistema con unidades base, presentaciones, stock decimal, kardex y venta fraccionada ya implementados.
> **Documentos históricos revisados:** `plan-control-vencimientos-lote.md`, `plan-kardex.md`, `kardex.txt` y el código eliminado en `ade7c19`.

---

## 0. Estado de implementación

| Componente | Estado | Evidencia |
|---|---|---|
| `producto_lote` | ✅ | identidad producto+código+vencimiento, correlativo autogenerado, estado y disponible en base |
| `lote_ingreso` | ✅ | capas por recepción, presentación, factor, costo y disponible |
| `lote_consumo` | ✅ | consumo por venta/atención/baja con costo |
| Ingreso con lote/vencimiento/costo | ✅ | formulario, request, controlador y kardex |
| FEFO con exclusión de vencidos | ✅ | `InventarioService::consumir()` |
| Consumo multi-lote | ✅ | una venta puede consumir varias capas |
| Reversión exacta | ✅ | `revertirConsumos()` |
| Bloqueo de ingreso consumido | ✅ | `revertirIngreso()` |
| Alertas y baja auditada | ✅ | `/admin/lotes`, kardex `BAJA` |
| Seeder con lotes | ✅ | lote `LOTE-DEMO-*` por producto |
| Costo real en reportes | ⏳ | costo guardado; falta reporte de margen |
| Backfill/bandera para producción | ⏳ | no hay datos productivos; queda documentado |

**Pruebas:** 122 pruebas aprobadas (423 aserciones), incluyendo `tests/Feature/LotesFefoTest.php`.

> **Migraciones:** se agregaron migraciones nuevas y se modificó el enum de kardex. Ejecutar `php artisan migrate:fresh --seed` en local.


---

## 1. Veredicto

La lógica anterior **no debe redesplegarse tal como estaba**. La idea era correcta —lote por ingreso, consumo FEFO y tablas de consumo para revertir—, pero la implementación tenía fallos que podían producir:

- ventas sin descontar todos los lotes;
- consumo de lotes vencidos;
- stock de producto distinto a la suma de lotes;
- pérdida de trazabilidad al editar un ingreso;
- duplicidad de lotes;
- condiciones de carrera por falta de bloqueo.

Para una botica real conviene **rediseñar el módulo** sobre el modelo actual de unidades/presentaciones. La regla central debe ser:

```text
El stock real de un producto es la suma del disponible de sus lotes.
producto.stock es una caché/denormalización que debe coincidir con esa suma.
El kardex y las tablas de consumo explican cada movimiento.
```

---

## 2. Qué hacía la implementación anterior

### 2.1. Lo aprovechable

- Separaba el stock por lote (`lote_producto`).
- Cada ingreso creaba un lote con código, vencimiento y cantidad.
- El consumo FEFO ordenaba por `fecha_vencimiento` y `id_lote`.
- Registraba qué lote se consumió en `detalle_venta_lote` y `atencion_producto_lote`.
- La reversión incrementaba exactamente los lotes consumidos.
- Existía una pantalla de alertas de vencimiento.

### 2.2. Fallos que impidieron usarla

| Fallo | Consecuencia real |
|---|---|
| `StockHelper::consumir()` no excluía lotes vencidos | FEFO vendía primero lo vencido |
| No validaba que la suma de lotes cubriera la cantidad vendida | Podía vender 10 y consumir solo 5, dejando stock inconsistente |
| No usaba `lockForUpdate()` | Dos cajas podían consumir el mismo lote a la vez |
| `lote_producto.cantidad` era entero | Incompatible con ml, gramos y medias tabletas |
| No se relacionaba con presentaciones/factores | Una caja y una tableta no se convertían a unidad base |
| Editar/eliminar un ingreso borraba lotes ya consumidos | Se perdía trazabilidad y el stock quedaba mal |
| No había unicidad de lote | Se duplicaba el mismo código/vencimiento |
| No había costo por ingreso/lote | Imposible calcular costo real de venta |
| No se verificaba `producto.stock = suma(lotes)` | Deriva silenciosa del inventario |
| La baja de vencidos no ajustaba stock/kardex | Producto seguía “disponible” contablemente |

Por eso se descartó, pero el problema de fondo —trazabilidad por lote y FEFO— sigue siendo válido para una botica.

---

## 3. Modelo propuesto

### 3.1. Dos niveles: lote físico + capa de recepción

Un mismo lote físico (`codigo_lote + producto + vencimiento`) puede recibirse varias veces, con costos distintos. Para precisión se separan:

```text
producto_lote          → identidad física del lote
lote_ingreso           → cada recepción/capa de ese lote
lote_consumo           → qué capa consumió cada venta/atención/ajuste
```

#### `producto_lote`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `correlativo` | varchar(20) unique | Generado automáticamente (`LOTE-00000001`) |
| `producto_id` | int FK | `producto.id_producto` |
| `codigo_lote` | varchar(50) | Normalizado en mayúsculas |
| `fecha_vencimiento` | date | |
| `estado` | enum | `disponible`, `agotado`, `vencido`, `bloqueado`, `baja` |
| `cantidad_inicial_base` | decimal(12,3) | Suma de recepciones |
| `cantidad_disponible_base` | decimal(12,3) | Debe ser la suma de capas |
| `created_at`, `updated_at` | | |

Restricciones:

- `unique(producto_id, codigo_lote, fecha_vencimiento)`.
- `index(producto_id, estado, fecha_vencimiento)`.
- Normalizar `codigo_lote` (trim + mayúsculas) antes de guardar.
- No permitir crear un lote con vencimiento ya pasado.

#### `lote_ingreso` (capa de recepción)

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `producto_lote_id` | FK | |
| `ingreso_id` | FK | |
| `detalle_ingreso_id` | FK unique | Una capa por línea de ingreso |
| `producto_presentacion_id` | FK | Presentación recibida (caja, blíster) |
| `cantidad_presentacion` | decimal(12,3) | Cantidad recibida en esa presentación |
| `factor` | decimal(12,3) | Snapshot del factor |
| `cantidad_base` | decimal(12,3) | `cantidad_presentacion × factor` |
| `cantidad_disponible_base` | decimal(12,3) | Se reduce con FEFO |
| `costo_unitario_base` | decimal(12,4) | Costo por unidad base |
| `costo_total` | decimal(12,2) | Costo real de la recepción |
| `created_at` | | |

Restricciones:

- `cantidad_base > 0`, `cantidad_disponible_base >= 0`.
- `costo_unitario_base >= 0`.
- `unique(detalle_ingreso_id)`.
- `index(producto_lote_id, cantidad_disponible_base)`.

#### `lote_consumo`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `lote_ingreso_id` | FK | Capa consumida |
| `producto_lote_id` | FK | Para consultas rápidas |
| `referencia_tipo` | varchar(30) | `Venta`, `Atencion`, `Ajuste`, `Baja` |
| `referencia_id` | bigint | ID del documento |
| `producto_presentacion_id` | FK nullable | Presentación vendida (si aplica) |
| `cantidad_base` | decimal(12,3) | Consumo real de esa capa |
| `usuario_id` | FK | |
| `created_at` | | |

Restricciones:

- `index(referencia_tipo, referencia_id)`.
- `index(lote_ingreso_id)`.
- No se edita ni elimina manualmente; se genera/revierte desde el servicio.

### 3.2. Cambios en tablas existentes

#### `detalle_ingreso`

Agregar:

```text
producto_lote_id          FK nullable
lote_codigo              varchar(50)   // snapshot
fecha_vencimiento        date          // snapshot
costo_unitario_base      decimal(12,4)
costo_total              decimal(12,2)
```

La línea sigue guardando `producto_presentacion_id`, `cantidad`, `factor` y `cantidad_base`.

#### `detalle_venta` y `atencion_producto`

Agregar:

```text
costo_total              decimal(12,2) // COGS real de la línea, opcional
```

El detalle de qué lotes se consumieron vive en `lote_consumo`.

#### `kardex_movimientos`

Agregar:

```text
producto_lote_id          FK nullable
lote_codigo              varchar(50) nullable
fecha_vencimiento        date nullable
```

Decisión recomendada: **un movimiento de kardex por cada capa de lote consumida**. Así una venta de 1 caja puede generar dos filas de kardex si FEFO usa dos lotes. La UI puede agruparlas por referencia.

> Alternativa más simple: un kardex por producto/línea y el detalle por lote solo en `lote_consumo`. Es menos preciso para auditar el stock por lote.

#### `producto`

- `stock` sigue siendo la caché operativa.
- `inventario` deja de ser relevante para lotes; no usarlo como fuente de verdad.
- `stock_minimo` sigue en unidad base.

---

## 4. Flujo de ingreso con lote

### 4.1. Formulario

Cada línea de ingreso debe pedir:

```text
Producto / presentación
Cantidad recibida
Código de lote
Fecha de vencimiento
Costo unitario o costo total (opcional pero recomendado)
```

Validaciones:

- presentación activa y del producto;
- cantidad > 0 y múltiplo del `paso`;
- lote no vacío y normalizado;
- vencimiento no anterior a hoy;
- costo >= 0;
- no permitir el mismo producto+presentación duplicado en la misma línea (o consolidar);
- si el lote ya existe, sumar una nueva capa `lote_ingreso`.

### 4.2. Transacción

1. Crear `ingreso` y su correlativo.
2. Por cada línea:
   - calcular `cantidad_base = cantidad_presentacion × factor`;
   - `firstOrCreate` del `producto_lote` por producto+código+vencimiento;
   - si el lote está vencido o dado de baja, rechazar;
   - crear `lote_ingreso` con cantidad inicial/disponible y costo;
   - actualizar cantidades del `producto_lote`;
   - crear `detalle_ingreso` con FK/snapshots;
   - incrementar `producto.stock` en base;
   - registrar kardex `INGRESO` con lote y vencimiento.
3. Al final, verificar que `producto.stock == suma(lotes disponibles)`.

### 4.3. Edición/eliminación

Regla recomendada para uso real:

> **Un ingreso con consumo no se edita ni elimina.** Se corrige con un ajuste o devolución.

Si la capa no tuvo consumo:

- revertir la capa y el kardex;
- permitir editar cantidades/lote/costo;
- volver a crear las capas.

Si la capa tuvo consumo:

- bloquear la operación;
- ofrecer “ajuste de inventario” o “devolución a proveedor”, con motivo y auditoría.

Esto evita el error histórico de borrar lotes ya vendidos.

---

## 5. FEFO correcto

### 5.1. Algoritmo

Para cada línea de venta o atención:

```text
requerido_base = cantidad_presentacion × factor

1. Bloquear producto y lotes.
2. Buscar capas disponibles:
   - producto = producto de la línea
   - cantidad_disponible_base > 0
   - estado = disponible
   - fecha_vencimiento >= hoy
   - ordenar por fecha_vencimiento ASC, created_at ASC, id ASC
3. Si suma(disponible) < requerido_base → error y rollback.
4. Consumir capa por capa:
   - tomar = min(capa.disponible, restante)
   - capa.disponible -= tomar
   - lote.disponible -= tomar
   - crear lote_consumo
   - crear kardex por capa
   - restante -= tomar
5. Actualizar producto.stock y verificar suma de lotes.
6. Registrar detalle de venta/atención y caja.
```

### 5.2. SQL de selección

```sql
SELECT li.*, pl.fecha_vencimiento, pl.codigo_lote
FROM lote_ingreso li
JOIN producto_lote pl ON pl.id = li.producto_lote_id
WHERE pl.producto_id = :producto
  AND pl.estado = 'disponible'
  AND pl.fecha_vencimiento >= CURRENT_DATE
  AND li.cantidad_disponible_base > 0
ORDER BY pl.fecha_vencimiento ASC, li.created_at ASC, li.id ASC
FOR UPDATE;
```

### 5.3. Reglas

- **Nunca vender lotes vencidos.** Si solo hay vencidos, la venta se rechaza y se debe dar de baja.
- Un lote vence al final del día de `fecha_vencimiento`; definir si se permite vender ese día.
- Si dos lotes vencen el mismo día, consumir primero el recibido antes (`created_at`, `id`).
- Una línea puede consumir varias capas; registrar todas.
- La presentación de venta no cambia el FEFO: solo define cuántas unidades base se requieren.
- Si el producto tiene stock `producto.stock` pero no lotes, **no se puede vender**; hay que regularizar lotes.
- Los combos que descuentan stock deben consumir sus componentes con el mismo servicio FEFO.

### 5.4. Concurrencia

- Bloquear producto con `lockForUpdate()`.
- Bloquear capas antes de leer cantidades.
- Ordenar bloqueos por `producto_id`, `fecha_vencimiento`, `id` para evitar deadlocks.
- Validar y consumir dentro de la misma transacción; no confiar en la validación previa del FormRequest.

---

## 6. Reversión exacta

Al editar o eliminar una venta/atención:

1. Leer `lote_consumo` por `referencia_tipo` + `referencia_id`.
2. Bloquear las capas afectadas.
3. Devolver `cantidad_base` a cada `lote_ingreso`.
4. Actualizar `producto_lote.cantidad_disponible_base`.
5. Actualizar `producto.stock`.
6. Eliminar los `lote_consumo` de esa referencia.
7. Eliminar/regenerar kardex.
8. Si es edición, volver a ejecutar FEFO con las nuevas líneas.

Ventaja: la reversión es exacta porque se guardó qué capa consumió cada operación. No se “adivina” el lote.

---

## 7. Vencimientos, bajas y alertas

### 7.1. Estados

| Estado | Regla |
|---|---|
| `disponible` | `fecha_vencimiento >= hoy` y `cantidad_disponible > 0` |
| `agotado` | `cantidad_disponible = 0` |
| `vencido` | `fecha_vencimiento < hoy` y queda cantidad |
| `bloqueado` | Cuarentena manual, no vendible |
| `baja` | Retirado del inventario |

### 7.2. Alertas

- **Vencido**: fecha < hoy y disponible > 0.
- **Crítico**: vence hoy o en los próximos 7 días.
- **Próximo**: 8–30 días.
- **Vigente**: más de 30 días.

Sidebar: **Inventario → Lotes / Vencimientos**.
Filtros: producto, lote, estado, rango de fechas, proveedor.

### 7.3. Baja de vencidos

No basta con poner `cantidad = 0`. Debe ser un movimiento auditado:

```text
lote_consumo:
  referencia_tipo = Baja
  referencia_id = id de la baja
  cantidad_base = cantidad retirada

kardex:
  tipo = BAJA
  cantidad = -cantidad_base
  observacion = "Baja por vencimiento"
```

El kardex ahora admite `INGRESO`, `VENTA`, `ATENCION`, `AJUSTE`, `BAJA` y `DEVOLUCION`.

### 7.4. Devolución a proveedor

Mismo patrón que la baja, con referencia `Devolucion` y kardex de salida. No reutilizar “eliminar ingreso”.

---

## 8. Costos y reportes

### 8.1. Costo real

Con `lote_ingreso.costo_unitario_base` se puede calcular:

```text
costo_venta_linea = sum(cantidad_consumida_base × costo_unitario_base de cada capa)
```

Agregar `costo_total` a `detalle_venta`/`atencion_producto` o calcularlo desde `lote_consumo`.

Esto permite:

- margen real por venta;
- costo de inventario por lote;
- valorización a costo real, no costo maestro.

### 8.2. Reportes nuevos

- Inventario por lote: producto, lote, vencimiento, disponible, costo, estado. La columna de disponible muestra la cantidad base y su equivalencia en presentaciones.
- Vencimientos: vencidos, críticos, próximos, vigentes.
- Kardex por lote.
- Ventas con lotes consumidos.
- Costo de ventas y margen.

### 8.3. Reportes existentes

- Inventario: stock = suma de lotes.
- Kardex: mostrar lote/vencimiento.
- Ventas/atenciones: detalle de lotes consumidos.
- Dashboard: tarjeta “lotes por vencer”.

---

## 9. Migración y datos existentes

### 9.1. Sin datos productivos

Si el sistema sigue local y sin datos reales:

1. Reescribir migraciones originales con el esquema nuevo.
2. Actualizar seeders para crear lotes de demostración.
3. `php artisan migrate:fresh --seed`.
4. No hace falta backfill.

### 9.2. Con datos existentes

1. Crear tablas/columnas nuevas (nullable).
2. Por cada producto con `stock > 0`, crear un lote inicial:
   - `codigo_lote = 'SIN-LOTE'`;
   - `fecha_vencimiento = '2099-12-31'`;
   - `cantidad_disponible_base = producto.stock`;
   - `estado = 'disponible'`;
   - una capa `lote_ingreso` con costo `producto.precio_compra`.
3. Marcar ese lote como `es_inicial = true` para reportes.
4. Los detalles históricos quedan sin lote; se reportan como “sin lote”.
5. Antes de activar FEFO, hacer inventario físico por lote y reemplazar el lote inicial.
6. Activar la funcionalidad con bandera `inventario.lotes` por producto/categoría.

> Un lote “SIN-LOTE” permite arrancar, pero no es trazabilidad real. Para una botica, el inventario inicial debe levantarse por lote y vencimiento. Ver [`plan-inventario-inicial-d1.md`](plan-inventario-inicial-d1.md).

---

## 10. Roadmap por fases

| Fase | Entregable | Pruebas |
|---|---|---|
| 0 | Definir política: lote único, vencimiento, costo, edición de ingresos, bajas | Revisión con negocio |
| 1 | Tablas `producto_lote`, `lote_ingreso`, `lote_consumo`; columnas en detalle/kardex | Migraciones y modelos |
| 2 | Ingreso con lote, vencimiento y costo | Crear/editar/eliminar ingreso |
| 3 | Servicio FEFO y consumo en ventas/atenciones | FEFO, multi-lote, stock insuficiente |
| 4 | Reversión exacta y bloqueo de edición de ingresos consumidos | Editar/eliminar venta e ingreso |
| 5 | Alertas, bajas y reportes | Vencidos, críticos, bajas |
| 6 | Costos reales y margen | COGS por lote |
| 7 | Backfill, bandera, piloto y capacitación | Inventario físico |

---

## 11. Pruebas manuales clave

1. Crear lote A (vence en 10 días) y lote B (vence en 60 días) del mismo producto.
2. Vender 1 caja; verificar que consume A antes que B.
3. Vender una cantidad que supere A; verificar que consume A y parte de B, con dos `lote_consumo`.
4. Intentar vender un lote vencido: debe rechazar.
5. Intentar vender más que la suma de lotes: debe rechazar y no crear venta/caja.
6. Editar venta: debe devolver exactamente A/B y volver a consumir.
7. Eliminar venta: debe restaurar lotes y stock.
8. Editar ingreso ya consumido: debe bloquearse.
9. Dar de baja un lote vencido: stock y kardex deben bajar.
10. Verificar `producto.stock == suma(lotes disponibles)` después de cada operación.
11. Probar con presentaciones: 2 cajas = 200 base; 5 tabletas = 5 base.
12. Probar concurrencia: dos ventas simultáneas no deben consumir el mismo lote.

---

## 12. Decisiones que hay que cerrar

1. ¿Un lote se identifica por producto+código+vencimiento, o por cada recepción?
2. ¿Se permite editar/eliminar un ingreso con consumo? Recomendado: **no**.
3. ¿Los lotes vencidos se pueden vender el mismo día de vencimiento? Definir.
4. ¿Se exige costo por ingreso o se mantiene costo maestro?
5. ¿Se manejan devoluciones a proveedor y bajas por vencimiento?
6. ¿Se activa FEFO para todos los productos o solo para los que tienen lote?
7. ¿Qué hacer con productos sin lote: bloquear venta o usar lote “SIN-LOTE”?

---

## 13. Impacto concreto en el código actual

| Archivo actual | Cambio |
|---|---|
| `app/Services/InventarioService.php` | Reemplazar consumo directo por FEFO con lotes y capas |
| `app/Helpers/KardexHelper.php` | Aceptar lote, vencimiento y costo |
| `app/Http/Controllers/Admin/IngresoController.php` | Crear lote/capa, validar vencimiento, bloquear edición consumida |
| `app/Http/Requests/Admin/StoreIngresoRequest.php` | `lote`, `fecha_vencimiento`, costo |
| `VentaController` / `AtencionController` | Consumir FEFO y guardar `lote_consumo` |
| `resources/views/admin/ingresos/*` | Campos de lote/vencimiento/costo |
| `resources/views/admin/ventas/show*` / `atenciones/show*` | Mostrar lotes consumidos |
| `ReporteService` | Inventario por lote, vencimientos, costo |
| `routes/admin.php` | Rutas de lotes/vencimientos |
| `Permisos` | `lotes` y `vencimientos` |

---

## 14. Recomendación final

No reutilizar `StockHelper` ni el esquema anterior sin cambios. Implementar la **v2** con:

1. lote físico + capa de recepción;
2. cantidades en unidad base;
3. FEFO con bloqueo y exclusión de vencidos;
4. consumo exacto por capa y reversión exacta;
5. ingresos inmutables después del consumo;
6. costos por recepción;
7. alertas y bajas auditadas;
8. conciliación `producto.stock == suma(lotes)`.

Es la única forma de tener lotes y FEFO **correctos y precisos** para una botica real, y no solo un descuento de stock con una etiqueta de lote.

---

*Documento de reevaluación. Para implementar, crear las migraciones/modelos/servicios descritos y actualizar `arquitectura-y-stack.md`, `funcionalidades-del-sistema.md` y el índice de `docs/`.*
