# Módulo de atenciones

> **Última actualización:** 21/11/2026
> **Estado:** atención confirmada + anulación con motivo + ticket imprimible.
> **Referencias:** `modulo-ventas.md`, `modulo-metodos-pago.md`, `modulo-caja.md`, `kardex-append-only.md`.

---

## 1. Idea general

Una atención combina **servicios y productos**, nace **confirmada** y solo puede terminar **anulada**. No se edita ni se elimina: si hubo un error, se anula con motivo y se conserva el detalle original para auditoría.

```text
atencion
  ├─ estado: confirmada → anulada
  ├─ correlativo A001-XXXXXXXX
  ├─ atencion_producto / atencion_servicio (intactos)
  ├─ lote_consumo (se revierte al anular)
  ├─ kardex ATENCION + REVERSION
  └─ caja: ingreso por método + egreso compensatorio
```

---

## 2. Crear atención

Ruta: `POST /admin/atenciones`.

1. Exige caja abierta.
2. Resuelve o crea el cliente (incluido el genérico o alta rápida por RENIEC).
3. Resuelve productos por presentación y suma servicios; calcula el total.
4. Crea `atencion` con `estado = confirmada` y correlativo `A001-XXXXXXXX`.
5. Por cada producto llama a `InventarioService::consumir()`:
   - FEFO por vencimiento;
   - descuenta `producto.stock`;
   - kardex `ATENCION` con lote.
6. Registra un ingreso en caja por cada método de pago (`CajaService::registrarPagos`).
7. Guarda el desglose en `pago` con `referencia_tipo = 'Atencion'`.
8. Redirige al ticket imprimible con `?nueva=1`.

Los servicios no descuentan stock. Su precio se guarda en `atencion_servicio.subtotal` y en el ticket se muestran como cantidad `1`.

### Stock vendible y alertas en el formulario

El selector de productos solo lista presentaciones con **stock vendible** (lotes `disponible`, con cantidad y no vencidos), la misma condición de `InventarioService::consumir()`. Cada opción incluye stock vendible, stock mínimo, alerta (`Bajo mínimo` / `Sin stock vendible`) y la equivalencia de esa presentación: con 499 tabletas, `Caja x100` muestra `4 Cajas x100 (sobran 99 TAB)` y no 499 cajas. Al cambiar producto o cantidad, bajo el selector se muestra el disponible y la conversión a unidad base (`2 × 100 = 200 TAB`), además de la advertencia correspondiente. Las presentaciones son equivalentes del mismo stock y no se suman entre sí. Son avisos visuales: el bloqueo real lo aplica `StoreAtencionRequest` y, en última instancia, `consumir()`.

---

## 3. Ticket imprimible

Ruta: `GET /admin/atenciones/{atencione}/ticket` (permiso `atenciones`).

- Comparte la vista base `resources/views/admin/tickets/base.blade.php` con el ticket de venta.
- El ancho de papel, la moneda, los datos del negocio y el pie salen de `config/ticket.php`.
- Se abre automáticamente el diálogo de impresión (`window.print()`), tanto al guardar como al reimprimir.
- Contenido:
  - `TICKET DE ATENCIÓN`;
  - correlativo, fecha, cliente, DNI y **Atendido por**;
  - observación, si existe;
  - sección **PRODUCTOS** con `Cant.`, `Descripción`, `P. Unit.` e `Importe`;
  - sección **SERVICIOS** con el mismo detalle;
  - resumen de productos, servicios y `TOTAL`;
  - desglose de pagos por método.
- Cliente genérico (`DNI 00000001` / nombre `SIN IDENTIFICAR`): se imprime `CLIENTE GENERAL` y DNI `---`.
- Si la atención está anulada, el ticket muestra `*** ANULADA ***` con fecha, motivo y usuario.

Accesos: icono de impresora en el listado y botón **Imprimir ticket** en el modal de detalle.

---

## 4. Editar y eliminar

**Bloqueados.** `edit`, `update` y `destroy` responden `422`:

> Las atenciones confirmadas no se editan. Si hubo un error, anula la atención con un motivo.

El listado ya no ofrece acciones de edición ni eliminación. Así el kardex, los pagos y el detalle no se reescriben.

---

## 5. Anular atención

Rutas:

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `/admin/atenciones/{atencione}/anular` | Modal con motivo obligatorio |
| `POST` | `/admin/atenciones/{atencione}/anular` | Ejecuta la anulación |

`AnulacionAtencionService`:

1. Bloquea la atención y rechaza una segunda anulación.
2. Revierte lotes y stock con `InventarioService::revertirConsumos('Atencion', ...)`.
3. Registra kardex `REVERSION`.
4. Reembolsa la caja con `CajaService::reembolsarAtencion()`:
   - si la caja original está abierta, revierte allí;
   - si está cerrada, registra el egreso compensatorio en la caja abierta actual.
5. Marca `estado = anulada`, `anulada_at`, `anulada_user_id` y `motivo_anulacion`.
6. Conserva productos, servicios y pagos originales.

Categoría de caja: `anulacion_atencion`, naturaleza `reversion`.

---

## 6. Efecto en otros módulos

| Módulo | Efecto |
|---|---|
| Kardex | Conserva `ATENCION` y agrega `REVERSION`; nunca se borra |
| Caja | Ingreso original + egreso compensatorio |
| Pagos | Se conserva el desglose histórico |
| Reporte de atenciones | Excluye anuladas |
| Rentabilidad y servicios | Excluyen anuladas |
| Reporte de clientes y dashboard | Excluyen anuladas |
| Perfil del cliente | Solo muestra atenciones confirmadas |
| Ticket | Muestra `*** ANULADA ***`, fecha, usuario y motivo |

---

## 7. Campos agregados a `atencion`

| Campo | Uso |
|---|---|
| `correlativo` | `A001-XXXXXXXX`, único |
| `estado` | `confirmada` o `anulada` |
| `anulada_at` | Fecha y hora de la anulación |
| `anulada_user_id` | Usuario que anuló |
| `motivo_anulacion` | Motivo obligatorio |

---

## 8. Pruebas

- `tests/Feature/AtencionTicketTest.php`: ticket con productos, servicios, pagos, cliente genérico, autoimpresión, bloqueo de edición y anulación.
- `tests/Feature/PagoVentaAtencionTest.php`: pagos mixtos, edición/eliminación bloqueadas y reversión de caja.
- `tests/Feature/KardexAppendOnlyTest.php`: kardex `ATENCION` + `REVERSION`.
- `tests/Feature/AtencionConReniecTest.php`: alta de atención y consumo de stock.
