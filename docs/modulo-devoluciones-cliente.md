# Devoluciones de cliente — análisis y flujo operativo (C1)

> **Última actualización:** 19/11/2026
> **Estado:** implementado sin módulo nuevo (C1). Devolución total con anulación de venta (B1); reingreso condicionado y reembolso parcial con ajustes por lote y caja.
> **Referencias:** `modulo-ventas.md`, `modulo-ajustes-inventario.md`, `modulo-caja.md`, `kardex-append-only.md`, `plan-fase-8-mejoras.md`.

---

## 1. Decisión

No se implementa un documento `devolucion_cliente`. La lógica ya existe y se reutiliza:

```text
Devolución total
  └─ anular venta (B1)
       ├─ caja: egreso compensatorio
       ├─ stock/lote: REVERSION exacta
       └─ venta: estado anulada + motivo

Reingreso condicionado
  └─ ajuste por lote
       ├─ apto: ajuste_positivo
       └─ no apto: dano / vencimiento / otro

Reembolso parcial
  └─ egreso manual en caja (opcional)
```

Ventajas: no duplica la reversión de B1, no agranda el modelo de datos y mantiene el control por lote de los ajustes.

---

## 2. Qué reconoce el sistema hoy

| Evento | Dónde queda | ¿Vinculado a la venta? | ¿Es una devolución para el sistema? |
|---|---|---|---|
| Devolución total | `venta.estado = anulada`, `anulada_at`, `anulada_user_id`, `motivo_anulacion` | Sí | **Sí** |
| Reversión de stock/lote | `kardex_movimientos` tipo `REVERSION` | Sí, por `referencia_id` | Sí, como consecuencia |
| Reembolso total | `caja_movimiento` tipo `egreso`, `referencia_tipo = venta` | Sí | Sí |
| Reingreso apto | `ajuste_inventario` tipo `ajuste_positivo`, kardex `AJUSTE` | **No** | No: es corrección de inventario |
| Producto no apto | `ajuste_inventario` tipo `dano`/`vencimiento`/`otro` | **No** | No: es corrección de inventario |
| Reembolso parcial | `caja_movimiento` tipo `egreso`, `referencia_tipo = egreso_manual` | **No** | No: es un egreso de caja |

No existe tabla `devolucion_cliente`, ni campos `condicion`, `reingresa`, `reembolsado` o `total_devuelto` en la venta. El sistema **solo reconoce como devolución la venta anulada**.

---

## 3. Devolución total (flujo reconocido)

### 3.1. Cuándo usarla

- El cliente devuelve **todas** las unidades de la venta.
- Se desea revertir el cobro completo.
- La venta está confirmada y no fue anulada antes.

### 3.2. Procedimiento

1. Abrir el listado de ventas y ubicar el correlativo.
2. Pulsar **Anular venta**.
3. Escribir el motivo (obligatorio, 3–255 caracteres). Ej.: `Devolución total del cliente`.
4. Confirmar.

### 3.3. Efectos automáticos

| Área | Efecto |
|---|---|
| Venta | `estado = anulada`, fecha, usuario y motivo |
| Stock | Se restaura la cantidad exacta vendida |
| Lote/capa | Se devuelve a la capa original consumida |
| Costo | Se conserva el costo original de la capa |
| Kardex | `VENTA` original + `REVERSION` por cada capa |
| Caja | Egreso compensatorio por el total, en la caja del cobro |
| Reportes | La venta deja de sumar |
| Ticket | Muestra `*** ANULADA ***` y motivo |

### 3.4. Reglas y bloqueos

- No se puede anular dos veces.
- Si la caja donde se cobró está **cerrada**, la anulación se bloquea.
- Si la venta no tuvo movimiento de caja (dato legado), se revierte el stock sin egreso.
- La venta conserva su detalle original; no se borra.

### 3.5. Advertencia importante

La anulación **ya devuelve el stock**. No registrar además un `ajuste_positivo` por las mismas unidades: se duplicaría el inventario.

Si el producto **no es apto** para volver a la venta, después de anular se registra un **ajuste negativo** (`dano`, `vencimiento` u `otro`) por el lote restaurado.

---

## 4. Devolución parcial

No existe un documento nativo de devolución parcial. Hay dos caminos operativos.

### 4.1. Opción A — Anular la venta y crear una nueva por lo que el cliente conserva

