# Mejora de compras a proveedores — contraste carwash → botica

> **Fecha:** 19/11/2026
> **Fuente analizada:** `docs/flujo-productos-compras-inventario-ventas.md` (sistema `carwash_sistema`).
> **Sistema destino:** botica actual, con unidades, presentaciones, lotes, FEFO y venta fraccionada.
> **Objetivo:** mejorar el flujo actual de **Ingresos** para que funcione como un verdadero flujo de **Compras a proveedores**, sin copiar decisiones del carwash que no sirven para farmacia.

> **Estado de implementación (19/11/2026):**
> - **Fase 1 implementada:** cabecera documental, estados, documento obligatorio, correlativo `CMP-`, totales en servidor y edición solo en borrador.
> - **Fase 2 implementada:** recepción separada, lotes/stock/kardex, pago desde caja o externo, anulación con reversión.
> - **Fase 3 implementada:** columna de pago en el listado, filtro por forma de pago en el reporte de compras y resumen caja vs. externo.
> - **Fase 4 implementada:** devolución a proveedor por lote, reembolso opcional a caja y anulación con restauración exacta.
> - **Fase 5 implementada:** ajustes de inventario inmutables por lote (conteo físico, daño, vencimiento, consumo interno y ajuste positivo).
> - **Fase 6 implementada:** kardex append-only con movimientos `REVERSION`; no se borra ni regenera el kardex.
> - **Fase 7 implementada:** reportes de rentabilidad, lotes/costos y movimientos especiales, más advertencia de costo mayor al precio de venta.
> - **Plan completo:** fases 1 a 7 implementadas.
> - Detalle operativo: `modulo-compras-proveedores.md`, `modulo-ajustes-inventario.md`, `kardex-append-only.md` y `modulo-reportes.md`.

---

## 1. Resumen ejecutivo

El documento del carwash describe un flujo de compras más maduro que el actual de la botica:

```text
Compra en borrador → Recepción → actualización de stock/Kardex/caja
```

La botica hoy hace todo en un solo paso desde **Ingresos**:

```text
Ingreso → stock + lote + kardex (inmediatamente)
```

Eso es rápido, pero deja fuera cosas importantes para una botica:

- número y tipo de documento del proveedor;
- borrador revisable antes de recibir;
- control del pago: desde caja o registrado fuera de ella;
- anulación/reversión formal;
- ajustes de inventario por conteo, daño, vencimiento o consumo interno;
- reportes de compras por proveedor/documento;
- Kardex estrictamente append-only.

**Recomendación:** no reemplazar la lógica de lotes/FEFO ya implementada. Se debe **evolucionar `ingreso` a “Compra”** agregando cabecera documental, estados, recepción separada y pago en caja al recibir, manteniendo el detalle con presentación, lote, vencimiento, costo y `lote_ingreso`.

---

## 2. Estado previo y estado actual

### 2.1. Antes de la implementación (histórico)

Flujo anterior de `Ingresos`:

1. Se elegía proveedor y fecha.
2. Se agregaban líneas con producto/presentación, cantidad, lote, vencimiento y costo.
3. Al guardar:
   - se generaba `ING-XXXXXXXX`;
   - se creaba/actualizaba el lote;
   - se creaba la capa `lote_ingreso`;
   - aumentaba `producto.stock`;
   - se registraba Kardex `INGRESO`;
   - se guardaba el costo por lote.
4. Si el ingreso ya tenía consumo, no se podía editar ni eliminar.
5. No se registraba el pago al proveedor en caja.
6. No había estado borrador/recibida/anulada.
7. No había tipo/número de factura, boleta o guía.
8. No había reporte específico de compras por proveedor/documento.
9. El Kardex se eliminaba y regeneraba al editar/eliminar.

### 2.2. Ahora (fases 1 y 2 implementadas)

1. La compra se crea como **borrador** con documento obligatorio y totales calculados.
2. El borrador no mueve stock, lotes, kardex ni caja.
3. **Recibir** es la única acción que crea lotes, aumenta stock y registra kardex.
4. El pago se decide al recibir:
   - desde caja: exige caja abierta y genera egreso;
   - externo: no mueve caja y guarda método/referencia.
