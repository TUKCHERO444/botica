# Roadmap de mejora de reportes — de lo más simple a lo más complejo

> **Fecha:** 20/11/2026
> **Base:** [`analisis-reportes-y-recomendaciones.md`](analisis-reportes-y-recomendaciones.md)
> **Naturaleza:** plan de implementación. El estado de avance está en la sección 15.
> **Criterio de orden:** primero lo que se corrige con las plantillas y consultas actuales; después los reportes nuevos que usan tablas existentes; al final los campos, procesos y cumplimiento que requieren migraciones o cambios operativos.

---

## 1. Principios de secuencia

1. **Primero la verdad, después el valor.** No tiene sentido agregar reportes nuevos si los actuales suman mal.
2. **No migrar mientras se pueda calcular.** Todo lo que se pueda resolver con `venta`, `detalle_venta`, `atencion`, `ingreso`, `lote_ingreso`, `kardex`, `caja` y `auditoria` va antes de tocar el modelo.
3. **Cada paso debe poder desplegarse solo.** Nada de "big bang": un cambio, una verificación, un despliegue.
4. **Cada reporte nuevo debe responder una pregunta y disparar una acción.** Si no cambia una decisión, no se construye.
5. **PDF para archivar, CSV para analizar.** El PDF no reemplaza la hoja de cálculo del dueño.
6. **Lo nuevo no debe romper lo existente.** Los reportes son de solo lectura; cualquier cambio de cálculo se prueba contra datos reales.

### Escala de esfuerzo

| Talla | Equivalente aproximado | Ejemplo |
|---|---|---|
| **S** | ≤ 4 horas | Cambiar un rótulo, agregar una fila de totales |
| **M** | 1–2 días | Corregir una consulta, agregar un reporte simple |
| **L** | 3–5 días | Reporte con cruce de tablas y filtros nuevos |
| **XL** | 1–2 semanas | Campo nuevo con migración, formulario y reporte |
| **XXL** | > 2 semanas | Cambio de proceso con impacto en varias áreas |

---

## 2. Vista general

| Nivel | Naturaleza | ¿Requiere migración? | Esfuerzo | Resultado |
|---|---|---|---|---|
| **0** | Presentación y textos | No | S | Reportes presentables y sin ambigüedades |
| **1** | Corrección de cálculos y filtros | No | S–M | Números confiables |
| **2** | Reportes nuevos con datos existentes | No | M–L | Reportes de gestión |
| **3** | Campos nuevos simples | Sí | L–XL | Habilitadores de reportes |
| **4** | Cambios de proceso | Sí | XL | Tesorería, impuestos y control |

La línea de corte más importante: **los niveles 0, 1 y 2 no tocan la base de datos**. Se pueden ejecutar sin riesgo de migración y sin detener la operación.

> **Fuera de alcance:** facturación electrónica SUNAT, receta/controlados DIGEMID, multi-sede y BI/analítica avanzada. No forman parte del alcance real del sistema y no se incluyen en este roadmap.

---

## 3. Nivel 0 — Presentación y textos

**Objetivo:** que los reportes actuales se lean bien y no induzcan a error. No cambia ningún cálculo.

| ID | Tarea | Dónde | Talla | Criterio de aceptación |
|---|---|---|---|---|
| E0-01 | Cambiar "Subtotal" por "Total" en el reporte de ventas | `resources/views/admin/reportes/pdf/ventas.blade.php` | S | El encabezado coincide con el dato |
| E0-02 | Separar "Ingreso por ventas" de "Movimiento de caja" en el general | `pdf/general.blade.php` | S | Dos secciones con nombres distintos |
| E0-03 | Agregar fila de totales al reporte de compras | `pdf/ingresos.blade.php` | S | Total general visible |
| E0-04 | Agregar totales consolidados al reporte de caja | `pdf/caja.blade.php` | S | Totales del período, no solo por caja |
| E0-05 | Mostrar "—" en cajas abiertas en lugar de S/ 0.00 | `pdf/caja.blade.php` | S | `monto_final` nulo no se muestra como cero |
| E0-06 | Identidad del negocio en todos los PDF | `pdf/*.blade.php`, `config/ticket.php` | S | Nombre, RUC y dirección en el encabezado |
| E0-07 | Mostrar el rango de fechas y los filtros aplicados | `pdf/*.blade.php` | S | El PDF se entiende sin abrir el panel |
| E0-08 | Corregir la numeración de páginas | `pdf/*.blade.php`, `ReporteController` | S | "Página X de Y" real (verificar DomPDF) |
| E0-09 | Aislar la vista previa en un iframe | `resources/js/admin/reportes/index.js` | S | El CSS del reporte no altera el panel |
| E0-10 | Mensaje claro cuando no hay resultados | `pdf/*.blade.php` | S | "No hay registros para los filtros seleccionados" |

