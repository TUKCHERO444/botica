# Módulo de compras a proveedores

> **Última actualización:** 19/11/2026
> **Estado:** fases 1 a 7 implementadas.
> **Referencias:** `plan-mejora-compras-proveedores.md`, `flujo-inventario-lotes-fefo.md`, `plan-lotes-fefo-v2.md`.

---

## 1. Idea general

La compra es un **documento con estados**. No mueve stock al crearse: primero se revisa y luego se recibe.

```text
Borrador  →  Recibida  →  Anulada
   │             │            │
   │             │            └─ revierte stock, lotes y caja
   │             └─ crea lotes, stock, kardex y pago
   └─ no toca inventario, kardex ni caja

Recibida  →  Devolución a proveedor  →  Anulada
                  │                        │
                  ├─ descuenta una capa    └─ restaura capa, stock y caja
                  ├─ kardex DEVOLUCION
                  └─ reembolso opcional a caja
```

El módulo vive en la tabla `ingreso` por compatibilidad histórica, pero en la interfaz se llama **Compras**. La ruta interna sigue siendo `/admin/ingresos` y el permiso sigue siendo `ingresos`.

---

## 2. Campos de la cabecera

| Campo | Obligatorio | Qué hace |
|---|---|---|
| `proveedor_id_proveedor` | Sí | Proveedor de la compra |
| `fecha` | No | Fecha del documento; por defecto hoy |
| `tipo_documento` | Sí | `factura`, `boleta`, `ticket`, `nota_venta`, `guia_remision`, `otro` |
| `numero_documento` | Sí | Número del proveedor; único por proveedor + tipo |
| `subtotal` | Calculado | Suma de líneas |
| `total` | Calculado | Total de la compra |
| `observaciones` | No | Notas, máx. 500 |
| `pagado_desde_caja` | No | Si el pago se registra en caja |
| `metodo_pago` | Si paga por caja | `efectivo`, `transferencia`, `yape`, `tarjeta`, `otro` |
| `referencia_pago` | No | Nro. de operación o nota del pago externo |
| `caja_id` | Automático | Caja usada, solo si se pagó por caja |
| `egreso_caja_id` | Automático | Movimiento de egreso generado |
| `fecha_recepcion` | Automático | Día en que se recibió |
| `recepcion_user_id` | Automático | Usuario que recibió |
| `anulada_at` | Automático | Fecha de anulación |
| `motivo_anulacion` | Al anular | Motivo obligatorio |
| `estado` | Automático | `borrador`, `recibida`, `anulada` |
| `correlativo` | Automático | `CMP-00000001`; se asigna al recibir |

## 3. Campos de cada línea

| Campo | Obligatorio | Qué hace |
|---|---|---|
| `producto_presentacion_id` | Sí | Producto + presentación comprada |
| `cantidad` | Sí | Cantidad en esa presentación |
| `lote` | Sí | Código de lote del fabricante |
| `fecha_vencimiento` | Sí | No puede ser pasada al recibir |
| `costo_unitario` | No | Costo por presentación; si falta se usa el precio de compra del producto |
| `cantidad_base` | Calculado | `cantidad × factor` |
| `costo_unitario_base` | Calculado | `costo_unitario ÷ factor` |
| `costo_total` | Calculado | `cantidad × costo_unitario` |

Una compra puede tener **dos líneas del mismo producto** si cambia el lote, el vencimiento o la presentación.

Al elegir presentación y cantidad, la línea muestra en vivo la conversión a unidad base, por ejemplo `Ingreso: 2 × 100 = 200 TAB (unidad base)`. Es solo una ayuda visual: el cálculo y la validación siguen en el servidor.

---

## 4. Flujo operativo

### 4.1. Crear borrador

Ruta: `POST /admin/ingresos`.

1. Valida proveedor, documento y líneas.
2. Crea `ingreso` con `estado = borrador` y `correlativo = null`.
3. Guarda las líneas en `detalle_ingreso`.
4. Calcula `subtotal` y `total` en el servidor.
5. No crea lotes, no mueve stock, no registra kardex y no toca caja.