5. El correlativo `CMP-XXXXXXXX` se asigna al recibir.
6. Una compra recibida no se edita ni elimina; se **anula** con reversión.
7. La anulación revierte stock, lotes, kardex y caja cuando corresponde.

Lo que sigue igual:

- lotes y vencimientos reales;
- FEFO;
- costo por lote;
- bloqueo de anulación si el lote ya fue consumido;
- trazabilidad por kardex.

---

## 3. Contraste general

| Aspecto | Carwash (referencia) | Botica actual | Decisión |
|---|---|---|---|
| Documento de compra | `compras` + `detalle_compras` con estados | `ingreso` + `detalle_ingreso`, sin estados | **Emular** estados y cabecera |
| Alta de producto | Nace con stock 0 | Nace con stock inicial y lote `SIN-LOTE` | **Adaptar** (la botica ya lo resolvió) |
| Recepción | Acción separada `recibir` | Se recibe al guardar | **Emular** recepción separada |
| Stock | Entero | Decimal en unidad base + lotes | **No copiar** |
| Inventario/alerta | Ciclo 75 % (`inventario - stock`) | `stock_minimo` + vencimientos | **No copiar** el ciclo 75 % |
| Lotes/vencimiento | No existen | `producto_lote`, `lote_ingreso`, `lote_consumo` | **Conservar botica** |
| FEFO | No existe | Implementado | **Conservar botica** |
| Detalle de compra | UNIQUE(compra, producto) | Permite presentación/lote | **Adaptar**, no unique por producto |
| Pago de compra | Egreso de caja obligatorio | No se registra | **Adaptar**: pago desde caja o externo |
| Caja | Requerida para recibir | No interviene en ingreso | **Adaptar**: pago desde caja o externo, según la compra |
| Costo | Último costo / CPP incompleto | Costo real por lote | **Conservar y mejorar** |
| Kardex | Append-only | Se elimina/regenera en edición | **Emular append-only** |
| Ajustes | Documento formal | Solo baja de lote | **Emular/adaptar** |
| Devoluciones | Documento formal | No implementado | **Adaptar** a proveedor/cliente |
| Correlativos | `max()+1` | `max(id)+1` | **Mejorar** con secuencia/unique |
| UI | Autocomplete, estados, confirmaciones | Formulario directo | **Emular** lo útil |

---

## 4. Qué emular del carwash y cómo hacerlo

### 4.1. Convertir “Ingreso” en “Compra” con estados

**Emular:** separar cabecera y detalle, y darle ciclo de vida al documento.

**Cómo adaptarlo en botica:**

Agregar a `ingreso` (o crear `compra` y migrar):

| Campo nuevo | Uso |
|---|---|
| `estado` | `borrador`, `recibida`, `anulada` |
| `tipo_documento` | Factura, Boleta, Ticket, Nota de venta, Guía de remisión, Otros |
| `numero_documento` | Número del proveedor |
| `subtotal` | Suma de líneas |
| `total` | Total de la compra |
| `observaciones` | Notas de la compra |
| `fecha_recepcion` | Cuándo se recibió físicamente |
| `recepcion_user_id` | Quién recibió |
| `pagado_desde_caja` | Sí/No: si el pago de esta compra se registra en caja |
| `metodo_pago` | Efectivo, transferencia, Yape, etc. (obligatorio si se paga en caja) |
| `referencia_pago` | Nro. de operación o nota del pago externo (opcional) |
| `caja_id` | Caja usada, solo si `pagado_desde_caja = 1` |
| `egreso_caja_id` | Movimiento de egreso generado, solo si se pagó por caja |
| `anulada_at`, `motivo_anulacion` | Anulación formal |

Recomendación: **evolucionar `ingreso`**, no crear desde cero, porque `detalle_ingreso`, `lote_ingreso` y Kardex ya dependen de esa cabecera. Se puede cambiar la etiqueta visible a **Compras** sin renombrar la tabla de inmediato.

