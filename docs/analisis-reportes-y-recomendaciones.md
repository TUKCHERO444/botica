# Análisis profundo de reportes y recomendaciones

> **Fecha:** 20/11/2026
> **Alcance:** módulo `/admin/reportes` (30 tipos en la versión actual: 13 originales + 14 de gestión), datos que los alimentan y oportunidades de mejora.
> **Naturaleza:** diagnóstico base. Las correcciones de los niveles 0 y 1 y el reporte de cobros por método ya fueron implementados; ver [`roadmap-mejora-reportes.md`](roadmap-mejora-reportes.md) §15. Las limitaciones que se describen a continuación reflejan el estado **anterior** a esa implementación.
> **Método:** revisión de `ReporteController`, `ReporteService`, las 12 vistas PDF, el JS de filtros, los modelos/migraciones y los servicios que generan ventas, atenciones, compras, caja, kardex, ajustes, devoluciones y conciliaciones.

---

## 1. Resumen ejecutivo

El módulo funciona y cubre las áreas importantes, pero en su estado original era un **generador de listados**, no un sistema de **reportes para decidir**. La mayoría de los reportes originales mostraban el detalle de un documento; solo algunos (rentabilidad, lotes y cobros) calculaban indicadores. Las correcciones de confiabilidad y los reportes de gestión ya están implementados.

Conclusiones principales:

1. **Hay un error de cálculo confirmado en el reporte de clientes.** El doble `LEFT JOIN` a `venta` y `atencion` produce un producto cartesiano: el total gastado se multiplica por la cantidad de transacciones del otro tipo. Un cliente con 2 ventas y 3 atenciones ve sus montos inflados (ejemplo: S/ 350 reales aparecen como S/ 900).
2. **El filtro "Entrada/Salida" del reporte de kardex nunca devuelve resultados**, porque el servicio compara contra `tipo_movimiento` (`INGRESO`, `VENTA`, etc.) y la interfaz envía `entrada`/`salida`.
3. **La valorización de inventario usa `producto.precio_compra`**, no el costo real de las capas (`lote_ingreso.costo_unitario_base`). El reporte de lotes sí usa capas. Dos reportes de inventario pueden dar valores distintos para el mismo stock.
4. **El reporte general mezcla ingresos por ventas con movimientos de caja** y los rotula como "ingresos". Un cobro de venta, un reembolso de proveedor y la anulación de una compra no son lo mismo, pero se suman en la misma tarjeta.
5. **Se suman cantidades de unidades distintas** ("Stock total", "Entradas/Salidas" de movimientos). Sumar tabletas, mililitros y gramos no tiene significado; el número es técnicamente correcto pero inútil para decidir.
6. **Hay datos valiosos que ya se capturan y no se explotan**: costo real por línea de venta y atención, costo por capa/lote, historial de costos de compra, desglose de pagos de ventas y atenciones (`pago`/`metodo_pago`), método de pago de compras, trazabilidad de kardex, tipos de ajuste (daño/vencimiento), conciliaciones, clics de promociones, registro web y último login de clientes.
7. **Faltan datos estructurales** para reportes que una botica realmente necesita: tipo de comprobante e IGV, plazo de crédito de proveedores, arqueo de caja (monto contado), marca de afectación a efectivo por método de pago, costo de servicios, receta/controlados y fecha de nacimiento del cliente.
8. **Riesgos técnicos del módulo**: el `tipo` de reporte no está validado contra una lista blanca, los parámetros de fecha/mes/año no se validan, la vista previa inyecta CSS global que puede romper el panel, y el reporte de auditoría corta en 500 registros sin advertirlo.
9. **La oportunidad más grande no es "agregar más PDF"**, sino cambiar el enfoque: reportes **por excepción** (qué debo hacer hoy), **comparativos** (vs. mes anterior), **exportables** (CSV/Excel) y **con una pregunta de negocio detrás**.

Prioridad recomendada:

| Prioridad | Qué hacer | Por qué |
|---|---|---|
| **P0** | Corregir clientes, kardex, valorización, etiquetas y validaciones | Sin esto, los números no son confiables |
| **P1** | Reportes de ventas por producto, margen, compras por proveedor, vencimientos y merma usando datos actuales | Máximo valor con bajo esfuerzo |
| **P2** | Medios de pago, IGV/comprobantes, crédito de proveedores y arqueo | Habilita reportes tributarios y de tesorería |
| **P3** | Promociones y reportes programados | Mejora continua |

---

## 2. Cómo funciona el módulo hoy

### 2.1. Arquitectura

```
routes/admin.php
  GET /admin/reportes            -> ReporteController@index      (panel de filtros)
  GET /admin/reportes/preview    -> ReporteController@preview    (HTML)
  GET /admin/reportes/generate   -> ReporteController@generate   (PDF)

ReporteController
  - carga catálogos para los selects (productos, clientes, proveedores, usuarios, categorías, marcas)
  - getReportData() elige el método del servicio según `tipo`
  - preview() renderiza la vista Blade
  - generate() renderiza la misma vista con DomPDF y descarga

ReporteService
  - 13 métodos: ventas, atenciones, ingresos, caja, inventario, lotes,
    movimientos, rentabilidad, kardex, clientes, pagos, general, auditoria

resources/views/admin/reportes/pdf/*.blade.php
  - una plantilla HTML por reporte, con estilos inline
resources/js/admin/reportes/index.js
  - envía todos los inputs `[id^="reporte-"]` como query string
```

No hay routers, colas ni caché: el PDF se genera de forma síncrona con DomPDF y se descarga. La vista previa es el mismo HTML del PDF inyectado dentro del panel.

### 2.2. Flujo de filtros

El JS no arma filtros por reporte: toma **todos** los campos cuyo `id` empieza con `reporte-` y los manda. El servidor decide cuáles usa según `tipo`. Ventajas: simple. Riesgos:

- No hay validación de fechas, mes, año, límite ni IDs.
- Un filtro oculto igual se envía si tiene valor.
- El reporte de kardex puede ejecutarse sin producto y devuelve vacío en lugar de un error claro.
- El reporte de auditoría no expone el filtro por módulo aunque el servicio lo soporta.

### 2.3. Patrón de cálculo

| Patrón | Dónde se usa | Consecuencia |
|---|---|---|
| Eloquent + colecciones | ventas, atenciones, compras, caja, inventario, lotes, movimientos, kardex, auditoría | Carga todas las filas en memoria; simple, pero pesado con volúmenes altos |
| SQL agregado | rentabilidad, clientes, general | Más eficiente, pero con errores de join y de agrupación |
| Cálculo en PHP | márgenes, valor de lotes, promedios | Correcto, pero disperso y difícil de reutilizar |

No existe una capa de "consulta de reporte" reutilizable ni un contrato de salida (DTO). Cada método devuelve un array con nombres distintos (`total_general`, `total_compras`, `total_ingresos`, `total_valor`), lo que impide comparar o componer reportes.

