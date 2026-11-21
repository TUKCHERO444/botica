# Módulo Caja — Control de efectivo y arqueo

> **Última actualización:** 21/11/2026
> **Estado:** implementado. Caja separa efectivo de digital, registra un movimiento por método de pago y cierra con arqueo (conteo rápido o detallado, revisión, motivo y observación de diferencia).
> **Referencias:** `modulo-metodos-pago.md`, `modulo-ventas.md`, `modulo-compras-proveedores.md`, `modulo-devoluciones-cliente.md`, `analisis-caja-y-recomendaciones.md`.

---

## 1. Idea general

La caja controla el **efectivo físico** del negocio. Los cobros digitales (Yape, Plin, tarjeta, transferencia) se registran y se reportan, pero **no se cuentan en el cajón**.

Reglas centrales:

1. Una caja es un turno: fondo inicial, movimientos, arqueo y cierre.
2. Solo puede existir **una caja abierta** a la vez.
3. Cada venta o atención genera **un movimiento por método de pago**. Una venta mixta de S/ 100 (S/ 60 efectivo + S/ 40 Yape) genera dos filas.
4. Los movimientos de caja son **append-only**: no se editan ni se eliminan. Las correcciones se hacen con movimientos compensatorios.
5. El cierre es un **arqueo**: se cuenta el efectivo, se revisa la diferencia y se exige motivo y observación si no es cero.
6. Las cajas cerradas son inmutables. Una devolución posterior se registra en la caja abierta actual, referenciando la caja original.

---

## 2. Flujo

### 2.1. Apertura

```text
POST /admin/caja/apertura
  - monto_inicial (fondo de sencillo)
  - observacion_apertura (opcional)
  - estado = abierta
  - abierta_unica = 1 (índice único)
```

Si ya hay una caja abierta, se rechaza con un mensaje claro. La restricción se valida en el servicio y en la base de datos.

### 2.2. Operación

| Operación | Movimiento de caja | Afecta efectivo |
|---|---|---|
| Venta con efectivo | ingreso por el importe | Sí |
| Venta con Yape/Plin/tarjeta/transferencia | ingreso por el importe | No |
| Venta mixta | un ingreso por cada método | Solo la parte en efectivo |
| Atención | igual que venta | Igual que venta |
| Compra pagada desde caja | egreso | Según `metodo_pago` de la compra |
| Devolución a proveedor reembolsada | ingreso | Según `metodo_pago` de la devolución |
| Egreso manual | egreso | Según el método elegido |
| Ingreso manual | ingreso | Según el método elegido |
| Anulación de venta | egreso compensatorio | Según el movimiento original |

Un egreso en efectivo **no puede superar el efectivo disponible**. Si el método es digital, no consume el cajón.

### 2.3. Ingresos y egresos manuales

Rutas:

```text
GET  /admin/caja/ingreso   -> formulario
POST /admin/caja/ingreso   -> registra ingreso manual
GET  /admin/caja/egreso    -> formulario
POST /admin/caja/egreso    -> registra egreso manual
```

Categorías de ingreso:

- Aporte de sencillo
- Cobro de crédito
- Ajuste positivo
- Otro ingreso

Categorías de egreso:

- Luz, agua, internet, alquiler, limpieza, movilidad, útiles, mantenimiento
- Otro gasto
- Retiro a banco
- Retiro del propietario
- Ajuste negativo
- Otro egreso

Los movimientos de aporte/retiro se marcan con naturaleza `transferencia`; los ajustes con `ajuste`; el resto con `operativo`.

### 2.4. Cierre y arqueo

```text
GET  /admin/caja/cierre    -> modal de conteo
POST /admin/caja/cierre    -> cierra y guarda el arqueo
```

El modal muestra el **efectivo esperado desde el inicio** y permite dos formas de contar:

- **Conteo rápido:** se ingresa directamente el total contado.
- **Conteo detallado:** se ingresan cantidades por billetes y monedas; el sistema calcula el total.

El cierre tiene dos pasos:

1. **Conteo:** resumen del turno, modo de conteo y total contado.
2. **Revisión:** muestra esperado, contado y diferencia antes de confirmar. Si hay diferencia, exige `motivo_diferencia` y `observacion_cierre`. Si el contado es cero, exige una confirmación explícita.

Al cerrar:

```text
efectivo_esperado = monto_inicial
                  + total_ingresos_efectivo
                  - total_egresos_efectivo

efectivo_contado  = monto rápido o suma de denominaciones
diferencia        = efectivo_contado - efectivo_esperado
monto_final       = efectivo_contado
```

