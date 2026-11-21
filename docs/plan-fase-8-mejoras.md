# Plan de mejoras post fase 7 — backlog ordenado por dificultad

> **Fecha:** 19/11/2026
> **Estado:** A1, A1.1, A2, A3, B1, C1 y D1 implementados. D2 implementada en código (regularización de legados pendiente). E1 a E6 pendientes de revisión.
> **Base:** fases 1 a 7 implementadas + informe técnico del flujo de inventario.
> **Objetivo:** revisar tarea por tarea, de la más fácil a la más difícil, y decidir si se implementa, se ajusta o se descarta.

---

## 1. Decisiones tomadas

| Tema | Decisión |
|---|---|
| Venta confirmada | **Anular con motivo**. No se edita ni elimina libremente. |
| Devolución de cliente | **Cubierta sin módulo nuevo**: total = anular venta; parcial = `ajuste_positivo` + egreso manual opcional (o anular + nueva venta); no apto = ajuste negativo. |
| Lote `SIN-LOTE` | **Eliminar** y exigir inventario inicial por lotes. |
| Campo `inventario` | **Mantener como legado**: se oculta del formulario y de los reportes, pero no se elimina la columna todavía. |
| Código de barras | **No implementar ahora**. Se deja documentado para una etapa posterior. |
| Cuarentena de lote | **No por ahora**. Se usa baja cuando el lote no es vendible. |
| Campo `paso` | **Ocultar del formulario normal**, valor por defecto `1`, modo avanzado para fracciones y presentación base editable. |

---

## 2. Lo que ya está resuelto y no se debe reabrir

Estas piezas ya están implementadas y probadas:

- Compras con tipo/número de documento, estados `borrador → recibida → anulada`.
- Recepción separada de la compra.
- Pago desde caja o pago externo.
- Anulación de compra con reversión de stock, lotes, kardex y caja.
- Devolución a proveedor por capa de lote.
- Ajustes de inventario inmutables: conteo físico, daño, vencimiento, consumo interno y ajuste positivo.
- FEFO con capas de recepción y costo por lote.
- Kardex append-only con movimientos `REVERSION`.
- Reporte de rentabilidad.
- Reporte de lotes y costos.
- Reporte de movimientos especiales.
- Transacciones, bloqueos `lockForUpdate`, `DECIMAL` y `bcmath`.
- Costo histórico en el detalle de venta y atención.
- Auditoría, stock mínimo y alertas visuales de lote.

**No se debe volver a discutir ni reimplementar.**

---

## 3. Criterios de orden

El orden principal es **de menor a mayor dificultad técnica**, no de menor a mayor urgencia.

Cada tarea se evalúa con:

- **Dificultad:** S (baja), M (media), L (alta), XL (muy alta).
- **Riesgo:** bajo, medio, alto, muy alto.
- **Dependencias:** qué debe existir antes.
- **Impacto:** negocio, inventario, caja, reportes o UX.
- **Decisión:** implementar, ajustar, diferir o descartar.

---

## 4. Backlog ordenado

| Orden | ID | Tarea | Dificultad | Riesgo | Depende de | Decisión sugerida |
|---|---|---|---|---|---|---|
| 1 | A1 | Ocultar `inventario` del formulario y reportes | S | Bajo | — | Implementada |
| 2 | A2 | Simplificar `paso` y hacer editable la presentación base | M | Medio | A1 | Implementada |
| 3 | A3 | Comando de conciliación de stock | M | Bajo | — | Implementada |
| 4 | B1 | Anulación de venta con motivo | L | Alto | A3 | Implementada |
| 5 | C1 | Devolución de cliente con reingreso condicionado | L | Alto | B1 | Implementada (B1 + ajustes) |
| 6 | D1 | Inventario inicial por lotes | L | Alto | A2 | Implementada (D1.1–D1.4) |
| 7 | D2 | Eliminar `SIN-LOTE` y el stock inicial genérico | XL | Muy alto | D1 | Implementada (núcleo); legados pendientes |
| 8 | E1 | Código de barras en venta y compra | M | Medio | — | Diferir |
| 9 | E2 | Cuarentena de lote | M | Medio | D2 | Diferir |
| 10 | E3 | Órdenes de compra y cuentas por pagar | XL | Alto | — | Diferir |
| 11 | E4 | Historial de precios | M | Bajo | — | Evaluar |
| 12 | E5 | Campos farmacéuticos del producto | M | Bajo | — | Evaluar |
| 13 | E6 | Multi-almacén, IA, tableta individual | XL | Alto | — | Descartar |