### 2.4. Inventario de reportes

| # | Reporte | Tablas principales | Filtros reales | Tipo de salida |
|---|---|---|---|---|
| 1 | Ventas | `venta`, `detalle_venta`, `cliente`, `users`, `producto` | fechas, usuario, cliente | Listado de ventas con productos |
| 2 | Atenciones | `atencion`, `atencion_producto`, `atencion_servicio`, `servicio` | fechas, usuario, cliente | Listado de atenciones |
| 3 | Compras | `ingreso`, `detalle_ingreso`, `proveedor`, `producto` | fechas, proveedor, usuario, pago | Listado de compras |
| 4 | Caja | `caja`, `caja_movimiento`, `users` | caja o fechas de apertura | Fichas de caja + movimientos |
| 5 | Inventario | `producto`, `categoria`, `marca` | categoría, marca, stock <= X | Listado valorizado |
| 6 | Lotes y costos | `producto_lote`, `lote_ingreso`, `producto` | categoría, marca, próximos N días | Listado de lotes valorizado |
| 7 | Kardex | `kardex_movimientos` | producto, fechas, tipo | Movimientos de un producto |
| 8 | Movimientos | `kardex_movimientos` | fechas, tipo, producto, usuario | Movimientos de inventario |
| 9 | Rentabilidad | `detalle_venta`, `atencion_producto`, `producto` | fechas, categoría, marca | Margen por producto |
| 10 | Clientes | `cliente`, `venta`, `atencion` | fechas, top N | Ranking de clientes |
| 11 | General | varias | mes, año | Resumen mensual |
| 12 | Auditoría | `auditoria`, `users` | fechas, usuario, acción | Bitácora |
| 13 | Cobros por método | `pago`, `metodo_pago`, `venta`, `atencion` | fechas, método, origen | Totales por método |
| 14-30 | Reportes de gestión | ventas, compras, inventario, margen, clientes, caja | ver [`modulo-reportes.md`](modulo-reportes.md) | Producto, proveedor, rotación, merma, RFM, web, sugerido, cumpleaños, resumen |

---

## 3. Ficha detallada por reporte

### 3.1. Ventas

**Qué hace.** Lista las ventas `confirmada` del período, con cliente, usuario y todos los productos de cada venta. Calcula total, cantidad y promedio.

**Datos que usa.** `venta.created_at`, `venta.total`, `venta.estado`, `detalle_venta.cantidad`, `detalle_venta.sub_total`, `producto.nombre`, `cliente`, `users`.

**Qué muestra.** Una fila por venta; los productos van uno debajo del otro dentro de la celda. Al final, total general.

**Qué responde bien.** "¿Cuánto vendí en el período?" y "¿qué vendió cada ticket?".

**Limitaciones.**

- Es un listado, no un análisis: no hay total por producto, por categoría, por día ni por vendedor.
- La columna "Subtotal" muestra en realidad `venta.total` (el nombre induce a error).
- No muestra ventas anuladas ni su motivo (se excluyen correctamente, pero no hay visibilidad).
- **El desglose de pagos ya existe** (`pago`/`metodo_pago`), pero el reporte de ventas no lo muestra ni permite filtrar por método. Tampoco hay tipo de comprobante, IGV ni descuento: esos campos siguen ausentes en el modelo.
- Al anular una venta, los registros de `pago` **no se borran**; cualquier reporte de cobros debe cruzar con `venta.estado = confirmada` para no contar el cobro de una venta anulada.
- No hay comparación con el período anterior.
- No hay resumen diario ni por hora.
- El precio unitario del request se ignora: `VentaController::resolverLineas()` siempre usa `presentacion.precio_venta`. Es decir, **hoy no se pueden registrar descuentos manuales** y la rentabilidad siempre refleja precio de lista.
- Con muchas ventas, el PDF crece mucho porque incrusta todos los productos de cada venta.

**Qué se puede sacar con los datos actuales.**

- Ventas por producto/presentación: unidades base, ingresos, costo real (`detalle_venta.costo_total`), margen y participación.
- Ventas por categoría, marca y vendedor.
- Ventas por día y por hora (para dotación y horarios).
- Ticket promedio, unidades por ticket, % de cliente genérico.
- Anulaciones por motivo y usuario.
- Cobros por método de pago (incluidos los mixtos) uniendo `pago` por `referencia_tipo = Venta`.

**Qué falta para reportes completos.** Tipo de comprobante, IGV, descuento aplicado y fecha de negocio separada de `created_at`. El medio de pago ya está registrado, pero falta explotarlo en reportes.

---

### 3.2. Atenciones

**Qué hace.** Lista atenciones con cliente, usuario, productos, servicios y observación.

**Datos que usa.** `atencion.created_at`, `atencion.total`, `atencion_producto`, `atencion_servicio.subtotal`, `servicio.nombre`.

**Qué muestra.** Una fila por atención; productos y servicios en columnas separadas.

**Qué responde bien.** "¿Qué atenciones se hicieron y cuánto se cobró?".

**Limitaciones.**

- **Ya tiene estado y anulación.** La atención nace `confirmada` con correlativo `A001-XXXXXXXX`, se anula con motivo y los reportes filtran confirmadas. La edición/eliminación libre ya no está disponible.
- **Editar o eliminar una atención con la caja cerrada desincroniza caja e historial.** `AtencionController::update()` solo ajusta el movimiento de caja si hay una caja abierta; `destroy()` solo borra el movimiento si hay caja abierta. Si la atención pertenece a una caja ya cerrada, el stock y el total cambian, pero el ingreso histórico de caja queda intacto (o el movimiento queda huérfano).
- No muestra el costo de los productos consumidos (`atencion_producto.costo_total`) ni el margen.
- Los servicios no tienen costo registrado; cualquier margen de servicio sería 100% ficticio.
- No hay profesional, diagnóstico, receta ni tipo de atención.
- No hay desglose producto vs servicio en el resumen.
- No hay ticket promedio por servicio ni ranking de servicios.

**Qué se puede sacar con los datos actuales.**

- Servicios más demandados por cantidad e ingreso.
- Mix producto/servicio por atención y por período.
- Atenciones por usuario y por cliente.
- Productos consumidos en atenciones (hoy invisibles en el reporte).
- Cobros por método de pago, incluidos los mixtos (`pago` por `referencia_tipo = Atencion`).

**Qué falta.** Estado/anulación con motivo, costo del servicio y campos clínicos si el negocio los necesita.

---

### 3.3. Compras

**Qué hace.** Lista compras `recibida` por fecha de documento, con proveedor, usuario, documento, total, forma de pago y productos recibidos. Resume total, desde caja y fuera de caja.

**Datos que usa.** `ingreso.fecha`, `ingreso.total`, `ingreso.pagado_desde_caja`, `ingreso.metodo_pago`, `detalle_ingreso`, `proveedor`.

