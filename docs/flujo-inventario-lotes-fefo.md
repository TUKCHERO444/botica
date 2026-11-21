# Flujo de inventario — Producto, compra, lotes, FEFO y venta fraccionada

> **Última actualización:** 19/11/2026
> **Alcance:** describe el flujo real implementado en el panel y su efecto en base de datos.
> **Documentos relacionados:** `modulo-compras-proveedores.md`, `plan-venta-fraccionada.md`, `plan-lotes-fefo-v2.md`, `arquitectura-y-stack.md`.

---

## 1. Idea general

El inventario se maneja en cuatro capas:

```text
Catálogos globales
  unidad_medida + presentacion
        ↓
Producto
  unidad base + presentaciones asignadas
        ↓
Compra
  documento (borrador → recibida → anulada)
  lote físico + capa de recepción + stock + pago opcional
        ↓
Venta / atención
  FEFO consume lotes + kardex + caja
        ↓
Devolución a proveedor
  capa exacta + kardex DEVOLUCION + reembolso opcional
        ↓
Ajuste de inventario
  por lote + kardex AJUSTE + inmutable
```

Regla central:

```text
producto.stock = suma de lotes disponibles (cantidad_disponible_base)
```

`producto.stock` es una caché operativa. La fuente real es `lote_ingreso.cantidad_disponible_base`.

> **Stock físico vs. stock vendible.** `producto.stock` incluye todas las capas no dadas de baja, incluso las de lotes vencidos. FEFO solo consume lotes `disponible` con `fecha_vencimiento >= hoy`, así que el **stock vendible** es la suma de esas capas. El buscador de ventas y el selector de atenciones usan stock vendible: un producto con stock físico pero todo vencido no se ofrece y aparece como `Sin stock vendible`.

---

## 2. Prerrequisitos: catálogos globales

Antes de crear productos deben existir unidades y presentaciones.

### 2.1. Unidades de medida (`/admin/unidades-medida`)

| Campo | Qué significa |
|---|---|
| `codigo` | Código corto único: `UND`, `TAB`, `CAP`, `ML`, `G` |
| `nombre` | Nombre visible: Unidad, Tableta, Cápsula, Mililitro, Gramo |
| `permite_fraccion` | Indica si la unidad admite fracciones |
| `presentacion_base_id` | Presentación global que se usará como base del producto |
| `activo` | Si está disponible para asignar a productos |

Una unidad sin `presentacion_base_id` no puede usarse para crear productos.

### 2.2. Presentaciones (`/admin/presentaciones`)

Catálogo global de empaques o formas de venta:

```text
Unidad, Tableta, Cápsula, Mililitro, Gramo,
Caja, Blíster, Frasco, Sobre, Ampolla, Tubo
```

Una presentación no se puede eliminar si está asignada a un producto o si es la base de una unidad.

---

## 3. CRUD de Productos

Ruta: `/admin/productos`.

### 3.1. Campos del formulario Crear/Editar

| Campo | Obligatorio | Qué hace |
|---|---|---|
| `nombre` | Sí | Nombre único del producto, máx. 50 |
| `descripcion` | No | Descripción corta, máx. 50 |
| `categoria_id_categoria` | Sí | Categoría del producto |
| `marca_id_marca` | Sí | Marca del producto |
| `unidad_base_id` | Sí | Unidad en la que se controla el stock (`TAB`, `ML`, etc.) |
| `precio_compra` | No | Costo por **unidad base** |
| `precio_venta` | Sí | Precio de la presentación base |
| `inventario` | No (legado) | Se conserva en BD, oculto del formulario y reportes; se sincroniza con `stock` |
| `stock` | No | El producto nace en 0; en edición es solo lectura. Se carga desde **Inventario inicial** |
| `stock_minimo` | Sí | Umbral de alerta en unidad base |
| `estado` | Sí | Activo/Inactivo |
| `imagenes[]` | No | Hasta 4 imágenes; se suben a Cloudinary |
| `orden_imagenes[]` | No | Orden de las imágenes |

### 3.2. Presentaciones adicionales

Cada fila del repeater tiene:

| Campo | Obligatorio | Qué hace |
|---|---|---|
| `presentacion_id` | Sí | Presentación global asignada |
| `factor` | Sí | Cuántas unidades base contiene la presentación |
| `precio_venta` | Sí | Precio de esa presentación |
| `paso` | No | Incremento mínimo de venta; por defecto `1`. Solo se muestra en **Configuración avanzada** |
| `activo` | Sí | Si se puede vender/ingresar |
| `codigo_barras` | No | Reservado para lector (aún sin UI visible) |

La **presentación base** no se agrega en el repeater:

- se crea automáticamente;
- usa la presentación base de la unidad;
- `factor = 1`;
- `paso` configurable (por defecto `1`);
- `precio_venta = producto.precio_venta`.

> El `paso` de la presentación base se edita desde **Configuración avanzada** en el formulario del producto. Ej.: `0.5` permite vender media unidad base.

### 3.3. Qué hace “Crear producto”

1. Valida campos y presentaciones.
2. Crea `producto` con `stock = 0` e `inventario = 0`.
3. Crea la presentación base en `producto_presentacion`.
4. Crea las presentaciones adicionales.
5. Sube las imágenes a Cloudinary.
6. Actualiza contadores de categoría/marca.
7. Registra auditoría.

No crea lote ni mueve stock. Las existencias se cargan desde **Inventario inicial** (`/admin/inventario-inicial`), que exige lote y vencimiento y registra kardex `INGRESO` con referencia `InventarioInicial`.

### 3.4. Qué hace “Inventario inicial”

1. Se crea un borrador con líneas de producto, presentación, lote, vencimiento, cantidad base y costo.
2. El borrador no mueve stock ni kardex.
3. Al confirmar, por cada línea:
   - crea `producto_lote` (`es_inicial = true`);
   - crea `lote_ingreso`;
   - suma stock al producto;
   - registra kardex `INGRESO`.
4. Solo acepta productos sin stock ni lotes previos.
5. El inventario confirmado es inmutable; las correcciones se hacen con ajustes.

La importación CSV permite cargar catálogo y lotes en **unidad base**, con dry-run y reporte de errores. La carga manual permite elegir cualquier presentación y convierte a base. Ver [`plan-inventario-inicial-d1.md`](plan-inventario-inicial-d1.md).

### 3.5. Qué hace “Editar producto”

- Actualiza datos generales.
- Sincroniza la presentación base con el precio de venta.
- Actualiza/crea presentaciones adicionales.
- Las presentaciones quitadas se **desactivan**, no se borran.
- **No permite cambiar `stock` ni `inventario` directamente**: se gestionan por ingresos, ventas, atenciones o bajas.
- Si se cambia la unidad base y hay conflicto con una presentación usada, puede bloquear la operación.
- Mantiene imágenes existentes, permite agregar, reordenar o eliminar.

### 3.6. Qué hace “Eliminar producto”

No borra físicamente:

```text
estado = 0
```

El producto queda inactivo, conserva historial, lotes y kardex.

---

## 4. CRUD de Compras (Ingresos)

Ruta: `/admin/ingresos`. En la interfaz se llama **Compras**.

La compra es un documento con estados:

```text
borrador → recibida → anulada
```

### 4.1. Campos del formulario Crear/Editar

| Campo | Obligatorio | Qué hace |
|---|---|---|
| `proveedor_id_proveedor` | Sí | Proveedor de la compra |
| `fecha` | No | Fecha del documento; por defecto hoy |
| `tipo_documento` | Sí | Factura, boleta, ticket, nota de venta, guía u otro |
| `numero_documento` | Sí | Número del proveedor; único por proveedor + tipo |
| `observaciones` | No | Notas de la compra |
| `pagado_desde_caja` | No | Si el pago se registra en caja |
| `metodo_pago` | Si paga por caja | Efectivo, transferencia, Yape, tarjeta u otro |
| `referencia_pago` | No | Nro. de operación o nota del pago externo |
| `productos[].producto_presentacion_id` | Sí | Producto + presentación comprada |
| `productos[].cantidad` | Sí | Cantidad en esa presentación |
| `productos[].lote` | Sí | Código de lote del fabricante/proveedor |
| `productos[].fecha_vencimiento` | Sí | Vencimiento del lote; no puede ser pasado |
| `productos[].costo_unitario` | No | Costo por presentación; se convierte a costo base |