Se guardan `motivo_diferencia`, `observacion_cierre`, `arqueo_user_id`, `arqueo_at` y `arqueo_detalle` (JSON con el conteo por denominación cuando se usa el modo detallado).

### 2.5. Historial

```text
GET /admin/caja/historial
GET /admin/caja/historial/{caja}
```

Muestra fondo, ingresos/egresos de efectivo y digital, efectivo esperado, contado, diferencia, observación, usuarios y movimientos con método y categoría. Las cajas cerradas no se editan.

---

## 3. Modelo de datos

### 3.1. Tabla `caja`

| Columna | Tipo | Uso |
|---|---|---|
| `id_caja` | integer PK | Identificador |
| `monto_inicial` | decimal(10,2) | Fondo inicial |
| `total_ingresos` / `total_egresos` | decimal(12,2) | Totales de todos los métodos |
| `total_ingresos_efectivo` / `total_egresos_efectivo` | decimal(12,2) | Movimientos que afectan el cajón |
| `total_ingresos_digital` / `total_egresos_digital` | decimal(12,2) | Movimientos digitales |
| `monto_final` | decimal(10,2) | Efectivo contado al cierre |
| `efectivo_esperado` | decimal(12,2) | Fórmula del arqueo |
| `efectivo_contado` | decimal(12,2) | Conteo físico |
| `diferencia` | decimal(12,2) | Contado − esperado |
| `motivo_diferencia` | string(40) nullable | Faltante, sobrante, error de vuelto, gasto no registrado u otro |
| `arqueo_detalle` | JSON | Conteo por denominación |
| `observacion_apertura` / `observacion_cierre` | text | Novedades y justificación de diferencia |
| `estado` | abierta/cerrada | Estado |
| `abierta_unica` | boolean nullable, unique | Garantiza una sola caja abierta |
| `apertura_user_id` / `cierre_user_id` / `arqueo_user_id` | FK users | Responsables |
| `apertura_at` / `cierre_at` / `arqueo_at` | datetime | Fechas |

Los totales se recalculan desde `caja_movimiento` al registrar movimientos y al cerrar. La fuente de verdad son los movimientos, no los incrementos manuales.

### 3.2. Tabla `caja_movimiento`

| Columna | Tipo | Uso |
|---|---|---|
| `id_movimiento` | integer PK | Identificador |
| `caja_id_caja` | FK caja | Caja |
| `tipo` | ingreso/egreso | Sentido |
| `monto` | decimal(10,2) | Importe |
| `descripcion` | text nullable | Detalle |
| `referencia_tipo` | enum existente | Documento origen (`venta`, `atencion`, `compra`, `devolucion_compra`, `egreso_manual`) |
| `referencia_id` | integer nullable | ID del documento |
| `metodo_pago_id` | FK metodo_pago nullable | Método usado |
| `afecta_efectivo` | boolean | Snapshot histórico del método |
| `categoria` | string(40) nullable | Categoría de negocio |
| `naturaleza` | string(20) | `operativo`, `transferencia`, `ajuste`, `reversion` |
| `movimiento_origen_id` | integer nullable | Movimiento compensado |
| `caja_origen_id` | integer nullable | Caja original en una devolución posterior |
| `comprobante` | string(100) nullable | Boleta/factura/recibo |
| `user_id` | FK users | Responsable |
| `created_at` | timestamp | Fecha/hora |

`afecta_efectivo` se guarda como snapshot: si mañana se cambia la configuración del método, el histórico no se altera.

### 3.3. Tabla `metodo_pago`

Se agregó `afecta_efectivo` (boolean). Efectivo = `true`; Yape/Plin/BCP/tarjeta = `false` por defecto. Es configurable desde el módulo de métodos de pago.

---

## 4. Integración con otros módulos

### 4.1. Ventas y atenciones

`CajaService::registrarPagos()` crea un movimiento por cada fila de `pagos`, toma el método y su `afecta_efectivo`, y recalcula los totales. La caja se bloquea dentro de la transacción para evitar que una venta se registre mientras se cierra.

### 4.2. Compras y devoluciones a proveedor

`CompraService` y `DevolucionProveedorService` usan `CajaService` para registrar el egreso/ingreso. La afectación se deriva del método guardado en el documento (`efectivo` afecta; `transferencia`, `yape`, `tarjeta` no).

### 4.3. Anulación de ventas

`AnulacionVentaService` delega en `CajaService::reembolsarVenta()`:

- Si la caja original está abierta, el egreso se registra allí.
- Si está cerrada, el egreso se registra en la caja abierta actual y se guarda `caja_origen_id`.
- Si no hay ninguna caja abierta, la anulación se bloquea con un mensaje claro.
- Se crea un egreso por cada método original, conservando efectivo/digital.

### 4.4. Anulación de atenciones

`AnulacionAtencionService` delega en `CajaService::reembolsarAtencion()`:

- Las atenciones confirmadas no se editan ni se eliminan; se anulan con motivo.
- Si la caja original está abierta, el egreso compensatorio se registra allí.
- Si está cerrada, se registra en la caja abierta actual y se guarda `caja_origen_id`.
- Se revierte el neto por método (`anulacion_atencion` / `reversion`), conservando los movimientos originales.
- El desglose de `pago` se conserva para auditoría, pero los reportes excluyen la atención anulada.

---

## 5. Archivos principales

```text
app/
  Http/Controllers/Admin/CajaController.php
  Http/Requests/Admin/
    StoreAperturaRequest.php
    StoreIngresoCajaRequest.php
    StoreEgresoRequest.php
    CerrarCajaRequest.php
  Models/
    Caja.php
    CajaMovimiento.php
    MetodoPago.php
  Services/
    CajaService.php
    AnulacionVentaService.php
    CompraService.php
    DevolucionProveedorService.php
database/migrations/
  2026_11_21_000001_add_arqueo_fields_to_caja_table.php
  2026_11_21_000002_add_afecta_efectivo_to_metodo_pago_table.php
  2026_11_21_000003_add_efectivo_fields_to_caja_movimiento_table.php
resources/
  views/admin/caja/{index,apertura,ingreso,egreso,cierre,historial,historial-show,full}.blade.php
  js/admin/caja/cierre.js
routes/admin.php
```

---

## 6. Rutas

```text
GET  /admin/caja
GET  /admin/caja/apertura
POST /admin/caja/apertura
GET  /admin/caja/ingreso
POST /admin/caja/ingreso
GET  /admin/caja/egreso
POST /admin/caja/egreso
GET  /admin/caja/cierre
POST /admin/caja/cierre
GET  /admin/caja/historial
GET  /admin/caja/historial/{caja}
```

Todas protegidas con el permiso `caja`.

---

## 7. Migración de datos históricos

La migración `2026_11_21_000003` separa los movimientos históricos de ventas/atenciones usando la tabla `pago`:

- Si una venta tenía un solo método, actualiza `metodo_pago_id`, `afecta_efectivo` y `categoria`.
- Si tenía varios, conserva la primera fila y crea una nueva por cada método adicional.
- Para compras y devoluciones a proveedor, toma el método del documento.
- Para anulaciones históricas, marca `categoria = anulacion_venta` y `naturaleza = reversion`.

Si la suma de `pago` no coincide con el movimiento original, no lo modifica; queda como efectivo y debe revisarse manualmente.

---

## 8. Pruebas

`tests/Feature/CajaTest.php` cubre:

- apertura y caja única;
- egreso con método y categoría;
- egreso que supera el efectivo disponible;
- cierre con arqueo y diferencia;
- exigencia de motivo y observación cuando hay diferencia;
- confirmación explícita para cierre en cero;
- conteo rápido y por denominaciones;
- separación efectivo/digital;
- historial.

`tests/Feature/PagoVentaAtencionTest.php` cubre:

- un movimiento de caja por método;
- totales de efectivo y digital;
- bloqueo de edición/eliminación de atenciones;
- anulación de atención con reversión de caja y conservación del movimiento original.

`tests/Feature/AnulacionVentaTest.php` cubre:

- anulación con caja original cerrada y reembolso en la caja actual;
- bloqueo cuando no hay caja abierta.

---

## 9. Consideraciones

- **Inmutabilidad:** una caja cerrada no se modifica. Las correcciones se registran en la caja actual.
- **Concurrencia:** el cierre y los movimientos bloquean la fila de caja; el índice `abierta_unica` impide dos aperturas simultáneas.
- **Totales:** se recalculan desde los movimientos. Si se edita una caja por SQL, los totales pueden desincronizarse; usar `CajaService::recalcularTotales()`.
- **Permisos:** hoy existe un único permiso `caja`. La separación fina (`caja.abrir`, `caja.arquear`, `caja.autorizar`) queda para una fase posterior.
- **Caché:** `CacheaPanel` es no-op; no hay caché real que invalidar.
- **Deuda pendiente:** conciliación automática (`caja:conciliar`), reportes de flujo de caja, diferencias por usuario, cuentas por pagar y fiado/cobranza. Ver `analisis-caja-y-recomendaciones.md`.