**Qué responde bien.** "¿Cuánto compré y a quién?" y "¿cuánto salió de caja?".

**Limitaciones.**

- No hay fila de totales en la tabla (solo tarjetas).
- No incluye devoluciones a proveedor ni compras anuladas; el "total comprado" no es el **neto**.
- Usa `fecha` (documento) y no `fecha_recepcion`; una compra recibida después puede caer en otro período.
- No hay IGV: `subtotal` y `total` son iguales.
- No hay plazo de crédito, vencimiento de pago ni saldo.
- No hay compras por producto, proveedor ni evolución de costos.
- `total_productos` se calcula sumando `cantidad_base` de productos distintos y no se muestra; además mezcla unidades.
- No hay comparación de costo entre proveedores.

**Qué se puede sacar con los datos actuales.**

- Compras por proveedor: monto, cantidad, ticket promedio, participación, días desde la última compra.
- Costo histórico por producto y proveedor (`detalle_ingreso.costo_unitario_base` + `fecha`).
- Comparativo del mismo producto entre proveedores.
- Compras por método de pago.
- Devoluciones a proveedor y neto comprado.
- Concentración de proveedores (Pareto).

**Qué falta.** IGV, plazo/saldo, orden de compra y costo de servicios.

---

### 3.4. Caja

**Qué hace.** Muestra una o varias cajas con apertura, cierre, usuarios, montos y todos los movimientos.

**Datos que usa.** `caja`, `caja_movimiento` (`tipo`, `monto`, `descripcion`, `referencia_tipo`, `referencia_id`, `metodo_pago_id`, `afecta_efectivo`, `categoria`).

**Qué responde bien.** "¿Cómo cerró la caja?" y "¿qué movimientos tuvo?".

**Limitaciones.**

- El filtro por fechas usa `apertura_at`, no la fecha de los movimientos. Una caja abierta el 30 y cerrada el 1 se imputa al mes anterior; una caja de varios días no se puede cortar por día.
- No hay totales consolidados del período cuando se listan varias cajas.
- El arqueo ya está implementado (`efectivo_esperado`, `efectivo_contado`, `diferencia`); falta consolidar el resumen por método y categoría.
- Una caja abierta mostraba "Monto Final S/ 0.00"; ya se corrigió para mostrar "—".
- Los egresos ya tienen categoría (`gasto_luz`, `gasto_utiles`, etc.) y método de pago; falta explotarlos en un resumen por rubro.
- "Ingresos" de caja incluye ventas, atenciones, anulaciones de compra y reembolsos de proveedor. No es equivalente a "ventas".
- La caja ya guarda `metodo_pago_id`, `afecta_efectivo` y `categoria` por movimiento; el reporte los muestra.
- El reporte no agrupa ni totaliza por `referencia_tipo`, método o categoría, aunque el dato existe.

**Qué se puede sacar con los datos actuales.**

- Cierre diario con ingresos/egresos por referencia (venta, atención, compra, devolución, manual).
- Duración de cajas y cajas abiertas demasiado tiempo.
- Egresos por usuario y por descripción recurrente.
- Comparación ventas del día vs. ingresos de caja.
- Cobros por método dentro de la caja agrupando `caja_movimiento.metodo_pago_id`, con `afecta_efectivo` para separar efectivo de digital.
- Resumen de egresos por categoría (`gasto_luz`, `gasto_utiles`, `retiro_propietario`, etc.).

**Qué falta.** Consolidar el resumen por método/categoría y mostrar el arqueo por separado en el reporte.

---

### 3.5. Inventario

**Qué hace.** Lista productos con stock, precio de compra, precio de venta y valorizado (`stock * precio_compra`). Resume cantidad de productos, stock total y valor total.

**Datos que usa.** `producto.stock`, `producto.precio_compra`, `producto.precio_venta`, `producto.stock_minimo`, categoría y marca.

**Qué responde bien.** "¿Qué tengo y a qué precio de lista?".

**Limitaciones.**

- **Valoriza a `precio_compra` del producto, no al costo real de las capas.** Si el último costo cambió, el valor no coincide con el reporte de lotes ni con el costo usado en rentabilidad.
- Incluye productos inactivos y con stock 0 (infla el conteo).
- El filtro `stock_menor_que=0` se ignora porque el servicio usa `if ($stockMenorQue)`; no se puede listar "solo agotados".
- "Stock total" suma unidades heterogéneas (tabletas + ml + g). El número no es interpretable.
- No muestra stock por presentación, por lote ni por vencimiento.
- No hay rotación, días de cobertura, sobrestock ni slow movers.
- No marca inconsistencias `producto.stock` vs. suma de lotes (eso vive en conciliaciones).
- No hay costo promedio ponderado ni margen potencial.

**Qué se puede sacar con los datos actuales.**

- Valorizado real por capas (`lote_ingreso.cantidad_disponible_base * costo_unitario_base`).
- Diferencia entre valor a costo real y valor a `precio_compra`.
- Inventario por categoría/marca con #SKU y valor.
- Agotados, críticos y sobrestock.
- Rotación y días de cobertura usando ventas de 30/90 días.
- ABC de inventario (Pareto por valor y por rotación).
- Productos sin movimiento (capital inmovilizado).
- Margen potencial (`precio_venta` vs. costo real).

**Qué falta.** Stock máximo/reorden por producto, ubicación y costo de reposición.

---

### 3.6. Lotes y costos

**Qué hace.** Lista lotes con stock disponible, calcula valor desde las capas y costo promedio. Permite filtrar por categoría, marca y "próximos N días".

**Datos que usa.** `producto_lote`, `lote_ingreso.cantidad_disponible_base`, `lote_ingreso.costo_unitario_base`, `producto`.

**Qué responde bien.** "¿Qué lotes tengo, a qué costo y cuándo vencen?".

**Limitaciones.**

- El filtro "próximos N días" excluye los lotes **ya vencidos** (`fecha_vencimiento >= today`). Paradójicamente, el reporte de vencimientos no muestra lo más urgente salvo que se deje el filtro vacío.
- No hay cubos de vencimiento (vencido, 0-30, 31-60, 61-90, 91-180, >180).
- No muestra valor en riesgo ni merma potencial.
- No muestra proveedor/origen del lote ni fecha de recepción (aunque `lote_ingreso.ingreso_id` lo permite).
- No hay rotación por lote ni días estimados para agotarlo.
- No sugiere devolución a proveedor.
- No alerta lotes con costo mayor al precio de venta.
- No hay trazabilidad de qué ventas consumieron el lote (para un recall).

**Qué se puede sacar con los datos actuales.**

- Vencimientos por cubos, con unidades y valor.
- Valor en riesgo (vencido + próximos 30/60/90 días).
- Lotes con margen negativo (costo de capa > precio de venta).
- Proveedor de cada lote y devolución sugerida.
- Días para agotar según ventas recientes.
- Trazabilidad de lote vía `kardex_movimientos` (no vía `lote_consumo`, porque la anulación de venta elimina esos registros; el kardex es append-only).