### 4.2. Editar borrador

Ruta: `PUT /admin/ingresos/{ingreso}`.

- Solo si `estado = borrador`.
- Reemplaza todas las líneas.
- Recalcula totales.
- No mueve stock.

### 4.3. Recibir

Ruta: `POST /admin/ingresos/{ingreso}/recibir`.

1. Bloquea la compra y verifica que siga en borrador.
2. Decide el pago:
   - `pagado_desde_caja = 1`: exige caja abierta y método de pago.
   - `pagado_desde_caja = 0`: no toca caja; guarda método y referencia.
3. Por cada línea:
   - crea o reutiliza `producto_lote`;
   - crea una capa `lote_ingreso`;
   - aumenta `producto.stock`;
   - registra kardex `INGRESO` con referencia `Compra`.
4. Genera el correlativo `CMP-00000001`.
5. Marca `estado = recibida`, `fecha_recepcion` y `recepcion_user_id`.
6. Si se pagó por caja, crea `caja_movimiento` de tipo `egreso` y actualiza `caja.total_egresos`.

Todo ocurre en una transacción: si una línea falla, no queda nada.

### 4.4. Anular

Ruta: `POST /admin/ingresos/{ingreso}/anular`.

1. Solo si `estado = recibida`.
2. Verifica que ninguna capa haya sido consumida.
3. Si se pagó por caja, exige que esa caja siga abierta.
4. Revierte capas, lotes, stock y kardex.
5. El kardex no se borra: registra `REVERSION` por cada capa.
6. Si se pagó por caja, crea un `caja_movimiento` de tipo `ingreso`.
7. Marca `estado = anulada`, `anulada_at` y `motivo_anulacion`.

Si el lote ya fue vendido o dado de baja, la anulación se rechaza: se debe usar una devolución o un ajuste.

### 4.5. Eliminar

Ruta: `DELETE /admin/ingresos/{ingreso}`.

- Solo borradores.
- Elimina cabecera y líneas.
- No toca stock ni caja.

---

## 5. Reglas de pago

```text
¿Pagado desde caja?
├─ Sí → caja abierta obligatoria + método de pago → egreso en caja
└─ No → sin movimiento de caja → método/referencia informativos
```

- El pago se decide al **recibir**, no al crear el borrador.
- Si no hay caja abierta, la compra se puede recibir como pago externo.
- Anular revierte la caja solo si el pago pasó por caja.
- No se implementa crédito ni cuentas por pagar.

### 5.1. Conciliación y reporte de pagos

- El listado de **Compras** muestra una columna **Pago** con `Caja` o `Fuera de caja` y el método usado.
- El **Reporte de Compras** permite filtrar por:
  - todas las compras;
  - pagadas desde caja;
  - pagadas fuera de caja.
- El PDF resume:
  - cantidad de compras;
  - monto total;
  - total y cantidad pagadas desde caja;
  - total y cantidad pagadas fuera de caja.
- Cada fila del reporte muestra el total, la forma de pago y el método.
- La conciliación usa `pagado_desde_caja`, `metodo_pago` y `referencia_pago`; no se recalcula desde caja.

---

## 6. Validaciones

| Regla | Dónde |
|---|---|
| Tipo y número de documento obligatorios | `StoreIngresoRequest`, `UpdateIngresoRequest` |
| Documento único por proveedor + tipo | Validación + índice `ingreso_documento_unique` |
| Cantidad múltiplo del paso | Requests de compra |
| Vencimiento no pasado | Request y `InventarioService` |
| Solo borradores se editan o eliminan | `CompraService` |
| Solo borradores se reciben | `CompraService::recibir` |
| No doble recepción | `CompraService::recibir` |
| Caja abierta si se paga por caja | `CompraService::recibir` |
| No anular con lotes consumidos | `InventarioService::revertirIngreso` |
| No anular si la caja ya se cerró | `CompraService::anular` |
| Totales calculados en servidor | `CompraService` |
| Devolución solo desde compra recibida | `DevolucionProveedorService::crear` |
| No devolver más que la capa disponible | `InventarioService::consumirDeCapa` |
| No repetir línea ni mezclar compras | Request + `DevolucionProveedorService` |
| Caja abierta si el reembolso entra a caja | `DevolucionProveedorService::crear` |
| No anular devolución si la caja se cerró | `DevolucionProveedorService::anular` |
| Restauración exacta al anular | `InventarioService::revertirConsumos` |