---

## 5. Detalle por tarea

### A1 — Ocultar `inventario` del formulario y reportes

**Estado:** implementada el 19/11/2026.

**Qué se hizo**

- Se quitó `inventario` de `StoreProductoRequest` y `UpdateProductoRequest`.
- Se quitó el campo de `productos/create.blade.php` y `productos/edit.blade.php`.
- Se quitó la columna “Inventario Inicial” del reporte de inventario PDF.
- `producto.inventario` se mantiene como legado y se sincroniza con `stock` al crear el producto.
- Se actualizaron pruebas y documentación.

**Qué hacer**

- Quitar `inventario` de `StoreProductoRequest` y `UpdateProductoRequest`.
- Quitar el campo de `productos/create.blade.php` y `productos/edit.blade.php`.
- Dejar de mostrarlo en el reporte de inventario PDF.
- Mantener la columna `producto.inventario` como legado, sin uso en la interfaz.
- Documentar que la fuente real es `producto.stock` + capas de lote.

**Por qué**

Hoy se piden dos campos que representan lo mismo. El usuario no debe elegir entre `stock` e `inventario`.

**Criterios de aceptación**

- El formulario de producto no muestra `inventario`.
- El reporte de inventario no usa `inventario`.
- Crear y editar producto sigue funcionando.
- `producto.stock` y los lotes siguen coincidiendo.

**Pruebas**

- Crear producto sin enviar `inventario`.
- Editar producto sin enviar `inventario`.
- Renderizar el reporte de inventario.

**Decisión:** implementada.

---

### A1.1 — Formateo de decimales en formularios

**Estado:** implementado el 19/11/2026.

**Causa**

Los modelos usan casts `decimal:4` para precios y `decimal:3` para cantidades. Al imprimir el valor crudo en un `<input>` se mostraban `0.8000`, `20.000` o `10.000`.

**Solución**

- Precios de producto y presentación: 2 decimales.
- Stock, stock mínimo, factor y paso: hasta 3 decimales, sin ceros sobrantes.
- Se agregaron accessors de presentación:
  - `Producto::precio_compra_input`
  - `Producto::precio_venta_input`
  - `Producto::stock_input`
  - `Producto::stock_minimo_input`
  - `ProductoPresentacion::factor_input`
  - `ProductoPresentacion::precio_venta_input`
  - `ProductoPresentacion::paso_input`
- Se aplicó en:
  - edición de producto;
  - presentaciones adicionales;
  - edición de ventas;
  - edición de atenciones;
  - atributos `data-*` de compras y atenciones.

**Pruebas**

- `ProductoPresentacionesTest`: verifica que la edición muestra `0.80`, `20`, `10`, `6.00` y no `0.8000`, `20.000`, `10.000`, `6.0000`.
- `VentaFraccionadaTest`: verifica que la edición de venta muestra `1` y `50.00`, no `1.000` ni `50.0000`.

---

### A2 — Simplificar `paso` y hacer editable la presentación base

**Estado:** implementada el 19/11/2026.

**Qué se hizo**

- El formulario normal ya no muestra `paso`.
- Cada presentación tiene un checkbox **Configuración avanzada**:
  - si está desmarcado, `paso = 1`;
  - si está marcado, se muestra el incremento mínimo.
