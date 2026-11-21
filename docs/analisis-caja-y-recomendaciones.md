# Análisis del módulo Caja y recomendaciones para un flujo de caja real

> **Fecha:** 20/11/2026
> **Alcance:** módulo `/admin/caja` (apertura, operación, egresos, cierre, historial), su integración con ventas, atenciones, compras, devoluciones de proveedor y anulaciones, y los reportes que lo consumen.
> **Naturaleza:** documento de análisis. **No se implementó ningún cambio de código.**
> **Método:** revisión de `CajaController`, modelos `Caja`/`CajaMovimiento`, migraciones, vistas y JS del módulo, `VentaController`, `AtencionController`, `CompraService`, `AnulacionVentaService`, `DevolucionProveedorService`, `PagoService`, `ReporteService`, `CajaTest` y la documentación existente (`modulo-caja.md`, `modulo-metodos-pago.md`, `analisis-reportes-y-recomendaciones.md`).
>
> **Estado de implementación (21/11/2026):** se implementaron la **Fase 0** y el núcleo de la **Fase 1**: separación efectivo/digital, un movimiento por método, arqueo con conteo por denominación y diferencia, movimientos compensatorios, bloqueo y cierre transaccional, egreso con control de saldo, anulación con caja cerrada y categorías/ingresos/retiros. Quedan pendientes la Fase 2 (reportes de flujo, conciliación, permisos finos) y la Fase 3 (cuentas por pagar, fiado, multi-caja). El detalle operativo actualizado está en [`modulo-caja.md`](modulo-caja.md).

---

## 1. Resumen ejecutivo

### 1.1. Diagnóstico en una frase

La caja actual funciona como un **cuaderno de ingresos y egresos teóricos**: registra totales, pero no controla el dinero físico. No distingue efectivo de Yape/tarjeta/transferencia, no tiene arqueo (conteo real), no separa turnos y permite que sus movimientos se editen o eliminen. Para una botica pequeña con flujo real —ventas mixtas, compras pagadas en efectivo o transferencia, retiros del dueño, gastos del día, devoluciones al día siguiente— **hoy no sirve para saber si falta o sobra dinero**.

### 1.2. Hallazgos críticos

| # | Severidad | Hallazgo | Evidencia | Impacto |
|---|---|---|---|---|
| 1 | **P0** | La caja suma **todo** ingreso como si fuera efectivo, incluidos Yape, Plin, tarjeta y transferencia. | `VentaController.php:223-231`, `AtencionController.php:157-165`; `metodo_pago` no tiene `afecta_efectivo` (`2026_11_20_000001_create_metodo_pago_table.php`) | El "monto actual" no coincide con el dinero del cajón. Un arqueo siempre dará faltante. |
| 2 | **P0** | **No existe arqueo**: no se registra efectivo contado, diferencia, observación ni responsable del conteo. | `CajaController.php:132-157`; migración `caja` sin `efectivo_contado`/`diferencia` | No hay control ni responsabilidad por faltantes. El cierre solo confirma una fórmula. |
| 3 | **P0** | La "Diferencia" del detalle histórico **siempre es S/ 0.00**. | `historial-show.blade.php:29`: `monto_final - (inicial + ingresos - egresos)` | Métrica decorativa que oculta la ausencia de arqueo. |
| 4 | **P0** | Los movimientos de caja **se editan y eliminan** desde atenciones. | `AtencionController.php:278-291` (`update`) y `333-337` (`delete`) | Rompe la trazabilidad tipo kardex. Si la caja ya cerró, deja totales descuadrados u movimientos huérfanos. |
| 5 | **P0** | **Condiciones de carrera**: la caja se busca fuera de la transacción y no se bloquea al vender/atender. | `VentaController.php:153`, `AtencionController.php:84`; cierre sin `lockForUpdate` (`CajaController.php:134-157`) | Una venta puede registrarse en una caja que se está cerrando; el `monto_final` queda desactualizado. También se pueden abrir dos cajas a la vez. |
| 6 | **P0** | Un egreso **no valida el saldo disponible**. | `StoreEgresoRequest.php:14-20`; `CajaController.php:89-116` | Se puede dejar la caja en negativo sin advertencia. |
| 7 | **P0** | Anular una venta de una caja **ya cerrada es imposible**. | `AnulacionVentaService.php:42-50` | Caso real: cliente devuelve al día siguiente. El sistema bloquea la anulación. |
| 8 | **P1** | Los egresos no tienen **categoría, comprobante, proveedor ni adjunto**; solo texto libre. | `egreso.blade.php`, `caja_movimiento` | No se puede reportar "cuánto gasté en luz, limpieza o movilidad". |
| 9 | **P1** | No hay **ingresos manuales, retiros ni depósitos**. | Solo `egreso_manual` en el enum de `caja_movimiento` | Si el dueño retira efectivo o deposita al banco, el arqueo no cuadra y no hay registro. |
| 10 | **P1** | **No hay turnos ni cajero asignado**. Una sola caja global y un solo permiso `caja`. | `PermissionSeeder.php:33`; layout y rutas | No se puede responsabilizar un faltante a un turno o usuario. |
| 11 | **P1** | Compras y devoluciones de proveedor registran caja **aunque el método sea digital**. | `CompraService.php:156-172`; `DevolucionProveedorService.php:112-130` | Igual que el punto 1: dinero que nunca entró/salió del cajón contamina el efectivo. |
| 12 | **P1** | Los totales de caja son **columnas denormalizadas** y no se reconcilian contra los movimientos. | `caja.total_ingresos`/`total_egresos` | Pueden desincronizarse por ediciones, borrados o carreras, sin que nadie lo detecte. |
| 13 | **P1** | El historial y los reportes **no filtran por fecha/usuario/método** ni consolidan el período; el PDF de caja vive fuera del módulo. | `CajaController.php:175-215`; `ReporteService.php:124-140`; `reportes/pdf/caja.blade.php` | Difícil responder "¿cómo va el mes?" sin exportar y sumar a mano. |
| 14 | **P2** | No hay **cuentas por pagar** (pago posterior a proveedor), **fiado/cobranza** ni **fecha operativa** para ventas después de medianoche. | `PagoService` exige pago completo; `ingreso` solo paga al recibir | El flujo real de una botica incluye crédito y pagos diferidos; hoy no tienen dónde registrarse. |
| 15 | **P2** | No hay soporte para **más de una caja/terminal/sede**. | `Caja::abierta()` global | Si mañana hay dos cajeros o un segundo punto, el modelo no lo soporta. |
| 16 | **P2** | La documentación dice permisos `caja.ver`/`caja.operar`, pero el sistema usa un único `caja`. | `docs/modulo-caja.md:165` vs `PermissionSeeder.php:33` | Expectativa incorrecta al configurar roles. |