**Por qué primero:** son cambios de una sola plantilla, sin lógica, sin pruebas de cálculo y con impacto visual inmediato. El dueño percibe mejora el primer día.

---

## 4. Nivel 1 — Correcciones de cálculo con datos existentes

**Objetivo:** que los números de los reportes actuales sean confiables. Todo se resuelve con consultas y validaciones; no hay migraciones.

| Orden | ID | Tarea | Dónde | Talla | Depende de |
|---|---|---|---|---|---|
| 1 | E1-01 | Corregir el filtro Entrada/Salida del kardex | `ReporteService::kardex()` + `index.blade.php` | S | — |
| 2 | E1-02 | Permitir `stock_menor_que = 0` y separar activos/inactivos | `ReporteService::inventario()` | S | — |
| 3 | E1-03 | Corregir el producto cartesiano de clientes | `ReporteService::clientes()` | M | — |
| 4 | E1-04 | Valorizar inventario con costo real de capas | `ReporteService::inventario()` | M | — |
| 5 | E1-05 | Quitar o desglosar sumas de unidades heterogéneas | `inventario`, `lotes`, `movimientos`, `ingresos` | M | E1-04 |
| 6 | E1-06 | Incluir vencidos en el reporte de vencimientos | `ReporteService::lotes()` | S | — |
| 7 | E1-07 | Compras netas: mostrar devoluciones y anuladas | `ReporteService::ingresos()` + vista | M | — |
| 8 | E1-08 | Validar `tipo`, fechas, mes, año, límite e IDs | `ReporteController` + `FormRequest` | M | — |
| 9 | E1-09 | Advertir el corte de 500 y habilitar filtros de auditoría | `ReporteService::auditoria()` + vista | S | E1-08 |
| 10 | E1-10 | Agregar exportación CSV a los reportes tabulares | `ReporteController` | M | E1-08 |

### Detalle de las correcciones críticas

**E1-03 — Clientes.** Reemplazar el doble `LEFT JOIN` por dos subconsultas agregadas (ventas por cliente y atenciones por cliente) y sumar una sola vez. Prueba obligatoria: un cliente con 2 ventas y 3 atenciones debe mostrar el monto real, no el multiplicado.

**E1-04 — Inventario.** Calcular el valor con `SUM(lote_ingreso.cantidad_disponible_base * lote_ingreso.costo_unitario_base)` por producto, igual que ya hace el reporte de lotes. Si se quiere conservar la referencia, mostrar ambas columnas: "valor a costo real" y "valor a precio de compra", con su diferencia.

**E1-05 — Unidades.** No sumar tabletas con mililitros. Opciones:
- mostrar "unidades por unidad de medida";
- reemplazar el total por "#SKU" y "valor";
- mantener el total solo cuando todos los productos comparten unidad.

**E1-06 — Vencidos.** Agregar cubos: vencido, 0–30, 31–60, 61–90, 91–180, >180. El cubo "vencido" es el más importante y hoy queda fuera del filtro "próximos N días".

**E1-08 — Validación.** Lista blanca de tipos, fechas válidas, rango máximo (por ejemplo 1 año), límite de clientes entre 1 y 100, y producto obligatorio en kardex.

**Salida del nivel:** los reportes actuales calculan bien y no explican mal sus datos. Es la base para todo lo demás.

---

## 5. Nivel 2 — Reportes nuevos con datos existentes

**Objetivo:** pasar de listados a reportes de gestión sin agregar una sola columna a la base de datos. Cada reporte se construye con la anatomía estándar del módulo:

1. método en `ReporteService`;
2. rama en `ReporteController::getReportData()`;
3. título en `getTitulo()`;
4. opción en `index.blade.php` con su `data-reporte-show`;
5. vista en `resources/views/admin/reportes/pdf/`;
6. prueba de cálculo en `tests/Feature/`.

### 5.1. Anatomía de un reporte nuevo