**Qué falta.** Política de vencimiento por categoría y lead time de proveedor.

---

### 3.7. Kardex

**Qué hace.** Muestra los movimientos de un producto en un rango, con tipo, referencia, lote, cantidad, stock anterior/nuevo, observación y usuario.

**Datos que usa.** `kardex_movimientos`.

**Qué responde bien.** "¿Por qué este producto tiene este stock?".

**Limitaciones.**

- **El filtro "Entrada/Salida" no funciona**: la interfaz envía `entrada`/`salida` y la base guarda `INGRESO`, `VENTA`, `ATENCION`, `AJUSTE`, `BAJA`, `DEVOLUCION`, `REVERSION`. El resultado es siempre vacío.
- No exige producto: sin producto devuelve un reporte vacío en vez de un error.
- No muestra costo unitario ni valor del movimiento, aunque el kardex lo guarda.
- No hay saldo inicial ni saldo final valorizado.
- No hay filtro por lote ni por referencia.
- No hay límite de filas: un producto con años de movimientos puede generar un PDF enorme.

**Qué se puede sacar con los datos actuales.**

- Kardex valorizado: cantidad, costo unitario, costo total, saldo cantidad y saldo valor.
- Saldo inicial del período y saldo final.
- Movimientos por lote y por referencia.
- Detección de ajustes, bajas y reversiones por producto.

**Qué falta.** Saldo valorizado persistido (hoy se puede calcular, no consultar).

---

### 3.8. Movimientos especiales

**Qué hace.** Lista movimientos de kardex del período y resume cantidad de movimientos, entradas y salidas.

**Datos que usa.** `kardex_movimientos` con filtros por tipo, producto y usuario.

**Qué responde bien.** "¿Qué pasó con el inventario en el período?".

**Limitaciones.**

- "Entradas" y "salidas" suman cantidades de productos distintos: mezcla unidades.
- No muestra el impacto económico (costo de la merma, valor de la reversión).
- No distingue el motivo del ajuste (`daño`, `vencimiento`, `conteo`, `consumo interno`) porque el kardex solo guarda `AJUSTE` y una observación; el motivo está en `ajuste_inventario.tipo`.
- No incluye anulaciones de venta/compra a nivel documento ni devoluciones a proveedor con su motivo.
- No agrupa por tipo ni por usuario.
- No hay enlace al documento de origen.

**Qué se puede sacar con los datos actuales.**

- Movimientos por tipo con cantidad de operaciones, unidades y valor.
- Merma por motivo cruzando `ajuste_inventario` + detalles.
- Devoluciones a proveedor por proveedor y motivo.
- Anulaciones de venta y compra por usuario/motivo.
- Actividad por usuario (control interno).

**Qué falta.** Nada crítico; es cuestión de cruzar tablas que ya existen.

---

### 3.9. Rentabilidad

**Qué hace.** Cruza líneas de venta y atención por producto, suma ingresos, costo real (`costo_total`) y calcula margen y margen %.

**Datos que usa.** `detalle_venta`, `atencion_producto`, `producto.categoria_id_categoria`, `producto.marca_id_marca`.

**Qué responde bien.** "¿Qué productos dejan más margen?".

**Limitaciones.**

- **Excluye los servicios de las atenciones.** Como toda atención exige al menos un servicio, el reporte subestima el ingreso y el margen real de las atenciones.
- Usa la categoría/marca **actual** del producto, no la histórica; recategorizar cambia márgenes pasados.
- Si `costo_total` es 0 o null (datos antiguos), el margen aparece inflado sin advertencia.
- No muestra unidades por presentación ni precio promedio efectivo.
- No hay margen por categoría, marca, proveedor ni vendedor.
- No hay comparación con el período anterior ni evolución mensual.
- No hay contribución al margen (Pareto) ni umbral objetivo.
- No incluye el costo de servicios (no existe).
- No hay devoluciones parciales descontadas (la venta sigue contando completa).

**Qué se puede sacar con los datos actuales.**

- Margen bruto por producto, categoría y marca.
- Contribución al margen y ABC de rentabilidad.
- Productos con margen negativo o bajo objetivo.
- Productos con costo 0 (alerta de calidad de datos).
- Margen por vendedor.
- Margen de productos usados en atenciones (agregándolos).

**Qué falta.** Costo de servicios, costos fijos/gastos operativos, descuentos e IGV.

---

### 3.10. Clientes

**Qué hace.** Ranking de clientes por total gastado (ventas + atenciones) y número de transacciones.

**Datos que usa.** `cliente`, `venta` (confirmada, rango), `atencion` (rango).

**Qué responde bien.** "¿Quiénes son mis mejores clientes?" (con el error descrito abajo).

**Limitaciones.**

- **Error de producto cartesiano:** el `LEFT JOIN` simultáneo a `venta` y `atencion` multiplica las filas. `SUM(venta.total)` se repite una vez por cada atención y `SUM(atencion.total)` una vez por cada venta. Ejemplo: 2 ventas de S/ 100 y 3 atenciones de S/ 50 → 6 filas → S/ 600 + S/ 300 = S/ 900, cuando lo real es S/ 350.
- El cliente genérico (`00000001`, "SIN IDENTIFICAR") concentra las ventas anónimas y suele aparecer primero, distorsionando el ranking.
- No muestra teléfono/email, última compra, frecuencia, ticket promedio ni días sin comprar.
- No distingue clientes web (`registro_web`) de presenciales.
- No hay clientes nuevos por período, retención ni recompra.
- El `LIMIT` no se valida en el servicio (solo en el HTML, 1-100).

**Qué se puede sacar con los datos actuales.**

- Top clientes con ticket promedio, frecuencia y última compra.
- Segmentación RFM (recencia, frecuencia, monto).
- Clientes nuevos por mes (`cliente.created_at`).
- Clientes inactivos para campaña de recuperación.
- % de ventas a cliente genérico (indicador de fidelización).
- Clientes web activos (`ultimo_login`).

**Qué falta.** Fecha de nacimiento, dirección y consentimiento de marketing.

---

### 3.11. General

**Qué hace.** Resumen mensual: ventas, atenciones, total ingresos, ingresos/egresos de caja, compras (solo cantidad), stock crítico y top 10 productos por unidades.

**Datos que usa.** `venta`, `atencion`, `ingreso`, `caja`, `producto`, `detalle_venta`.

**Qué responde bien.** "¿Cómo va el mes en grandes números?".

**Limitaciones.**

