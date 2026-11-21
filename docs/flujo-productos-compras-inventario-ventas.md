# Flujo de Productos, Compras, Inventario y Ventas

**Sistema de referencia:** `carwash_sistema` (Laravel 12 / PHP 8.2 / MySQL o SQLite).
**Fecha de análisis:** 2026-11-19.
**Fuente de verdad:** código actual en `app/`, `database/migrations/`, `resources/views/`, `resources/js/` y specs en `.kiro/specs/`.
**Objetivo:** documentar el flujo real —no el ideal— para poder modelar y portar el módulo completo a otro sistema.

---

## 0. Resumen ejecutivo

1. **No existe un CRUD de "inventario"**. El inventario es el resultado de operaciones: compras recibidas, ventas, cambios de aceite, devoluciones y ajustes. Todas escriben en el libro mayor `movimientos_kardex`.
2. **El alta de un producto nace con `stock = 0` e `inventario = 0`**. No se ingresa cantidad en el formulario de producto. La existencia se construye recibiendo compras o con ajustes.
3. **La compra es un documento con estados** (`borrador → recibida → anulada`). El borrador no toca stock ni caja. La **recepción** es la operación que ingresa mercadería, actualiza costos, registra Kardex y genera el egreso de caja.
4. **`stock` e `inventario` no son lo mismo**: `stock` es la existencia actual; `inventario` es la línea base del ciclo vigente (stock al último abastecimiento/ajuste), usada solo para calcular la alerta de consumo del 75 %.
5. **Toda operación que mueve dinero exige una caja abierta** (`cajas.estado = 'abierta'`). Sin caja, la recepción de compra y la venta se rechazan con el flash `error_caja`.
6. **El servidor nunca confía en los totales del formulario**: recalcula líneas y totales con `round(..., 2)`.
7. **Las operaciones críticas son transaccionales** y bloquean productos en orden ascendente por `producto_id` para evitar deadlocks.
8. **El Kardex es append-only**: no se edita ni se borra; las correcciones se hacen con movimientos compensatorios.
9. **La venta descuenta `stock`, pero no toca `inventario`**. La recepción de compra y los ajustes sí reinician `inventario`.
10. Hay **deuda técnica real** (columnas referenciadas que no existen, costos no enviados al Kardex, rutas sin método, textos de UI que contradicen el código). Está listada en la sección 15 para no replicarla al portar.

---

## 1. Conceptos y diferencia entre `stock` e `inventario`

### 1.1 Glosario operativo

| Término | Significado en este sistema |
|---|---|
| **Producto** | Ítem del catálogo. Puede ser `comercial` (se vende / se usa en servicios) o `consumo_interno` (insumo de operación; no se vende). |
| **Stock** | Unidades disponibles **ahora**. Es la cifra que se descuenta al vender y se incrementa al recibir compras, devoluciones o ajustes positivos. |
| **Inventario** (columna) | Unidades de referencia del **ciclo vigente**: el stock que había en el último abastecimiento o ajuste. No es un almacén ni un valor monetario. |
| **Kardex** | Libro mayor de movimientos (`movimientos_kardex`): entrada/salida, fuente, origen, stock antes/después, costo. |
| **Compra** | Documento a proveedor. Nace en borrador; al **recibirse** genera el ingreso real de mercadería. |
| **Recepción** | Acción `compras.recibir`: única transición que ingresa existencias, registra Kardex, actualiza costo y paga por caja. |
| **Ajuste de inventario** | Documento formal para corregir existencias (conteo, daño, consumo interno, corrección positiva/negativa). |
| **Venta** | Ticket de productos comercializables. Descuenta stock, registra pago y Kardex. |
| **Devolución / cambio** | Reincorpora stock de una venta y, si es cambio, genera una venta sustituta. |
| **Caja** | Sesión de caja abierta. Requisito para compras recibidas, ventas y devoluciones. |
| **CPP** | Costo promedio ponderado. El sistema lo referencia, pero **la columna no existe** en el esquema actual (ver §15). |

### 1.2 Diferencia conceptual

| Pregunta | `stock` | `inventario` |
|---|---|---|
| ¿Qué representa? | Existencia física/lógica actual. | Base del ciclo para medir consumo. |
| ¿Quién lo mueve? | Compras recibidas (+), ventas (−), devoluciones (+), ajustes (+/−), anulación de venta (+), anulación de compra recibida (−), cambio de aceite confirmado (−), modal provisional (+). | Solo se **reinicia** al recibir una compra, al aplicar un ajuste, al usar el modal provisional o al anular una compra recibida. |
| ¿Se vende con él? | Sí: la venta valida y descuenta `stock`. | No. Nunca se usa para validar una venta. |
| ¿Se usa en reportes de valor? | Sí: valorizado = `stock × precio_compra`. | No. |
| ¿Genera alerta? | Agotado si `stock = 0`. | Base del cálculo: `consumido = inventario − stock`. |
| ¿Es editable desde el formulario de producto? | No. | No. |
| ¿Puede ser negativo? | El flujo normal no lo permite (venta valida stock; ajuste exige ≥ 0; anulación de compra valida). No hay CHECK de BD. | No en el flujo normal; tampoco hay CHECK de BD. |

### 1.3 Fórmula de alerta vigente

```text
consumido = inventario - stock
alerta_75 = inventario > 0 AND consumido >= ceil(inventario * 0.75)
agotado   = stock <= 0
```

Implementación real (`Producto::getEstaEnAlertaAttribute`):

```php
if ($this->inventario <= 0) return false;
$consumido = $this->inventario - $this->stock;
return $consumido >= (int) ceil($this->inventario * 0.75);
```

Estados de UI (`Producto::getEstadoAlertaAttribute`):

| Estado | Condición | Nota |
|---|---|---|
| `agotado` | `stock === 0` | Prevalece sobre los demás. |
| `alerta` | `stock > 0 && stock_minimo > 0 && stock <= stock_minimo` | **Nunca se activa hoy**: `stock_minimo` no existe en la tabla. |
| `alerta_legacy` | consumo ≥ 75 % del `inventario` | Es la alerta realmente operativa. |
| `normal` | resto | — |

Filtros de stock (`Producto::scopeConStock`):

```text
sin_stock: stock <= 0
bajo:      stock > 0 AND inventario > 0 AND (inventario - stock) * 4 >= inventario * 3
normal:    stock > 0 AND (inventario <= 0 OR (inventario - stock) * 4 < inventario * 3)
```

La comparación entera `(inventario - stock) * 4 >= inventario * 3` equivale a `consumido >= ceil(inventario * 0.75)` sin depender de `CEIL`.

### 1.4 Qué evento reinicia `inventario` y cuál no

| Evento | `stock` | `inventario` | Efecto sobre la alerta |
|---|---|---|---|
| Alta de producto | 0 | 0 | Sin ciclo; no alerta. |
| Recepción de compra | `+ cantidad` | **= stock resultante** | Reinicia el ciclo. |
| Ajuste de inventario | **= stock final** | **= stock final** | Reinicia el ciclo. |
| Modal "Actualizar stock" (provisional) | `+ cantidad` | **= stock resultante** | Reinicia el ciclo. |
| Venta | `− cantidad` | sin cambio | Aumenta el consumo del ciclo. |
| Devolución de venta | `+ cantidad` | sin cambio | Reduce el consumo; puede dejar `stock > inventario`. |
| Cambio de aceite confirmado | `− cantidad` | sin cambio | Aumenta el consumo. |
| Anulación de venta | `+ cantidad` | sin cambio | Reduce el consumo. |
| Anulación de compra recibida | `− cantidad` | **= stock resultante** | Reinicia el ciclo hacia abajo. |

> **Consecuencia importante para portar:** el `inventario` no es acumulativo. Si hay 10 unidades de un ciclo de 100 y llegan 5, el nuevo `inventario` es 15, no 105. El ciclo anterior se descarta.

### 1.5 Ejemplo numérico

```text
Producto recibe 100 unidades:
  stock = 100, inventario = 100

Vende 75:
  stock = 25, inventario = 100
  consumido = 75; ceil(100 * 0.75) = 75 → alerta = true

Vende 1 más:
  stock = 24, inventario = 100
  consumido = 76 → alerta = true

Llega una compra de 50:
  stock = 74, inventario = 74   ← reinicio del ciclo
  consumido = 0 → alerta = false
```

---

## 2. Arquitectura funcional y navegación

### 2.1 Módulos reales

| Módulo | Pantallas / rutas | Permiso |
|---|---|---|
| Catálogo | `productos.*`, `categorias.*`, `marcas.*` | `acceso-inventario` |
| Proveedores | `proveedores.*` | `acceso-proveedores` |
| Compras | `compras.*` (+ `recibir`, `anular`, `anular-recibida`) | `acceso-compras` |
| Ajustes | `ajustes.*` | `acceso-ajustes` |
| Ventas | `ventas.*`, `ventas.devoluciones.*`, `devoluciones.*` | `acceso-ventas` |
| Caja | `caja.*` | `acceso-caja`, `gestionar-caja`, `registrar-egresos-caja`, `ver-resumen-caja`, `historial-caja` |
| Kardex | `kardex.index`, `kardex.porProducto` | `acceso-auditoria` |
| Reportes | `reportes.inventario`, `reportes.kardex`, `reportes.comprasProveedor`, etc. | `acceso-auditoria` |

### 2.2 Menú lateral (orden actual)

```text
Gestión de productos
  ├─ Productos
  ├─ Categorías
  └─ Marcas
Gestión administrativa
  ├─ Proveedores
  ├─ Ajustes          ← dentro del @can('acceso-proveedores')
  └─ Compras          ← dentro del @can('acceso-proveedores')
Auditoría
  └─ Kardex
Reportes
  ├─ Inventario
  ├─ Kardex
  └─ Compras por proveedor
```

> Quirk de UI: Ajustes y Compras están anidados bajo `@can('acceso-proveedores')` en `layouts/app.blade.php`; un usuario con `acceso-compras` pero sin `acceso-proveedores` no ve el enlace. Al portar, separar los `@can`.

### 2.3 Diagrama de dependencias