Recomendada cuando se quiere trazabilidad completa y el cliente puede esperar.

Ejemplo: vendió 3 cajas, devuelve 1 y se queda con 2.

1. Anular la venta original con motivo `Devolución parcial: devuelve 1 de 3`.
2. Crear una **nueva venta** por las 2 cajas que el cliente conserva.
3. Cobrar la nueva venta (mismos precios si no cambiaron).

Resultado:

- Caja: se devuelve el total original y se cobra la nueva venta; el neto equivale a la devolución de 1 caja.
- Stock: vuelven las 3 y se consumen 2; queda 1 disponible.
- Trazabilidad: venta anulada + venta nueva, ambas con ticket y kardex.

Ventajas: el sistema reconoce todo, la caja queda vinculada y el stock se valida con FEFO.

Limitaciones:

- Se emite un nuevo correlativo/ticket.
- La nueva venta usa el **precio actual** de la presentación; si cambió, el neto puede no coincidir.
- El FEFO puede consumir una capa distinta a la original.
- La venta anulada aparece como devolución total, no parcial.

### 4.2. Opción B — Ajuste positivo + egreso manual

Recomendada para devoluciones pequeñas o cuando no se quiere rehacer la venta.

Ejemplo: vendió 3 cajas, devuelve 1, se reembolsa el valor de 1.

1. Verificar la venta original y la cantidad devuelta.
2. Registrar un **ajuste de inventario**:
   - tipo `ajuste_positivo`;
   - lote de origen;
   - cantidad en **unidad base** (ej.: 1 caja = 100 tabletas → `100`);
   - motivo: `Devolución parcial venta T001-00000005`.
3. Si corresponde reembolso, registrar un **egreso manual** en caja:
   - monto del reembolso;
   - descripción: `Reembolso devolución parcial T001-00000005`.

Resultado:

- Stock: reingresa la cantidad devuelta en una capa nueva del lote.
- Caja: egreso manual por el monto reembolsado.
- Venta: sigue confirmada y suma completa en reportes.

Ventajas: simple, conserva el ticket original y no obliga a re-vender.

Limitaciones:

- El ajuste **no queda vinculado** a la venta; solo el motivo lo referencia.
- No hay validación automática contra la cantidad vendida; el encargado debe verificarla.
- La venta sigue contando completa en reportes de ventas y rentabilidad; el reembolso solo afecta caja.
- El ajuste positivo crea una **capa nueva** con el último costo del lote, no restaura la capa original.
- El ajuste positivo se bloquea si el lote está `baja` o `vencido`.
- No se debe anular después la venta original: volvería a devolver todas las unidades y duplicaría stock.

### 4.3. Cómo elegir

| Situación | Opción recomendada |
|---|---|
| Devolución total | Anular venta |
| Parcial con reembolso y cliente presente | A (anular + nueva venta) |
| Parcial pequeño, sin rehacer ticket | B (ajuste + egreso manual) |
| Parcial sin reembolso (cambio) | B, solo ajuste positivo |
| Producto no apto | Ajuste negativo, con o sin anulación según el reembolso |

---

## 5. Reembolso opcional

### 5.1. Devolución total

El reembolso es **automático** al anular: se registra un egreso por el total de la venta. Hoy **no existe la opción de anular sin reembolsar**.

Si el negocio no quiere devolver dinero (por ejemplo, entrega otro producto), no debe anular la venta: debe usar ajustes y el flujo normal de venta para el producto entregado.

### 5.2. Devolución parcial

El reembolso es **opcional y manual**:

- Se registra en **Caja → Registrar egreso**.
- Requiere una caja abierta (no necesariamente la caja original).
- `descripcion` es obligatoria (mínimo 5 caracteres); usar un formato estable, por ejemplo:
  `Reembolso devolución parcial T001-00000005`.
- No se guarda `referencia_id` a la venta: el vínculo es solo textual.
- Si no hay reembolso, no se registra ningún movimiento de caja.

### 5.3. Matriz de escenarios