| Paso | Archivo | Riesgo |
|---|---|---|
| Consulta | `app/Services/ReporteService.php` | Bajo (solo lectura) |
| Registro | `app/Http/Controllers/Admin/ReporteController.php` | Bajo |
| Filtros | `resources/views/admin/reportes/index.blade.php` | Bajo |
| Salida | `resources/views/admin/reportes/pdf/<tipo>.blade.php` | Bajo |
| Prueba | `tests/Feature/Reporte*Test.php` | Medio (datos de prueba) |

### 5.2. Olas de implementación

#### Ola 2A — Ventas (la más simple)

| ID | Reporte | Pregunta | Datos | Talla |
|---|---|---|---|---|
| E2-01 | Ventas por producto/presentación | ¿Qué se vende y cuánto deja? | `detalle_venta` + `producto_presentacion` + `costo_total` | M |
| E2-02 | Resumen diario de ventas | ¿Cómo va el día? | `venta` agrupada por fecha | S |
| E2-03 | Ventas por vendedor y hora | ¿Quién y cuándo vende? | `venta.user_id`, `created_at` | S |
| E2-04 | Anulaciones y motivos | ¿Qué se anula y por qué? | `venta.estado`, `anulada_at`, `motivo_anulacion` | S |

**Por qué primero:** `detalle_venta` ya tiene `cantidad_base`, `sub_total` y `costo_total`; no hay que cruzar módulos ni inventar cálculos.

**Criterio de aceptación de E2-01:** el margen del reporte debe coincidir con el de rentabilidad para el mismo período y filtros. Si no coincide, hay un error de criterio que corregir antes de publicarlo.

#### Ola 2B — Compras

| ID | Reporte | Pregunta | Datos | Talla |
|---|---|---|---|---|
| E2-05 | Compras por proveedor | ¿A quién le compro y cuánto? | `ingreso` + `proveedor` | M |
| E2-06 | Evolución de costos por producto/proveedor | ¿Cuánto subió el costo? | `detalle_ingreso.costo_unitario_base` + `fecha` | L |
| E2-07 | Devoluciones a proveedor | ¿Qué devolví y recuperé? | `devolucion_proveedor` + detalles | M |
| E2-08 | Compras netas | ¿Cuánto compré realmente? | compras - devoluciones - anuladas | M |

**Por qué después de ventas:** compras requiere entender el ciclo borrador → recibida → anulada y las devoluciones por capa. Es más delicado que ventas, pero sigue sin migraciones.

#### Ola 2C — Inventario y merma

| ID | Reporte | Pregunta | Datos | Talla |
|---|---|---|---|---|
| E2-09 | Vencimientos y valor en riesgo | ¿Qué se me va a vencer? | `producto_lote` + `lote_ingreso` | M |
| E2-10 | Ajustes y merma por motivo | ¿Cuánto pierdo y por qué? | `ajuste_inventario` + `detalle_ajuste` | M |
| E2-11 | Rotación y cobertura | ¿Qué se mueve y qué está muerto? | ventas 30/90 días + stock | L |
| E2-12 | Productos sin movimiento | ¿Qué capital tengo inmovilizado? | última venta por producto | M |

**Por qué en este orden:** vencimientos y merma son consecuencias directas de lo que ya se registra; rotación exige definir la ventana de ventas y el tratamiento de productos nuevos.

#### Ola 2D — Rentabilidad

| ID | Reporte | Pregunta | Datos | Talla |
|---|---|---|---|---|
| E2-13 | Margen por categoría/marca | ¿Dónde está el negocio rentable? | ventas + atenciones + costo | M |
| E2-14 | Margen por vendedor | ¿Quién vende con mejor margen? | `venta.user_id` + `costo_total` | M |
| E2-15 | Servicios más demandados | ¿Qué servicios conviene impulsar? | `atencion_servicio` + `servicio` | S |
| E2-16 | Productos usados en atenciones | ¿Qué productos mueven las atenciones? | `atencion_producto` | S |

**Nota:** el margen de servicios no se puede calcular todavía porque `servicio` no tiene costo. En esta ola solo se reporta volumen e ingreso; el margen real llega en el nivel 3.

#### Ola 2E — Clientes y caja

