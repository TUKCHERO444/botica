# Módulo Caja — Gestión de Caja Diaria

## Requisito Funcional

El módulo de **Caja** permite gestionar el flujo de efectivo diario del negocio. Su propósito es centralizar el control de ingresos generados por ventas y atenciones, así como registrar egresos justificados.

### Flujo

1. **Apertura de Caja** — El usuario inserta un monto inicial obligatorio. La caja queda en estado `abierta`.
2. **Operación** — Mientras la caja está abierta, se reflejan automáticamente los ingresos por ventas y atenciones registradas. Adicionalmente se pueden registrar **egresos** opcionales con descripción obligatoria.
3. **Cierre de Caja** — El usuario cierra la caja. Se calcula:
   - `monto_final = monto_inicial + total_ingresos - total_egresos`
   - La caja pasa a estado `cerrada` y sus datos quedan **inalterables**.
4. **Historial** — Panel de consulta de todas las cajas previamente cerradas, con datos inmutables.

---

## 2. Estructura de Archivos

```
app/
  Http/Controllers/Admin/CajaController.php       ← CRUD + apertura/cierre/egreso
  Http/Requests/Admin/
    StoreAperturaRequest.php                       ← Validación apertura (monto_inicial)
    StoreEgresoRequest.php                          ← Validación egreso (monto + descripcion)
  Models/
    Caja.php                                        ← Modelo con accessors y relaciones
    CajaMovimiento.php                              ← Modelo para movimientos (ingresos/egresos)
database/migrations/
  2026_05_26_XXXXXX_create_cajas_table.php
  2026_05_26_XXXXXX_create_caja_movimientos_table.php
resources/
  views/admin/caja/
    index.blade.php        ← Partial — caja activa + botones apertura/cierre/egreso
    apertura.blade.php     ← Partial — modal para apertura (monto inicial)
    egreso.blade.php       ← Partial — modal para registrar egreso
    historial.blade.php    ← Partial — listado de cajas cerradas
    historial-show.blade.php ← Partial — detalle de caja cerrada (inmutable)
    full.blade.php         ← Vista completa (extends layouts.admin)
  js/admin/caja/
    index.js               ← Lógica del panel activo
    historial.js           ← Lógica del historial
routes/admin.php           ← Rutas protegidas con permiso caja.*
```

---

## 3. Diseño de Base de Datos

### Tabla `caja`

| Columna        | Tipo                   | Descripción                            |
|----------------|------------------------|----------------------------------------|
| id_caja        | integer (PK, AI)       | ID                                     |
| monto_inicial  | decimal(10,2)          | Monto con que se abre la caja          |
| total_ingresos | decimal(10,2)          | Suma de ventas + atenciones            |
| total_egresos  | decimal(10,2)          | Suma de egresos registrados            |
| monto_final    | decimal(10,2)          | monto_inicial + total_ingresos - total_egresos |
| estado         | enum('abierta','cerrada') | Estado actual                       |
| apertura_user_id | FK -> users          | Usuario que abrió la caja              |
| cierre_user_id | FK -> users (nullable) | Usuario que cerró la caja              |
| apertura_at    | datetime               | Fecha/hora de apertura                 |
| cierre_at      | datetime (nullable)    | Fecha/hora de cierre                   |
| created_at     | timestamp              |                                         |
| updated_at     | timestamp              |                                         |

### Tabla `caja_movimiento`

| Columna          | Tipo                   | Descripción                                |
|------------------|------------------------|--------------------------------------------|
| id_movimiento    | integer (PK, AI)       | ID                                         |
| caja_id_caja     | FK -> caja             | Caja a la que pertenece                    |
| tipo             | enum('ingreso','egreso') | Tipo de movimiento                       |
| monto            | decimal(10,2)          | Monto del movimiento                       |
| descripcion      | text (nullable)        | Descripción (obligatoria en egresos)       |
| referencia_tipo  | enum('venta','atencion','egreso_manual') | Origen                         |
| referencia_id    | integer (nullable)     | ID de la venta/atencion que generó el ingreso |
| user_id          | FK -> users            | Usuario que registró el movimiento         |
| created_at       | timestamp              | Fecha/hora del movimiento                  |