### 4.2. Separar “guardar” de “recibir”

**Emular:**

```text
Borrador: no toca stock, lotes, kardex ni caja.
Recibir: única acción que ingresa mercadería.
```

**Cómo adaptarlo:**

- Al crear la compra, guardar cabecera y detalles con `estado = borrador`.
- El borrador no crea `lote_ingreso`, no mueve stock, no toca caja.
- La acción **Recibir**:
  - valida que siga en borrador;
  - crea/actualiza lotes y capas;
  - aumenta stock;
  - registra kardex;
  - si `pagado_desde_caja = 1`, valida caja abierta y registra el egreso por el total;
  - si `pagado_desde_caja = 0`, no toca caja: solo guarda método y referencia del pago externo;
  - cambia estado a `recibida`.

Esto permite revisar la factura antes de afectar inventario.

### 4.3. Documento del proveedor y control de duplicados

**Emular:** tipo y número de documento.

**Adaptación botica:**

- Agregar `tipo_documento` y `numero_documento`.
- Validar duplicado por proveedor + tipo + número:
  ```text
  unique(proveedor_id, tipo_documento, numero_documento)
  ```
- Evita recibir dos veces la misma factura.
- Guardar observaciones y fecha de recepción.

### 4.4. Recepción atómica y con bloqueo

**Emular:** toda la recepción ocurre en una transacción; si falla algo, no queda nada.

**Adaptación botica:**

- Bloquear la compra para que no se reciba dos veces.
- Bloquear cada producto/lote.
- Validar que el producto esté activo.
- Validar lote y vencimiento.
- Crear `lote_ingreso` y actualizar `producto_lote`.
- Actualizar `producto.stock`.
- Registrar Kardex.
- Si `pagado_desde_caja = 1`, validar caja abierta y registrar el egreso por el total.
- Si `pagado_desde_caja = 0`, no generar movimiento de caja.
- Si algo falla, rollback total.

La base actual ya usa transacciones y FEFO; hay que agregar el bloqueo de la cabecera de compra y el estado.

### 4.5. Edición solo en borrador

**Emular:** una compra recibida no se edita.

**Adaptación botica:**

- Borrador: se puede editar, reemplazando líneas.
- Recibida: no se edita.
- Si hubo error después de recibir:
  - anulación con reversión, o
  - devolución a proveedor, o
  - ajuste de inventario.
- Si el lote ya fue consumido, no se puede borrar la compra; se debe usar un documento compensatorio.

### 4.6. Anulación de compra recibida

**Emular:** anulación con reversión, no simple borrado.

**Adaptación botica:**

- Validar que la compra esté recibida.
- Validar que el stock/lote alcance para revertir.
- Si se pagó desde caja, registrar el ingreso compensatorio por el total.
- Si el pago fue externo, no tocar caja.
- Devolver cantidades a los lotes correspondientes.
- Registrar Kardex compensatorio.
- Marcar `anulada`.
- No reutilizar el correlativo.
- No borrar físicamente si ya hay historial.

### 4.7. Autocompletado de proveedor y producto

**Emular:**

- Buscar proveedor por razón social o RUC.
- Buscar producto por nombre.
- Mostrar stock, costo y presentación.
- Solo activos.

**Adaptación botica:**

- El autocomplete de producto debe trabajar con **producto/presentación**.
- Debe mostrar stock en unidad base y costo por presentación.
- Debe permitir varias líneas del mismo producto si cambia el lote o la presentación.

### 4.8. Totales calculados en servidor

**Emular:** el navegador solo muestra; el servidor recalcula.

**Adaptación botica:**

- `subtotal = cantidad × costo_unitario`.
- `total = suma de subtotales`.
- Guardar `costo_total` en `detalle_ingreso`.
- Redondear a 2 decimales en servidor.

### 4.9. Reporte de compras por proveedor

**Emular:** reporte específico de compras.