- **Confunde ingreso por ventas con movimiento de caja.** "Total Ingresos" es ventas + atenciones; "Ingresos a Caja" incluye también anulaciones de compra y reembolsos de proveedor; "Egresos de Caja" incluye compras, anulaciones de venta y egresos manuales. No es utilidad ni flujo neto.
- No muestra costo ni margen.
- No muestra el monto de compras, solo la cantidad.
- El top 10 usa solo ventas, no atenciones, y ordena por unidades (no por ingreso ni margen).
- No hay comparación mes anterior / mismo mes del año anterior.
- No hay ticket promedio, clientes atendidos ni merma.
- `stock_critico` cuenta productos inactivos.
- Solo acepta mes/año; no un rango arbitrario.
- No hay gráficos ni semáforos.

**Qué se puede sacar con los datos actuales.**

- Resumen gerencial de una página: ventas netas, atenciones, margen bruto, compras, merma, caja neta, ticket promedio, #transacciones, clientes, top 5 por ingreso/margen.
- Comparativo mes actual vs. anterior y vs. mismo mes del año anterior.
- Alertas: lotes vencidos, stock crítico, caja abierta, margen negativo.
- Tendencia diaria del mes.

**Qué falta.** Costos operativos/gastos fijos para utilidad real y presupuesto.

---

### 3.12. Auditoría

**Qué hace.** Bitácora de acciones administrativas con usuario, módulo, acción, registro, descripción, IP y diferencias JSON.

**Datos que usa.** `auditoria.valores_anteriores`, `valores_nuevos`, `user_id`, `modulo`, `accion`.

**Qué responde bien.** "¿Quién hizo qué?".

**Limitaciones.**

- **Corta en 500 registros sin avisar.** Un período con mucha actividad queda incompleto y el PDF no lo advierte.
- No hay resumen por usuario, módulo o acción.
- Las acciones reales incluyen `baja`, `toggle-estado`, etc.; el filtro de la interfaz solo ofrece crear/actualizar/eliminar, por lo que no se pueden aislar las demás.
- El filtro por módulo existe en el servicio pero **no está en la interfaz**.
- El diff se muestra como JSON crudo, poco legible.
- No hay filtro por IP ni por registro.
- No hay reporte específico de eventos críticos (anulaciones, ajustes, cambios de precio/costo, roles).

**Qué se puede sacar con los datos actuales.**

- Resumen de actividad por usuario/módulo/acción.
- Eventos críticos: anulaciones, ajustes, bajas de lote, cambios de precio/costo, apertura/cierre de caja, cambios de roles.
- Reconstrucción de un cambio campo a campo con nombres traducidos.
- Exportación completa con advertencia de truncamiento.

**Qué falta.** Política de retención y firma/hash de integridad.

---

## 4. Hallazgos transversales

### 4.1. Errores funcionales confirmados

| ID | Severidad | Hallazgo | Evidencia | Impacto |
|---|---|---|---|---|
| H-01 | **Crítica** | Producto cartesiano en el reporte de clientes | `ReporteService::clientes()`: dos `leftJoin` en el mismo query | Totales gastados inflados; decisiones de fidelización sobre datos falsos |
| H-02 | **Alta** | Filtro Entrada/Salida del kardex no coincide con los valores almacenados | `index.blade.php` envía `entrada`/`salida`; BD guarda `INGRESO`/`VENTA`/... | Reporte vacío; parece que no hay movimientos |
| H-03 | **Alta** | Inventario valoriza con `precio_compra`, lotes con costo de capa | `ReporteService::inventario()` vs. `lotes()` | Dos valores distintos para el mismo inventario |
| H-04 | **Alta** | El reporte de caja filtra por `apertura_at` y no consolida | `ReporteService::caja()` | Cajas de varios días mal imputadas; sin total del período |
| H-05 | **Alta** | "Total ingresos" del general mezcla ventas y caja | `ReporteService::general()` | Lectura financiera equivocada |
| H-06 | **Media** | Suma de unidades heterogéneas | `inventario.total_stock`, `lotes.total_disponible`, `movimientos.total_entradas/salidas`, `ingresos.total_productos` | Indicadores sin significado |
| H-07 | **Media** | La vista previa inyecta CSS global | `preview()` devuelve `<html><style>body{...}` dentro del panel | Puede romper tipografía/estilos del panel |
| H-08 | **Media** | `tipo` no se valida contra lista blanca | `generate()` usa `view("admin.reportes.pdf.{$tipo}")` | Error 500 con tipos inválidos; superficie de riesgo |
| H-09 | **Media** | Fechas, mes, año, límite e IDs sin validación | `ReporteController::getReportData()` | 500 por fechas inválidas; límites arbitrarios |
| H-10 | **Media** | `stock_menor_que=0` se ignora | `if ($stockMenorQue)` | No se puede listar solo agotados |
| H-11 | **Media** | "Próximos N días" excluye vencidos | `lotes()`: `whereDate >= today()` | El reporte de vencimientos omite lo más urgente |
| H-12 | **Media** | Auditoría truncada en 500 sin aviso | `auditoria()`: `limit(500)` | Evidencia incompleta |
| H-13 | **Media** | Acciones y módulo no filtrables en auditoría | `index.blade.php` solo ofrece 3 acciones | No se aíslan eventos críticos |
| H-14 | **Media** | Compras sin totales ni devoluciones/anuladas | Vista `ingresos.blade.php` | "Total comprado" no es neto |
| H-15 | **Baja** | Paginación `{PAGE_NUM}` probablemente literal | 12 vistas PDF; no hay `page_text` configurado | Pie de página poco profesional (verificar visualmente) |
| H-16 | **Baja** | Columna "Subtotal" muestra el total | `ventas.blade.php` | Confusión menor |
| H-17 | **Baja** | Sin identidad del negocio en el PDF | No usa `config/ticket.php` | Reporte informal para terceros |
| H-18 | **Baja** | Solo PDF, sin CSV/Excel | `generate()` | El dueño no puede analizar en hoja de cálculo |
| H-19 | **Baja** | Selects cargan todos los productos/clientes | `ReporteController::index()` | Panel lento con catálogos grandes |
| H-20 | **Baja** | `precio` del request de venta se ignora | `VentaController::resolverLineas()` | No hay descuentos manuales; margen siempre a lista |
| H-21 | **Resuelto** | Atenciones editables/eliminables sin estado | `AtencionController` + `AnulacionAtencionService` | Ya tienen estado, anulación y filtro en reportes |
| H-22 | **Media** | Costo 0 en líneas antiguas infla margen | `COALESCE(costo_total, 0)` en rentabilidad | Margen falso sin señal de alerta |
| H-23 | **Alta** | Editar/eliminar una atención con caja cerrada deja la caja descuadrada | `AtencionController::update()` y `destroy()` solo ajustan caja si está abierta | El historial de caja no coincide con las atenciones |

### 4.2. Limitaciones del modelo de datos

No son "bugs": son campos que no existen y que bloquean reportes que el negocio sí necesita.

