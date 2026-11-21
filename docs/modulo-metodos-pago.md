# Módulo de métodos de pago

> **Última actualización:** 20/11/2026
> **Estado:** implementado. Entidad dinámica + registro obligatorio y pagos mixtos en ventas y atenciones.
> **Referencias:** `modulo-ventas.md`, `modulo-caja.md`, `panel-administrativo.md`.

---

## 1. Idea general

Los métodos de pago ya no son valores fijos en el código: viven en la tabla `metodo_pago` y se administran desde el panel. Solo los métodos **activos** aparecen en los formularios de venta y atención.

Cada operación guarda su **desglose de pagos** en la tabla `pago`, lo que permite:

- Registrar un solo método (pago simple).
- Repartir el importe entre varios métodos (pago mixto).
- Saber cuánto se pagó con cada método, incluso si luego el método se desactiva.

```text
metodo_pago (Efectivo, Yape, Plin, BCP, ...)
   ├─ activo / inactivo
   └─ usado por
        pago
          ├─ referencia_tipo: Venta | Atencion
          ├─ referencia_id
          ├─ monto
          └─ metodo_pago_id
```

---

## 2. Entidad `metodo_pago`

Ruta del módulo: `/admin/metodos-pago` (permiso `metodos-pago`).

| Columna | Tipo | Uso |
|---|---|---|
| `id_metodo_pago` | bigint unsigned (PK) | Identificador |
| `nombre` | varchar(50), único | Nombre visible (Efectivo, Yape, Plin, BCP…) |
| `descripcion` | varchar(100), nullable | Texto de apoyo opcional |
| `estado` | boolean | `1` activo, `0` inactivo |
| `afecta_efectivo` | boolean | `1` si el dinero entra al cajón (Efectivo); `0` si es digital |

Reglas:

- El nombre es único.
- Los métodos **no se eliminan físicamente**: se desactivan. Así se conserva el historial de pagos.
- Un método inactivo no se muestra en los formularios y el backend rechaza su uso.
- El seeder `MetodoPagoSeeder` crea Efectivo (`afecta_efectivo = 1`), Yape, Plin y BCP (`afecta_efectivo = 0`) en una instalación nueva.
- `afecta_efectivo` es configurable: un negocio puede decidir que Yape sí entra al cajón.

---

## 3. Desglose de pagos

Tabla `pago`:

| Columna | Tipo | Uso |
|---|---|---|
| `id_pago` | bigint (PK) | Identificador |
| `referencia_tipo` | varchar(20) | `Venta` o `Atencion` |
| `referencia_id` | bigint | ID de la venta o atención |
| `metodo_pago_id` | bigint unsigned, FK -> `metodo_pago` | Método usado |
| `monto` | decimal(10,2) | Importe pagado con ese método |
| `user_id` | FK -> `users`, nullable | Usuario que registró la operación |
| `created_at` / `updated_at` | timestamps | Auditoría temporal |

Se sigue el mismo patrón `referencia_tipo` + `referencia_id` que usan `kardex_movimientos` y `lote_consumo`.

---

## 4. Reglas de validación

En ventas (`StoreVentaRequest`) y atenciones (`StoreAtencionRequest`):

1. `pagos` es **obligatorio** y debe tener al menos una fila con importe.
2. Cada fila exige `metodo_pago_id` activo y `monto > 0` con máximo 2 decimales.
3. Cada método puede aparecer **una sola vez** en el desglose.
4. La **suma de los pagos debe ser exactamente igual al total** de la operación.
   - El total se calcula en el servidor con los precios vigentes de las presentaciones y los servicios declarados.
   - No se permiten pagos parciales ni crédito: si falta o sobra dinero, la operación se rechaza con un mensaje que indica la diferencia.
5. Las filas con importe vacío o cero se descartan antes de validar.

El cálculo compartido vive en `App\Helpers\TotalesHelper`; la validación, en `App\Http\Requests\Admin\Concerns\ValidaPagos`.

---

## 5. Experiencia de usuario

En los formularios de venta y atención se muestra el bloque **Pago**:

- **Total a pagar** (se sincroniza con el total de productos y servicios).
- **Pago único (por defecto):** los métodos activos se muestran como **botones seleccionables**. Al elegir uno, se asume que ese método cubre el **total**; no se pide importe. El cajero verifica el pago en vivo y el sistema muestra `Pagado`/`Pago completo` automáticamente.
- **Pago mixto:** el botón **Pago mixto** despliega los campos de importe para repartir el total entre métodos. Mientras está activo:
  - Los botones de métodos quedan **bloqueados** para evitar selecciones ambiguas.
  - Los campos muestran el importe por método, el botón **Resto** completa el pendiente y el resumen indica `Pagado` y `Falta`.
  - Se exige al menos **dos métodos con importe** y que la suma sea exactamente el total.
- **Revertir:** el mismo botón cambia a **Volver a pago único**, limpia los importes, desbloquea los botones y regresa a la selección simple.
- Al intentar guardar con una diferencia distinta de cero (o sin método en pago único), se bloquea el envío y se muestra el mensaje.

La lógica está en `resources/js/admin/pagos.js`. Los formularios de productos/servicios emiten el evento `total:changed` para mantener el bloque sincronizado.

En la **edición de atenciones**, si el registro tiene un solo pago se abre en modo único con ese método seleccionado; si tiene varios, se abre en modo mixto con los importes precargados.

---

## 6. Visualización

- **Detalle de venta** y **detalle de atención**: tabla de pagos con método e importe.
- **Ticket de venta**: líneas de pago debajo del total.
- Los registros históricos sin desglose (datos demo antiguos) muestran “No se registró el desglose de pagos”.

---

## 7. Relación con caja

La caja registra **un movimiento de ingreso por cada método de pago** de la venta o atención (`CajaService::registrarPagos`). Cada movimiento guarda `metodo_pago_id`, `afecta_efectivo` y `categoria` (`venta`/`atencion`).

Por eso:

- `caja.total_ingresos` es la suma de los movimientos por método, no el total duplicado.
- `caja.total_ingresos_efectivo` y `caja.total_ingresos_digital` separan el dinero del cajón del digital.
- El arqueo usa `efectivo_esperado`, `efectivo_contado` y `diferencia`.
- La anulación de una venta revierte cada movimiento por método (`CajaService::reembolsarVenta`).

El detalle de arqueo y categorías vive en `CajaService` y `CajaMovimiento`.

---

## 8. Archivos principales

```text
app/
  Helpers/TotalesHelper.php
  Http/Controllers/Admin/MetodoPagoController.php
  Http/Requests/Admin/StoreMetodoPagoRequest.php
  Http/Requests/Admin/UpdateMetodoPagoRequest.php
  Http/Requests/Admin/Concerns/ValidaPagos.php
  Models/MetodoPago.php
  Models/Pago.php
  Services/PagoService.php
database/
  migrations/2026_11_20_000001_create_metodo_pago_table.php
  migrations/2026_11_20_000002_create_pago_table.php
  seeders/MetodoPagoSeeder.php
resources/
  views/admin/metodos-pago/{index,full,create,edit}.blade.php
  views/admin/partials/pagos-form.blade.php
  js/admin/pagos.js
```

---

## 9. Pruebas

- `tests/Feature/MetodoPagoTest.php`: CRUD, nombre único, activar/desactivar y visibilidad en formularios.
- `tests/Feature/PagoVentaAtencionTest.php`: pago mixto, suma incorrecta, método inactivo, reemplazo en edición y visualización en ticket/detalle.