```text
Categoría ─┐
Marca ─────┼──> Producto ──> Detalle de compra ──> Compra ──> Egreso de caja
           │         │                                │
           │         ├──> Detalle de venta ──> Venta ──> Pago
           │         │                        │
           │         │                        └──> Devolución / Cambio
           │         │
           │         ├──> Movimiento de Kardex (append-only)
           │         │
           │         └──> Detalle de ajuste ──> Ajuste de inventario
           │
Proveedor ─┴──> Compra

Caja ──> Compra recibida / Venta / Devolución / Egreso
```

---

## 3. Modelo de datos

### 3.1 `productos`

| Columna | Tipo | Nulo | Default | Regla / uso |
|---|---|---|---|---|
| `id` | bigint unsigned PK | no | — | — |
| `nombre` | varchar(150) | no | — | Obligatorio; regex que bloquea `< > " ' \`` y controles. |
| `descripcion` | varchar(100) | sí | NULL | Alfanumérico + `% + - . , ( ) / &` y espacios. |
| `precio_compra` | decimal(10,2) | no | — | Se persiste `0` cuando se desconoce. Se actualiza en la recepción de compra. |
| `precio_venta` | decimal(10,2) | sí | NULL | Obligatorio si `destino = comercial` y `> 0`; NULL para consumo interno. |
| `stock` | int | no | 0 | Existencia actual. Nunca se edita desde el form de producto. |
| `inventario` | int | no | 0 | Base del ciclo de alerta. |
| `categoria_id` | FK `categorias` | no | — | Obligatorio. |
| `marca_id` | FK `marcas` | no | — | Obligatorio. |
| `precio_incoherente` | tinyint(1) | no | 0 | `precio_compra > precio_venta` con ambos > 0. |
| `activo` | tinyint(4) | no | 1 | Un producto inactivo no se puede recibir en compra ni vender. |
| `destino` | varchar(20) | no | `'comercial'` | `comercial` \| `consumo_interno`. |
| `foto` | text | sí | NULL | URL Cloudinary o ruta local. |
| `created_at`, `updated_at` | timestamp | sí | NULL | Zona horaria `America/Lima`. |

> **No existen** en la tabla: `stock_minimo`, `costo_promedio_ponderado`. El modelo y un servicio los referencian (ver §15).

### 3.2 `categorias` y `marcas`

| `categorias` | Tipo | Nota |
|---|---|---|
| `id` | PK | — |
| `nombre` | varchar(150) | Único; solo letras y espacios simples. |
| `slug` | varchar(160) | Generado automáticamente desde `nombre` en `saving`. |
| `descripcion` | text | Alfanumérico. |
| `contador_productos` | int unsigned | Se incrementa/decrementa al crear/editar/borrar producto. |

| `marcas` | Tipo | Nota |
|---|---|---|
| `id` | PK | — |
| `nombre` | varchar(150) | — |
| `descripcion` | text | — |
| `foto` | varchar(255) | — |

### 3.3 `proveedores`

| Columna | Tipo | Nulo | Default | Regla |
|---|---|---|---|---|
| `id` | PK | no | — | — |
| `ruc` | char(11) | no | — | 11 dígitos, empieza con `20`, único. |
| `razon_social` | varchar(150) | no | — | Letras, números y signos básicos. |
| `direccion` | varchar(200) | sí | NULL | — |
| `estado_tributario` | varchar(100) | sí | NULL | Solo letras y espacios. |
| `condicion` | varchar(100) | sí | NULL | — |
| `estado` | char(1) | no | `'1'` | `1` activo, `0` inactivo. Solo los activos aparecen en compras. |

### 3.4 `compras` (cabecera)

| Columna | Tipo | Nulo | Default | Nota |
|---|---|---|---|---|
| `id` | PK | no | — | — |
| `correlativo` | varchar(20) | sí | NULL | Único. `CMP-####`; NULL mientras es borrador. |
| `proveedor_id` | FK `proveedores` | no | — | `ON DELETE RESTRICT`. |
| `fecha` | date | no | — | Fecha de recepción/documento. En UI es `readonly` con la fecha de hoy. |
| `tipo_documento` | varchar(20) | no | — | Factura, Boleta, Ticket, Nota de venta, Guía de remisión, Otros. |
| `numero_documento` | varchar(50) | no | — | Obligatorio desde la migración `2026_10_24`. |
| `estado` | enum | no | `borrador` | `borrador` \| `recibida` \| `anulada`. |
| `subtotal` | decimal(10,2) | no | 0 | Suma de líneas, calculada en servidor. |
| `total` | decimal(10,2) | no | 0 | Igual al subtotal (sin impuestos ni descuentos). |
| `observaciones` | text | sí | NULL | Máx. 500 en validación. |
| `user_id` | FK `users` | no | — | Creador. |
| `caja_id` | FK `cajas` | sí | NULL | Se llena al recibir. |
| `egreso_caja_id` | FK `egresos_caja` | sí | NULL | Se llena al recibir. |
| `fecha_recepcion` | timestamp | sí | NULL | Se llena al recibir. |
| `fecha_anulacion` | timestamp | sí | NULL | Se llena al anular una recibida. |
| `created_at`, `updated_at` | timestamp | sí | NULL | — |

Índices: `estado`, `fecha`, `proveedor_id`; único en `correlativo`.

### 3.5 `detalle_compras`

| Columna | Tipo | Nulo | Default | Nota |
|---|---|---|---|---|
| `id` | PK | no | — | — |
| `compra_id` | FK `compras` | no | — | `ON DELETE CASCADE`. |
| `producto_id` | FK `productos` | no | — | `ON DELETE RESTRICT`. |
| `cantidad` | int | no | 1 | Entero ≥ 1, máx. 999999. |
| `costo_unitario` | decimal(10,2) | no | 0 | ≥ 0, máx. 9 999 999.99. |
| `subtotal` | decimal(10,2) | no | 0 | `cantidad × costo_unitario`, calculado en servidor. |
| `destino` | varchar(20) | sí | NULL | `comercial` \| `consumo_interno`; NULL en compras históricas. |
| `created_at`, `updated_at` | timestamp | sí | NULL | — |

Restricción clave: **`UNIQUE(compra_id, producto_id)`**. Evita duplicar el mismo producto en una compra y el doble conteo al recibir.

### 3.6 `movimientos_kardex` (libro mayor)

| Columna | Tipo | Nulo | Default | Nota |
|---|---|---|---|---|
| `id` | PK | no | — | — |
| `producto_id` | FK `productos` | no | — | `ON DELETE CASCADE`. |
| `tipo` | enum | no | — | `entrada` \| `salida`. |
| `fuente` | varchar(30) | no | — | Catálogo en `KardexService::FUENTES` (ver §4). |
| `origen_id` | varchar(20) | no | — | Correlativo o identificador del documento origen. **No es FK.** |
| `cantidad` | int | no | — | Siempre positiva; el signo lo da `tipo`. |
| `stock_antes` | int | no | — | Estado antes del movimiento. |
| `stock_despues` | int | no | — | Estado después del movimiento. |
| `costo_unitario` | decimal(10,2) | sí | NULL | Frecuentemente 0 por deuda técnica (ver §15). |
| `costo_total` | decimal(12,2) | sí | NULL | `cantidad × costo_unitario`. |
| `usuario_id` | FK `users` | no | — | Usuario autenticado (`auth()->id()`). |
| `fecha_movimiento` | timestamp | no | CURRENT_TIMESTAMP | Generado con `now()` (hora Perú). |
| `created_at`, `updated_at` | timestamp | sí | NULL | — |

Índices: `origen_id`, `fecha_movimiento`. En PostgreSQL existe un `CHECK` sobre `fuente` con la misma lista del catálogo.

> **Invariante del libro:** a nivel de aplicación el Kardex no se actualiza ni se borra. La única escritura es `INSERT` mediante `KardexService::registrar()`. El stock lo muta el llamador **antes** de registrar; el servicio solo materializa el antes/después.
>
> **Excepción técnica:** `movimientos_kardex.producto_id` tiene `ON DELETE CASCADE`. Si se elimina un producto que no está referenciado por compras/ventas, sus movimientos se borran con él. Para un Kardex estrictamente inmutable, usar `RESTRICT` y prohibir el borrado físico del producto (baja lógica con `activo = 0`).

### 3.7 `ajustes_inventario` y `detalle_ajustes_inventario`

`ajustes_inventario`:

| Columna | Tipo | Nulo | Nota |
|---|---|---|---|
| `id` | PK | no | — |
| `correlativo` | varchar(20) | no | Único. `AJU-####`. |
| `tipo` | varchar(20) | no | `positivo`, `negativo`, `daño`, `conteo_fisico`, `consumo_interno`. |
| `motivo` | text | sí | Obligatorio en servidor solo para `consumo_interno`. |
| `observaciones` | text | sí | — |
| `user_id` | FK `users` | no | — |
| `created_at`, `updated_at` | timestamp | sí | — |

`detalle_ajustes_inventario`:

| Columna | Tipo | Nota |
|---|---|---|
| `ajuste_id` | FK `ajustes_inventario` | `ON DELETE CASCADE`. |
| `producto_id` | FK `productos` | `ON DELETE RESTRICT`. |
| `cantidad` | int | **Firmada**: `+` entrada, `−` salida. |
| `stock_antes` | int | — |
| `stock_despues` | int | Igual al stock final declarado. |

### 3.8 `ventas`, `detalle_ventas`, pagos y devoluciones

`ventas`:

| Columna | Tipo | Nulo | Nota |
|---|---|---|---|
| `id` | PK | no | — |
| `correlativo` | varchar(255) | no | `VTA-####`; **sin unique** en BD. |
| `observacion` | text | sí | Máx. 500. |
| `subtotal` | decimal(10,2) | no | Suma de líneas. |
| `total` | decimal(10,2) | no | Puede ser menor por descuento; tope `suma × 1.18 + 0.01`. |
| `user_id` | FK `users` | no | — |
| `caja_id` | FK `cajas` | sí | Se asigna al crear. |