| Área | Campo faltante | Reporte que habilita |
|---|---|---|
| Ventas | `tipo_comprobante`, `serie`, `numero`, `igv`, `descuento` | Libro de ventas, margen neto de descuentos |
| Atenciones | `estado`, `motivo_anulacion`, `profesional_id`, `diagnostico`, `receta` | Atenciones anuladas, productividad clínica, libro de controlados |
| Productos | `principio_activo`, `registro_sanitario`, `requiere_receta`, `categoria_terapeutica`, `stock_maximo`, `ubicacion` | Control DIGEMID, reposición, surtido |
| Clientes | `fecha_nacimiento`, `direccion`, `sexo` | Cumpleaños, segmentación, campañas |
| Proveedores | `plazo_credito`, `lead_time`, `contacto` | Cuentas por pagar, sugerido de compra |
| Compras | `fecha_vencimiento_pago`, `saldo`, `estado_pago`, `orden_compra`, `igv` | Tesorería, compras al crédito, libro de compras |
| Caja | `monto_contado`, `diferencia`, `categoria_egreso`; `metodo_pago.afecta_efectivo` | Arqueo y control de diferencias por método |
| Servicios | `costo` | Margen real de servicios |
| Promociones | `impresiones`, `conversion` | ROI de promociones (hoy solo hay clics) |
| Devoluciones de cliente | Documento propio | Devoluciones parciales trazables |

---

## 5. Datos que ya existen y no se explotan

| Fuente | Dato | Insight que habilita | Estado |
|---|---|---|---|
| `detalle_venta` | `costo_total` | Margen real por línea | Solo se usa en rentabilidad |
| `atencion_producto` | `costo_total` | Margen de productos en atenciones | **No se usa** |
| `lote_ingreso` | `costo_unitario_base`, `costo_total` | Valor real, evolución de costos | Solo en lotes |
| `detalle_ingreso` | `costo_unitario_base`, `fecha` | Comparativo de proveedores | **No se usa** |
| `ingreso` | `fecha_recepcion`, `metodo_pago`, `referencia_pago` | Plazos y medios de pago | Solo se muestra el método |
| `pago` / `metodo_pago` | desglose por método y monto | Cobros por método, adopción Yape/tarjeta, conciliación con caja | **No se usa en reportes** |
| `caja_movimiento` | `referencia_tipo`, `referencia_id` | Cierre por origen del movimiento | No se resume |
| `kardex_movimientos` | `costo_unitario_base`, `stock_anterior/nuevo` | Kardex valorizado | No se muestra el costo |
| `producto_lote` | `es_inicial`, `cantidad_inicial_base` | Inventario inicial vs. compras | **No se usa** |
| `lote_consumo` | consumos por lote | Trazabilidad (con cuidado: se borra al anular) | Solo en detalle de venta |
| `ajuste_inventario` | `tipo`, `motivo`, `total` | Merma por causa y costo | **No se reporta** |
| `devolucion_proveedor` | `motivo`, `total`, `reembolsado_a_caja` | Devoluciones y recuperación | **No se reporta** |
| `conciliacion_inventario` | diferencias stock vs. lotes | Calidad de inventario | Solo módulo de conciliaciones |
| `cliente` | `registro_web`, `ultimo_login`, `created_at` | Adopción web, clientes nuevos, inactivos | **No se usa** |
| `promocion` | `clicks` | Interés por campaña (limitado) | Solo contador |
| `auditoria` | `valores_anteriores/nuevos` | Control interno | Solo bitácora |
| `inventario_inicial` | `total_costo`, líneas | Línea base de valorización | **No se usa** |

---

## 6. Diccionario de KPIs propuesto

Definir los indicadores antes de construir reportes evita que cada PDF calcule "margen" de forma distinta.

| KPI | Fórmula | Fuente | Cuidado |
|---|---|---|---|
| Venta neta | Σ `venta.total` con `estado = confirmada` | `venta` | No incluir anuladas |
| Ingreso por atenciones | Σ `atencion.total` | `atencion` | Filtrar `estado = confirmada` |
| Ingreso total | Venta neta + atenciones | — | No es "ingreso de caja" |
| Ticket promedio | Ingreso total / #transacciones | — | Separar ventas y atenciones |
| Unidades vendidas | Σ `cantidad_base` | `detalle_venta` | Solo comparable dentro del mismo producto/unidad |
| Costo de ventas | Σ `costo_total` | `detalle_venta`, `atencion_producto` | Alerta si es 0 |
| Margen bruto | Ingreso - costo de ventas | — | No es utilidad neta |
| Margen % | Margen bruto / ingreso | — | Excluir ingresos 0 |
| Markup | Margen bruto / costo | — | Útil para fijar precios |
| Rotación | Unidades vendidas / stock promedio | ventas + stock | Requiere período comparable |
| Días de cobertura | Stock actual / promedio diario vendido | stock + ventas 90 días | No aplica a productos nuevos |
| GMROI | Margen bruto / inventario promedio a costo | — | Indicador de eficiencia |
| Merma | Σ costo de ajustes negativos (`daño`, `vencimiento`) + bajas | `ajuste_inventario` | Separar de conteo físico |
| Quiebre | #SKU con stock 0 / #SKU activos | `producto` | Excluir inactivos |
| % cliente genérico | Ventas a cliente genérico / venta neta | `venta` | Indicador de fidelización |
| Anulación % | Monto anulado / monto vendido | `venta` | Revisar por usuario/motivo |
| Cobro por método | Σ `pago.monto` agrupado por `metodo_pago` | `pago` + `metodo_pago` | Excluir ventas anuladas; los pagos mixtos se reparten |
| Valor en riesgo | Σ cantidad * costo de lotes que vencen en ≤ 90 días | `producto_lote` + capas | Incluir vencidos |
| Cobertura de compra | Días de stock vs. lead time | stock + ventas + proveedor | Requiere lead time |
| Contribución al margen | Margen del producto / margen total | rentabilidad | Pareto 80/20 |

---

## 7. Recomendaciones

### 7.1. Fase 0 — Confiabilidad (hacer primero)

| ID | Recomendación | Detalle | Esfuerzo |
|---|---|---|---|
| R-01 | Corregir el reporte de clientes | Reemplazar el doble join por dos subconsultas agregadas (o `UNION ALL` + `GROUP BY`) y sumar en PHP/SQL una sola vez | S |
| R-02 | Corregir el filtro de kardex | Usar la lista real de `tipo_movimiento` o interpretar Entrada/Salida por el signo de `cantidad` | S |
| R-03 | Unificar la valorización de inventario | Valorizar con capas disponibles y mostrar, si se quiere, la diferencia vs. `precio_compra` | M |
| R-04 | Eliminar sumas de unidades heterogéneas | Mostrar "unidades por unidad de medida" o reemplazar por #SKU y valor | S |
| R-05 | Separar "ingreso por ventas" de "movimiento de caja" | Renombrar tarjetas y agrupar por origen (`referencia_tipo`) | S |
| R-06 | Validar `tipo` con lista blanca y validar filtros | `FormRequest` para fechas, mes, año, límite, IDs; rechazar `tipo` desconocido | S |
| R-07 | Aislar la vista previa | Renderizar en iframe o sanear el HTML para que no filtre `body`, `table`, `.footer` al panel | S |
| R-08 | Agregar totales y contexto | Fila de totales en compras y caja; período y filtros visibles en el encabezado | S |
| R-09 | Reporte de vencimientos con vencidos | Cubos vencido / 0-30 / 31-60 / 61-90 / 91-180 / >180 y valor en riesgo | S |
| R-10 | Advertir truncamientos | Auditoría: indicar "mostrando 500 de N" o paginar | S |
| R-11 | Identidad del negocio en PDF | Usar `config/ticket.php` (nombre, RUC, dirección) y numerar páginas correctamente | S |
| R-12 | Exportación CSV | Agregar `formato=csv` a los reportes tabulares | M |