- La presentación base tiene su propio bloque de configuración avanzada.
- Se puede editar el `paso` de la base.
- El backend usa `paso = 1` por defecto si no se envía.
- Validación: `paso > 0`, `paso <= factor` y base `paso <= 1`.

**Qué hacer**

- Ocultar el campo `paso` en el formulario normal de presentaciones.
- Dejar `paso = 1` por defecto.
- Agregar un bloque **Configuración avanzada** por presentación:
  - “Permitir fracción”.
  - “Incremento mínimo”.
- Permitir editar el `paso` de la presentación base.
- Validar que `paso > 0` y `paso <= factor`.
- Mantener el motor actual: no se toca FEFO ni el consumo.

**Por qué**

El usuario de botica no debería pensar en incrementos mínimos para cada presentación. La mayoría de productos se vende en unidades enteras.

**Criterios de aceptación**

- El formulario normal no muestra `paso`.
- Sin configuración, `paso = 1`.
- Si se activa fracción, se puede usar `0.5` u otro incremento válido.
- La presentación base se puede ajustar.
- Los tests de venta fraccionada siguen pasando.

**Riesgos**

- No romper la creación automática de la presentación base.
- No permitir `paso = 0`.
- No confundir `factor` con `paso`.

**Decisión:** implementada.

---

### A3 — Comando de conciliación de stock

**Qué hacer**

- Crear un comando `inventario:conciliar`.
- Comparar por producto:
  - `producto.stock` vs. suma de `lote_ingreso.cantidad_disponible_base`.
  - `producto_lote.cantidad_disponible_base` vs. suma de sus capas.
- Mostrar una tabla con diferencias.
- Devolver código de salida distinto de cero si hay diferencias.
- No corregir automáticamente: solo alertar.

**Por qué**

`producto.stock` es una caché. Si se desincroniza, las ventas y los reportes mienten.

**Criterios de aceptación**

- En una base consistente, el comando no reporta diferencias.
- Si se altera manualmente un stock, el comando lo detecta.
- El comando no modifica datos.

**Pruebas**

- Test con datos consistentes.
- Test con diferencia inducida en `producto.stock`.
- Test con diferencia inducida en `producto_lote`.

**Decisión:** implementada con persistencia de cada corrida.

**Implementación**

- Servicio `App\Services\ConciliacionInventarioService` (comparación y persistencia transaccional).
- Comando `inventario:conciliar` con `--dry-run`, `--user=` y `--json`.
- Panel `/admin/conciliaciones` (permiso `conciliaciones`): historial, botón **Ejecutar conciliación** y detalle por producto/lote en modal.
- Tablas: `conciliacion_inventario`, `detalle_conciliacion_inventario`, `detalle_conciliacion_lote`.
- La conciliación **no corrige** stock, lotes ni kardex; solo deja evidencia. La corrección se hace con ajuste de inventario o inventario inicial.

**Verificación manual (19/11/2026)**

- `php artisan migrate:fresh --seed` crea las tres tablas y el permiso `conciliaciones`; el rol Administrador lo tiene.
- `php artisan inventario:conciliar` guarda el registro y devuelve `0` sin diferencias.
- Con una diferencia inducida de `+5` en `producto.stock`, el comando devuelve `1`, el listado muestra **Con diferencias** y el modal detalla el producto con `stock caché 395`, `stock lotes 390`, `diferencia 5`.
- El botón del panel guarda una conciliación con origen `manual`, usuario autenticado, recarga el historial y muestra el toast con el resultado.
- Datos de prueba revertidos al terminar (stock restaurado y conciliaciones eliminadas).

---

### B1 — Anulación de venta con motivo

**Qué hacer**

- Agregar a `venta`:
  - `estado` (`confirmada`, `anulada`).
  - `anulada_at`.
  - `anulada_user_id`.
  - `motivo_anulacion`.
- Deshabilitar edición y eliminación libre de ventas confirmadas.
- Agregar acción **Anular venta** con motivo obligatorio.
- Al anular:
  - revertir consumos con `revertirConsumos('Venta', id, correlativo)`;
  - registrar `REVERSION`;
  - devolver stock y lotes;
  - registrar un egreso compensatorio en caja;
  - marcar la venta como anulada.