`detalle_ventas`: `venta_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal`. **No tiene** `UNIQUE(venta_id, producto_id)`.

`pagos`: tabla polimórfica (`pagable_type`, `pagable_id`) con `metodo_pago_id`, `monto`, `user_id`, `caja_id`. 1..N pagos por venta; la suma debe igualar el total.

`devoluciones_venta`: `correlativo` (`DEV-####`), `venta_id`, `venta_nueva_id`, `caja_id`, `tipo` (`devolucion` \| `cambio`), `motivo`, `total_devuelto`, `user_id`.

`detalle_devoluciones_venta`: `devolucion_id`, `detalle_venta_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal`.

### 3.9 `cajas` y `egresos_caja`

`cajas`: `id`, `user_id`, `estado` (`abierta` \| `cerrada`), `monto_inicial`, `fecha_apertura`, `fecha_cierre`, campos de arqueo (`efectivo_esperado`, `efectivo_contado`, `diferencia`, `arqueo_user_id`, `fecha_arqueo`, `observacion_arqueo`).

`egresos_caja`: `id`, `caja_id`, `monto`, `descripcion`, `tipo_pago` (`efectivo`, `yape`, `transferencia`, `tarjeta`), `categoria` (ej. `compra_abastecimiento`), `trabajador_id`, `user_id`.

> El sistema usa **monto negativo** en `egresos_caja` para representar una nota de crédito/ingreso compensatorio. Es un hack funcional: el balance suma todos los egresos y el negativo compensa.

---

## 4. Catálogo de fuentes del Kardex

`KardexService::FUENTES` es la única fuente de verdad para UI, validación y (en PostgreSQL) el `CHECK`:

| Valor | Etiqueta UI | Quién lo genera | `origen_id` típico |
|---|---|---|---|
| `venta` | Venta | Venta (salida) y anulación de venta (entrada) | `VTA-0001` |
| `compra` | Compra | Recepción de compra (entrada) | `CMP-0001` |
| `cambio_aceite` | Cambio de aceite | Confirmación/anulación de cambio de aceite | Placa del vehículo |
| `inventario` | Inventario | Modal provisional "Actualizar stock" | `INV-0001` |
| `ajuste_positivo` | Ajuste positivo | Ajuste tipo `positivo` | `AJU-0001` |
| `ajuste_negativo` | Ajuste negativo | Ajuste tipo `negativo` y **anulación de compra recibida** | `AJU-0001` / `CMP-0001` |
| `daño` | Daño | Ajuste tipo `daño` | `AJU-0001` |
| `conteo_fisico` | Conteo físico | Ajuste tipo `conteo_fisico` | `AJU-0001` |
| `devolucion` | Devolución | Devolución de venta (entrada) y venta sustituta de un cambio (salida) | `DEV-0001` |
| `consumo_interno` | Consumo interno | Ajuste tipo `consumo_interno` | `AJU-0001` |

Reglas:

- Una fuente desconocida lanza `InvalidArgumentException` al registrar.
- En el listado, una fuente desconocida o vieja se trata como "sin filtro" en lugar de romper.
- `origen_id` **no es único**: una compra con 5 productos genera 5 filas con el mismo `CMP-####`; una venta con 3 productos, 3 filas con el mismo `VTA-####`.
- No hay FK desde el Kardex al documento; la relación es lógica por `origen_id`. Para unir un ajuste con sus movimientos: `origen_id = ajustes_inventario.correlativo`.

---

## 5. Flujo completo de Compras e ingreso a inventario

### 5.1 Diagrama de estados real

```text
                 crear
                   │
                   ▼
              ┌──────────┐   editar / borrar / anular    ┌───────────┐
              │ borrador │ ────────────────────────────> │  anulada  │
              └────┬─────┘                               └───────────┘
                   │ recibir                                (terminal)
                   │  - exige caja abierta
                   │  - ingresa stock/inventario
                   │  - Kardex entrada
                   │  - egreso de caja
                   ▼
              ┌──────────┐   anular-recibida (reversión)  ┌───────────┐
              │ recibida │ ────────────────────────────> │  anulada  │
              └──────────┘   - salida compensatoria       └───────────┘
                             - nota de crédito
                             - exige stock suficiente
                             - exige caja abierta
```

La constante `CompraController::TRANSICIONES` solo declara `borrador → recibida|anulada`; la anulación de recibida se implementa en un endpoint separado (`anularRecibida`). No existe vuelta a borrador.

### 5.2 Orden de pantallas

```text
1. Compras → Listado (filtros estado/proveedor)
2. Compras → Nueva compra (formulario)
3. Guardar borrador → Detalle de compra
4. Detalle → Editar (solo borrador) → Actualizar
5. Detalle → Recibir (confirmación) → Recepción
6. Detalle → Anular (solo borrador) o Anular con reversión (recibida)
7. Kardex → filtrar por origen CMP-#### para auditar
8. Reportes → Compras por proveedor
```

### 5.3 Formulario "Nueva compra"

**Archivos:** `resources/views/compras/create.blade.php`, `resources/js/compras/create.js`, `buscar-proveedores.js`, `buscar-productos.js`.

Campos de cabecera:

| Campo | Comportamiento |
|---|---|
| Proveedor | Autocomplete por razón social o RUC, debounce 300 ms, mínimo 2 caracteres, `GET /compras/buscar-proveedores?q=`. Guarda `proveedor_id` en hidden. Solo proveedores activos. |
| Fecha | `<input type="date" readonly>` con `now()->format('Y-m-d')`. El servidor igual exige fecha válida. |
| Tipo de documento | Select obligatorio: Factura, Boleta, Ticket, Nota de venta, Guía de remisión, Otros. |
| N.º documento | Texto obligatorio, máx. 50. |
| Observaciones | Opcional, máx. 500. |

Detalle dinámico:

| Columna | Comportamiento |
|---|---|
| Producto | Autocomplete por nombre, `GET /compras/buscar-productos?q=`. Muestra `Stock: N \| Costo: S/ X`. Solo productos activos. Al seleccionar, si la línea no tiene costo, sugiere `precio_compra`. |
| Destino | Select por línea: `Comercialización / Operación` o `Consumo interno`. Default `comercial`. |
| Cantidad | Entero ≥ 1. |
| Costo unitario | Numérico ≥ 0. Si es 0, la recepción **no** sobrescribe el `precio_compra` del producto. |
| Subtotal | `cantidad × costo`, calculado en JS y recalculado en servidor. |
| Eliminar | Quita la línea. |

Totales:

- `subtotal` y `total` son inputs `readonly`.
- JS los actualiza en tiempo real (`calcularTotal`, `calcularSubtotal` con `toFixed(2)`).
- El servidor **ignora** esos valores y recalcula con `round(..., 2)`.

Validación cliente:

- Al menos una fila.
- Cada fila: producto seleccionado, cantidad ≥ 1, costo > 0.
- Si falla, muestra `#error-detalle` y bloquea el submit.

Botón principal: **Guardar borrador** (no "Guardar y recibir"). La recepción es un paso separado.

### 5.4 `POST /compras` — crear borrador

Secuencia real (`CompraController::store`):

```text
1. Validar cabecera y detalle.
2. DB::transaction:
   a. prepararLineas():
      - castea cantidad a int, costo a float
      - subtotal línea = round(cantidad * costo, 2)
      - destino = línea o 'comercial' si no pertenece al catálogo
   b. subtotal = round(sum(subtotales), 2)
   c. AuditService::anotarAccion('crear borrador')
   d. Compra::create({
        correlativo: null,
        proveedor_id, fecha, tipo_documento, numero_documento,
        estado: 'borrador',
        subtotal, total: subtotal,
        observaciones, user_id
      })
   e. DetalleCompra::create por cada línea
3. Redirect a compras.show con mensaje:
   "Compra registrada como borrador. Recíbela para ingresar las existencias."
```

Efectos: **ninguno** sobre stock, inventario, Kardex ni caja. Solo cabecera + líneas + auditoría.

### 5.5 `GET /compras/{compra}` — detalle

Muestra:

- Correlativo (o "Sin asignar (borrador)").
- Proveedor, fecha, estado con badge (Borrador amarillo / Recibida verde / Anulada rojo).
- Tipo y número de documento.
- Subtotal y total.
- Observaciones, creador, fecha de creación, fecha de recepción.
- Líneas con producto, destino, cantidad, costo unitario, subtotal.
- Acciones según estado:
  - Borrador: **Recibir**, **Editar**.
  - Recibida: **Anular (con reversión)**.
  - Anulada: solo lectura.
- Modal "Caja cerrada" si llega con `error_caja`.

Confirmaciones (SweetAlert2 vía `data-confirm`):

- Recibir: advierte que ingresa existencias, registra egreso y actualiza costos; **no se puede deshacer**.
- Anular recibida: advierte salida compensatoria y nota de crédito; **irreversible**.
- Anular borrador: solo cambia estado.
- Eliminar borrador: solo borradores.

### 5.6 `POST /compras/{compra}/recibir` — recepción (núcleo del ingreso a inventario)

**Precondiciones fuera de la transacción:**

1. `compra.estado === 'borrador'`; si no, mensaje explícito de transición rechazada.
2. Existe caja abierta (`CajaService::getCajaActiva()`); si no, redirect con `error_caja` y **sin efectos**.
3. Ningún producto del detalle está inactivo; si hay inactivos, error con la lista y sin efectos.

**Transacción (todo o nada):**