### 7.2. Fase 1 — Reportes de gestión con datos actuales

| ID | Reporte propuesto | Pregunta que responde | Contenido mínimo | Filtros |
|---|---|---|---|---|
| R-13 | **Ventas por producto/presentación** | ¿Qué se vende y cuánto deja? | Producto, presentación, unidades base, ingreso, costo, margen, % participación, ABC | fechas, categoría, marca |
| R-14 | **Resumen diario de ventas** | ¿Cómo va el día? | Fecha, #tickets, unidades, ingreso, ticket promedio, margen | fechas, usuario |
| R-15 | **Ventas por vendedor y hora** | ¿Quién y cuándo se vende? | Usuario, hora, #tickets, ingreso, ticket promedio | fechas |
| R-16 | **Anulaciones y motivos** | ¿Qué se anula y por qué? | Correlativo, fecha, usuario, monto, motivo, cliente | fechas, usuario |
| R-17 | **Compras por proveedor** | ¿A quién le compro y a qué costo? | Proveedor, #compras, monto, ticket promedio, % participación, última compra | fechas |
| R-18 | **Evolución de costos por producto** | ¿Cuánto subió el costo? | Producto, proveedor, fecha, costo anterior, costo nuevo, variación % | fechas, proveedor |
| R-19 | **Devoluciones a proveedor** | ¿Qué devolví y recuperé? | Correlativo, proveedor, motivo, unidades, monto, reembolso | fechas, proveedor |
| R-20 | **Ajustes y merma por motivo** | ¿Cuánto pierdo y por qué? | Tipo (daño/vencimiento/conteo), producto, lote, unidades, costo | fechas, tipo |
| R-21 | **Vencimientos y valor en riesgo** | ¿Qué se me va a vencer? | Lote, producto, vencimiento, días, unidades, costo, valor, proveedor | cubos, categoría |
| R-22 | **Rotación y cobertura** | ¿Qué se mueve y qué está muerto? | Producto, ventas 30/90 días, stock, días de cobertura, clasificación | categoría, marca |
| R-23 | **Sugerido de compra** | ¿Qué debo reponer? | Producto, stock, mínimo, ventas diarias, sugerido, proveedor habitual | categoría, proveedor |
| R-24 | **Margen por categoría/marca/vendedor** | ¿Dónde está el negocio rentable? | Ingreso, costo, margen, margen %, #SKU | fechas |
| R-25 | **Servicios más demandados y rentables** | ¿Qué servicios conviene impulsar? | Servicio, cantidad, ingreso, ticket promedio, margen (si hay costo) | fechas |
| R-26 | **Clientes RFM / inactivos** | ¿A quién puedo recuperar? | Cliente, última compra, frecuencia, monto, ticket promedio, segmento | fechas, segmento |
| R-27 | **Cierre de caja diario** | ¿Cuadra la caja? | Apertura, ventas, otros ingresos, egresos por tipo, esperado, contado, diferencia | fecha, caja |
| R-27A | **Cobros por método de pago** | ¿Cuánto entra por efectivo, Yape, Plin o tarjeta? | Método, #operaciones, monto, % participación, ventas vs. atenciones | fechas, método, tipo |
| R-28 | **Resumen gerencial mensual** | ¿Cómo cerró el mes? | Ventas, atenciones, margen, compras, merma, caja, ticket, top 5, alertas, comparativos | mes/año |

### 7.3. Fase 2 — Habilitadores de datos

| ID | Cambio | Habilita |
|---|---|---|
| R-29 | Agregar `afecta_efectivo` a `metodo_pago` | Separar efectivo de digital en arqueo y conciliación (el desglose `pago` ya existe) |
| R-30 | Registrar tipo de comprobante, serie, número e IGV | Libro de ventas/compras y reportes tributarios |
| R-31 | Registrar `fecha_vencimiento_pago` y `saldo` en compras | Cuentas por pagar y flujo de caja proyectado |
| R-32 | Agregar arqueo (`monto_contado`, `diferencia`) | Control real de caja y responsabilidad por turno |
| R-33 | Agregar `categoria_egreso` | Reporte de gastos por rubro |
| R-34 | Agregar costo a servicios | Margen real de atenciones |
| R-35 | Agregar `stock_maximo` y `lead_time` | Sugerido de compra automático |
| R-36 | Agregar estado/anulación a atenciones | Reportes históricos confiables |
| R-37 | Agregar `fecha_nacimiento` y `direccion` a clientes | Campañas y segmentación |
| R-38 | Registrar receta/controlados y registro sanitario | Cumplimiento DIGEMID y libro de controlados |
| R-39 | Documentar devoluciones parciales de cliente | Trazabilidad de devoluciones |

### 7.4. Fase 3 — Plataforma de reportes

| ID | Mejora | Beneficio |
|---|---|---|
| R-40 | Separar "documentos" de "reportes de gestión" en la UI | El usuario entiende qué es listado y qué es análisis |
| R-41 | Filtros por rango rápido (hoy, ayer, semana, mes, mes anterior, año) | Menos errores de fecha |
| R-42 | Reportes programados por correo | Cierre diario/mensual automático |
| R-43 | Exportación CSV/Excel y enlaces compartibles | Análisis fuera del sistema |
| R-44 | Vista previa en iframe con estilos aislados | No rompe el panel |
| R-45 | Dashboard con filtros y drill-down | Del KPI al documento |
| R-46 | Reportes por excepción con semáforos | "Qué hacer hoy" en lugar de "qué pasó" |
| R-47 | Índices y consultas agregadas | PDFs rápidos con volúmenes reales |
| R-48 | Pruebas de regresión de cálculos | Evitar que un cambio rompa totales |

---

## 8. Catálogo objetivo por frecuencia de uso

### Diario (al cierre)

1. Cierre de caja y arqueo.
2. Ventas del día (resumen + ticket promedio + anulaciones).
3. Alertas: caja abierta, stock crítico, lotes vencidos.

### Semanal (abastecimiento)

4. Vencimientos y valor en riesgo.
5. Rotación y cobertura.
6. Sugerido de compra.
7. Compras y devoluciones de la semana.
8. Merma y ajustes.