**Adaptación botica:**

- Reporte por proveedor, fecha, tipo/número de documento.
- Incluir lote, vencimiento, cantidad base y costo.
- Reporte de compras por producto.
- Reporte de compras por documento.
- Reporte de lotes por vencer derivados de compras.

### 4.10. Ajustes de inventario formales

**Emular:** documento de ajuste con motivo y stock final.

**Adaptación botica:**

Tipos recomendados:

| Tipo | Uso en botica |
|---|---|
| `conteo_fisico` | Corrección por inventario físico |
| `daño` | Producto roto/derramado |
| `vencimiento` | Baja por vencimiento |
| `consumo_interno` | Insumos usados en servicios |
| `devolucion_proveedor` | Retiro/retorno al proveedor |
| `ajuste_positivo` | Sobrante o corrección |

Cada ajuste debe:

- operar por lote;
- registrar Kardex;
- actualizar stock;
- exigir motivo;
- ser inmutable;
- quedar auditado.

Hoy la baja de lotes cubre una parte, pero no reemplaza un módulo de ajustes.

### 4.11. Kardex append-only

**Emular:** no editar ni borrar movimientos; usar compensatorios.

**Impacto en botica:**

Hoy al editar/eliminar ventas o ingresos se eliminan y regeneran movimientos del kardex. Para una lógica contable estricta:

- no borrar kardex;
- registrar un movimiento de reversión;
- luego registrar el nuevo movimiento.

Esto es un cambio importante y debe hacerse en una fase específica.

### 4.12. UI y confirmaciones

**Emular:**

- badges de estado;
- confirmación al recibir;
- aviso si la caja está cerrada;
- totales en vivo;
- estado vacío;
- advertencia de costo mayor al precio de venta.

**Adaptación botica:**

- Interruptor **“¿Pagado desde caja?”** en la compra.
- Si está activo, exigir caja abierta y método de pago.
- Si está inactivo, permitir método/referencia del pago externo y no mover caja.
- Advertencia de lote vencido o próximo a vencer.
- Advertencia si el costo por unidad base supera el precio de venta (no bloquea).
- Confirmación antes de dar de baja un lote.

---

## 5. Qué adaptar de forma específica para botica

### 5.1. No usar `UNIQUE(compra, producto)`

En carwash una compra no puede repetir producto. En botica sí puede ocurrir:

- misma presentación con dos lotes distintos;
- caja y blíster del mismo producto;
- dos vencimientos diferentes.

**Regla recomendada:**

```text
unique(compra_id, producto_presentacion_id, lote_codigo, fecha_vencimiento)
```

O permitir líneas separadas y consolidarlas en la recepción.

### 5.2. Cantidades decimales y presentaciones

No copiar cantidades enteras. Mantener:

- `cantidad` en presentación;
- `factor`;
- `cantidad_base = cantidad × factor`;
- stock en unidad base.

Una compra puede recibir:

```text
2 cajas × 100 = 200 TAB
5 blísteres × 10 = 50 TAB
```

### 5.3. Costo por lote, no solo precio maestro

Carwash actualiza `producto.precio_compra` con el último costo. En botica eso no es suficiente:

- conservar `lote_ingreso.costo_unitario_base`;
- conservar `lote_ingreso.costo_total`;
- actualizar `producto.precio_compra` solo como referencia;
- si se implementa CPP, hacerlo con historial real, no con el servicio incompleto del carwash.

### 5.4. Pago de compra: desde caja o externo

Decisión del negocio: **no todos los pagos pasan por caja**. Cada compra decide si su pago se registra en caja o no.

Campos:

```text
pagado_desde_caja: 1 | 0
metodo_pago: efectivo | transferencia | yape | tarjeta | otro
referencia_pago: texto libre (nro. de operación, nota)
caja_id: solo si pagado_desde_caja = 1
egreso_caja_id: solo si pagado_desde_caja = 1
```