```text
BEGIN
  1. Revalidar estado borrador.
     (Hoy NO se bloquea la cabecera con lockForUpdate; ver deuda #25.)
  2. correlativo = compra.correlativo
     ?? KardexService::siguienteCorrelativoCompra()
        (max(origen_id) de movimientos con fuente='compra' y origen LIKE 'CMP-%' + 1)
  3. Cargar líneas JOIN productos, ORDER BY producto_id ASC.
  4. Para cada línea:
     a. Producto::lockForUpdate()->findOrFail(producto_id)
     b. Si !producto.activo → excepción (rollback total)
     c. stock_antes = producto.stock
        stock_despues = stock_antes + cantidad
     d. producto.update(stock = stock_despues, inventario = stock_despues)
     e. Si línea.destino no vacío → producto.update(destino = línea.destino)
     f. KardexService::registrarEntradaCompra(
          producto, cantidad, stock_antes, correlativo
        )
        → INSERT movimiento: tipo=entrada, fuente=compra,
          origen_id=CMP-####, cantidad, stock_antes, stock_despues
        (hoy sin costo_unitario; ver §15)
     g. Si costo_unitario > 0:
          producto.update(precio_compra = round(costo, 2))
          si producto.precio_venta > 0 y costo > precio_venta:
             producto.update(precio_incoherente = true)
             acumular nombre para advertencia
          si no:
             producto.update(precio_incoherente = false)
  5. CajaService::registrarEgreso(caja, {
       monto: compra.total,
       descripcion: "Pago compra CMP-#### (Proveedor)",
       tipo_pago: 'efectivo',              ← hardcodeado
       categoria: 'compra_abastecimiento',
       user_id
     })
  6. AuditService::anotarAccion('recibir compra')
  7. compra.update({
       correlativo, estado: 'recibida', caja_id, egreso_caja_id,
       fecha_recepcion: now()
     })
COMMIT
```

Mensaje de éxito:

```text
"Compra recibida como CMP-0001. Stock e inventario actualizados."
+ " ⚠ Productos con costo > precio de venta: A, B" (si aplica)
```

Errores: cualquier excepción revierte stock, inventario, Kardex, precio, egreso y estado. Una segunda recepción se rechaza antes de entrar y no duplica nada.

### 5.7 Edición y borrado de borrador

`PUT /compras/{compra}`:

- Solo si `estado === 'borrador'`.
- Revalida todo.
- `DB::transaction`: actualiza cabecera, **borra todas las líneas** (`detalles()->delete()`) y las vuelve a crear.
- Audita `actualizar borrador`. Si solo cambiaron líneas y ningún atributo de cabecera, `registrarEdicionSinCambiosDeCabecera()` fuerza el registro.

`DELETE /compras/{compra}`:

- Solo borradores.
- Borra cabecera; `detalle_compras` cae por `ON DELETE CASCADE`.
- No toca Kardex ni caja porque un borrador nunca los generó.

`POST /compras/{compra}/anular` (borrador):

- Solo si la transición `borrador → anulada` está permitida.
- Cambia estado; no genera movimientos ni egresos.

### 5.8 Anulación de compra recibida (`POST /compras/{compra}/anular-recibida`)

**Esta es una reversión real, no un simple cambio de estado.**

Precondiciones:

- `estado === 'recibida'`.
- Caja abierta; si no, `error_caja`.
- Stock suficiente para descontar todas las líneas; si alguna quedaría negativo, error y rollback.

Transacción:

```text
BEGIN
  1. Compra::lockForUpdate()
  2. Verificar que sigue recibida.
  3. Líneas ORDER BY producto_id ASC.
  4. Para cada línea:
     a. Producto::lockForUpdate()
     b. stock_despues = stock_antes - cantidad
        si < 0 → RuntimeException con disponible/requerido
     c. producto.update(stock = stock_despues, inventario = stock_despues)
     d. KardexService::registrarSalidaCompensatoria(
          producto, cantidad, stock_antes, compra.correlativo
        )
        → tipo=salida, fuente='ajuste_negativo', origen_id=CMP-####
  5. CajaService::registrarIngresoCompensatorio(caja, {
       monto: compra.total,
       descripcion: "Anulación compra CMP-#### (Proveedor)",
       tipo_pago: 'transferencia',
       user_id
     })
     → INSERT egresos_caja con monto NEGATIVO
  6. Auditar 'anular compra recibida'
  7. compra.update(estado='anulada', fecha_anulacion=now())
COMMIT
```

Lo que **no** revierte:

- No devuelve `precio_compra` al valor anterior.
- No revierte `producto.destino` si la línea lo cambió.
- No elimina el egreso original; lo compensa con un ingreso negativo.
- No reutiliza el correlativo.

### 5.9 Ingreso a inventario: las tres vías

| Vía | Cuándo usarla | Efecto en Kardex | Efecto en caja | Formalidad |
|---|---|---|---|---|
| **Recepción de compra** | Abastecimiento real con proveedor/documento. | Entrada `fuente=compra`, `origen=CMP-####`. | Egreso por el total. | Formal, recomendada. |
| **Ajuste positivo / conteo** | Corrección de inventario, sobrantes, conteo físico. | Entrada `fuente=ajuste_positivo` o `conteo_fisico`, `origen=AJU-####`. | No toca caja. | Formal, documentada. |
| **Modal "Actualizar stock"** | Reposición provisional rápida desde el listado. | Entrada `fuente=inventario`, `origen=INV-####`. | No toca caja. | Provisional; la UI advierte que lo formal es Compras. |

> El texto del modal dice "sin generar compra ni entrada en Kardex", pero el controlador **sí** registra una entrada `INV-####`. Es una contradicción de UI; al portar, corregir el texto o el comportamiento.

---

## 6. Flujo de Ajustes de Inventario

### 6.1 Semántica clave: se declara el stock final

El formulario **no pide "cuánto entra" o "cuánto sale"**: pide el **stock final** que debe quedar. El servidor calcula:

```text
delta = stock_final - stock_actual
delta > 0 → entrada de |delta|
delta < 0 → salida de |delta|
delta = 0 → no genera movimiento
```

El **tipo de ajuste solo define la fuente/motivo** del Kardex; **no define el sentido**. Un ajuste `positivo` con stock final menor genera una **salida** con fuente `ajuste_positivo`. Esto es intencional y debe preservarse.

### 6.2 Formulario

**Archivos:** `resources/views/ajustes/create.blade.php`, `resources/js/ajustes/create.js`.

| Campo | Comportamiento |
|---|---|
| Tipo | `positivo`, `negativo`, `daño`, `conteo_fisico`, `consumo_interno`. |
| Motivo | Se muestra condicionalmente para `daño`, `consumo_interno` y `conteo_fisico`. El servidor solo lo exige para `consumo_interno`. |
| Observaciones | Opcional. |
| Líneas | Producto (select con stock actual), chip de movimiento (`Entrada +N` / `Salida −N` / `Sin cambio`), stock actual, **stock final**, eliminar. |
| Producto repetido | Rechazado por `distinct`. |
| Stock final | Entero entre 0 y 99 999. |

Al elegir un producto, el campo de stock final se precarga con el stock actual; el usuario lo corrige con lo contado. El chip se actualiza en tiempo real.

### 6.3 `POST /ajustes` — registro

Validación:

```text
tipo: required | in:positivo,negativo,daño,conteo_fisico,consumo_interno
motivo: nullable | string | max:500
observaciones: nullable | string | max:500
lineas: required | array | min:1
lineas.*.producto_id: required | integer | exists:productos,id | distinct
lineas.*.cantidad: required | integer | min:0 | max:99999   ← stock final
```

Luego:

```text
si tipo = consumo_interno y motivo vacío → error de validación
AjusteInventarioService::crear(data, user):
  BEGIN
    1. Normalizar líneas a {producto_id, stock_objetivo}; ordenar por producto_id ASC.
    2. Si no hay líneas → excepción.
    3. correlativo = max(AJU-#### de ajustes_inventario) + 1
    4. Auditoría 'crear ajuste'
    5. AjusteInventario::create(correlativo, tipo, motivo, observaciones, user_id)
    6. Por cada línea:
       a. Producto::lockForUpdate()
       b. Si inactivo → excepción (rollback total)
       c. Si stock_objetivo < 0 → excepción
       d. delta = stock_objetivo - stock_actual
       e. Si delta = 0 → continuar
       f. producto.update(stock = stock_objetivo, inventario = stock_objetivo)
       g. fuente = según tipo:
            positivo → ajuste_positivo
            negativo → ajuste_negativo
            daño → daño
            conteo_fisico → conteo_fisico
            consumo_interno → consumo_interno
       h. KardexService::registrar(
            producto,
            tipo = delta > 0 ? entrada : salida,
            cantidad = abs(delta),
            stock_antes,
            stock_despues = stock_objetivo,
            fuente,
            origen = AJU-####,
            costo = CostoPromedioService::obtenerCpp(producto)
          )
       i. DetalleAjusteInventario::create(
            ajuste_id, producto_id,
            cantidad = delta (firmado),
            stock_antes, stock_despues
          )
    7. Si ninguna línea cambió stock → excepción y rollback
       ("Ninguna línea cambia el stock...")
  COMMIT
```

### 6.4 Consulta

`GET /ajustes` lista con filtro por tipo y `paginate(10)`.
`GET /ajustes/{ajuste}` muestra cabecera, líneas y movimientos de Kardex vinculados por `origen_id = correlativo` (se keyean por `producto_id`).

### 6.5 Limitaciones actuales

- No hay edición, borrado ni anulación de un ajuste: es un documento inmutable. Sin embargo, el `Route::resource` registra `edit`, `update` y `destroy`, y el controlador **no implementa** esos métodos → si se invocan, error. La UI no los expone.
- El motivo obligatorio para `daño`/`conteo_fisico` solo está en el texto de la vista; el backend no lo exige.
- El texto de ayuda dice "mínimo 10 caracteres"; no se valida.

---

## 7. Flujo de Ventas

### 7.1 Precondiciones y orden

```text
1. Caja abierta (si no → error_caja)
2. Formulario:
   - buscar productos activos, destino comercial, stock > 0
   - agregar líneas, validar stock en cliente
   - método de pago simple o mixto
   - descuento porcentual opcional
3. POST /ventas
4. Detalle / ticket
5. Devolución o cambio (opcional)
6. Anulación (solo si no tiene devoluciones)
```

### 7.2 Búsqueda de productos (`GET /ventas/buscar-productos`)

Filtros obligatorios:

```text
producto.activo = true
producto.destino = 'comercial'
producto.stock > 0
nombre LIKE %q%
limit 10
```

Devuelve `id`, `nombre`, `precio_venta`, `stock`, `inventario`, `stock_bajo`.

### 7.3 `POST /ventas` — registro