### 1.3. Priorización recomendada

| Prioridad | Objetivo | Qué incluye |
|---|---|---|
| **P0 — Confiabilidad** | Que los números sean verdaderos y no se corrompan | Separar efectivo/digital, arqueo, movimientos inmutables, bloqueo y transacciones, validar saldo, resolver anulación con caja cerrada |
| **P1 — Control operativo** | Que la caja sirva para operar todos los días | Categorías de egreso, comprobantes, ingresos/retiros/depósitos, turnos, corte imprimible, reportes de flujo, conciliación |
| **P2 — Escala** | Que acompañe el crecimiento | Cuentas por pagar, fiado/cobranza, fecha operativa, multi-caja/sede, permisos finos |

---

## 2. Estado actual

### 2.1. Flujo implementado

```text
POST /admin/caja/apertura   → crea caja con monto_inicial y estado=abierta
POST /admin/caja/egreso     → crea caja_movimiento tipo=egreso, referencia=egreso_manual
POST /admin/caja/cierre     → monto_final = inicial + ingresos - egresos; estado=cerrada
GET  /admin/caja/historial  → lista cajas cerradas
GET  /admin/caja/historial/{caja} → detalle inmutable
```

Integraciones que escriben en caja:

| Origen | Tipo | Referencia | Método de pago |
|---|---|---|---|
| Venta | ingreso | `venta` | Se guarda en `pago`, **no** en caja |
| Atención | ingreso | `atencion` | Se guarda en `pago`, **no** en caja |
| Anulación de venta | egreso | `venta` | No se guarda |
| Compra recibida pagada desde caja | egreso | `compra` | `ingreso.metodo_pago` (no afecta el cálculo) |
| Devolución a proveedor reembolsada | ingreso | `devolucion_compra` | `devolucion_proveedor.metodo_pago` (no afecta el cálculo) |
| Egreso manual | egreso | `egreso_manual` | No se guarda |

### 2.2. Modelo de datos actual

`caja`:

```text
id_caja, monto_inicial, total_ingresos, total_egresos, monto_final,
estado(abierta|cerrada), apertura_user_id, cierre_user_id,
apertura_at, cierre_at, created_at, updated_at
```

`caja_movimiento`:

```text
id_movimiento, caja_id_caja, tipo(ingreso|egreso), monto, descripcion,
referencia_tipo(venta|atencion|egreso_manual|compra|devolucion_compra),
referencia_id, user_id, created_at
```

Limitaciones estructurales:

- No hay `metodo_pago_id` ni `afecta_efectivo` en el movimiento.
- No hay `categoria`, `comprobante`, `observacion` ni `correlativo`.
- `referencia_tipo` es un `enum` cerrado: no admite `ingreso_manual`, `retiro`, `deposito`, `ajuste`, `pago_servicio` ni `anulacion_venta`.
- No hay índices por `caja_id_caja` ni por `(referencia_tipo, referencia_id)`.
- `caja_movimiento` no tiene `updated_at` (bien para inmutabilidad), pero el código igual actualiza y elimina filas.