---

## 7. Relación con lotes y FEFO

La compra es la principal fuente de lotes:

```text
compra / ingreso
  └─ detalle_ingreso (una línea por lote/presentación)
       └─ lote_ingreso
            └─ producto_lote
                 └─ lote_consumo (ventas, atenciones, bajas)
```

- `producto.stock` sigue siendo una caché.
- La fuente real sigue siendo `lote_ingreso.cantidad_disponible_base`.
- FEFO no cambió: consume primero el lote que vence antes.
- El kardex de recepción usa `referencia_tipo = 'Compra'`.
- La anulación elimina las capas, pero el kardex de `INGRESO` y `REVERSION` permanece.

---

## 8. Endpoints

| Método | Ruta | Acción |
|---|---|---|
| GET | `/admin/ingresos` | Listado de compras |
| GET | `/admin/ingresos/create` | Formulario de borrador |
| POST | `/admin/ingresos` | Crear borrador |
| GET | `/admin/ingresos/{ingreso}` | Detalle |
| GET | `/admin/ingresos/{ingreso}/edit` | Editar borrador |
| PUT | `/admin/ingresos/{ingreso}` | Actualizar borrador |
| DELETE | `/admin/ingresos/{ingreso}` | Eliminar borrador |
| GET | `/admin/ingresos/{ingreso}/recibir` | Confirmar recepción |
| POST | `/admin/ingresos/{ingreso}/recibir` | Recibir compra |
| GET | `/admin/ingresos/{ingreso}/anular` | Confirmar anulación |
| POST | `/admin/ingresos/{ingreso}/anular` | Anular compra |
| GET | `/admin/devoluciones` | Listado de devoluciones |
| GET | `/admin/devoluciones/create` | Formulario de devolución |
| POST | `/admin/devoluciones` | Registrar devolución |
| GET | `/admin/devoluciones/{devolucion}` | Detalle |
| GET | `/admin/devoluciones/{devolucion}/anular` | Confirmar anulación |
| POST | `/admin/devoluciones/{devolucion}/anular` | Anular devolución |

Las rutas de compras usan el permiso `ingresos`; las de devoluciones, `devoluciones`.

---

## 9. Pruebas automatizadas

| Archivo | Qué cubre |
|---|---|
| `tests/Feature/CompraDocumentoTest.php` | Borrador, documento obligatorio, duplicados, edición, eliminación, estados, totales |
| `tests/Feature/CompraRecepcionTest.php` | Recepción, lotes, stock, kardex, pago en caja/externo, anulación, vencimiento, múltiples lotes |
| `tests/Feature/CompraPagoReporteTest.php` | Totales y filtros por forma de pago, conciliación en el PDF y columna de pago en el listado |
| `tests/Feature/DevolucionProveedorTest.php` | Devolución por lote, stock, kardex, reembolso, anulación y validaciones |

Comando:

```bash
php artisan test tests/Feature/CompraDocumentoTest.php
php artisan test tests/Feature/CompraRecepcionTest.php
php artisan test tests/Feature/CompraPagoReporteTest.php
php artisan test tests/Feature/DevolucionProveedorTest.php
```

Resultado esperado: **39 pruebas** entre los cuatro archivos.

---

## 10. Pruebas manuales

### 10.1. Crear borrador

1. Abrir **Compras → Nueva compra**.
2. Elegir proveedor, tipo y número de documento.
3. Agregar una línea: producto/presentación, cantidad, lote, vencimiento y costo.
4. Guardar.
5. Verificar:
   - aparece como **Borrador**;
   - correlativo vacío;
   - el stock del producto no cambió;
   - no hay movimiento de caja.