Validaciones principales:

```text
observacion: nullable | string | max:500
subtotal: required | numeric | min:0
total: required | numeric | gt:0
productos: required | array | min:1
productos.*.producto_id: required | integer | exists | ProductoVendible
productos.*.cantidad: required | integer | min:1
productos.*.precio_unitario: required | numeric | gt:0
productos.*.subtotal: required | numeric | min:0
+ reglas de pago (método activo o mixto; montos; suma = total)
```

Coherencia aritmética:

```text
1. normalizarLineas(): subtotal línea = round(cantidad × precio, 2)
2. suma = round(sum(subtotales), 2)
3. validarIgualdad(subtotal_cabecera, suma, tolerancia 0.01)
4. validarCabecera(total_cabecera, suma):
     0 < total <= round(suma × 1.18, 2) + 0.01
```

Validación de stock previa (fuera de la transacción):

```text
por cada línea:
  si cantidad > producto.stock → error y volver con input
  stock_inicial[producto_id] ??= producto.stock
```

Transacción:

```text
BEGIN
  1. correlativo = 'VTA-' + pad(max(ventas.id) + 1, 4)
  2. Venta::create(correlativo, observacion, subtotal=suma,
                   total, user_id, caja_id)
  3. PagoService::guardarLineas(venta, lineasPago, user, caja)
  4. Por cada línea:
     a. DetalleVenta::create(...)
     b. stock_antes = producto.stock
     c. Producto::where(id)->decrement('stock', cantidad)
     d. KardexService::registrarSalida(
          producto, cantidad, stock_antes,
          fuente='venta', origen=correlativo
        )
COMMIT
```

Devuelve redirect al detalle con `StockAgotado::adjuntar(...)`, que añade un flash con los productos que pasaron de `> 0` a `0`.

> `inventario` no se modifica al vender.

### 7.4 Anulación de venta (`DELETE /ventas/{venta}`)

- Rechazada si la venta tiene devoluciones.
- Transacción:
  1. Borra pagos.
  2. Por cada detalle: incrementa `stock`, registra **entrada** con `fuente = venta`, `origen = VTA-####`.
  3. Audita `anular venta`.
  4. Borra la venta (detalles caen por cascada).
- **No restaura `inventario`.**
- El borrado de la venta elimina su ingreso del resumen de caja (la caja suma ventas existentes).

### 7.5 Ticket

`GET /ventas/{venta}/ticket` carga `user`, `detalles.producto`, `pagos.metodoPago` y renderiza el comprobante imprimible.

---

## 8. Flujo de Devoluciones y Cambios

### 8.1 Regla base

La venta original **nunca se modifica ni se elimina**. La devolución es un documento nuevo (`DEV-####`) con líneas que referencian `detalle_ventas`.

Disponible por línea:

```text
disponible = cantidad_vendida - suma(cantidades ya devueltas de esa línea)
```

### 8.2 Formulario

- Motivo obligatorio (máx. 500).
- Tipo: `devolucion` o `cambio`.
- Líneas: cantidad a devolver por producto (0 se ignora).
- Si es cambio: producto sustituto (`ProductoVendible`) y cantidad nueva.

### 8.3 `POST /ventas/{venta}/devoluciones`

Precondiciones: caja abierta; venta existente.

Transacción:

```text
BEGIN
  1. Venta::lockForUpdate()
  2. DetalleVenta de la venta, lockForUpdate, ORDER BY producto_id
  3. Calcular ya_devuelto por detalle
  4. Validar pertenencia y tope: cantidad <= vendida - ya_devuelto
  5. total_devuelto = round(sum(cantidad × precio_unitario original), 2)
     si <= 0 → excepción
  6. correlativo DEV-#### = max(id de devoluciones) + 1
  7. DevolucionVenta::create(...)
  8. Por cada producto (ordenado):
     a. Producto::lockForUpdate()
     b. DetalleDevolucionVenta::create(...)
     c. stock_antes = producto.stock
     d. increment stock
     e. Kardex entrada fuente='devolucion', origen=DEV-####
        (NO toca inventario)
  9. Egresos compensatorios:
     - mapear cada pago original a tipo_pago de egresos_caja
     - prorratear el total devuelto
     - registrar 1..N egresos (suma exacta = total_devuelto)
 10. Si tipo = cambio:
     a. Producto sustituto lockForUpdate
     b. validar activo, vendible, stock suficiente
     c. precio = producto.precio_venta
     d. crear Venta sustituta VTA-####
     e. pago en efectivo por el subtotal
     f. DetalleVenta de la venta nueva
     g. decrement stock
     h. Kardex salida fuente='devolucion', origen=DEV-####
     i. devolucion.update(venta_nueva_id)
COMMIT
```

### 8.4 Efectos sobre `inventario`

La devolución incrementa `stock` pero **no** `inventario`. Si se devuelve más de lo consumido en el ciclo, `stock` puede quedar por encima de `inventario`; el cálculo `consumido = inventario - stock` da negativo y no alerta. Es un comportamiento a decidir al portar.

---

## 9. Consumo interno y cambio de aceite (relación con inventario)

- Un producto con `destino = consumo_interno` **no se puede vender** ni aplicar en cambio de aceite (`ProductoVendible`).
- Se descuenta solo por **ajuste de inventario** tipo `consumo_interno` (fuente `consumo_interno`), con motivo obligatorio.
- Un cambio de aceite **pendiente no descuenta stock**. Solo al **confirmar** el ticket se descuenta, con `fuente = cambio_aceite` y `origen_id = placa`.
- Editar o eliminar un ticket confirmado restaura stock con movimientos compensatorios de entrada/salida.
- La confirmación exige caja abierta y stock suficiente.

---

## 10. Orden recomendado de CRUDs, formularios y dependencias

### 10.1 Orden mínimo para construir el módulo desde cero

| # | Entidad | Formularios / acciones | Depende de | Por qué en este orden |
|---|---|---|---|---|
| 1 | **Categoría** | index, create, edit, delete, buscar AJAX | — | `productos.categoria_id` es NOT NULL. |
| 2 | **Marca** | index, create, edit, delete, buscar AJAX | — | `productos.marca_id` es NOT NULL. |
| 3 | **Producto** | index + filtros, create, edit, toggle, delete, buscar AJAX | Categoría, Marca | Nace con stock 0; no hay campo de cantidad. |
| 4 | **Proveedor** | index, create, edit, delete | — | Necesario para compras. |
| 5 | **Compra (borrador)** | index + filtros, create, edit, update, show, delete, anular | Producto, Proveedor | Cabecera + detalle sin efectos de inventario. |
| 6 | **Recepción de compra** | acción `recibir` | Compra, Producto, Caja, Kardex | Primer ingreso formal de stock. |
| 7 | **Kardex** | index + filtros, por producto | Movimientos | Permite auditar todo lo anterior. |
| 8 | **Ajuste de inventario** | index + filtro, create, show | Producto, Kardex | Corrige diferencias y consume internos. |
| 9 | **Venta** | index, create, store, show, ticket, delete | Producto, Caja, Pagos, Kardex | Consume stock; depende de que exista stock. |
| 10 | **Devolución / cambio** | create, store, index, show | Venta, Caja, Kardex, Pagos | Reincorpora stock y compensa caja. |
| 11 | **Reportes** | inventario, kardex, compras por proveedor | Todo lo anterior | Solo lectura/agregación. |
| 12 | **Mejoras opcionales** | `stock_minimo`, CPP, pagos de compra, anulación total | — | Fases posteriores del spec. |

### 10.2 Orden de construcción de una compra (vertical slice)

```text
1. Migración compras + detalle_compras + unique(compra_id, producto_id)
2. Modelos Compra / DetalleCompra + relaciones
3. Rutas resource + rutas estáticas ANTES del resource
4. CompraController: index, create, store (borrador)
5. Vista create + JS de líneas/autocompletes + totales
6. CompraController: show, edit, update, destroy, anular
7. KardexService: fuentes, correlativo CMP, registrarEntradaCompra
8. CajaService: getCajaActiva, registrarEgreso
9. CompraController: recibir + validaciones + transacción + locks
10. Vista show con acciones y confirmaciones + modal caja
11. Auditoría de Compra
12. Tests de recepción, idempotencia, atomicidad, márgenes
```

### 10.3 CRUDs que **no** existen y no deben inventarse al portar

| No existe | Alternativa real |
|---|---|
| CRUD de "inventario" | Operaciones + Kardex + reportes. |
| Editar stock desde el formulario de producto | Recepción de compra, ajuste o modal provisional. |
| Editar/borrar movimientos de Kardex | Movimiento compensatorio. |
| Editar/borrar una compra recibida | Anulación con reversión. |
| Editar una venta | Anulación + nueva venta (o devolución/cambio). |
| Editar/borrar un ajuste | Documento inmutable. |

---

## 11. Contratos de formularios, validaciones y mensajes

### 11.1 Producto

| Campo | Regla servidor |
|---|---|
| `nombre` | required, string, max:150, regex sin `< > " ' \`` ni controles |
| `descripcion` | nullable, string, max:100, regex `^[A-Za-z0-9áéíóúÁÉÍÓÚñÑüÜ%+\-.,()\/& ]+$` |
| `categoria_id` | required, integer, exists:categorias,id |
| `marca_id` | required, integer, exists:marcas,id |
| `destino` | nullable, in:comercial,consumo_interno |
| `precio_compra` | nullable, numeric, min:0 → se guarda `0` si falta |
| `precio_venta` | comercial: required, numeric, gt:0; si `precio_compra > 0`, además `gt:precio_compra`. Consumo interno: nullable, numeric, gt:0 |
| `activo` | nullable, boolean |
| `foto` | nullable, image, mimes:jpg,jpeg,png,webp, max:2048 |
| `stock`, `inventario` | **ignorados** en update; no existen en el form |
| Mensaje clave | "El precio de venta debe ser mayor al precio de compra." |

Comportamientos:

- Crear: `stock = 0`, `inventario = 0`, `precio_incoherente = false`; incrementa `categorias.contador_productos`.
- Editar: no toca stock/inventario; si cambia de categoría, decrementa la anterior e incrementa la nueva; si pasa a consumo interno, `precio_venta = NULL` y `precio_incoherente = false`.
- Eliminar: borra foto fuera de la transacción; decrementa contador; la FK `RESTRICT` de compras/ventas puede impedir el borrado (hoy no se captura → error 500).
- Toggle: AJAX `PATCH productos/{id}/toggle-status`, devuelve JSON `{success, activo}`.
- Buscar: `GET productos/buscar?q=&categoria_id=&estado=&destino=&stock=`, devuelve JSON con datos y URLs de acción.

### 11.2 Compra

| Campo | Regla |
|---|---|
| `proveedor_id` | required, integer, exists:proveedores,id |
| `fecha` | required, date |
| `tipo_documento` | required, in:Factura,Boleta,Ticket,Nota de venta,Guia de remision,Otros |
| `numero_documento` | required, string, max:50 |
| `observaciones` | nullable, string, max:500 |
| `detalle` | required, array, min:1 |
| `detalle.*.producto_id` | required, integer, exists, distinct |
| `detalle.*.cantidad` | required, integer, min:1, max:999999 |
| `detalle.*.costo_unitario` | required, numeric, min:0, max:9999999.99 |
| `detalle.*.destino` | nullable, in:comercial,consumo_interno |

Mensajes en español definidos en `CompraController::MENSAJES`.

### 11.3 Ajuste

Ver §6.3.

### 11.4 Venta

Ver §7.3. Regla adicional: el producto debe pasar `ProductoVendible` (destino comercial). El stock se valida dos veces: cliente (no superar `max`) y servidor.

### 11.5 Devolución

| Campo | Regla |
|---|---|
| `motivo` | required, string, max:500 |
| `tipo` | required, in:devolucion,cambio |
| `lineas` | required, array, min:1 |
| `lineas.*.detalle_venta_id` | required, integer, exists:detalle_ventas,id |
| `lineas.*.cantidad` | required, integer, min:0 |
| `producto_nuevo_id` | required_if:tipo,cambio, exists, ProductoVendible |
| `cantidad_nueva` | required_if:tipo,cambio, integer, min:1 |

Reglas de negocio: pertenencia a la venta, tope vendida−devuelta, total > 0, stock del sustituto.

---

## 12. Invariantes que el sistema debe preservar

1. **Ningún usuario edita la existencia desde el catálogo.** `stock` e `inventario` solo cambian por operaciones.
2. **Todo cambio de stock genera exactamente una fila de Kardex** con `stock_antes` y `stock_despues` coherentes.
3. **El Kardex es append-only a nivel de aplicación.** No hay `update` ni `delete`; las correcciones se hacen con movimientos compensatorios. El borrado físico de un producto sin documentos arrastra sus movimientos por `ON DELETE CASCADE` (excepción a corregir si se exige inmutabilidad total).
4. **La suma de `detalle_compras.subtotal` = `compras.subtotal` = `compras.total`**, calculada en servidor.
5. **La suma de `detalle_ventas.subtotal` = `ventas.subtotal`**, y `0 < ventas.total <= suma × 1.18 + 0.01`.
6. **La suma de `pagos.monto` = `ventas.total` (±0.01)**.
7. **Una compra no se recibe dos veces.** La segunda intenta falla por estado.
8. **Una recepción es atómica:** si falla stock, Kardex o caja, no queda nada.
9. **Una compra recibida no se edita ni se borra.** Solo se anula con reversión.
10. **Una venta con devoluciones no se borra.**
11. **No se vende un producto inactivo, de consumo interno o sin stock.**
12. **No se recibe una compra con productos inactivos.**
13. **No se registra dinero sin caja abierta.**
14. **El stock nunca debe quedar negativo**; se valida en venta, ajuste y anulación de compra.
15. **En las operaciones que bloquean productos (recepción, anulación de compra, ajustes, devoluciones), los locks se toman en orden ascendente por `producto_id`** para evitar deadlocks. La venta actual no bloquea productos (deuda técnica).
16. **Los correlativos se generan dentro de la transacción** del documento.
17. **`inventario` solo se reinicia en eventos de entrada/ajuste**, no en ventas.
18. **Un ajuste sin ningún cambio de stock se rechaza completo.**
19. **Las líneas de compra y ajuste no repiten producto** (compra por unique; ajuste por `distinct`).
20. **Los importes se redondean a 2 decimales en servidor**, nunca se confía en el cliente.

---

## 13. Rutas y permisos

### 13.1 Permisos usados

| Permiso | Alcance |
|---|---|
| `acceso-inventario` | `productos.*`, `categorias.*`, `marcas.*` |
| `acceso-proveedores` | `proveedores.*` |
| `acceso-compras` | `compras.*`, recepción y anulaciones |
| `acceso-ajustes` | `ajustes.*` |
| `acceso-ventas` | `ventas.*`, devoluciones, lavados, cambio de aceite |
| `acceso-caja` | panel de caja chica |
| `gestionar-caja` | abrir/cerrar caja |
| `registrar-egresos-caja` | egresos de caja chica |
| `ver-resumen-caja` | resumen de caja general |
| `historial-caja` | historial y detalle |
| `acceso-auditoria` | Kardex, auditoría y reportes |

`AppServiceProvider` otorga al rol `Administrador` un `Gate::before` que omite todos los permisos.

### 13.2 Rutas relevantes

| Método | URI | Nombre | Acción |
|---|---|---|---|
| GET | `/productos/buscar` | `productos.buscar` | AJAX listado |
| GET | `/productos` | `productos.index` | Listado |
| GET | `/productos/create` | `productos.create` | Formulario |
| POST | `/productos` | `productos.store` | Crear |
| GET | `/productos/{producto}/edit` | `productos.edit` | Editar |
| PUT/PATCH | `/productos/{producto}` | `productos.update` | Actualizar |
| PATCH | `/productos/{producto}/stock` | `productos.updateStock` | Reposición provisional |
| PATCH | `/productos/{producto}/toggle-status` | `productos.toggleStatus` | Activar/inactivar |
| DELETE | `/productos/{producto}` | `productos.destroy` | Eliminar |
| GET | `/compras/buscar-proveedores` | `compras.buscar-proveedores` | Autocomplete |
| GET | `/compras/buscar-productos` | `compras.buscar-productos` | Autocomplete |
| POST | `/compras/{compra}/recibir` | `compras.recibir` | Recepción |
| POST | `/compras/{compra}/anular` | `compras.anular` | Anular borrador |
| POST | `/compras/{compra}/anular-recibida` | `compras.anular-recibida` | Reversión |
| GET/POST | `/compras` | `compras.index/store` | Listado/crear |
| GET | `/compras/create` | `compras.create` | Formulario |
| GET | `/compras/{compra}` | `compras.show` | Detalle |
| GET | `/compras/{compra}/edit` | `compras.edit` | Editar borrador |
| PUT/PATCH | `/compras/{compra}` | `compras.update` | Actualizar borrador |
| DELETE | `/compras/{compra}` | `compras.destroy` | Borrar borrador |
| GET/POST | `/ajustes` | `ajustes.index/store` | Listado/crear |
| GET | `/ajustes/create` | `ajustes.create` | Formulario |
| GET | `/ajustes/{ajuste}` | `ajustes.show` | Detalle |
| GET | `/ajustes/{ajuste}/edit` | `ajustes.edit` | **Ruta sin método** |
| PUT/PATCH | `/ajustes/{ajuste}` | `ajustes.update` | **Ruta sin método** |
| DELETE | `/ajustes/{ajuste}` | `ajustes.destroy` | **Ruta sin método** |
| GET | `/ventas/buscar-productos` | `ventas.buscar-productos` | Autocomplete |
| GET/POST | `/ventas` | `ventas.index/store` | Listado/crear |
| GET | `/ventas/create` | `ventas.create` | Formulario |
| GET | `/ventas/{venta}` | `ventas.show` | Detalle |
| DELETE | `/ventas/{venta}` | `ventas.destroy` | Anular |
| GET | `/ventas/{venta}/ticket` | `ventas.ticket` | Ticket |
| GET | `/ventas/{venta}/devoluciones/create` | `ventas.devoluciones.create` | Formulario devolución/cambio |
| POST | `/ventas/{venta}/devoluciones` | `ventas.devoluciones.store` | Registrar devolución/cambio |
| GET | `/devoluciones` | `devoluciones.index` | Listado de devoluciones |
| GET | `/devoluciones/{devolucion}` | `devoluciones.show` | Detalle de devolución |
| GET | `/kardex` | `kardex.index` | Libro mayor |
| GET | `/kardex/producto/{producto}` | `kardex.porProducto` | Historial por producto |
| GET | `/reportes/inventario` | `reportes.inventario` | Stock valorizado |
| GET | `/reportes/kardex` | `reportes.kardex` | Reporte Kardex |
| GET | `/reportes/compras-proveedor` | `reportes.comprasProveedor` | Compras por proveedor |

> **Gotcha de routing:** las rutas estáticas (`buscar`, `recibir`, `anular`, `create`) deben declararse **antes** del `Route::resource`; de lo contrario Laravel las interpreta como `{modelo}`. Aplica a productos, compras, ventas y devoluciones.

---

## 14. Comportamientos de UI/UX a replicar

### 14.1 Listados

- `paginate(10)` en controladores.
- Cabeceras `py-6`; celdas `py-8`.
- Contenedor `overflow-x-auto` (nunca `overflow-hidden`).
- Badges de estado y de stock.
- Filtros GET con `withQueryString()`.
- Búsqueda AJAX con debounce (400 ms en productos; 300 ms en autocompletes de compras).
- Confirmaciones con SweetAlert2 mediante `data-confirm` y `data-confirm-button`.
- Modo oscuro con clases semánticas (`bg-surface`, `input-main`, `label-main`, `text-primary`, `text-secondary`, `border-main`, `divide-main`), sin negro puro.

### 14.2 Formularios dinámicos