### 2.3. Cobertura de pruebas

`tests/Feature/CajaTest.php` cubre apertura, egreso, cierre, historial y decimales. **No cubre**:

- egreso mayor al saldo;
- arqueo/diferencia;
- concurrencia de apertura/cierre;
- venta mientras se cierra la caja;
- edición/eliminación de atención y su efecto en caja;
- anulación de venta con caja cerrada;
- separación efectivo/digital;
- conciliación de totales contra movimientos.

---

## 3. Hallazgos detallados

### 3.1. P0 — La caja no distingue efectivo de dinero digital

**Qué pasa.** Cada venta o atención genera **un** movimiento por el total, sin importar si se pagó con efectivo, Yape, Plin, tarjeta o transferencia. La tabla `pago` sí tiene el desglose, pero la caja no lo usa (`VentaController.php:233`, `AtencionController.php:167`). `metodo_pago` tampoco tiene una marca `afecta_efectivo`.

**Por qué importa.** En una botica real, el cajón solo debe contener efectivo. Si el día tuvo S/ 1,000 en ventas, pero S/ 600 fueron Yape, el efectivo esperado es S/ 400 (más fondo y movimientos). Hoy la caja dirá S/ 1,000 y cualquier arqueo parecerá un faltante de S/ 600.

**Recomendación.**

1. Agregar `afecta_efectivo` (boolean) a `metodo_pago`, con valor `true` para Efectivo y `false` para Yape/Plin/tarjeta/transferencia. Debe ser configurable porque algunos negocios cobran Yape al mismo cajón.
2. Registrar el método **en el movimiento de caja**, no solo en `pago`. La opción más limpia es **un movimiento por método de pago** (una venta mixta de S/ 100 = S/ 60 efectivo + S/ 40 Yape genera dos filas). Así el efectivo esperado se calcula directo, sin joins frágiles.
3. Mantener `total_ingresos` como total de la operación y agregar `total_ingresos_efectivo` / `total_ingresos_digital` (y equivalentes de egresos).
4. Para datos históricos, no reescribir cajas cerradas: la nueva lógica aplica desde el despliegue; si hay una caja abierta al momento del cambio, cerrarla y abrir una nueva.

### 3.2. P0 — No hay arqueo de caja

**Qué pasa.** El cierre calcula `monto_final = monto_inicial + total_ingresos - total_egresos` y no pide contar el dinero. No existen `efectivo_esperado`, `efectivo_contado`, `diferencia`, `observacion_cierre`, `arqueo_user_id` ni detalle de billetes/monedas.

**Por qué importa.** Sin conteo físico no hay control interno, no se detectan faltantes, no se puede pedir responsabilidad al cajero y no se puede imprimir un corte de caja real.

**Recomendación.**

1. Al cerrar, pedir **conteo por denominación** (billetes y monedas) o, como mínimo, un monto contado.
2. Calcular `efectivo_esperado` solo con movimientos que afectan efectivo.
3. Guardar `efectivo_contado`, `diferencia = contado - esperado`, `observacion_cierre` y `arqueo_user_id`.
4. Aplicar **conteo ciego**: el cajero ingresa el conteo sin ver el esperado; el sistema muestra la diferencia después de guardar. Reduce el ajuste intencional.
5. Si la diferencia supera una tolerancia configurable, exigir observación y autorización de un segundo usuario (`caja.autorizar`).
6. Emitir un **corte de caja** imprimible/PDF con resumen por método, egresos por categoría, conteo, diferencia y firmas.
7. Corregir la tarjeta "Diferencia" del historial: hoy siempre da cero.

### 3.3. P0 — Movimientos mutables y edición/eliminación de atenciones

**Qué pasa.** `AtencionController@update` busca el movimiento en la **caja abierta actual** y le cambia el monto (`278-291`). `AtencionController@destroy` lo **elimina** (`333-337`). Si la atención pertenece a una caja ya cerrada, no la encuentra y deja la caja histórica intacta pero la atención modificada; si la encuentra, puede descuadrar una caja abierta.

**Por qué importa.** La caja pierde trazabilidad y deja de ser auditable. Es el mismo problema que el proyecto ya resolvió en kardex con movimientos append-only.

**Recomendación.**

1. Hacer `caja_movimiento` **append-only**: nunca `update` ni `delete`; toda corrección se hace con un movimiento compensatorio (`REVERSION`/`AJUSTE`) que referencia al original.
2. Al editar una atención, no modificar el movimiento: registrar un egreso por el monto anterior y un ingreso por el nuevo (o un ajuste por la diferencia), ambos con referencia a la atención y al movimiento original.
3. Al eliminar una atención, registrar un egreso compensatorio, no borrar el ingreso.
4. Alinear la política de atenciones con la de ventas: una atención confirmada debería anularse con motivo, no editarse libremente. Si se mantiene la edición, que sea con movimientos compensatorios.
5. Agregar una prueba que verifique que una caja cerrada no cambia al editar/eliminar una atención.