### 4.2. Qué hace “Guardar borrador”

1. Crea `ingreso` con `estado = borrador` y `correlativo = null`.
2. Calcula `cantidad_base = cantidad × factor`.
3. Guarda las líneas en `detalle_ingreso` con costo unitario y total.
4. Calcula `subtotal` y `total` en el servidor.
5. **No** crea lotes, **no** mueve stock, **no** registra kardex y **no** toca caja.

### 4.3. Qué hace “Editar borrador”

- Solo si `estado = borrador`.
- Reemplaza todas las líneas.
- Recalcula totales.
- No mueve stock ni caja.

### 4.4. Qué hace “Recibir”

1. Bloquea la compra y verifica que siga en borrador.
2. Decide el pago:
   - desde caja: exige caja abierta y método de pago;
   - externo: no toca caja.
3. Por cada línea:
   - busca o crea `producto_lote` por producto + código + vencimiento;
   - crea una capa `lote_ingreso`;
   - guarda cantidad, factor, cantidad base, costo y disponible;
   - actualiza `detalle_ingreso` con lote y costos reales;
   - suma `producto.stock`;
   - registra kardex `INGRESO` con referencia `Compra`.
4. Genera el correlativo `CMP-XXXXXXXX`.
5. Marca `estado = recibida`, `fecha_recepcion` y `recepcion_user_id`.
6. Si se pagó por caja, crea el egreso en `caja_movimiento`.

Si el lote ya existía, agrega una nueva capa de recepción. Si `producto.stock` no coincide con la suma de lotes, la recepción se bloquea; no se crea `SIN-LOTE`. Regularizar con inventario inicial o un ajuste.

### 4.5. Qué hace “Anular”

- Solo si `estado = recibida`.
- Verifica que ninguna capa haya sido consumida.
- Revierte capas, lotes, stock y kardex.
- Si se pagó por caja, registra un ingreso compensatorio.
- Marca `estado = anulada`, `anulada_at` y `motivo_anulacion`.

Si hubo consumo:

```text
422 — No se puede editar ni eliminar este ingreso porque parte de sus lotes ya fue consumida.
```

Se debe usar una devolución o un ajuste.

### 4.6. Qué hace “Eliminar”

- Solo borradores.
- Elimina cabecera y líneas.
- No toca stock ni caja.

> Detalle completo del módulo: `modulo-compras-proveedores.md`.

---

## 5. Relación entre producto, unidad, presentación y lote

```text
unidad_medida
    │ 1:N
producto ──────────────┐
    │ 1:N              │
producto_presentacion ─┘
    │ 1:N
lote_ingreso ──────── producto_lote
    │ 1:N
lote_consumo
```

Ejemplo:

```text
Producto: Ibuprofeno 400mg
Unidad base: TAB
Presentaciones:
  Caja x 100    factor 100   precio S/ 50
  Blíster x 10  factor 10    precio S/ 6
  Tableta       factor 1     precio S/ 0.80

Lote A: vence 10/12/2026, disponible 60 TAB
Lote B: vence 10/03/2027, disponible 200 TAB
```

Cada lote tiene:

- `correlativo` autogenerado: `LOTE-00000001`;
- `codigo_lote`: código del fabricante, por ejemplo `LOT-2405A`;
- `fecha_vencimiento`;
- `cantidad_disponible_base`.

---

## 6. Venta fraccionada

Ruta: `/admin/ventas`.

### 6.1. Campos del formulario

| Campo | Obligatorio | Qué hace |
|---|---|---|
| `cliente_id_cliente` o `cliente_data` | Sí | Cliente existente, genérico o alta rápida RENIEC |
| `productos[].producto_presentacion_id` | Sí | Producto + presentación vendida |
| `productos[].cantidad` | Sí | Cantidad en esa presentación |
| `productos[].precio` | No | Se muestra readonly; el servidor usa el precio de la presentación |

La caja debe estar abierta antes de registrar.

### 6.2. `factor` y `paso`