| Devolución | ¿Reembolso? | Stock | Caja | Cómo queda |
|---|---|---|---|---|
| Total | Sí | Reversión automática | Egreso vinculado a la venta | Venta anulada |
| Total | No | Reversión automática si se anula; si no se anula, ajuste positivo | Sin movimiento | Venta confirmada + ajuste |
| Parcial | Sí | `ajuste_positivo` o nueva venta | Egreso manual o nueva venta | Sin vínculo (opción B) o trazable (opción A) |
| Parcial | No | `ajuste_positivo` | Sin movimiento | Venta confirmada + ajuste |
| Cualquiera | Producto no apto | Ajuste negativo (`dano`/`vencimiento`) | Según reembolso | Producto fuera de stock |

---

## 6. Criterio de reingreso (decisión del encargado)

El sistema **no evalúa** la condición física. El encargado decide y lo refleja con el tipo de ajuste.

### 6.1. Apto para reingreso

- Envase sellado, sin abrir ni dañar.
- Lote y fecha de vencimiento legibles y coincidentes con la venta.
- Producto no vencido.
- Conservación y temperatura adecuadas.
- Sin indicios de contaminación, humedad o exposición.
- No retirado del mercado.

### 6.2. No apto

- Envase abierto, roto o manipulado.
- Lote o vencimiento ilegible.
- Producto vencido al momento de la devolución.
- Cadena de frío interrumpida o exposición a condiciones inadecuadas.
- Producto retirado o en cuarentena.

En ese caso:

- Si la devolución es total: anular y luego ajuste `dano` o `vencimiento`.
- Si es parcial: ajuste `dano` o `vencimiento` (no `ajuste_positivo`).

---

## 7. Trazabilidad y limitaciones

### 7.1. Cadena de kardex

```text
VENTA        (salida original, por capa)
REVERSION    (devolución total)
AJUSTE +     (reingreso parcial)
AJUSTE -     (producto no apto)
```

### 7.2. Limitaciones actuales

| Limitación | Impacto |
|---|---|
| `revertirConsumos` elimina `lote_consumo` | El detalle de la venta deja de mostrar los lotes consumidos; el kardex conserva la evidencia |
| No hay `devolucion_cliente` | No se puede consultar “devoluciones por venta” ni sumar devoluciones parciales |
| Ajuste positivo sin vínculo | No hay FK a la venta; solo motivo/observación |
| Sin validación de cantidad devuelta | El encargado debe evitar devolver más de lo vendido |
| Reportes de ventas no netean devoluciones parciales | La venta sigue sumando completa |
| Reembolso parcial sin vínculo | Queda como `egreso_manual` |
| Ajuste positivo no valida fecha de vencimiento | Solo bloquea lote `baja` o `vencido`; un lote `disponible` con fecha pasada puede recibir stock no vendible por FEFO |
| Anulación bloqueada con caja cerrada | No se puede compensar en una caja ya cerrada |

### 7.3. Riesgo de doble conteo

Evitar estas combinaciones:

- Anular la venta **y** registrar `ajuste_positivo` por las mismas unidades.
- Devolución parcial con ajuste positivo **y** luego anular la venta.
- Registrar dos egresos manuales para el mismo reembolso.

---

## 8. Mejoras futuras (no implementar ahora)

Solo si el volumen de devoluciones parciales lo justifica:

1. Documento `devolucion_cliente` con líneas por venta, lote y condición (`reingresa`/`no_reingresa`).
2. Reembolso opcional con `referencia_id` a la devolución y a la venta.
3. Reporte de devoluciones y neteo en reportes de ventas/rentabilidad.
4. Conservar el historial de `lote_consumo` (o snapshot de lotes en el detalle) al anular.
5. Validar vencimiento y estado al reingresar stock.
6. Extender el enum de `caja_movimiento.referencia_tipo` con `devolucion_cliente`/`venta_anulacion`.
7. Cuarentena de lote (E2) para productos devueltos no aptos.

---

## 9. Ejemplos rápidos

| Caso | Acción |
|---|---|
| Vendió 2 cajas, devuelve ambas, se reembolsa todo | Anular venta con motivo |
| Vendió 2 cajas, devuelve ambas, una está dañada | Anular + ajuste `dano` por la dañada |
| Vendió 3 cajas, devuelve 1, se reembolsa 1 | Opción A o B |
| Vendió 3 cajas, devuelve 1, no se reembolsa | `ajuste_positivo` por 1 |
| Vendió 1 frasco, lo devuelve vencido | Anular + ajuste `vencimiento` |
| Cliente cambia un producto por otro | Ajuste positivo del devuelto + venta normal del entregado |