- Las líneas se renderizan por JS; no hay `<script>` de comportamiento inline en Blade.
- Solo se permite un `<script>` de inicialización de datos (`window.productos = @json(...)`) antes de `@vite`.
- Cada módulo JS debe estar en `vite.config.js` y cargarse con `@vite([...])` al final de `@section('content')`.
- Los inputs ocultos se sincronizan antes del submit.
- Los totales se muestran en tiempo real y se recalculan en servidor.

### 14.3 Modales y estados vacíos

- Modal "Caja cerrada" con enlace a `caja.index`.
- Modal "Actualizar stock" con advertencia de reposición provisional.
- Estado vacío en tablas cuando no hay registros.
- Badge "Sin stock" prevalece sobre "Stock bajo".
- Aviso de productos agotados tras una venta (`StockAgotado`).

### 14.4 Mensajes de transición rechazada

En lugar de un 403 genérico, `CompraController::errorTransicion` explica:

- Recibida: "ya fue recibida... la reversión no está disponible en esta fase".
- Anulada: "está anulada y no admite cambios".
- Otro: "no está en un estado que permita la operación".

Adaptar el texto si en el sistema destino la reversión ya está implementada.

---

## 15. Deuda técnica y discrepancias detectadas (no replicar)

| # | Hallazgo | Impacto | Recomendación al portar |
|---|---|---|---|
| 1 | `Producto::$fillable`, `$casts` y `estaEnAlertaMinimo()` usan `stock_minimo`, pero la columna **no existe**. | La alerta por stock mínimo nunca se activa; el estado `alerta` es inalcanzable. | Agregar `stock_minimo` con migración o eliminar la rama muerta. |
| 2 | `CostoPromedioService` usa `costo_promedio_ponderado`, columna **inexistente**. `actualizarPorEntrada()` nunca se llama. | El "CPP" siempre cae al fallback `precio_compra`. El Kardex de ajustes usa `precio_compra`. | Decidir costo: último costo (`precio_compra`) o CPP real con columna e historial. |
| 3 | `CompraController::recibir()` llama `registrarEntradaCompra()` sin `costo_unitario`. | El Kardex de compras queda con costo 0. | Pasar el costo de la línea y calcular `costo_total`. |
| 4 | Ventas y devoluciones registran Kardex sin costo. | Valorización histórica incompleta. | Pasar `precio_unitario` o CPP al registrar salidas/entradas. |
| 5 | La recepción de compra registra el egreso siempre con `tipo_pago = 'efectivo'`. No hay campo de método de pago en compras. | Una compra transferida descuadra el efectivo esperado de caja. | Agregar método de pago a la compra o un pago asociado. |
| 6 | La anulación de compra recibida no revierte `precio_compra` ni `destino`. | El costo y la clasificación quedan "sucios" tras anular. | Guardar snapshot por línea y restaurarlo, o recalcular desde la compra recibida anterior. |
| 7 | La anulación de compra recibida usa `fuente = ajuste_negativo` con `origen = CMP-####`. | El filtro por fuente "Compra" no muestra la reversión. | Crear fuente `anulacion_compra` o documentar la convención. |
| 8 | La nota de crédito se modela como `egresos_caja.monto` negativo. | Reportes de egresos pueden mostrar montos negativos. | Modelar `ingresos_caja` o un campo `signo`/`tipo_movimiento`. |
| 9 | `Route::resource('ajustes')` expone `edit`, `update`, `destroy` sin métodos en el controlador. | Error 500 si se invoca la URL. | Usar `->only(['index','create','store','show'])`. |
| 10 | La vista de ajustes exige motivo para `daño` y `conteo_fisico`; el backend solo para `consumo_interno`. | Inconsistencia funcional. | Unificar la regla en servidor y UI. |
| 11 | El modal de stock dice "sin generar compra ni entrada en Kardex", pero el controlador **sí** inserta `INV-####`. | Documentación/UX engañosa. | Corregir el texto o eliminar el registro de Kardex (no recomendado). |
| 12 | `detalle_ventas` no tiene `UNIQUE(venta_id, producto_id)`. | Una venta puede repetir producto y generar varias salidas con el mismo `origen_id`. | Agregar unique o normalizar líneas. |
| 13 | `ventas.correlativo` no es único y se calcula con `max(id)+1`. | Colisiones tras borrados o concurrencia. | Secuencia/tabla de correlativos + unique. |
| 14 | La compra calcula `CMP-####` con `max(origen_id)` del Kardex. | Depende de que el Kardex esté consistente; colisión bajo concurrencia. | Secuencia dedicada + lock/unique retry. |
| 15 | `ProductoController::destroy` no valida referencias en compras/ventas. | La FK `RESTRICT` produce error 500. | Capturar y devolver mensaje "producto en uso". |
| 16 | El menú anida Ajustes y Compras bajo `@can('acceso-proveedores')`. | Usuarios con permisos de compras no ven el enlace. | Separar los `@can`. |
| 17 | `CompraSeeder` crea compras `recibida` con correlativo pero sin `caja_id`/`egreso_caja_id`; `KardexSeeder` reconstruye el Kardex. | Datos demo no representan una recepción real. | Sembrar recepciones con caja/egreso o marcar el seed como demo. |
| 18 | `KardexSeeder` asigna `inventario = stock` después de **cada** movimiento, incluidas ventas. | En demo, la alerta del 75 % nunca se dispara. | Sembrar `inventario` solo en entradas. |
| 19 | La validación de stock de venta ocurre fuera de la transacción y sin `lockForUpdate`. | Carrera: dos ventas simultáneas pueden dejar stock negativo. | Bloquear producto dentro de la transacción y revalidar. |
| 20 | La devolución incrementa stock sin ajustar `inventario`. | `stock > inventario` y consumo negativo. | Decidir si la devolución reinicia ciclo o no. |
| 21 | `CompraController::anularRecibida` deja `\Log::info` de depuración. | Ruido en logs. | Eliminar. |
| 22 | `fecha_anulacion` no está en `$casts`. | Se maneja como string. | Castear a `datetime`. |
| 23 | `ProductoController::updateStock` audita "ajustar stock" pero no hay documento que lo respalde. | Trazabilidad solo por Kardex + auditoría. | Migrar el modal a un ajuste formal o mantenerlo como provisional documentado. |
| 24 | `CompraController::recibir` valida productos inactivos antes y dentro de la transacción; el resto de reglas no se revalida. | Correcto, pero duplicado. | Mantener el doble chequeo por concurrencia. |
| 25 | La recepción **no bloquea la cabecera** de la compra (`lockForUpdate` se omite deliberadamente por SQLite); solo revalida el estado en memoria del route binding. | Dos recepciones concurrentes podrían pasar el chequeo y duplicar stock/Kardex/egreso. | Bloquear la compra o hacer una transición atómica `UPDATE compras SET estado='recibida' WHERE id=? AND estado='borrador'` y verificar filas afectadas. |

---

## 16. Guía de portabilidad a otro sistema

### 16.1 Checklist de modelado

- [ ] Tablas: `productos`, `categorias`, `marcas`, `proveedores`, `compras`, `detalle_compras`, `movimientos_kardex`, `ajustes_inventario`, `detalle_ajustes_inventario`, `ventas`, `detalle_ventas`, `devoluciones_venta`, `detalle_devoluciones_venta`, `cajas`, `egresos_caja`, `pagos`, `metodos_pago`.
- [ ] `productos.stock` e `inventario` como enteros separados, con semántica documentada.
- [ ] `UNIQUE(compra_id, producto_id)`.
- [ ] `movimientos_kardex` append-only, sin FK al documento, con `origen_id` indexado.
- [ ] Estados de compra como enum/check.
- [ ] `destino` del producto y de la línea de compra.
- [ ] `precio_compra` nullable en entrada, persistido como 0.
- [ ] `precio_venta` nullable para consumo interno.
- [ ] Zona horaria `America/Lima` en el bootstrap.
- [ ] Redondeo a 2 decimales en servidor.

### 16.2 Servicios que conviene extraer

| Servicio | Responsabilidad |
|---|---|
| `KardexService` | Catálogo de fuentes, correlativos, `registrar()`, helpers de entrada/salida. |
| `CajaService` | Caja activa, egresos, ingresos compensatorios, resumen, arqueo. |
| `AjusteInventarioService` | Ajuste con stock final, delta, Kardex, detalles. |
| `TotalesService` | Normalización y validación aritmética. |
| `PagoService` | Métodos activos, validación de pagos simple/mixto, mapeo a egresos. |
| `CostoPromedioService` | CPP (si se implementa de verdad). |
| `AuditService` | Registro de acciones con etiquetas propias. |

### 16.3 Pseudocódigo portable de recepción

```pseudo
function recibirCompra(compraId, usuario):
    compra = bloquearCompra(compraId)
    si compra.estado != BORRADOR: error("ya no está en borrador")
    caja = obtenerCajaAbierta()
    si caja == null: error("caja requerida")

    validarProductosActivos(compra)

    iniciarTransaccion()
    correlativo = compra.correlativo ?? siguienteCorrelativo("CMP")
    lineas = cargarLineas(compra).ordenarPor(productoId)

    para linea en lineas:
        producto = bloquearProducto(linea.productoId)
        si !producto.activo: error
        stockAntes = producto.stock
        stockDespues = stockAntes + linea.cantidad
        producto.stock = stockDespues
        producto.inventario = stockDespues
        si linea.destino != null: producto.destino = linea.destino
        guardar(producto)
        kardex.registrarEntrada(
            producto, linea.cantidad, stockAntes,
            fuente="compra", origen=correlativo,
            costo=linea.costoUnitario
        )
        si linea.costoUnitario > 0:
            producto.precioCompra = redondear(linea.costoUnitario)
            producto.precioIncoherente =
                producto.precioVenta > 0 &&
                linea.costoUnitario > producto.precioVenta
            guardar(producto)

    caja.registrarEgreso(
        monto=compra.total,
        descripcion="Pago compra " + correlativo,
        tipoPago=metodoDePagoDeLaCompra,
        categoria="compra_abastecimiento",
        usuario
    )

    compra.correlativo = correlativo
    compra.estado = RECIBIDA
    compra.cajaId = caja.id
    compra.fechaRecepcion = ahora()
    guardar(compra)

    confirmarTransaccion()
```