| Concepto | Significado | Ejemplo |
|---|---|---|
| `factor` | Unidades base por presentación | Caja x 100 → 100 |
| `paso` | Incremento mínimo de venta | Tableta → 0.5 |

```text
cantidad_base = cantidad_vendida × factor
```

Validaciones:

- cantidad > 0;
- cantidad múltiplo de `paso`;
- presentación activa;
- producto activo;
- stock total suficiente;
- stock por lotes suficiente.

Ejemplos:

```text
1 caja        = 1 × 100 = 100 TAB
0.5 tableta   = 0.5 × 1 = 0.5 TAB
1.5 blíster   = inválido si paso = 1
```

### 6.3. Qué hace “Crear venta”

1. Verifica caja abierta.
2. Crea o resuelve el cliente.
3. Calcula subtotales y total.
4. Crea `venta` con correlativo `T001-XXXXXXXX`.
5. Por cada línea:
   - inserta `detalle_venta` con presentación, cantidad, factor, cantidad base, precio y subtotal;
   - llama a `InventarioService::consumir()`.
6. `consumir()` aplica FEFO:
   - bloquea el producto;
   - busca lotes disponibles no vencidos;
   - ordena por vencimiento y antigüedad;
   - consume una o varias capas;
   - crea `lote_consumo` por capa;
   - descuenta `producto.stock`;
   - registra kardex `VENTA` por capa;
   - calcula costo total de la línea.
7. Crea movimiento de caja por el total.
8. Auditoría y recarga del panel.

### 6.4. Editar y eliminar venta

Bloqueados. Una venta confirmada **no se edita ni se elimina**: el endpoint responde `422` con un mensaje que invita a anular. Así se conserva el detalle original y la trazabilidad del kardex.

### 6.5. Qué hace “Anular venta”

Ruta: `POST /admin/ventas/{venta}/anular`, motivo obligatorio.

1. Bloquea la venta y verifica que esté confirmada.
2. Busca el ingreso original en `caja_movimiento`.
3. Si la caja del cobro está cerrada, bloquea la anulación.
4. Revierte los consumos exactos:
   - devuelve cantidad a cada capa;
   - restaura lotes y stock;
   - registra movimientos `REVERSION` por cada capa.
5. Crea un egreso compensatorio en la misma caja y actualiza `total_egresos`.
6. Marca `venta.estado = anulada`, `anulada_at`, `anulada_user_id` y `motivo_anulacion`.
7. El detalle de la venta se conserva; el ticket muestra `*** ANULADA ***` y el motivo.

Las ventas anuladas se excluyen de reportes y dashboard. En caja quedan el ingreso original y el egreso compensatorio, por lo que el saldo neto es correcto.

Para devoluciones parciales, reingreso condicionado y reembolsos opcionales, ver [`modulo-devoluciones-cliente.md`](modulo-devoluciones-cliente.md).

---

## 7. Atenciones

Ruta: `/admin/atenciones`.

Es igual a ventas, con estas diferencias:

- exige al menos un servicio;
- puede tener productos y servicios;
- `observacio` es opcional;
- el total suma productos + servicios;
- los productos también consumen por FEFO;
- la caja registra el total combinado.

Campos:

| Campo | Obligatorio | Qué hace |
|---|---|---|
| `cliente_id_cliente` / `cliente_data` | Sí | Cliente |
| `observacio` | No | Observación máx. 50 |
| `productos[].producto_presentacion_id` | No | Producto consumido |
| `productos[].cantidad` | Si hay producto | Cantidad con paso |
| `servicios[].servicio_id_servicio` | Sí | Servicio prestado |
| `servicios[].precio` | Sí | Precio del servicio |

---

## 8. FEFO paso a paso

FEFO significa **First Expiry, First Out**: primero vence, primero sale.

```text
Lote A: vence 10/12/2026, 60 TAB
Lote B: vence 10/03/2027, 200 TAB

Venta de 100 TAB:
  60 desde A
  40 desde B
```

El sistema:

1. Ignora lotes con `estado = baja`.
2. Ignora lotes vencidos (`fecha_vencimiento < hoy`).
3. Ignora capas con `cantidad_disponible_base = 0`.
4. Ordena por:
   ```text
   fecha_vencimiento ASC,
   lote_ingreso.created_at ASC,
   lote_ingreso.id ASC
   ```
5. Comprueba que la suma disponible cubra la cantidad.
6. Consume capa por capa.
7. Registra cada consumo en `lote_consumo`.
8. Registra kardex por capa.

Si no hay lotes suficientes, la venta se rechaza completa. Si `producto.stock` no coincide con la suma de lotes, la venta también se bloquea; no se vende “sin lote” ni se crea `SIN-LOTE` automáticamente.

---

## 9. Lotes y vencimientos

Ruta: `/admin/lotes`.

### 9.1. Columnas

| Columna | Qué muestra |
|---|---|
| Producto | Producto y unidad base |
| Correlativo | `LOTE-00000001` autogenerado |
| Lote | Código del fabricante |
| Vencimiento | Fecha y días restantes |
| Disponible | Cantidad en unidad base |
| Estado | Vigente, próximo, crítico, vencido, agotado, baja |
| Acciones | Dar de baja |

### 9.2. Qué hace “Dar de baja”

1. Bloquea el lote.
2. Recorre sus capas con disponible.
3. Descuenta cada capa y `producto.stock`.
4. Registra `lote_consumo` con referencia `Baja`.
5. Registra kardex `BAJA`.
6. Marca el lote como `baja` y disponible `0`.

Se usa para vencidos, cuarentena o retiro físico.

---

## 10. Resumen CRUD

| Acción | Producto | Compra (Ingreso) | Venta/Atención |
|---|---|---|---|
| Crear | Producto + presentación base + presentaciones + lote inicial si hay stock | Borrador + líneas + totales calculados; no mueve stock | Venta + detalle + FEFO + caja |
| Editar | Datos, presentaciones e imágenes; stock no editable | Solo borrador; reemplaza líneas y totales | **Bloqueado**: la venta confirmada conserva su detalle |
| Eliminar | Desactiva (`estado=0`) | Solo borrador; elimina cabecera y líneas | **Bloqueado**: se usa anular |
| Recibir | — | Crea lote/capa, stock, kardex y pago opcional | — |
| Anular | — | Revierte stock/lotes/kardex y caja si se pagó por caja | Revierte stock/lotes/kardex y egreso compensatorio en caja |
| Devolver a proveedor | — | Consume una capa exacta, registra kardex `DEVOLUCION` y reembolso opcional | — |
| Anular devolución | — | Restaura capa, lote, stock, kardex y caja | — |
| Ajustar inventario | — | Suma o resta stock por lote, kardex `AJUSTE`, sin caja | — |
| Ver | Listado, imágenes, presentaciones | Detalle con documento, estado, pago, lote, vencimiento y costo | Detalle con presentaciones y lotes consumidos |

---

## 11. Validaciones principales

| Regla | Dónde |
|---|---|
| Código de unidad único | `unidad_medida.codigo` |
| Presentación global única | `presentacion.nombre` |
| Una presentación no se repite en el producto | unique `producto_id_producto + presentacion_id` |
| Factor > 0 | Request de producto |
| Paso > 0 y paso ≤ factor | Request de producto |
| Una sola presentación base | Lógica del producto |
| Vencimiento no pasado | Request de compra |
| Cantidad múltiplo del paso | Compras, ventas, atenciones |
| Stock suficiente | Requests + `InventarioService` |
| Lotes disponibles no vencidos | `InventarioService` |
| Documento obligatorio y único por proveedor | Requests + índice `ingreso_documento_unique` |
| Caja abierta si se paga desde caja | `CompraService::recibir` |
| Solo borrador editable, recibible o eliminable | `CompraService` |
| Compra con consumo no anulable | `InventarioService::revertirIngreso` |
| Devolución no mayor a la capa disponible | `InventarioService::consumirDeCapa` |
| Devolución solo desde compra recibida | `DevolucionProveedorService::crear` |
| Ajuste no mayor a lo disponible | `InventarioService::ajustarSalidaLote` |
| Ajuste inmutable | Sin rutas de edición, eliminación o anulación |