> **Estado actual:** superado. Las atenciones ya son inmutables (`AtencionController@update/destroy` responden 422) y se anulan con `AnulacionAtencionService` + `CajaService::reembolsarAtencion()`. Ver [`modulo-atenciones.md`](modulo-atenciones.md).

### 3.4. P0 — Concurrencia: apertura doble, cierre contra venta y totales sin bloqueo

**Qué pasa.**

- `apertura` verifica `Caja::abierta()->exists()` y luego crea, sin bloqueo ni restricción única (`CajaController.php:49-70`). Dos peticiones simultáneas pueden abrir dos cajas.
- `VentaController@store` y `AtencionController@store` obtienen `Caja::abierta()->first()` **antes** de la transacción y no vuelven a verificar el estado dentro de ella. El cierre puede ejecutarse entre medio.
- `cierre` lee la caja y calcula el total fuera de un bloqueo; no recalcula desde los movimientos.

**Por qué importa.** Se pueden perder ventas del cierre, registrar movimientos en una caja ya cerrada o dejar `monto_final` desactualizado. Es un problema silencioso y difícil de detectar.

**Recomendación.**

1. Centralizar en un `CajaService` (o `CajaAbiertaService`) que, dentro de la transacción, haga `Caja::abierta()->lockForUpdate()->first()` y verifique `estado === 'abierta'` antes de insertar el movimiento.
2. En `cierre`, hacer `lockForUpdate` sobre la caja, recalcular los totales desde `caja_movimiento` y recién entonces cerrar.
3. Garantizar una sola caja abierta a nivel de base de datos. En MySQL no hay índices parciales; una opción es una columna generada `abierta_unica = IF(estado='abierta', 1, NULL)` con índice único. Otra es una tabla singleton de control con `SELECT ... FOR UPDATE`.
4. Repetir el patrón que ya usa bien `CompraService` (`Caja::abierta()->lockForUpdate()` en `109`).

### 3.5. P0 — Egresos sin control de saldo

**Qué pasa.** `StoreEgresoRequest` valida monto y descripción, pero nadie compara contra el efectivo disponible. Se puede egresar S/ 1,000 de una caja con S/ 50.

**Recomendación.**

- Validar contra el **efectivo esperado**, no contra el total (que incluye digital).
- Si el egreso es una transferencia o un retiro a banco, permitir la operación aunque supere el efectivo, pero marcarla como movimiento no operativo y pedir confirmación.
- Si es efectivo y supera el esperado, bloquear con mensaje claro. Si el negocio decide permitirlo, exigir autorización y observación.

### 3.6. P0 — Anulación de venta con caja cerrada

**Qué pasa.** `AnulacionVentaService` busca el movimiento original y, si su caja está cerrada, lanza una excepción (`42-50`). La venta no se puede anular. Es frecuente que una devolución ocurra al día siguiente.

**Recomendación.**

1. Permitir la anulación aunque la caja original esté cerrada.
2. Registrar el reembolso en la **caja abierta actual**, con referencia a la venta y a la caja original (`caja_origen_id`).
3. Si el pago original fue digital, el reembolso debe ser digital (no afecta el cajón). Si fue efectivo, egreso de efectivo.
4. Si no hay ninguna caja abierta, permitir dejar la anulación como "reembolso pendiente" o exigir abrir caja antes de confirmar. Para una botica pequeña, lo más simple y seguro es exigir caja abierta y registrar el egreso.
5. Nunca modificar la caja cerrada; el histórico queda intacto y la devolución se ve en la caja nueva.

### 3.7. P1 — Falta el resto del flujo real de efectivo

Una caja de botica no solo recibe ventas y paga compras. Como mínimo necesita:

| Movimiento | Tipo | Por qué |
|---|---|---|
| Ingreso manual / aporte de sencillo | ingreso | Reponer cambio sin que parezca venta |
| Retiro a banco | egreso (transferencia) | El efectivo no puede quedarse indefinidamente en el cajón |
| Retiro del propietario | egreso (transferencia) | Sucede en negocios pequeños; si no se registra, el arqueo falla |
| Depósito/ajuste de fondo | ingreso/egreso | Regularizar diferencias de conteo |
| Gasto operativo | egreso | Luz, agua, internet, alquiler, limpieza, movilidad, útiles, mantenimiento |
| Pago a proveedor | egreso | Compra pagada después de recibida (hoy solo se paga al recibir) |
| Devolución a cliente | egreso | Reembolso total o parcial |
| Cobro de crédito/fiado | ingreso | Si se habilita venta a crédito |