- La decisión se toma **al recibir**, no al crear el borrador.
- Si `pagado_desde_caja = 1`:
  - se exige caja abierta;
  - se registra el egreso por el total;
  - la anulación revierte con un ingreso compensatorio.
- Si `pagado_desde_caja = 0`:
  - no se mueve caja;
  - se guarda método y referencia como dato informativo;
  - la anulación no toca caja.
- No se implementa crédito ni cuentas por pagar: “externo” solo significa que el pago no se registró en la caja del sistema.

### 5.5. Vencimientos y FEFO

Toda compra debe crear lotes con vencimiento. El FEFO ya está implementado:

- no vender vencidos;
- consumir primero lo que vence antes;
- registrar `lote_consumo`;
- reportar lotes próximos a vencer.

La compra es la principal fuente de lotes.

### 5.6. Productos de consumo interno

El carwash separa `comercial` y `consumo_interno`. En botica podría servir para insumos de curación, guantes, alcohol o material de servicio.

Decisión: **no se agrega por ahora**. Todos los productos se tratan como vendibles. Si más adelante se necesita, se agrega un campo `tipo_producto` o `es_vendible` sin tocar el resto del flujo.

### 5.7. Devoluciones

El carwash tiene devoluciones de venta. En botica hay dos flujos distintos:

1. **Devolución de proveedor**: se devuelve mercadería comprada; afecta lote, stock y caja. Decisión: **sin nota de crédito** por ahora; solo revierte lote, stock y registra kardex compensatorio.
2. **Devolución de cliente**: se reincorpora producto vendido; afecta lote y caja.

No se deben mezclar. Se implementa primero la devolución a proveedor y luego la de cliente, ambas con lotes.

### 5.8. `inventario` y alertas

No copiar el ciclo del 75 % del carwash. En botica:

- `stock_minimo` es el umbral operativo;
- los vencimientos se controlan por lote;
- `inventario` actual es un dato heredado que no debe usarse como fuente de verdad.

Mantener las alertas por:

- stock bajo;
- lote crítico;
- lote vencido;
- lote agotado.

---

## 6. Qué NO copiar del carwash

| No copiar | Motivo |
|---|---|
| `inventario` como ciclo del 75 % | La botica usa stock mínimo y vencimientos por lote |
| Stock entero | La botica maneja decimales y fracciones |
| `UNIQUE(compra, producto)` | Impide dos lotes/presentaciones del mismo producto |
| Egreso de caja obligatorio en toda compra | El pago puede registrarse fuera de caja |
| Borrado físico de compra recibida | Rompe trazabilidad; usar anulación/reversión |
| Editar/borrar Kardex | Debe ser append-only con compensatorios |
| `max()+1` para correlativos | Riesgo de colisión; usar secuencia/unique |
| Costo solo en `producto.precio_compra` | La botica necesita costo por lote |
| Servicio de CPP incompleto | El propio documento lo marca como deuda |
| Entidades de carwash (vehículos, aceite, cambio de aceite) | No aplican a botica |
| Modal de “actualizar stock” sin documento | En botica debe ser ajuste formal |
| `tipo_pago = efectivo` fijo en compras | Debe elegirse el método de pago |
| Textos de UI que contradicen el código | Corregir al adaptar |

---

## 7. Modelo recomendado para botica

Se puede evolucionar lo existente:

```text
ingreso / compra (cabecera)
  id
  correlativo            CMP-00000001
  proveedor_id
  fecha_documento
  tipo_documento
  numero_documento
  estado                 borrador | recibida | anulada
  subtotal
  total
  pagado_desde_caja      boolean
  metodo_pago            nullable (obligatorio si pagado_desde_caja)
  referencia_pago        nullable
  observaciones
  caja_id                nullable (solo si pagado_desde_caja)
  egreso_caja_id         nullable
  fecha_recepcion
  recepcion_user_id
  anulada_at
  motivo_anulacion
  user_id

detalle_ingreso / detalle_compra
  id
  ingreso_id
  producto_id
  producto_presentacion_id
  cantidad
  factor
  cantidad_base
  costo_unitario (por presentación)
  costo_unitario_base
  costo_total
  producto_lote_id
  lote_codigo
  fecha_vencimiento

lote_ingreso
  (ya existe)
```