---

## 12. Ejemplo completo

### Producto

```text
Ibuprofeno 400mg
Unidad base: TAB
Stock mínimo: 20
Precio venta base: S/ 0.80
Presentaciones:
  Caja x 100 → factor 100 → S/ 50.00 → paso 1
  Blíster x 10 → factor 10 → S/ 6.00 → paso 1
```

### Ingreso

```text
Lote A: 60 TAB, vence 10/12/2026
Lote B: 200 TAB, vence 10/03/2027
Stock: 260 TAB
```

### Venta

```text
1 caja + 5 tabletas
Base: 100 + 5 = 105 TAB
Total: S/ 50 + S/ 4 = S/ 54
```

FEFO consume:

```text
60 TAB de A
45 TAB de B
```

Resultado:

```text
A: 0 TAB (agotado)
B: 155 TAB
producto.stock: 155 TAB
kardex: -60 A y -45 B
```

### Edición

```text
Cambiar a 2 blísteres (20 TAB)
→ se devuelven 60 a A y 45 a B
→ se consumen 20 desde A
→ A queda 40, B queda 200
```

### Eliminación

```text
→ se devuelven los 20 TAB a A
→ A queda 60, B queda 200
```

---

## 13. Estados y limitaciones actuales

- Lotes vencidos no se venden; deben darse de baja.
- Una compra recibida no se edita ni elimina; solo se anula mientras sus lotes no tengan consumo.
- El alta de producto nace con stock 0 y no crea `SIN-LOTE`; las existencias se cargan por inventario inicial. D2 eliminó la creación automática y bloquea compras/ventas si el stock no cuadra con los lotes. Los `SIN-LOTE` heredados se listan con `php artisan inventario:sin-lote` y se regularizan manualmente.
- La presentación base tiene `paso` configurable (por defecto `1`) desde **Configuración avanzada**.
- Los combos aún no consumen lotes; usan presentación base.
- No hay reporte de costo/margen por lote todavía.
- La devolución a proveedor ya está implementada: descuenta una capa exacta y permite reembolso a caja.
- Los ajustes de inventario son inmutables y se registran por lote en unidad base.
- El kardex es append-only: las reversiones se registran con `REVERSION` y no se eliminan movimientos.
- El pago de la compra puede registrarse en caja o quedar como pago externo; no hay cuentas por pagar.

---

## 14. Archivos principales

| Función | Archivo |
|---|---|
| Producto CRUD | `App\Http\Controllers\Admin\ProductoController` |
| Compra CRUD y recepción | `App\Http\Controllers\Admin\IngresoController` + `App\Services\CompraService` |
| Devolución a proveedor | `App\Http\Controllers\Admin\DevolucionProveedorController` + `App\Services\DevolucionProveedorService` |
| Ajustes de inventario | `App\Http\Controllers\Admin\AjusteInventarioController` + `App\Services\AjusteInventarioService` |
| Venta CRUD | `App\Http\Controllers\Admin\VentaController` |
| Atención CRUD | `App\Http\Controllers\Admin\AtencionController` |
| Lotes/vencimientos | `App\Http\Controllers\Admin\LoteController` |
| FEFO, capas y reversión | `App\Services\InventarioService` |
| Kardex | `App\Helpers\KardexHelper` |
| Modelos | `Producto`, `ProductoPresentacion`, `ProductoLote`, `LoteIngreso`, `LoteConsumo`, `Ingreso`, `DetalleIngreso`, `DevolucionProveedor`, `DetalleDevolucionProveedor`, `AjusteInventario`, `DetalleAjusteInventario` |
| Formularios | `resources/views/admin/productos`, `ingresos`, `devoluciones`, `ajustes`, `ventas`, `atenciones`, `lotes` |
| Tests | `LotesFefoTest`, `VentaFraccionadaTest`, `CarritoFraccionadoTest`, `CompraDocumentoTest`, `CompraRecepcionTest`, `DevolucionProveedorTest`, `AjusteInventarioTest` |

---

*Documento de referencia operativa. Si cambia un formulario, una validación o el algoritmo FEFO, actualizar este archivo y `plan-lotes-fefo-v2.md`.*