Hoy solo existe `egreso_manual` y el ingreso automático de ventas/atenciones. Todo lo demás se registra como un egreso con texto libre, lo que impide reportar y conciliar.

### 3.8. P1 — Categorías, comprobantes y adjuntos

**Recomendación.**

- Tabla `categoria_movimiento_caja` (`nombre`, `tipo` ingreso/egreso, `es_transferencia`, `requiere_comprobante`, `estado`).
- En `caja_movimiento`: `categoria_id`, `comprobante_tipo`, `comprobante_numero`, `proveedor_id` (opcional), `adjunto_path` (opcional).
- Un egreso de S/ 80 por "luz" debe poder reportarse como servicio, no como texto libre.
- Los movimientos de transferencia (retiro/depósito) no deben contarse como gasto operativo en el estado de flujo; solo mueven dinero entre caja y banco.

### 3.9. P1 — Turnos, usuarios y permisos

Hoy cualquier usuario con permiso `caja` puede abrir, egresar y cerrar. No hay noción de turno ni de cajero responsable. Para una botica pequeña puede bastar una caja, pero conviene:

- `caja.abierta_por` y `caja.cerrada_por` (ya existen) más `turno`/`fecha_operativa`.
- Permisos separados: `caja.ver`, `caja.abrir`, `caja.operar`, `caja.cerrar`, `caja.arquear`, `caja.autorizar`, `caja.reportes`.
- Si hay más de un turno, permitir cerrar y abrir una nueva caja sin perder la anterior. Ya se puede hacer manualmente, pero no hay un campo que lo explique en los reportes.
- Registrar en auditoría cada apertura, egreso, arqueo y cierre (hoy se audita apertura/egreso/cierre, pero no el detalle del arqueo).

### 3.10. P1 — Reportes y conciliación

**Qué falta.**

- **Corte de caja** desde el propio módulo (hoy el PDF de caja está en Reportes y no incluye arqueo).
- **Libro de caja diario**: saldo inicial, movimientos cronológicos, saldo corriente de efectivo y total.
- **Flujo de caja por período**: ingresos operativos, egresos operativos, transferencias, neto; por método y categoría.
- **Diferencias de arqueo por usuario/turno**: histórico de faltantes/sobrantes.
- **Conciliación técnica**: comparar `caja.total_ingresos`/`total_egresos` contra `SUM(caja_movimiento)` y avisar diferencias, igual que las conciliaciones de inventario.
- **Alertas**: caja abierta más de X horas, diferencia de arqueo, egresos sobre umbral, caja en negativo.
- **Filtros**: fecha, usuario, método, categoría, estado, texto.
- **Exportación CSV/Excel** además del PDF.

### 3.11. P2 — Decisiones de negocio pendientes

Estas no son "bugs", pero definen el diseño:

1. **¿Yape/Plin se consideran efectivo?** Si el dinero se retira al banco, no; si se usa para pagar al día siguiente, quizá. Debe ser configurable por método.
2. **¿Hay uno o varios turnos/cajeros?** Determina si la caja es diaria o por turno.
3. **¿Se permite fiado/crédito?** Hoy el sistema obliga pago completo. Si la botica fía, hace falta cuentas por cobrar y cobranza en caja.
4. **¿Se paga a proveedores después de recibir la mercadería?** Hoy solo se puede pagar al recibir. Si hay crédito de proveedores, hace falta cuentas por pagar y pagos desde caja.
5. **¿El dueño retira efectivo?** Casi siempre sí; debe existir "retiro" para que el arqueo cuadre.
6. **¿Qué tolerancia de diferencia se acepta?** Cero, S/ 0.10 por redondeo, o un monto configurable.
7. **¿Quién autoriza diferencias y egresos grandes?** Idealmente el dueño/administrador.
8. **¿Se imprimen comprobantes de egreso?** Recomendable para control interno.
9. **¿Habrá segunda caja/sede?** Si es probable, dejar `sede_id`/`terminal` desde el diseño.
10. **¿Las ventas después de medianoche pertenecen al día anterior?** Definir `fecha_operativa`.

---

## 4. Diseño objetivo propuesto

### 4.1. Principios

1. **La caja es efectivo.** Lo digital se registra, se reporta y se concilia, pero no se cuenta en el cajón.
2. **Append-only.** Los movimientos no se editan ni se borran; se compensan.
3. **Una caja abierta por terminal.** Bloqueo y restricción a nivel de base de datos.
4. **El cierre es un arqueo.** No es solo una fórmula: es un conteo con diferencia y responsable.
5. **Todo movimiento tiene origen, método, categoría y usuario.** Nada de texto libre como único dato.
6. **El histórico es inmutable.** Las correcciones se ven en la caja actual, referenciando la original.

### 4.2. Modelo de datos propuesto

#### `metodo_pago`