- Excluir ventas anuladas de reportes, dashboard y caja.
- Mostrar marca de “ANULADA” en el ticket.

**Por qué**

Una venta confirmada no debería poder reescribirse sin rastro. El kardex ya es append-only, pero la venta sigue siendo editable.

**Criterios de aceptación**

- Una venta confirmada no se edita ni se elimina.
- La anulación exige motivo.
- La venta conserva su detalle original.
- El kardex conserva `VENTA` y `REVERSION`.
- La caja revierte con un movimiento compensatorio.
- Los reportes no suman ventas anuladas.

**Riesgos**

- Afecta caja, reportes, dashboard y ticket.
- Requiere migración y pruebas de regresión.

**Decisión:** implementada.

**Implementación**

- Migración `2026_06_26_000001_add_anulacion_to_venta_table`: `estado`, `anulada_at`, `anulada_user_id`, `motivo_anulacion`.
- `App\Services\AnulacionVentaService`: valida el motivo, bloquea la venta, impide doble anulación, revierte consumos con `revertirConsumos`, registra egreso compensatorio en la caja del cobro y marca la venta.
- `GET/POST /admin/ventas/{venta}/anular` con `AnularVentaRequest` (motivo obligatorio, 3-255 caracteres).
- `edit`, `update` y `destroy` responden `422` para ventas confirmadas; el listado ya no muestra editar/eliminar.
- Reportes (`ReporteService`) y dashboard filtran `venta.estado = confirmada`.
- Ticket y detalle muestran `ANULADA`, fecha, usuario y motivo.

**Verificación manual (19/11/2026)**

- El listado muestra el estado **Confirmada** y la acción **Anular venta**; el modal pide motivo.
- Al anular, el listado pasa a **Anulada**, el toast confirma, la caja registra el egreso compensatorio y el ticket muestra `*** ANULADA ***` con el motivo.
- Datos de prueba eliminados al terminar.

---

### C1 — Devolución de cliente con reingreso condicionado

**Decisión:** implementada sin módulo nuevo. La devolución total usa la **anulación de venta (B1)**; el reingreso condicionado y el reembolso parcial se resuelven con **ajustes por lote** y la **caja**, según el flujo operativo detallado en [`modulo-devoluciones-cliente.md`](modulo-devoluciones-cliente.md).

**Ideas clave**

- No se crea `devolucion_cliente`: duplicaría la reversión de B1 y el control por lote de los ajustes.
- **Devolución total:** anular la venta revierte caja, stock y lotes automáticamente (`REVERSION`). No se debe registrar además un `ajuste_positivo`, porque el stock ya volvió.
- **Producto no apto:** después de anular, el encargado registra un ajuste negativo (`dano`, `vencimiento` u `otro`) para retirar del stock lo que no es vendible.
- **Devolución parcial:** no existe documento nativo. Se documentan dos alternativas: anular + nueva venta por lo que el cliente conserva (mejor trazabilidad) o `ajuste_positivo` + egreso manual (más simple, sin vínculo).
- **Reembolso opcional:** en la anulación total es automático y no se puede omitir; en devolución parcial solo se registra como egreso manual si el negocio decide reembolsar.
- **Criterio del sistema:** solo reconoce como devolución la venta anulada. Un ajuste es corrección de inventario y un egreso manual es movimiento de caja; ninguno queda vinculado a la venta.
- **Reingreso:** lo decide el encargado según condición del producto (sellado, lote/vencimiento legibles, no vencido, conservación adecuada). El sistema no valida esa condición física.

**Limitaciones conocidas**

