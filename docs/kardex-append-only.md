# Kardex append-only

> **Última actualización:** 19/11/2026
> **Estado:** fases 6 y 7 implementadas.
> **Referencias:** `flujo-inventario-lotes-fefo.md`, `modulo-compras-proveedores.md`, `modulo-ajustes-inventario.md`.

---

## 1. Regla

El kardex es un **libro mayor inmutable**:

```text
No se borra.
No se regenera.
No se edita.
```

Si una operación se revierte, se registra un movimiento compensatorio de tipo `REVERSION`.

```text
VENTA       -100  ← movimiento original
REVERSION   +100  ← devolución del stock
VENTA        -10  ← nueva venta, si se editó
```

---

## 2. Tipos de movimiento

| Tipo | Cuándo se usa |
|---|---|
| `INGRESO` | Recepción de compra |
| `VENTA` | Venta de producto |
| `ATENCION` | Consumo de producto en una atención |
| `AJUSTE` | Ajuste positivo o negativo por lote |
| `BAJA` | Baja de lote |
| `DEVOLUCION` | Devolución a proveedor |
| `REVERSION` | Reversión de venta, atención, compra o devolución |

---

## 3. Flujos afectados

### 3.1. Anular venta

1. Se registran movimientos `REVERSION` por cada capa consumida.
2. Se restauran lote y stock.
3. La venta se marca `anulada`; no se elimina ni se edita.

El kardex queda:

```text
VENTA original + REVERSION
```

### 3.2. Anular atención

Mismo comportamiento que una venta, con tipo `ATENCION`:

1. `REVERSION` por cada capa consumida.
2. Se restauran lote y stock.
3. La atención se marca `anulada`; no se elimina ni se edita.

### 3.4. Anular compra

1. Se registran `REVERSION` negativos por cada capa de la compra.
2. Se descuentan lote y stock.
3. Las capas se eliminan, pero el kardex de `INGRESO` y `REVERSION` permanece.

### 3.5. Anular devolución a proveedor

1. Se registran `REVERSION` positivos por cada consumo de la devolución.
2. Se restauran capa, lote y stock.
3. El kardex `DEVOLUCION` y `REVERSION` permanece.

### 3.6. Ajustes y bajas

- Los ajustes son inmutables y no se revierten.
- Las bajas de lote generan `BAJA` y tampoco se revierten.

---

## 4. Qué se conserva y qué se reemplaza

| Dato | Comportamiento |
|---|---|
| `kardex_movimientos` | **Nunca se borra** |
| `lote_consumo` | Se revierte al anular; las ventas/atenciones confirmadas no se editan |
| `lote_ingreso` | Se restaura o se elimina según la operación |
| `producto.stock` | Se ajusta en cada reversión |
| `producto_lote.cantidad_disponible_base` | Se restaura en cada reversión |

El kardex conserva lote, vencimiento y costo como **foto histórica**, aunque la capa ya no exista.

---

## 5. Implementación

| Función | Archivo |
|---|---|
| Registrar movimiento | `App\Helpers\KardexHelper::registrar()` |
| Revertir ventas/atenciones/devoluciones | `App\Services\InventarioService::revertirConsumos()` |
| Revertir compra | `App\Services\InventarioService::revertirIngreso()` |
| Anular venta | `App\Services\AnulacionVentaService` |
| Anular atención | `App\Services\AnulacionAtencionService` |
| Anular compra | `App\Services\CompraService::anular()` |
| Anular devolución | `App\Services\DevolucionProveedorService::anular()` |

`KardexHelper` ya **no expone** un método para eliminar movimientos por referencia.

---

## 6. Cómo leer el kardex

Cada movimiento guarda:

- `stock_anterior` y `stock_nuevo`;
- cantidad con signo;
- tipo;
- referencia (documento);
- lote y vencimiento;
- costo unitario base;
- usuario y fecha.

Para reconstruir la historia de un producto:

1. Ordenar por fecha.
2. Sumar las cantidades.
3. Contrastar con `stock_nuevo`.

Una `REVERSION` siempre debe tener signo contrario al movimiento que compensa.

---

## 7. Pruebas automatizadas

Archivo:

```text
tests/Feature/KardexAppendOnlyTest.php
```

Cubre:

1. Anular compra conserva `INGRESO` y agrega `REVERSION`.
2. Editar venta conserva la venta original, agrega `REVERSION` y la nueva venta.
3. Eliminar venta conserva `VENTA` y agrega `REVERSION`.
4. Anular devolución conserva `DEVOLUCION` y agrega `REVERSION`.
5. Eliminar atención conserva `ATENCION` y agrega `REVERSION`.

Comando:

```bash
php artisan test tests/Feature/KardexAppendOnlyTest.php
```

Resultado esperado: **5 pruebas**.

---

## 8. Pruebas manuales

### 8.1. Editar una venta

1. Vender un producto.
2. Editar la venta y cambiar la cantidad.
3. Ir a **Kardex**.
4. Verificar:
   - el movimiento `VENTA` original sigue visible;
   - aparece un `REVERSION` con signo contrario;
   - aparece la nueva `VENTA`.

### 8.2. Eliminar una venta

1. Vender un producto.
2. Eliminar la venta.
3. Verificar en **Kardex** que `VENTA` y `REVERSION` siguen visibles.
4. Confirmar que el stock volvió a su valor anterior.

### 8.3. Anular una compra

1. Recibir una compra.
2. Anularla.
3. Verificar `INGRESO` y `REVERSION` negativos en el kardex.

### 8.4. Anular una devolución

1. Registrar una devolución a proveedor.
2. Anularla.
3. Verificar `DEVOLUCION` y `REVERSION` positivos.

### 8.5. Filtro de tipos

1. Abrir **Kardex**.
2. Probar el filtro de tipo con `REVERSION`, `AJUSTE`, `BAJA` y `DEVOLUCION`.

---

## 9. Mejoras futuras

- Reporte de reversiones con filtro por usuario y motivo.
- Cuentas por pagar, si el negocio habilita compras al crédito.

---

*Si se agrega un nuevo flujo que revierte stock, debe registrar `REVERSION` y no borrar kardex.*