| Columna nueva | Tipo | Uso |
|---|---|---|
| `afecta_efectivo` | boolean, default `true` | Efectivo sí; Yape/tarjeta/transferencia no (configurable) |
| `es_digital` | boolean, opcional | Para reportes de conciliación bancaria |

#### `caja`

| Columna | Tipo | Uso |
|---|---|---|
| `correlativo` | string único | `CJ-00000123` para impresión |
| `fecha_operativa` | date | Día de negocio (resuelve ventas después de medianoche) |
| `sede_id` / `terminal` | nullable | Futuro multi-caja |
| `fondo_inicial` | decimal(10,2) | Reemplaza/alias de `monto_inicial` |
| `total_ingresos_efectivo` | decimal(12,2) | Ingresos que entran al cajón |
| `total_ingresos_digital` | decimal(12,2) | Yape/Plin/tarjeta/transferencia |
| `total_egresos_efectivo` | decimal(12,2) | Salidas del cajón |
| `total_egresos_digital` | decimal(12,2) | Salidas digitales |
| `efectivo_esperado` | decimal(12,2) | Fondo + ingresos efectivo − egresos efectivo |
| `efectivo_contado` | decimal(12,2) nullable | Conteo físico |
| `diferencia` | decimal(12,2) nullable | Contado − esperado |
| `observacion_apertura` | text nullable | Novedades al abrir |
| `observacion_cierre` | text nullable | Justificación de diferencia |
| `arqueo_user_id` | FK nullable | Quién contó |
| `arqueo_at` | datetime nullable | Cuándo se contó |
| `estado` | abierta/cerrada | Sin cambios |

Mantener `monto_inicial`, `total_ingresos`, `total_egresos` y `monto_final` por compatibilidad, pero tratarlos como totales agregados y recalcularlos desde movimientos al cerrar.

#### `caja_movimiento`

| Columna nueva | Tipo | Uso |
|---|---|---|
| `correlativo` | string único | `MOV-00000123` |
| `metodo_pago_id` | FK nullable | Método del movimiento |
| `afecta_efectivo` | boolean | Snapshot histórico (no cambia si luego se edita el método) |
| `categoria_id` | FK nullable | Venta, compra, luz, retiro, etc. |
| `naturaleza` | enum/string | `operativo`, `transferencia`, `ajuste`, `reversion` |
| `comprobante_tipo` | string nullable | Boleta/factura/ticket/recibo |
| `comprobante_numero` | string nullable | Número del documento |
| `proveedor_id` | FK nullable | Para egresos con proveedor |
| `adjunto_path` | string nullable | Foto/PDF del comprobante |
| `caja_origen_id` | FK nullable | Para reversiones de otra caja |
| `movimiento_origen_id` | FK nullable | Para compensaciones |
| `referencia_tipo` | string, no enum | Permite crecer sin migración de enum |

> **Nota:** cambiar `referencia_tipo` de `enum` a `string` evita migraciones cada vez que aparece un tipo nuevo (`ingreso_manual`, `retiro`, `deposito`, `pago_servicio`, `ajuste`, `anulacion_venta`). Usar constantes en el modelo.

#### `categoria_movimiento_caja`

```text
id, nombre, tipo(ingreso|egreso), es_transferencia, requiere_comprobante,
afecta_efectivo_default, estado, created_at, updated_at
```

Seed inicial sugerido:

- **Ingresos:** Venta, Atención, Devolución de proveedor, Cobro de crédito, Aporte de sencillo, Ajuste positivo.
- **Egresos:** Compra, Devolución a cliente, Anulación de venta, Luz, Agua, Internet, Alquiler, Limpieza, Movilidad, Útiles, Mantenimiento, Retiro a banco, Retiro del propietario, Ajuste negativo.

#### `caja_arqueo_detalle` (opcional pero recomendado)

```text
id, caja_id, denominacion, tipo(billete|moneda), cantidad, subtotal
```

Permite imprimir el conteo y auditar cómo se llegó al monto contado.

### 4.3. Flujo operativo propuesto

```text
1. APERTURA
   - Usuario cuenta el fondo inicial (por denominación o monto).
   - Registra observación si hay diferencia con el fondo esperado.
   - Se crea caja con fecha_operativa, fondo_inicial y estado=abierta.
   - Bloqueo: una sola caja abierta por terminal.

2. OPERACIÓN
   - Venta/atención: se registra un movimiento por cada método de pago.
     * Efectivo → ingreso que afecta el cajón.
     * Yape/tarjeta → ingreso digital, no afecta el cajón.
   - Egreso: método obligatorio + categoría + descripción + comprobante opcional.
     * Efectivo → valida contra efectivo esperado.
     * Digital → no afecta el cajón.
   - Ingreso manual / retiro / depósito: categoría de transferencia.
   - Todo movimiento es inmutable.

3. ARQUEO Y CIERRE
   - El cajero cuenta el efectivo (conteo ciego).
   - El sistema calcula efectivo_esperado.
   - diferencia = contado - esperado.
   - Si diferencia != 0: observación obligatoria; autorización si supera tolerancia.
   - Se guarda el arqueo y se cierra la caja.
   - Se imprime/exporta el corte.

4. POST-CIERRE
   - Devoluciones/anulaciones se registran en la caja abierta actual,
     referenciando la caja original.
   - La caja cerrada nunca se modifica.
   - Conciliación periódica: totales vs. movimientos.
```