---

## 4. Flujo Completo

### 4.1 Apertura

```
1. Usuario ve panel de caja
2. Si no hay caja abierta → botón "Abrir caja" → modal con campo monto_inicial
3. Submit → POST /admin/caja/apertura
   - Crea registro en caja con estado='abierta', monto_inicial, apertura_user_id, apertura_at
   - Invalida caché 'caja.index'
4. Panel recarga y muestra caja activa con monto_inicial, total_ingresos=S/0, botones
```

### 4.2 Integración con Ventas y Atenciones

Cuando se registra una venta o atención (en sus respectivos controladores), si hay una caja abierta:

```
1. Caja::abierta() existe → crear CajaMovimiento con tipo='ingreso', referencia_tipo='venta'|'atencion', referencia_id, monto
2. Actualizar caja.total_ingresos += monto
```

### 4.3 Egreso Manual

```
1. Botón "Registrar egreso" → modal con campos: monto (required, >0), descripcion (required)
2. Submit → POST /admin/caja/egreso
   - Crea CajaMovimiento tipo='egreso', referencia_tipo='egreso_manual', descripcion, monto
   - Actualiza caja.total_egresos += monto
   - Invalida caché
3. Panel recarga y muestra nuevo total
```

### 4.4 Cierre

```
1. Botón "Cerrar caja" → confirmación JS
2. Submit → POST /admin/caja/cierre
   - Calcula monto_final = monto_inicial + total_ingresos - total_egresos
   - Actualiza estado='cerrada', cierre_user_id, cierre_at, monto_final
   - Invalida caché
3. Panel recarga → muestra caja cerrada con resumen y botón para historial
```

### 4.5 Historial

```
1. Botón "Historial de cajas" → GET /admin/caja/historial
2. Lista todas las cajas cerradas con resumen (fecha apertura, fecha cierre, monto_final, usuario)
3. Click en una → GET /admin/caja/historial/{id} → detalle completo (inmutable)
   - Muestra: monto_inicial, lista de movimientos, total_ingresos, total_egresos, monto_final
   - Sin botones de edición/eliminación
```

---

## 5. Plan de Implementación

| Paso | Archivos | Descripción |
|------|----------|-------------|
| 1 | Migraciones | Crear `caja` y `caja_movimiento` |
| 2 | Modelos | `Caja.php`, `CajaMovimiento.php` |
| 3 | Form Requests | `StoreAperturaRequest`, `StoreEgresoRequest` |
| 4 | Controlador | `Admin/CajaController.php` con métodos: `index`, `apertura`, `egreso`, `cierre`, `historial`, `historialShow` |
| 5 | Vistas | `index.blade.php`, `apertura.blade.php`, `egreso.blade.php`, `historial.blade.php`, `historial-show.blade.php`, `full.blade.php` |
| 6 | JS | `index.js` para el panel activo |
| 7 | Rutas | Agregar rutas a `routes/admin.php` |
| 8 | Vite | Agregar entradas a `vite.config.js` |
| 9 | Sidebar | Agregar link en `layouts/admin.blade.php` |
| 10 | Tests | Feature tests para apertura, egreso, cierre, historial |

---

## 6. Consideraciones

- **Inmutabilidad**: Una vez cerrada la caja, no se puede modificar. No hay `update` ni `destroy` en el controlador para cajas cerradas.
- **Concurrencia**: Solo puede existir una caja abierta a la vez. Se valida en apertura: si ya hay una caja abierta, se rechaza.
- **Índices**: Se cachea la vista `index` con `CacheaPanel` y se invalida en apertura/egreso/cierre.
- **Permisos**: Se requiere permiso `caja.ver` para ver, `caja.operar` para apertura/egreso/cierre.