El flujo de recepción reutiliza `lote_ingreso` y `lote_consumo`; no hay que rehacer FEFO.

---

## 8. Plan por fases

### Fase 1 — Cabecera documental y estados — ✅ Implementada

- Agregar campos de compra a `ingreso`.
- Estado inicial `borrador`.
- Los ingresos históricos pasan a `recibida`.
- Generar correlativo `CMP-00000001` al recibir, no al crear.
- Vista de compra con estado, documento, totales y observaciones.
- Validación de documento duplicado.
- Cambiar la etiqueta visible del módulo a **Compras**.

### Fase 2 — Recepción separada — ✅ Implementada

- Acción **Recibir**.
- Bloqueo de cabecera.
- Crear/actualizar lotes y capas.
- Stock, Kardex y auditoría.
- Decidir **“¿pagado desde caja?”**:
  - sí: exigir caja abierta y registrar egreso;
  - no: guardar método/referencia y no tocar caja.
- No permitir doble recepción.

### Fase 3 — Conciliación y reporte de pagos — ✅ Implementada

- Método y referencia de pago por compra.
- Revertir el egreso al anular, solo si se pagó por caja.
- Columna **Pago** en el listado de compras.
- Filtro por forma de pago en el reporte de compras.
- Resumen de totales y cantidades pagadas por caja vs. fuera de caja.

### Fase 4 — Anulación y devolución a proveedor — ✅ Implementada

- Anulación de compra con reversión.
- Devolución a proveedor por capa de lote.
- Reembolso opcional a caja.
- Movimientos compensatorios al anular la devolución.
- Validar stock/lote suficiente.

### Fase 5 — Ajustes de inventario — ✅ Implementada

- Conteo físico, daño, vencimiento, consumo interno y ajuste positivo.
- Operación por lote y en unidad base.
- Kardex `AJUSTE` y `lote_consumo`.
- Ajustes inmutables: se corrigen con otro ajuste.
- Sin movimiento de caja.

### Fase 6 — Kardex append-only — ✅ Implementada

- No se borran ni regeneran movimientos de kardex.
- Las reversiones se registran como `REVERSION`.
- Aplica a ventas, atenciones, compras y devoluciones.
- El kardex conserva lote, vencimiento y costo histórico.

### Fase 7 — Reportes y optimización — ✅ Implementada

- Reporte de rentabilidad por producto (ventas + atenciones).
- Reporte de lotes, costo promedio y valor.
- Reporte de movimientos especiales (`REVERSION`, `AJUSTE`, `BAJA`, `DEVOLUCION`, etc.).
- Advertencia visual de costo mayor al precio de venta en compras.
- Filtros por fecha, categoría, marca, producto, usuario y vencimiento.

---

## 9. Pruebas recomendadas

1. Crear compra en borrador: no cambia stock, lotes, kardex ni caja.
2. Recibir compra: crea lotes, aumenta stock, registra kardex.
3. Intentar recibir dos veces: rechazar.
4. Compra con dos lotes del mismo producto: permitir.
5. Pago desde caja: exigir caja abierta y generar egreso. Pago externo: no mover caja.
6. Documento duplicado del proveedor: rechazar.
7. Editar borrador: permitir; editar recibida: rechazar.
8. Anular recibida sin consumo: revertir stock/lotes/kardex y caja solo si se pagó por caja.
9. Anular recibida con consumo: bloquear o exigir devolución.
10. Devolver a proveedor: descontar lote y stock, kardex compensatorio.
11. Ajuste por conteo/daño/vencimiento: actualizar stock y kardex.
12. Verificar que `producto.stock = suma(lotes disponibles)`.
13. Compra sin tipo/número de documento: rechazar.
14. Costo mayor al precio de venta: advertir y permitir continuar.
15. Verificar correlativo `CMP-` y etiqueta **Compras** en el menú.
16. Reporte de compras: filtro por caja/externo y totales de conciliación.
17. Devolución parcial: descuenta la capa exacta y registra kardex `DEVOLUCION`.
18. Devolución con reembolso: ingreso en caja.
19. Anular devolución: restaura capa, stock, kardex y caja.
20. Ajuste positivo: suma stock y crea capa.
21. Ajuste negativo y conteo físico: descuentan stock y registran kardex `AJUSTE`.
22. Ajuste inmutable: sin edición, eliminación ni anulación.
23. Kardex append-only: `REVERSION` al editar/eliminar venta, atención, compra o devolución.
24. Rentabilidad: ingresos, costo y margen por producto.
25. Lotes: costo promedio, valor y filtro por vencimiento.
26. Movimientos especiales: filtros por `REVERSION`, `AJUSTE` y `DEVOLUCION`.
27. Advertencia visual de costo mayor al precio de venta.