### 4.4. Reglas de negocio recomendadas

1. No se puede vender, atender, comprar ni egresar sin caja abierta.
2. No se puede egresar efectivo por encima del efectivo esperado (salvo autorización explícita).
3. Un movimiento digital no altera el efectivo esperado.
4. Toda anulación/devolución referencia el movimiento original y la caja original.
5. No se reabre una caja cerrada; se crea una nueva.
6. El cierre exige conteo; sin conteo no hay cierre.
7. Una diferencia distinta de cero exige observación; por encima de la tolerancia, autorización.
8. `total_ingresos`/`total_egresos` se recalculan desde movimientos al cerrar; no se confía en incrementos previos.
9. Los movimientos nunca se actualizan ni eliminan; las correcciones son filas nuevas.
10. Toda operación queda en auditoría con usuario, fecha y referencia.

### 4.5. Reportes propuestos

| Reporte | Pregunta que responde | Datos |
|---|---|---|
| **Corte de caja** | ¿Cómo cerró el turno? | Apertura, cierre, usuarios, fondo, ingresos/egresos por método y categoría, esperado, contado, diferencia, firmas |
| **Libro de caja** | ¿Qué pasó en el día, en orden? | Movimientos cronológicos con saldo corriente de efectivo y total |
| **Flujo de caja** | ¿Cuánto entró y salió, y de qué tipo? | Operativo vs. transferencia, por categoría y método, neto |
| **Arqueos y diferencias** | ¿Quién tiene faltantes/sobrantes? | Caja, usuario, diferencia, observación, tendencia |
| **Gastos por categoría** | ¿En qué se va el dinero? | Egresos operativos agrupados |
| **Ventas vs. cobranza** | ¿Vendí y cobré lo mismo? | Ventas confirmadas, cobros por método, digital pendiente de conciliar |
| **Cajas abiertas** | ¿Hay una caja olvidada? | Caja, usuario, horas abierta, efectivo esperado actual |

En el dashboard: efectivo esperado actual, ventas del día por método, última diferencia de arqueo y alerta de caja abierta demasiado tiempo.

### 4.6. Permisos propuestos

| Permiso | Alcance |
|---|---|
| `caja.ver` | Ver panel e historial |
| `caja.abrir` | Abrir caja |
| `caja.operar` | Registrar ingresos/egresos manuales |
| `caja.cerrar` | Cerrar caja y registrar arqueo |
| `caja.arquear` | Contar y ver el detalle del arqueo |
| `caja.autorizar` | Autorizar diferencias y egresos sobre umbral |
| `caja.reportes` | Cortes, libro y flujo de caja |

Para una botica de 2-3 personas, el dueño puede tener todos y el vendedor solo `caja.ver`, `caja.abrir`, `caja.operar` y `caja.cerrar`.

### 4.7. Conciliación técnica

Crear un comando similar a `inventario:conciliar`:

```bash
php artisan caja:conciliar
php artisan caja:conciliar --caja=123
```

Debe comparar, sin corregir automáticamente:

- `caja.total_ingresos` vs. `SUM(ingreso)` de sus movimientos.
- `caja.total_egresos` vs. `SUM(egreso)`.
- `monto_final` vs. fórmula.
- `efectivo_esperado` vs. fórmula de efectivo.
- Movimientos con `caja_id` inexistente o referencias huérfanas.

Salida con diferencias y código de error para cron. No corrige: la corrección es un ajuste autorizado.

---

## 5. Roadmap sugerido

### Fase 0 — Confiabilidad (P0, 1-2 días)

| Tarea | Archivos | Resultado |
|---|---|---|
| Bloquear caja dentro de la transacción en ventas/atenciones | `VentaController`, `AtencionController` | No se pierden ventas al cerrar |
| Bloqueo y recálculo en cierre | `CajaController@cierre` | `monto_final` confiable |
| Restricción de una caja abierta | migración + `CajaService` | No hay aperturas dobles |
| Validar egreso contra efectivo esperado (aunque aún sea total) | `StoreEgresoRequest`/controlador | No hay caja negativa |
| Atenciones: movimientos compensatorios, no update/delete | `AtencionController` | Caja append-only |
| Anulación de venta con caja cerrada | `AnulacionVentaService` | Devoluciones del día siguiente posibles |
| Quitar/etiquetar la "Diferencia" falsa del historial | vista | No se muestra un cero engañoso |
| Pruebas de concurrencia, saldo y anulación | `CajaTest` | Regresiones cubiertas |