### 10.2. Documento duplicado

1. Crear otra compra con el mismo proveedor, tipo y número.
2. Debe rechazarse con el mensaje de documento duplicado.
3. Cambiar el número y guardar: debe permitirlo.

### 10.3. Recibir con pago externo

1. Crear un borrador y pulsar **Recibir**.
2. Dejar **¿Pagado desde caja?** sin marcar.
3. Confirmar.
4. Verificar:
   - estado **Recibida**;
   - correlativo `CMP-00000001`;
   - stock aumentado;
   - lote creado con su vencimiento;
   - caja sin movimientos.

### 10.4. Recibir con pago en caja

1. Abrir caja.
2. Crear un borrador y marcar **¿Pagado desde caja?**.
3. Elegir método de pago y recibir.
4. Verificar en **Caja**:
   - egreso por el total;
   - `total_egresos` actualizado.
5. Verificar en la compra:
   - caja y movimiento de egreso asociados.

### 10.5. Recibir sin caja abierta

1. Cerrar la caja.
2. Crear un borrador y marcar **¿Pagado desde caja?**.
3. Intentar recibir.
4. Debe bloquearse con un mensaje claro.
5. Desmarcar el pago desde caja y recibir: debe permitirlo como pago externo.

### 10.6. Anular compra

1. Recibir una compra.
2. Pulsar **Anular**, escribir el motivo y confirmar.
3. Verificar:
   - estado **Anulada**;
   - stock revertido;
   - lote sin disponible;
   - kardex con `INGRESO` y `REVERSION` visibles;
   - si se pagó por caja, ingreso compensatorio en caja.

### 10.7. Anular compra con lote vendido

1. Recibir una compra.
2. Vender una unidad de ese lote.
3. Intentar anular la compra.
4. Debe rechazarse. Usar devolución o ajuste.

### 10.8. Editar compra recibida

1. Abrir una compra recibida.
2. No debe aparecer la opción de editar ni eliminar.
3. Solo debe permitir ver y anular.

### 10.9. Reporte de pagos

1. Recibir una compra pagada desde caja y otra pagada fuera de caja.
2. Ir a **Reportes → Compras**.
3. Probar el filtro **Forma de pago**:
   - Todas: deben aparecer ambas;
   - Pagadas desde caja: solo la primera;
   - Pagadas fuera de caja: solo la segunda.
4. Generar la vista previa o el PDF.
5. Verificar el resumen:
   - monto total;
   - total y cantidad desde caja;
   - total y cantidad fuera de caja.
6. Verificar que la columna **Pago** muestre `Caja` o `Fuera de caja` y el método.

### 10.10. Devolución parcial al proveedor

1. Recibir una compra con al menos un lote disponible.
2. Ir a **Compras** y pulsar el botón de devolución de esa compra, o abrir **Devoluciones → Nueva devolución**.
3. Ingresar una cantidad menor o igual al disponible.
   La columna **Disponible** muestra la presentación completa y su sobrante en unidad base (por ejemplo `3 Cajas x100 (sobran 90 TAB)`), y el campo de cantidad muestra su equivalencia base (`2 × 100 = 200 TAB`). Es solo una guía visual; la validación sigue en el servidor.
4. Elegir si el reembolso entra a caja y el método.
5. Registrar.
6. Verificar:
   - correlativo `DVP-00000001`;
   - stock y lote disminuidos;
   - kardex `DEVOLUCION` negativo;
   - si hubo reembolso, ingreso en caja.

### 10.11. Anular devolución

1. Abrir una devolución registrada.
2. Pulsar anular, escribir el motivo y confirmar.
3. Verificar:
   - estado **Anulada**;
   - stock y lote restaurados;
   - kardex `DEVOLUCION` y `REVERSION` visibles;
   - si hubo reembolso, egreso compensatorio en caja.

---

## 11. Devolución a proveedor

Ruta: `/admin/devoluciones`.