- La anulación elimina `lote_consumo`: el detalle de la venta deja de mostrar los lotes consumidos; el kardex conserva `VENTA` y `REVERSION`.
- Una devolución parcial no se descuenta de reportes de ventas; la venta sigue contando completa.
- El ajuste positivo crea una capa nueva con el último costo del lote, no restaura la capa original.
- El ajuste positivo bloquea lotes `baja`/`vencido`, pero no valida la fecha de vencimiento si el lote sigue `disponible`.
- Un reembolso parcial queda como `egreso_manual`, sin `referencia_id` a la venta.

**Criterios cubiertos**

- Devolución total acotada a lo vendido: `revertirConsumos` revierte exactamente lo consumido.
- Venta anulada no se vuelve a anular.
- Reversión exacta de capa y costo en devolución total.
- Reembolso total vinculado a la venta; kardex y caja trazables.

**Riesgo residual:** productos abiertos, vencidos o sin conservación verificable dependen del criterio del encargado y de un ajuste negativo; la cuarentena automática sigue diferida (E2).

---

### D1 — Inventario inicial por lotes

**Decisión:** implementar con alcance ampliado a migración: **inventario inicial manual + importación CSV**, antes de D2. El detalle completo está en [`plan-inventario-inicial-d1.md`](plan-inventario-inicial-d1.md).

**Resumen**

- Documento `inventario_inicial` en `borrador` → `confirmado`; el borrador no mueve stock.
- El borrador se puede **editar** (cabecera y líneas) antes de confirmar; al confirmar queda inmutable.
- Cada línea exige producto, presentación, lote, vencimiento, cantidad base y costo.
- Al confirmar: `producto_lote` (`es_inicial = true`), `lote_ingreso`, `producto.stock` y kardex `INGRESO` con referencia `InventarioInicial`.
- El alta de producto deja de pedir stock: nace con `stock = 0`; el stock inicial solo entra por este módulo.
- Importación CSV con dos archivos (catálogo y lotes), dry-run obligatorio, reporte de errores y confirmación transaccional.
- No se usan compras, ajustes positivos, SQL ni seeders para la carga inicial.
- No se acepta stock agregado sin lote/vencimiento ni `SIN-LOTE`.
- Para un cliente que viene de otro sistema: **corte limpio** (catálogo + inventario de apertura), sin migrar ventas/compras históricas.
- D2 se mantiene después de validar la migración.

**Criterios de aceptación**

- No se puede cargar stock inicial sin lote y vencimiento.
- El inventario inicial genera lotes y capas.
- El stock queda igual a la suma de capas.
- Se puede cargar un producto con varios lotes.
- Un error en cualquier línea revierte toda la confirmación.
- `inventario:conciliar` no reporta diferencias después de confirmar.

**Riesgos**

- Cambia la forma de crear productos.
- Requiere limpiar la lógica actual de stock inicial (`SIN-LOTE`, `asegurarLotes`).
- La calidad depende del conteo físico y de la plantilla del cliente.

**Estado:** implementada (D1.1–D1.4); piloto D1.5 pendiente.

---

### D2 — Eliminar `SIN-LOTE` y el stock inicial genérico

**Estado:** implementada la parte de código; la regularización de datos heredados es un procedimiento manual por cliente.

**Implementado**

- Se quitó el stock inicial del formulario de producto (D1.3).
- Se eliminó `InventarioService::asegurarLotes()` y `asegurarLotesInterno()`.
- `InventarioService::validarStockContraLotes()` bloquea compras/ventas/atenciones si `producto.stock` no coincide con la suma de lotes.
- `producto.stock` sin lotes ya no se “regulariza” creando `SIN-LOTE`; se bloquea con un mensaje que pide inventario inicial o ajuste.
- Comando `php artisan inventario:sin-lote` para listar lotes heredados (`--json` disponible).
- Tests actualizados para crear lotes reales en lugar de depender de stock suelto.
- El alta de producto sigue naciendo con `stock = 0` y sin lote.

**Pendiente**

- Regularizar en cada base con datos los `SIN-LOTE` existentes: conteo físico, ajuste negativo del `SIN-LOTE` e inventario inicial por lote real.
- La columna `producto.inventario` se conserva como legado oculto y sincronizado; eliminarla es una tarea posterior de limpieza, no bloquea la operación.

