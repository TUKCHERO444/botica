# Módulo de ventas

> **Última actualización:** 23/11/2026
> **Estado:** venta confirmada + anulación con motivo + pago obligatorio con desglose (tarea B1 del `plan-fase-8-mejoras.md`) + listado diario por usuario e historial con filtros.
> **Referencias:** `flujo-inventario-lotes-fefo.md`, `modulo-caja.md`, `kardex-append-only.md`.

---

## 1. Idea general

Una venta nace **confirmada** y solo puede terminar **anulada**. No se edita ni se elimina: si hubo un error, se anula con motivo y se conserva el detalle original para auditoría.

```text
venta
  ├─ estado: confirmada → anulada
  ├─ detalle_venta (intacto)
  ├─ lote_consumo (se revierte al anular)
  ├─ kardex VENTA + REVERSION
  └─ caja: ingreso + egreso compensatorio
```

### 1.1 Listado del día e historial

- `/admin/ventas` muestra **solo las ventas de hoy** (`created_at` del día en `America/Lima`) registradas por el usuario autenticado (`user_id`). Incluye sus anuladas y muestra contador/total de confirmadas.
- `/admin/ventas/historial` conserva el **listado total** y agrega filtros: `fecha` exacta, `desde`/`hasta`, `user_id`, `estado` y búsqueda por correlativo/cliente. Sin filtros equivale al listado histórico anterior.
- El paso de "hoy" al historial es **automático**: al cambiar el día, el filtro de fecha deja fuera los registros anteriores. No hay job, tabla de archivo ni columnas nuevas.
- `Venta` expone los scopes `delDia`, `deUsuario`, `entreFechas`, `deEstado` y `buscar` (`app/Models/Venta.php`).
- Ambas vistas comparten `admin/ventas/_tabla.blade.php`; "Registrado por" y la columna de fecha/hora aparecen en el historial. Paginación de 10 con `withQueryString()`.
- Detalle y pruebas: [`plan-listado-diario-ventas-atenciones.md`](plan-listado-diario-ventas-atenciones.md).

---

## 2. Crear venta

Ruta: `POST /admin/ventas`.

1. Exige caja abierta.
2. Resuelve o crea el cliente (incluido el genérico o alta rápida por RENIEC).
3. Convierte cada presentación a unidad base y calcula subtotales y total.
4. Crea `venta` con correlativo `T001-XXXXXXXX` y `estado = confirmada`.
5. Por cada línea llama a `InventarioService::consumir()`:
   - FEFO por vencimiento;
   - una o varias capas;
   - `lote_consumo` por capa;
   - descuenta `producto.stock`;
   - kardex `VENTA` con lote.
6. Registra un ingreso en caja por el total.
7. Redirige al ticket imprimible.

### Stock vendible y alertas en el formulario

El buscador de presentaciones (`GET /admin/ventas/presentaciones/search`) solo ofrece productos con **stock vendible**: suma de capas de lotes `disponible`, con cantidad y con `fecha_vencimiento >= hoy`. Es la misma condición que aplica `InventarioService::consumir()`, así que no se puede elegir un producto cuyo stock esté íntegramente vencido.

Cada resultado devuelve, además de `stock` (físico):

- `stock_vendible` / `stock_vendible_formateado`;
- `stock_desglose`: desglose del stock vendible en presentaciones equivalentes (por ejemplo `4 Cajas x100 + 9 Blísters x10 + 9 Tabletas`);
- `stock_equivalencias`: máximo vendible y sobrante por cada presentación;
- `presentacion_equivalencia`: texto de la presentación consultada (por ejemplo `4 Cajas x100 (sobran 99 TAB)`);
- `presentacion_maximo_formateado` y `presentacion_sobrante_formateado`;
- `stock_minimo` / `stock_minimo_formateado`;
- `dias_para_vencer` del lote más próximo;
- `alerta_stock` (`Bajo mínimo` o `Sin stock vendible`);
- `alerta_vencimiento` (`Vence en N días` dentro de los próximos 30).

La UI muestra esos avisos en el desplegable y en el chip del producto seleccionado. Cada presentación indica cuánto se puede vender realmente: con 499 tabletas, `Caja x100` muestra `4 Cajas x100 (sobran 99 TAB)`, no 499 cajas. Al cambiar la cantidad, la fila agrega la conversión a unidad base (`2 × 100 = 200 TAB`) y advierte si la línea supera el stock vendible o si dejará el producto por debajo del mínimo. Las presentaciones son **equivalentes del mismo stock**, nunca existencias separadas que se sumen. Son advertencias visuales: la validación que bloquea sigue siendo la del servidor (`StoreVentaRequest` + `consumir`).

### Registro de pago

El pago es **obligatorio** y se registra en el mismo formulario:

- Se listan solo los métodos activos de `metodo_pago` (Efectivo, Yape, Plin, BCP, etc.) como **botones seleccionables**.
- **Pago único (por defecto):** se elige un método y se asume que cubre el **total**; no se solicita importe. El cajero verifica el pago en vivo.
- **Pago mixto:** el botón **Pago mixto** despliega los importes para repartir el total entre dos o más métodos. Mientras está activo, los botones de métodos se bloquean y se exige que la suma sea exactamente el total. **Volver a pago único** desbloquea los botones y limpia el desglose.
- La UI muestra en vivo el total, lo pagado y cuánto **falta**; el botón **Resto** completa el pendiente con el método elegido en modo mixto.
- Cada importe se guarda en `pago` con `referencia_tipo = 'Venta'`, `referencia_id` y `metodo_pago_id`.
- El detalle de la venta y el ticket muestran el desglose.

La caja registra **un movimiento por cada método de pago**, con `metodo_pago_id` y `afecta_efectivo`, y el arqueo separa efectivo de digital. Ver [`modulo-metodos-pago.md`](modulo-metodos-pago.md).

### Contenido del ticket

- **Cliente sin identificar** (DNI `00000001` / nombre `SIN IDENTIFICAR`): se imprime `CLIENTE GENERAL` y el DNI como `---`.
- El responsable se imprime con la etiqueta **Atendido por**.
- El detalle usa cuatro columnas: **Cant.**, **Descripción**, **P. Unit.** e **Importe**; la cantidad y el precio unitario ya no se esconden dentro de la descripción.
- Bajo la tabla se indica la moneda de los precios y se resumen los **Artículos** y el **TOTAL**.
- Se incluye el desglose de pagos por método.
- La vista comparte la base `resources/views/admin/tickets/base.blade.php` con el ticket de atención y abre automáticamente el diálogo de impresión.

---

## 3. Editar y eliminar

**Bloqueados.** `edit`, `update` y `destroy` responden `422`:

> Las ventas confirmadas no se editan. Si hubo un error, anula la venta con un motivo.

El listado ya no ofrece acciones de edición ni eliminación. Así el kardex y el detalle no se reescriben.

---

## 4. Anular venta

Rutas:

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `/admin/ventas/{venta}/anular` | Modal con motivo obligatorio |
| `POST` | `/admin/ventas/{venta}/anular` | Ejecuta la anulación |

Reglas:

- Motivo obligatorio, 3 a 255 caracteres.
- No se puede anular dos veces.
- Si la caja donde se cobró está cerrada, se bloquea.
- El stock y los lotes se restauran con `revertirConsumos('Venta', ...)`.
- Se registra kardex `REVERSION` por cada capa.
- Se crea un egreso compensatorio en la caja del cobro y se actualiza `total_egresos`.
- `venta.estado = anulada`, con `anulada_at`, `anulada_user_id` y `motivo_anulacion`.
- El detalle de la venta no se modifica.

La caja conserva el ingreso original y el egreso compensatorio: el saldo neto queda en cero para esa venta.

---

## 5. Efecto en otros módulos

| Módulo | Efecto |
|---|---|
| Kardex | Conserva `VENTA` y agrega `REVERSION`; nunca se borra |
| Caja | Ingreso original + egreso compensatorio |
| Reporte de ventas | Excluye anuladas |
| Rentabilidad | Excluye anuladas |
| Reporte de clientes | Excluye importes de anuladas |
| Reporte general y dashboard | Excluyen anuladas |
| Ticket | Muestra `*** ANULADA ***`, fecha, usuario y motivo |
| Detalle de venta | Muestra badge **Anulada** y datos de la anulación |

---

## 6. Devolución de cliente

No existe un documento aparte. El flujo completo está en [`modulo-devoluciones-cliente.md`](modulo-devoluciones-cliente.md).

- **Devolución total:** anular la venta. Restaura stock/lote, revierte caja y deja kardex `REVERSION`.
- **Producto no apto:** después de anular, registrar un ajuste negativo (`dano`/`vencimiento`) por el lote restaurado.
- **Devolución parcial:** no es nativa. Opción A: anular + nueva venta por lo que el cliente conserva. Opción B: `ajuste_positivo` por lote + egreso manual opcional.
- La anulación **ya devuelve el stock**: no registrar además un `ajuste_positivo` por las mismas unidades.

---

## 7. Campos agregados a `venta`

| Campo | Uso |
|---|---|
| `estado` | `confirmada` o `anulada` |
| `anulada_at` | Fecha y hora de la anulación |
| `anulada_user_id` | Usuario que anuló |
| `motivo_anulacion` | Motivo obligatorio |

---

## 8. Pruebas

`tests/Feature/AnulacionVentaTest.php` cubre:

- Reversión de stock, lote, caja y kardex.
- Motivo obligatorio.
- Doble anulación bloqueada.
- Caja cerrada.
- Exclusión de reportes.
- Marca de anulación en el ticket.
- Estado y acción en el listado.

También se actualizaron `VentaFraccionadaTest`, `KardexAppendOnlyTest` y `LotesFefoTest` para el nuevo flujo.