| ID | Reporte | Pregunta | Datos | Talla |
|---|---|---|---|---|
| E2-17 | Clientes RFM / inactivos | ¿A quién puedo recuperar? | `cliente` + ventas + atenciones | L |
| E2-18 | Clientes nuevos y adopción web | ¿Estoy creciendo? | `cliente.created_at`, `registro_web`, `ultimo_login` | M |
| E2-19 | Cierre de caja por origen | ¿De dónde viene el dinero? | `caja_movimiento.referencia_tipo` | M |
| E2-19A | Cobros por método de pago | ¿Cuánto entra por efectivo, Yape, Plin o tarjeta? | `pago` + `metodo_pago` + ventas/atenciones | M |

**Cuidado con E2-17:** separar al cliente genérico (`00000001`) o el ranking seguirá distorsionado. Definir con el negocio si se excluye o se muestra como "consumidor final".

**E2-19A ya tiene los datos:** el módulo de métodos de pago registra el desglose en `pago` y los métodos en `metodo_pago`. Solo falta el reporte. Debe sumar ventas confirmadas y atenciones, repartir los pagos mixtos y excluir ventas anuladas. Para caja, sirve el desglose de ventas/atenciones; los egresos manuales y compras no tienen método por movimiento. **Se puede adelantar al sprint 3**: no depende de otros reportes y es de los pocos que ya tienen toda la información capturada.

**Ojo con el cruce:** `pago.referencia_tipo` usa `Venta`/`Atencion` (capitalizado) y `caja_movimiento.referencia_tipo` usa `venta`/`atencion` (minúsculas). Normalizar antes de unir ambas tablas.

#### Ola 2F — Resumen gerencial (último)

| ID | Reporte | Pregunta | Datos | Talla |
|---|---|---|---|---|
| E2-20 | Resumen gerencial mensual comparado | ¿Cómo cerró el mes? | consolida E2-01 a E2-19A | L |

**Por qué al final:** no debe construir sus propios cálculos. Debe reutilizar los reportes anteriores ya validados. Si se hace primero, se duplican fórmulas y se crean discrepancias.

---

## 6. Nivel 3 — Campos nuevos simples

**Objetivo:** habilitar reportes que hoy no se pueden calcular. Cada ítem requiere migración, formulario, validación, backfill y reporte.

| Orden | ID | Campo | Reporte que habilita | Talla | Riesgo |
|---|---|---|---|---|---|
| 1 | E3-01 | `servicio.costo` | Margen real de servicios y atenciones | M | Bajo |
| 2 | E3-02 | `caja_movimiento.categoria_egreso` | Gastos por rubro | M | Bajo |
| 3 | E3-03 | `producto.stock_maximo` y `proveedor.lead_time` | Sugerido de compra | L | Medio |
| 4 | E3-04 | `cliente.fecha_nacimiento` y `direccion` | Cumpleaños, segmentación, campañas | M | Bajo |
| 5 | E3-05 | `atencion.estado` + anulación | Atenciones anuladas y reportes reproducibles | L | Alto (toca stock y caja) |
| 6 | E3-06 | `caja.monto_contado` y `diferencia` | Arqueo y control de diferencias | L | Medio |
| 7 | E3-07 | `metodo_pago.afecta_efectivo` | Separar efectivo de digital en el arqueo | M | Bajo |

### Detalle

**E3-01 — Costo de servicio.** Migración nullable, campo en el formulario de servicios, uso en rentabilidad. Si no se conoce el costo, dejar `null` y mostrar "sin costo" en lugar de asumir 100% de margen.

**E3-02 — Categoría de egreso.** Catálogo simple (compras, servicios, sueldos, alquiler, otros). Sin esto, los egresos solo se pueden agrupar por texto libre y el reporte de gastos no es confiable.

**E3-03 — Stock máximo y lead time.** `stock_maximo` por producto y `lead_time` por proveedor. Habilita el sugerido de compra: `sugerido = ventas diarias * (lead time + cobertura) - stock actual`, acotado por mínimo y máximo.

**E3-05 — Estado de atención.** Es el cambio más delicado del nivel porque replica el patrón de ventas: estado, motivo, reversión de stock, kardex `REVERSION` y ajuste de caja. Debe hacerse después de estabilizar caja y con pruebas de regresión.

**E3-06 — Arqueo.** Agregar monto contado al cierre y calcular diferencia. Sin esto, `monto_final` seguirá siendo teórico.

**E3-07 — Afectación a efectivo.** Marcar cada método de pago con `afecta_efectivo` (efectivo sí; Yape/Plin/tarjeta no, salvo que el negocio lo defina distinto). Es la pieza que falta para que el arqueo separare efectivo de digital con precisión. El desglose `pago` ya existe desde el 20/11/2026.