### 16.4 Pseudocódigo portable de ajuste

```pseudo
function crearAjuste(tipo, motivo, lineas, usuario):
    si lineas.vacio: error
    lineas = normalizarYOrdenarPorProducto(lineas)
    correlativo = siguienteCorrelativo("AJU")

    iniciarTransaccion()
    ajuste = crearCabecera(correlativo, tipo, motivo, usuario)
    movimientos = 0

    para linea en lineas:
        producto = bloquearProducto(linea.productoId)
        si !producto.activo: error
        si linea.stockFinal < 0: error
        delta = linea.stockFinal - producto.stock
        si delta == 0: continuar

        producto.stock = linea.stockFinal
        producto.inventario = linea.stockFinal
        guardar(producto)

        fuente = mapearFuente(tipo)
        kardex.registrar(
            producto,
            tipo = delta > 0 ? ENTRADA : SALIDA,
            cantidad = abs(delta),
            stockAntes = producto.stock - delta,
            stockDespues = linea.stockFinal,
            fuente = fuente,
            origen = correlativo,
            costo = costoVigente(producto)
        )
        crearDetalle(ajuste, producto, delta)
        movimientos++

    si movimientos == 0: error("ninguna línea cambia el stock")
    confirmarTransaccion()
    retornar ajuste
```

### 16.5 Decisiones que el sistema destino debe tomar

1. **Costo:** ¿último costo (`precio_compra`) o CPP real? El sistema actual usa último costo para productos y fallback para Kardex.
2. **Pago de compras:** ¿efectivo fijo, método por compra o cuenta por pagar?
3. **Stock negativo:** ¿bloquear siempre o permitir con alerta? Hoy se bloquea en venta y anulación, pero no hay CHECK de BD.
4. **Devoluciones:** ¿reinician el ciclo de `inventario` o no?
5. **Anulación de compra recibida:** ¿reversión total (costo y destino) o solo stock/caja?
6. **Ajustes:** ¿inmutables o anulables con compensación?
7. **Consumo interno:** ¿solo ajustes o también salidas automáticas desde servicios?
8. **Correlativos:** ¿tabla de secuencias o `max()+1`? Recomendado: secuencia con unique.
9. **Auditoría:** ¿observar solo cabeceras o también detalles? Hoy detalles y Kardex no se auditan como entidades.
10. **Multi-almacén:** el modelo actual es de un solo almacén por producto. Si se necesita multi-sucursal, `stock` e `inventario` deben migrar a una tabla `stock_por_almacen`.

### 16.6 Tests mínimos a replicar

| Área | Casos |
|---|---|
| Producto | Alta con stock 0; update ignora stock; precio incoherente; consumo interno no vendible; filtros. |
| Compra | Crear borrador sin efectos; update reemplaza líneas; borrar solo borrador; anular solo borrador. |
| Recepción | Éxito completo; sin caja; producto inactivo; doble recepción; costo 0 no sobrescribe; margen perdido; atomicidad si falla egreso; orden por `producto_id`. |
| Anulación recibida | Éxito; stock insuficiente; sin caja; borrador no permite reversión. |
| Ajuste | Stock final menor/mayor; multi-línea; línea sin cambio; todas sin cambio; producto repetido; producto inactivo; show con Kardex. |
| Kardex | Venta salida; anulación entrada; alta producto sin Kardex; updateStock entrada `inventario`; filtros por fuente; fuente inválida. |
| Venta | Stock insuficiente; pago mixto; total con descuento; descuento mayor al subtotal. |
| Devolución | Parcial; tope por línea; sin caja; prorrateo mixto; cambio con venta sustituta; cambio sin stock; venta con devoluciones no se borra. |
| Reportes | Inventario valorizado; Kardex agregado; compras por proveedor. |

---

## 17. Anexos

### A. DDL mínimo portable (referencia)

```sql
CREATE TABLE categorias (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL UNIQUE,
  slug VARCHAR(160) NULL,
  descripcion TEXT NULL,
  contador_productos INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);

CREATE TABLE marcas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  descripcion TEXT NULL,
  foto VARCHAR(255) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);

CREATE TABLE productos (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  descripcion VARCHAR(100) NULL,
  precio_compra DECIMAL(10,2) NOT NULL DEFAULT 0,
  precio_venta DECIMAL(10,2) NULL,
  stock INT NOT NULL DEFAULT 0,
  inventario INT NOT NULL DEFAULT 0,
  stock_minimo INT NOT NULL DEFAULT 0,          -- recomendado; hoy no existe
  categoria_id BIGINT NOT NULL,
  marca_id BIGINT NOT NULL,
  precio_incoherente BOOLEAN NOT NULL DEFAULT FALSE,
  activo TINYINT NOT NULL DEFAULT 1,
  destino VARCHAR(20) NOT NULL DEFAULT 'comercial',
  foto TEXT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  CONSTRAINT fk_producto_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  CONSTRAINT fk_producto_marca FOREIGN KEY (marca_id) REFERENCES marcas(id),
  INDEX idx_producto_stock (stock)
);

CREATE TABLE proveedores (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ruc CHAR(11) NOT NULL,
  razon_social VARCHAR(150) NOT NULL,
  direccion VARCHAR(200) NULL,
  estado_tributario VARCHAR(100) NULL,
  condicion VARCHAR(100) NULL,
  estado CHAR(1) NOT NULL DEFAULT '1',
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_proveedor_ruc (ruc)
);

CREATE TABLE compras (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  correlativo VARCHAR(20) NULL UNIQUE,
  proveedor_id BIGINT NOT NULL,
  fecha DATE NOT NULL,
  tipo_documento VARCHAR(20) NOT NULL,
  numero_documento VARCHAR(50) NOT NULL,
  estado ENUM('borrador','recibida','anulada') NOT NULL DEFAULT 'borrador',
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  observaciones TEXT NULL,
  user_id BIGINT NOT NULL,
  caja_id BIGINT NULL,
  egreso_caja_id BIGINT NULL,
  fecha_recepcion TIMESTAMP NULL,
  fecha_anulacion TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  CONSTRAINT fk_compra_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
);

CREATE TABLE detalle_compras (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  compra_id BIGINT NOT NULL,
  producto_id BIGINT NOT NULL,
  cantidad INT NOT NULL DEFAULT 1,
  costo_unitario DECIMAL(10,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  destino VARCHAR(20) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  CONSTRAINT fk_detalle_compra FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_compra_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
  UNIQUE KEY uq_compra_producto (compra_id, producto_id)
);

CREATE TABLE movimientos_kardex (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  producto_id BIGINT NOT NULL,
  tipo ENUM('entrada','salida') NOT NULL,
  fuente VARCHAR(30) NOT NULL,
  origen_id VARCHAR(20) NOT NULL,
  cantidad INT NOT NULL,
  stock_antes INT NOT NULL,
  stock_despues INT NOT NULL,
  costo_unitario DECIMAL(10,2) NULL,
  costo_total DECIMAL(12,2) NULL,
  usuario_id BIGINT NOT NULL,
  fecha_movimiento TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  CONSTRAINT fk_kardex_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
  INDEX idx_kardex_origen (origen_id),
  INDEX idx_kardex_fecha (fecha_movimiento)
);
```

> En PostgreSQL, reemplazar `ENUM` por `VARCHAR + CHECK` y agregar el `CHECK` de `fuente` con la lista de §4.

### B. Ejemplo de respuesta del autocomplete de compras

```json
[
  {
    "id": 12,
    "nombre": "Aceite Motor 5W-30 Sintético",
    "precio_compra": "35.00",
    "stock": 42
  }
]
```

### C. Ejemplo de respuesta del autocomplete de ventas

```json
[
  {
    "id": 12,
    "nombre": "Aceite Motor 5W-30 Sintético",
    "precio_venta": "55.00",
    "stock": 42,
    "inventario": 120,
    "stock_bajo": false
  }
]
```

### D. Ejemplo de `updateStock`

Request:

```http
PATCH /productos/12/stock
Content-Type: application/json
X-CSRF-TOKEN: ...

{ "cantidad_adicional": 24 }
```

Response:

```json
{
  "success": true,
  "nuevo_stock": 66,
  "nuevo_inventario": 66
}
```

Efecto: `stock += 24`, `inventario = stock`, Kardex `INV-####` con `fuente = inventario`, auditoría "ajustar stock".

### E. Relación entre documentos y Kardex

| Documento | `origen_id` | Fuente(s) |
|---|---|---|
| Compra recibida | `CMP-0001` | `compra` (entrada) |
| Anulación compra recibida | `CMP-0001` | `ajuste_negativo` (salida) |
| Venta | `VTA-0001` | `venta` (salida) |
| Anulación venta | `VTA-0001` | `venta` (entrada) |
| Devolución de venta | `DEV-0001` | `devolucion` (entrada) |
| Venta sustituta de cambio | `DEV-0001` | `devolucion` (salida) |
| Ajuste | `AJU-0001` | `ajuste_positivo` / `ajuste_negativo` / `daño` / `conteo_fisico` / `consumo_interno` |
| Modal provisional | `INV-0001` | `inventario` (entrada) |
| Cambio de aceite confirmado | placa | `cambio_aceite` (salida) |

### F. Specs de referencia en el repositorio

- `.kiro/specs/compras-ingreso-mercaderia/` — requisitos, diseño y tareas del módulo de compras.
- `.kiro/specs/ajustes-kardex-completo/` — ajustes y Kardex.
- `.kiro/specs/mejoras-inventario-ventas/` — `stock_minimo`, CPP, alertas.
- `.kiro/specs/devoluciones-ventas/` — devoluciones y cambios.
- `.kiro/specs/ventas-module/` — ventas.
- `docs/plan-compras-ingreso-mercaderia.md` y `docs/pendientes-implementacion-compras.md` — plan por fases y pendientes.

---

**Fin del documento.** Cualquier decisión de portabilidad debe contrastarse contra las invariantes de §12 y la deuda técnica de §15; replicar el código sin corregir esos puntos reproduce los mismos defectos.