### 11.1. Campos

| Campo | Obligatorio | Qué hace |
|---|---|---|
| `ingreso_id_ingreso` | Sí | Compra recibida de la que se devuelve |
| `fecha` | No | Fecha de la devolución; por defecto hoy |
| `motivo` | Sí | Daño, vencimiento, error de pedido, retiro acordado |
| `reembolsado_a_caja` | No | Si el dinero ingresa a caja |
| `metodo_pago` | Si reembolsa | Efectivo, transferencia, Yape, tarjeta u otro |
| `referencia_pago` | No | Nro. de operación o nota |
| `lineas[].detalle_ingreso_id` | Sí | Línea exacta de la compra |
| `lineas[].cantidad` | Sí | Cantidad en la presentación comprada |

### 11.2. Flujo

1. Se elige una compra **recibida**.
2. Se cargan sus líneas con lote, vencimiento y disponible en la capa.
3. Se ingresan cantidades mayores a cero.
4. Se decide si el reembolso entra a caja.
5. Al registrar:
   - descuenta la **capa exacta** del lote, no aplica FEFO;
   - descuenta `producto_lote` y `producto.stock`;
   - registra `lote_consumo` con referencia `DevolucionProveedor`;
   - registra kardex `DEVOLUCION`;
   - calcula el total con el costo del lote;
   - si hay reembolso, crea un ingreso en caja.
6. Genera el correlativo `DVP-00000001`.

### 11.3. Anulación

- Solo devoluciones en estado `registrada`.
- Restaura capa, lote y stock.
- Elimina `lote_consumo` y registra `REVERSION`; el kardex `DEVOLUCION` permanece.
- Si hubo reembolso, crea un egreso compensatorio.
- Se bloquea si la caja del reembolso ya está cerrada.
- Guarda `anulada_at` y `motivo_anulacion`.

### 11.4. Reglas

- No se puede devolver más de lo disponible en la capa.
- No se puede devolver de compras en borrador o anuladas.
- No se pueden mezclar líneas de otra compra.
- No se puede repetir la misma línea.
- La cantidad debe ser múltiplo del paso de la presentación.
- No se emite nota de crédito: solo se registra el movimiento interno.

---

## 12. Mejoras futuras

- Cuentas por pagar, si el negocio habilita compras al crédito.
- Nota de crédito de proveedor.
- Reporte específico de devoluciones a proveedor.

> Los ajustes de inventario se documentan en `modulo-ajustes-inventario.md`, el kardex append-only en `kardex-append-only.md` y los reportes en `modulo-reportes.md`.

---

## 13. Archivos principales

| Función | Archivo |
|---|---|
| Controlador de compras | `app/Http/Controllers/Admin/IngresoController.php` |
| Lógica de compra | `app/Services/CompraService.php` |
| Controlador de devoluciones | `app/Http/Controllers/Admin/DevolucionProveedorController.php` |
| Lógica de devolución | `app/Services/DevolucionProveedorService.php` |
| Consumo por capa específica | `app/Services/InventarioService.php` (`consumirDeCapa`, `revertirConsumos`) |
| Modelos | `Ingreso`, `DetalleIngreso`, `DevolucionProveedor`, `DetalleDevolucionProveedor` |
| Requests | `StoreIngresoRequest`, `UpdateIngresoRequest`, `RecibirCompraRequest`, `AnularCompraRequest`, `StoreDevolucionProveedorRequest`, `AnularDevolucionProveedorRequest` |
| Vistas | `resources/views/admin/ingresos/`, `resources/views/admin/devoluciones/` |
| Migraciones | `2026_06_22_000001_add_compra_fields_to_ingreso_table.php`, `2026_06_23_000001_create_devolucion_proveedor_tables.php` |
| Pruebas | `CompraDocumentoTest`, `CompraRecepcionTest`, `CompraPagoReporteTest`, `DevolucionProveedorTest` |

---

*Si cambia un campo, una validación o una regla de pago, actualizar este documento y `plan-mejora-compras-proveedores.md`.*