---

## 7. Nivel 4 — Cambios de proceso

> **Estado:** pausado por ahora por decisión del negocio. **No se descarta.** La documentación se conserva completa para retomarlo cuando se defina.

**Objetivo:** incorporar información que hoy no se captura y que cambia la operación diaria.

| Orden | ID | Cambio | Reportes que habilita | Talla | Depende de |
|---|---|---|---|---|---|
| 1 | E4-01 | Tipo de comprobante, serie, número e IGV | Libro de ventas/compras, reportes tributarios | XXL | — |
| 2 | E4-02 | Plazo, vencimiento y saldo en compras | Cuentas por pagar, flujo proyectado | XL | — |
| 3 | E4-03 | Devoluciones de cliente documentadas | Devoluciones parciales trazables | XL | E3-05 |
| 4 | E4-04 | Impresiones y conversión de promociones | ROI de campañas | L | — |
| 5 | E4-05 | Reportes programados y dashboard | Cierre diario/mensual automático | XL | Nivel 2 completo |

### Por qué este orden

- **Los métodos de pago ya están implementados** (entidad `metodo_pago` + desglose `pago`). Por eso el nivel 4 ya no empieza por capturarlos: empieza por usarlos en reportes (E2-19A) y, si se necesita arqueo fino, por `afecta_efectivo` (E3-07).
- **E4-01 primero:** el IGV y los comprobantes cambian precios, márgenes y totales. Requiere una decisión de negocio antes de codificar.
- **E4-02 en paralelo:** compras al crédito es un proceso independiente de ventas; puede avanzar antes o después.
- **E4-03 al final del bloque:** depende de que la atención tenga estado y de que la caja esté estabilizada.
- **E4-05 al final:** sin reportes base confiables, programar el envío solo automatiza datos malos.

---

## 8. Fuera de alcance

No forman parte del alcance real del sistema y **no se planifican** en este roadmap:

- Facturación electrónica SUNAT.
- Receta y controlados DIGEMID.
- Multi-sede y consolidación.
- BI / analítica avanzada.

Si en el futuro el negocio decide abordarlos, deben tratarse como proyectos separados, con alcance, proveedor y presupuesto propios. No se deben mezclar con la mejora de reportes.

---

## 9. Secuencia recomendada por sprints

| Sprint | Contenido | Resultado |
|---|---|---|
| **1** | Nivel 0 completo + E1-01, E1-02, E1-03 | Reportes legibles y los tres errores más visibles corregidos |
| **2** | E1-04 a E1-10 | Inventario valorizado bien, compras netas, validaciones y CSV |
| **3** | Ola 2A (ventas) + E2-19A | Ventas por producto, día, vendedor, anulaciones y cobros por método |
| **4** | Ola 2B (compras) + E2-09 | Compras por proveedor, costos, devoluciones y vencimientos |
| **5** | Ola 2C (inventario y merma) | Merma por motivo, rotación, cobertura y slow movers |
| **6** | Ola 2D y 2E | Margen por categoría/marca/vendedor, servicios, RFM y caja por origen |
| **7** | Ola 2F | Resumen gerencial comparado |
| **8+** | Nivel 3 | Costo de servicio, categoría de egreso, stock máximo, cumpleaños, estado de atención, arqueo y afectación a efectivo |

Cada sprint debe terminar con: reporte probado, documento actualizado y una revisión con el dueño usando datos reales.

---

## 10. Si solo hay tiempo para cinco cosas

1. **Corregir clientes** (E1-03).
2. **Corregir el filtro de kardex** (E1-01).
3. **Valorizar inventario a costo real** (E1-04).
4. **Ventas por producto con margen** (E2-01).
5. **Vencimientos con valor en riesgo** (E2-09).

Con esas cinco, el negocio pasa de "tengo PDFs" a "sé qué vendo, cuánto gano y qué se me va a vencer".

**Bonus casi gratis:** E2-19A (cobros por método), porque el desglose `pago` ya se registra y solo falta el reporte.

---

## 11. Matriz impacto / esfuerzo