> La Fase 0 se puede hacer sin cambiar el esquema de arqueo; deja el sistema estable para la Fase 1.

### Fase 1 — Arqueo y efectivo real (P1, 4-6 días)

| Tarea | Resultado |
|---|---|
| `metodo_pago.afecta_efectivo` | Separar efectivo/digital |
| Movimiento de caja con método y `afecta_efectivo` | Efectivo esperado confiable |
| Un movimiento por método en ventas/atenciones | Trazabilidad exacta |
| Columnas de arqueo en `caja` | Conteo, diferencia y observación |
| UI de arqueo ciego + denominaciones | Cierre con control |
| Corte de caja imprimible | Comprobante interno |
| Categorías de egreso y comprobantes | Reportes de gasto |
| Ingresos manuales, retiros y depósitos | Flujo real completo |

### Fase 2 — Reportes y control (P1/P2, 4-6 días)

| Tarea | Resultado |
|---|---|
| Libro de caja y flujo de caja | Visibilidad diaria/mensual |
| Reporte de arqueos y diferencias | Responsabilidad por usuario |
| `caja:conciliar` | Detección de descuadres |
| Filtros y exportación CSV | Operación y contabilidad |
| Dashboard y alertas | Caja olvidada, diferencia, saldo bajo |

### Fase 3 — Escala (P2)

| Tarea | Resultado |
|---|---|
| Cuentas por pagar y pagos a proveedores | Compras a crédito reales |
| Fiado/cobranza | Ventas a crédito y cobros en caja |
| `fecha_operativa` | Ventas después de medianoche |
| Multi-caja/terminal/sede | Crecimiento |
| Permisos finos y autorizaciones | Control interno |

---

## 6. Decisiones que debe tomar el negocio

Antes de implementar la Fase 1, conviene definir:

1. ¿Yape/Plin se consideran efectivo o digital? ¿Se retiran diariamente al banco?
2. ¿La caja será diaria o por turno? ¿Cuántos cajeros hay?
3. ¿Se aceptará fiado? ¿Se cobrará en caja?
4. ¿Se pagará a proveedores después de recibir la mercadería?
5. ¿El dueño retira efectivo? ¿Con qué frecuencia?
6. ¿Qué tolerancia de diferencia se acepta? ¿Quién autoriza?
7. ¿Se exigirá comprobante en todos los egresos o solo en los que superan un monto?
8. ¿Se imprimirá el corte de caja? ¿Firma el cajero y el supervisor?
9. ¿Habrá una segunda caja o sede en el mediano plazo?
10. ¿Las ventas después de medianoche son del día anterior?

---

## 7. Checklist de pruebas recomendado

- [ ] No se puede abrir una segunda caja.
- [ ] Una venta en curso no se pierde si se cierra la caja al mismo tiempo.
- [ ] Una venta no puede registrarse en una caja ya cerrada.
- [ ] Un egreso mayor al efectivo esperado se rechaza (o exige autorización).
- [ ] Una venta mixta (efectivo + Yape) genera el efectivo esperado correcto.
- [ ] Un egreso por transferencia no reduce el efectivo esperado.
- [ ] El arqueo guarda contado, esperado, diferencia y observación.
- [ ] Una diferencia distinta de cero exige observación.
- [ ] Anular una atención no borra ni modifica el movimiento original.
- [ ] Anular una venta de una caja cerrada registra el reembolso en la caja actual.
- [ ] Los totales de caja coinciden con la suma de movimientos.
- [ ] `caja:conciliar` detecta un descuadre inducido.
- [ ] El corte de caja impreso coincide con la pantalla.
- [ ] Los reportes filtran por fecha, usuario, método y categoría.
- [ ] Una caja cerrada no se puede modificar desde ningún endpoint.

---

## 8. Conclusión

El módulo actual es un buen punto de partida, pero está diseñado para **registrar** caja, no para **controlarla**. Las tres piezas que más valor aportan, en orden, son:

1. **Separar efectivo de digital** (sin esto, ningún arqueo es posible).
2. **Arqueo real con conteo, diferencia y responsable** (sin esto, no hay control interno).
3. **Movimientos inmutables y cierre transaccional** (sin esto, los números se pueden corromper).

Con esas tres piezas, una botica pequeña puede saber todos los días cuánto dinero debería tener, cuánto tiene y quién responde por la diferencia. El resto —categorías, retiros, reportes, fiado, multi-caja— se construye encima sin rehacer el núcleo.

La recomendación es ejecutar la **Fase 0** de inmediato (bajo esfuerzo, alto impacto en confiabilidad) y decidir las preguntas de la sección 6 antes de iniciar la **Fase 1**.