---

## 10. Decisiones tomadas (19/11/2026)

| # | Pregunta | Decisión |
|---|---|---|
| 1 | ¿Compra al crédito? | **Solo contado**, pero el pago puede registrarse **desde caja o fuera de caja**. |
| 2 | ¿Documento del proveedor? | **Obligatorio**: tipo y número, con control de duplicados. |
| 3 | ¿Costo mayor al precio de venta? | **Solo advertir**, no bloquear. |
| 4 | ¿Método de costo? | **Costo por lote** (el que ya existe). Sin CPP por ahora. |
| 5 | ¿Devolución a proveedor? | **Sí, sin nota de crédito**: revierte lote, stock y caja. |
| 6 | ¿Ajustes inmutables? | **Sí, inmutables**: se corrigen con otro ajuste. |
| 7 | ¿Kardex append-only? | **Fase posterior** (fase 6). |
| 8 | ¿Renombrar Ingresos? | **Sí, a “Compras”** en la interfaz. |
| 9 | ¿Correlativo? | **`CMP-00000001`**. |
| 10 | ¿IGV? | **Solo control interno**, sin desglose tributario. |
| 11 | ¿Consumo interno? | **No por ahora**: todos los productos son vendibles. |
| 12 | ¿Alcance inicial? | **Fases 1 y 2**: cabecera con estados y recepción separada. |

Consecuencias directas:

- No se agrega `condicion_pago` ni cuentas por pagar.
- Cada compra decide con `pagado_desde_caja` si su pago pasa por caja.
- Si el pago pasa por caja, se exige caja abierta y se registra el egreso.
- Si el pago es externo, no se mueve caja; solo se guarda método y referencia.
- El documento del proveedor es obligatorio.
- La primera entrega no incluye devoluciones, ajustes ni Kardex append-only.

---

## 11. Recomendación final

**Sí emular del carwash:**

- compra como documento;
- estados borrador/recibida/anulada;
- recepción separada;
- documento del proveedor y control de duplicados;
- totales calculados en servidor;
- anulación/reversión;
- ajustes de inventario;
- reportes de compras;
- UI de estados y confirmaciones.

**Adaptar a botica:**

- presentaciones, factor y cantidades decimales;
- lote, vencimiento y FEFO;
- costo por lote;
- pago por caja o externo, decidido en cada compra;
- stock mínimo y alertas por vencimiento;
- múltiples lotes/presentaciones por producto;
- devoluciones a proveedor separadas de devoluciones de cliente.

**No copiar:**

- ciclo de inventario del 75 %;
- stock entero;
- unique por producto;
- caja obligatoria al crear el borrador;
- borrado físico de compras recibidas;
- Kardex mutable;
- `max()+1` de correlativos;
- CPP incompleto;
- lógica de carwash.

El objetivo no es “pasar el módulo de carwash”, sino **usar su madurez documental y adaptarla a la trazabilidad real de una botica**.

Con las fases 1 a 7 implementadas, el siguiente backlog de mejoras está en [`plan-fase-8-mejoras.md`](plan-fase-8-mejoras.md).