| Cuadrante | Ítems | Decisión |
|---|---|---|
| **Alto impacto / bajo esfuerzo** | E0-01 a E0-10, E1-01, E1-02, E1-03, E1-06, E2-01, E2-02, E2-04, E2-09 | Hacer ya |
| **Alto impacto / esfuerzo medio** | E1-04, E1-05, E1-07, E2-05, E2-10, E2-13, E2-17, E2-19A | Planificar en los primeros sprints |
| **Impacto medio / esfuerzo medio** | E2-03, E2-06, E2-08, E2-11, E2-15, E2-18, E2-19 | Después de la base |
| **Alto impacto / alto esfuerzo** | E3-05, E3-06, E3-07, E4-01, E4-02 | Requieren decisión y presupuesto |
| **Fuera de alcance** | SUNAT, DIGEMID, multi-sede, BI | No planificar en este roadmap |

---

## 12. Definition of Done de cada reporte

Antes de considerarlo terminado:

- [ ] Responde una pregunta de negocio concreta.
- [ ] Excluye o incluye anulados de forma explícita.
- [ ] Usa fecha de negocio, no `created_at`, cuando corresponda.
- [ ] No suma unidades heterogéneas.
- [ ] Valoriza a costo real cuando habla de inventario.
- [ ] Tiene totales y subtotales.
- [ ] Tiene filtros validados y valores por defecto.
- [ ] Se puede exportar a CSV.
- [ ] Tiene prueba automatizada del cálculo.
- [ ] Está documentado en `docs/modulo-reportes.md`.
- [ ] Fue revisado con datos reales por el dueño o encargado.

---

## 13. Riesgos y mitigaciones

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Corregir clientes cambia números históricos | El dueño puede pensar que "antes estaba bien" | Comunicar el error y mostrar el antes/después con un ejemplo |
| Cambiar la valorización de inventario altera el valor contable | Diferencia con la contabilidad | Presentar ambas columnas y explicar la diferencia |
| Migraciones en horario de operación | Bloqueo o datos inconsistentes | Migraciones nullable, backfill, ventana de mantenimiento |
| Cambiar atención a estado/anulación | Rompe stock y caja | Replicar el patrón de ventas y probar con regresión completa |
| IGV mal definido | Precios y márgenes incorrectos | Decidir primero si los precios incluyen IGV |
| Reportes nuevos con cálculos duplicados | Dos PDFs con números distintos | Reutilizar los métodos ya validados; un solo criterio por KPI |
| PDFs grandes y lentos | Timeout de DomPDF | Consultas agregadas, límites, rangos máximos y paginación |
| Dependencia de SUNAT/DIGEMID | Bloqueo externo | Tratar como proyecto separado con proveedor especializado |

---

## 14. Qué no hacer todavía

- **No** construir un dashboard nuevo antes de corregir los cálculos.
- **No** agregar 20 PDFs más; priorizar los que cambian una decisión.
- **No** implementar facturación electrónica sin antes definir IGV y comprobantes.
- **No** construir sugerido de compra sin `stock_maximo` y `lead_time`.
- **No** medir promociones con clics como si fueran ventas.
- **No** incorporar multi-sede: está fuera del alcance actual.
- **No** usar inteligencia artificial o pronósticos con datos que aún no son confiables.

---

## 15. Estado de implementación

**Actualizado:** 20/11/2026.

| Nivel / ítem | Estado |
|---|---|
| Nivel 0 (E0-01 a E0-10) | Implementado |
| Nivel 1 (E1-01 a E1-10) | Implementado, incluida exportación CSV |
| Nivel 2 (Olas 2A a 2F, E2-01 a E2-20) | Implementado: 15 reportes de gestión y resumen gerencial |
| Nivel 3 (E3-01 a E3-07) | Implementado: costo de servicio, stock máximo/lead time, cumpleaños/dirección y reportes de sugerido/cumpleaños; arqueo, estado de atención y `afecta_efectivo` ya existían |
| Nivel 4 | **Pausado por ahora; no descartado.** Documentación conservada para retomarlo |
| Nivel 5 (SUNAT, DIGEMID, multi-sede, BI) | Fuera de alcance |

**Verificación:** `php artisan test` — 339 pruebas aprobadas. La suite incluye `tests/Feature/ReporteMejorasTest.php` (clientes, valorización, vencidos, pagos, validaciones, PDF/CSV) y `tests/Feature/ReporteGestionTest.php` (reportes de gestión, servicios con costo/margen, sugerido de compra, cumpleaños y resumen gerencial). También cubre arqueo, movimientos por método y atención confirmada/anulada.

---

*Este documento es una hoja de ruta. El estado de implementación se refleja en la sección 15.*