### Mensual (gerencia)

9. Resumen gerencial comparado.
10. Ventas por producto/categoría/marca.
11. Margen por producto/categoría/vendedor.
12. Compras por proveedor y evolución de costos.
13. Clientes nuevos, activos, inactivos y RFM.
14. Servicios más demandados.

### A demanda (control y trazabilidad)

15. Ventas por cliente y por comprobante.
16. Kardex valorizado por producto.
17. Trazabilidad por lote (recall).
18. Anulaciones de venta/compra.
19. Auditoría de eventos críticos.
20. Conciliación stock vs. lotes.

---

## 9. Arquitectura recomendada (sin implementar)

No hace falta reescribir el módulo. Bastan cambios acotados:

1. **Lista blanca de reportes** en `ReporteController` (mapa `tipo => método`), validada con `FormRequest`.
2. **Separar consultas de presentación**: mantener `ReporteService` para cálculos, pero devolver estructuras estables (`titulo`, `periodo`, `resumen`, `columnas`, `filas`, `totales`) para reutilizar CSV/PDF.
3. **Consultas agregadas en SQL** para los reportes de volumen (ventas por producto, clientes, rotación), no colecciones completas.
4. **Índices sugeridos**:
   - `venta (estado, created_at)`
   - `atencion (created_at)`
   - `ingreso (estado, fecha)`
   - `detalle_ingreso (producto_id_producto)`
   - `caja_movimiento (caja_id_caja, created_at)`
   - `producto_lote (fecha_vencimiento, estado)`
   - `auditoria (created_at, user_id, modulo)`
5. **Vista previa aislada** (iframe con `srcdoc` o endpoint que devuelva solo el fragmento sin `<style>` global).
6. **Formato de salida**: PDF para firma/archivo, CSV/Excel para análisis.
7. **Pruebas**: un test por cálculo crítico (clientes, margen, valorización, vencimientos, caja).
8. **Metadatos**: identidad del negocio desde `config/ticket.php` y sello de generación (fecha, usuario, hash opcional).
9. **Permisos**: evaluar separar `reportes` en `reportes.ver` y `reportes.financieros` si se incorporan márgenes y caja a más roles.

---

## 10. Roadmap sugerido

| Etapa | Contenido | Resultado esperado |
|---|---|---|
| **1. Confiabilidad** | R-01 a R-12 | Números correctos, filtros que funcionan, PDFs presentables |
| **2. Gestión comercial** | R-13 a R-18, R-24, R-25 | Saber qué vender, a qué precio y con qué margen |
| **3. Inventario y abastecimiento** | R-19 a R-23 | Reducir quiebres, vencimientos y capital inmovilizado |
| **4. Clientes y caja** | R-26, R-27 | Fidelizar, recuperar clientes y cuadrar caja |
| **5. Datos habilitantes** | R-29 a R-36 | Reportes tributarios, tesorería y margen real |
| **6. Plataforma** | R-37 a R-48 | Reportería programada, exportable y por excepción |

---

## 11. Decisiones que debe tomar el negocio

Antes de implementar la fase 2, definir:

1. **¿Se emite boleta/factura electrónica?** Si sí, el sistema necesita tipo, serie, número, IGV y cliente con RUC. Sin eso, los reportes no sirven para SUNAT.
2. **¿Los precios incluyen IGV?** Cambia el cálculo de margen y de ingreso neto.
3. **¿Se usará el desglose de pagos para arqueo por método?** El registro ya existe; falta definir qué métodos afectan efectivo y cómo se compensan las anulaciones.
4. **¿Habrá arqueo de caja?** Sin monto contado no hay control de diferencias.
5. **¿Se controlarán recetas/psicotrópicos?** Obliga a registrar receta, profesional y libro de controlados.
6. **¿Se medirá al cliente genérico?** Definir si se excluye del ranking o se muestra aparte.
7. **¿Cuál es el margen objetivo por categoría?** Sin umbral no hay alerta de margen bajo.
8. **¿Se trabaja con compras al crédito?** Define cuentas por pagar y flujo proyectado.
9. **¿Habrá más de una sede?** Define consolidación y transferencias.
10. **¿Qué reportes se imprimen para terceros?** Define identidad, firma y formato.

---

## 12. Anexos

### A. Mapa rápido tabla → reporte

| Tabla | Reportes actuales | Reportes futuros |
|---|---|---|
| `venta` / `detalle_venta` | ventas, rentabilidad, clientes, general | ventas por producto, vendedor, anulaciones, ticket promedio |
| `atencion` / `atencion_producto` / `atencion_servicio` | atenciones, rentabilidad, clientes | servicios, mix, margen de atenciones |
| `ingreso` / `detalle_ingreso` | compras | compras por proveedor, evolución de costos, cuentas por pagar |
| `caja` / `caja_movimiento` | caja, general | cierre diario, arqueo, egresos por tipo |
| `producto` | inventario, general | rotación, cobertura, ABC, sugerido |
| `producto_lote` / `lote_ingreso` | lotes | vencimientos, valor en riesgo, trazabilidad |
| `kardex_movimientos` | kardex, movimientos | kardex valorizado, eventos críticos |
| `ajuste_inventario` | — | merma por motivo |
| `devolucion_proveedor` | — | devoluciones y recuperación |
| `cliente` | clientes | RFM, nuevos, inactivos, adopción web |
| `auditoria` | auditoría | eventos críticos, actividad por usuario |
| `conciliacion_inventario` | — | diferencias de inventario |

### B. Checklist para validar cualquier reporte nuevo

1. ¿Qué pregunta de negocio responde?
2. ¿Qué acción dispara si el número sale mal?
3. ¿Incluye o excluye anulados de forma explícita?
4. ¿Usa fecha de negocio o `created_at`?
5. ¿Suma unidades comparables?
6. ¿Valoriza a costo real o a precio de lista?
7. ¿Tiene totales y subtotales?
8. ¿Se puede exportar?
9. ¿Tiene filtros validados y valores por defecto?
10. ¿Tiene prueba automatizada del cálculo?

### C. Glosario breve

- **Capa**: registro de `lote_ingreso` con cantidad y costo; el stock real vive aquí.
- **Lote**: `producto_lote` con código y vencimiento; agrupa capas.
- **FEFO**: primero en vencer, primero en salir.
- **Kardex**: bitácora append-only de movimientos de inventario.
- **Merma**: pérdida de inventario por daño, vencimiento o baja.
- **Valor en riesgo**: costo del stock que vence pronto o ya venció.
- **RFM**: recencia, frecuencia y monto para segmentar clientes.

---

## 13. Hoja de ruta

La secuencia de implementación, ordenada de lo más simple a lo más complejo y con dependencias, esfuerzo y criterios de aceptación, está en [`roadmap-mejora-reportes.md`](roadmap-mejora-reportes.md).

---

*Este documento es un diagnóstico y una propuesta. No modifica el comportamiento actual del sistema.*