**Criterios de aceptación**

- Un producto nuevo nace con stock `0`.
- No se crea `SIN-LOTE` automáticamente.
- Si hay stock sin lotes o descuadre, el sistema bloquea la operación.
- El inventario inicial es la única vía para cargar stock existente.

**Riesgos**

- Es la tarea de mayor integridad.
- Afecta creación de productos, ventas, compras, ajustes y tests.
- Requiere plan de migración y pruebas de regresión completas.

---

### E1 — Código de barras

**Estado:** diferido por decisión.

**Qué haría falta**

- Mostrar el campo `codigo_barras` por presentación.
- Buscar por código en ventas, atenciones y compras.
- Asociar el código a `producto_presentacion`, no solo al producto.

**Por qué se difiere**

Es valioso para mostrador, pero no bloquea la operación actual. Se retoma cuando el flujo principal esté cerrado.

---

### E2 — Cuarentena de lote

**Estado:** diferido por decisión.

**Qué haría falta**

- Agregar estado `cuarentena`.
- Excluirlo de FEFO.
- Acción “Enviar a cuarentena” y “Liberar”.

**Por qué se difiere**

Se puede usar baja mientras no haya un proceso formal de retiro de calidad.

---

### E3 — Órdenes de compra y cuentas por pagar

**Estado:** diferido.

**Por qué**

La botica compra al contado o registra el pago fuera de caja. No hay crédito ni cuentas por pagar. El propio informe recomienda no construir órdenes de compra todavía.

---

### E4 — Historial de precios

**Estado:** evaluar.

**Qué haría falta**

- Tabla de historial por producto/presentación.
- O usar la auditoría existente si alcanza.

**Por qué se puede posponer**

La auditoría ya registra cambios. Un historial específico es útil, pero no urgente.

---

### E5 — Campos farmacéuticos del producto

**Estado:** evaluar.

**Qué haría falta**

- `principio_activo`.
- `concentracion`.
- `forma_farmaceutica`.
- `laboratorio` separado de marca.

**Por qué se puede posponer**

Mejora búsqueda y ficha, pero no cambia el control de inventario.

---

### E6 — Descartados por ahora

- Multi-almacén.
- Compras parciales complejas.
- IA para reposición.
- Control de cada tableta individual.
- Gestión avanzada de fabricantes/distribuidores.
- Configuración manual de fraccionamiento para todos los productos.

---

## 6. Orden alternativo por riesgo

Si se prioriza **integridad** antes que facilidad:

1. D1 — Inventario inicial por lotes.
2. D2 — Eliminar `SIN-LOTE`.
3. B1 — Anulación de venta.
4. C1 — Devolución de cliente (implementada vía B1 + ajustes).
5. A1 — Ocultar `inventario`.
6. A2 — Simplificar `paso`.
7. A3 — Conciliación de stock.

Recomendación: si hay poco tiempo, hacer **A1 + A2 + A3** ahora y dejar **D1 + D2** como bloque de integridad planificado.

---

## 7. Checklist para revisar cada tarea

Antes de implementar cada tarea, responder:

1. ¿Resuelve un problema real de la botica?
2. ¿Se puede hacer sin romper FEFO, lotes o kardex?
3. ¿Requiere migración? ¿Hay datos productivos? (Hoy no.)
4. ¿Afecta caja, reportes o dashboard?
5. ¿Se puede probar con feature tests?
6. ¿Se actualiza la documentación y `AGENTS.md`?
7. ¿Se implementa, se ajusta o se descarta?

---

## 8. Próximo paso

Revisar este documento en orden y marcar cada tarea:

- **Implementar**
- **Ajustar alcance**
- **Diferir**
- **Descartar**

Se sugiere empezar por **A1**, **A2** y **A3**, que son las de menor riesgo y no requieren migraciones grandes.

---

*Documento de planificación. No modifica el sistema por sí solo.*
