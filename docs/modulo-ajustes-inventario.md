# Módulo de ajustes de inventario

> **Última actualización:** 21/11/2026
> **Estado:** fases 5, 6 y 7 implementadas.
> **Referencias:** `modulo-compras-proveedores.md`, `flujo-inventario-lotes-fefo.md`, `plan-lotes-fefo-v2.md`.

---

## 1. Idea general

El ajuste es un documento **inmutable** que corrige el stock de un lote. No se edita, no se elimina y no se anula: si hubo un error, se registra otro ajuste compensatorio.

```text
Ajuste
  ├─ por lote
  ├─ en unidad base
  ├─ kardex AJUSTE
  └─ sin movimiento de caja
```

---

## 2. Tipos

| Tipo | Dirección | Uso |
|---|---|---|
| `conteo_fisico` | Según diferencia | Se ingresa el stock final contado; el sistema calcula la diferencia |
| `dano` | Salida | Producto roto, derramado o inutilizable |
| `vencimiento` | Salida | Retiro de un lote vencido |
| `consumo_interno` | Salida | Insumos usados en servicios o limpieza |
| `ajuste_positivo` | Entrada | Sobrante, corrección o stock encontrado |
| `otro` | Salida | Caso manual justificado |

---

## 3. Campos de la cabecera

| Campo | Obligatorio | Qué hace |
|---|---|---|
| `tipo` | Sí | Tipo de ajuste |
| `fecha` | No | Fecha del ajuste; por defecto hoy |
| `motivo` | Sí | Razón del ajuste |
| `observaciones` | No | Detalle adicional, máx. 500 |
| `total` | Calculado | Valor absoluto del ajuste |
| `correlativo` | Automático | `AJU-00000001` |
| `user_id` | Automático | Usuario que registra |

## 4. Campos de cada línea

| Campo | Qué hace |
|---|---|
| `producto_lote_id` | Lote ajustado |
| `cantidad_base` | Cantidad con signo: positiva entra, negativa sale |
| `producto_presentacion_id` | Presentación usada por la capa |
| `lote_ingreso_id` | Capa creada o usada |
| `costo_unitario_base` | Costo de referencia |
| `costo_total` | Valor con signo |

Las cantidades se manejan en **unidad base**, no en presentaciones.

---

## 5. Flujo

### 5.1. Crear ajuste

Ruta: `POST /admin/ajustes`.

1. Valida tipo, motivo y lotes.
2. Crea `ajuste_inventario` y genera `AJU-00000001`.
3. Por cada lote:
   - **conteo físico:** calcula `diferencia = contado − disponible`;
   - **ajuste positivo:** suma la cantidad;
   - **daño, vencimiento, consumo interno u otro:** resta la cantidad.
   El formulario muestra el disponible del lote y traduce la cantidad escrita a presentaciones equivalentes (por ejemplo `250 TAB = 2 Cajas x100 + 5 Blísters x10`). Es una ayuda visual; el ajuste se sigue registrando en unidad base.

El lote se elige con un **buscador dinámico** (`/admin/ajustes/lotes/search`) que filtra por producto, código de lote, correlativo o código de barras. Muestra disponible, vencimiento y costo, y conserva la validación del servidor.
4. Aplica el movimiento:
   - entrada: crea una nueva capa `lote_ingreso`, suma lote y stock, kardex `AJUSTE` positivo;
   - salida: consume las capas del lote, resta lote y stock, registra `lote_consumo` y kardex `AJUSTE` negativo.
5. Calcula el total con el costo del lote.

### 5.2. Consultar

Ruta: `GET /admin/ajustes/{ajuste}`.

- Muestra cabecera, líneas, cantidades con signo y valor.
- No tiene edición, eliminación ni anulación.

### 5.3. Corregir un error

Se registra un **nuevo ajuste** en sentido contrario. El ajuste original queda intacto.

### 5.4. Devolución de cliente

El ajuste es la herramienta para el reingreso condicionado (ver [`modulo-devoluciones-cliente.md`](modulo-devoluciones-cliente.md)):

- **Producto apto:** `ajuste_positivo` por el lote de origen, en unidad base y con motivo que cite el correlativo de la venta. Crea una capa nueva con el último costo del lote.
- **Producto no apto:** `dano`, `vencimiento` u `otro` por el lote, para retirarlo del stock.
- Si la venta se **anula**, el stock ya vuelve por `REVERSION`; no registrar además un ajuste positivo por las mismas unidades.
- El ajuste no mueve caja: un reembolso parcial se registra aparte como egreso manual.

---

## 6. Reglas

| Regla | Dónde |
|---|---|
| Ajuste solo por lote | `StoreAjusteInventarioRequest` |
| No repetir lote en el mismo ajuste | Request + `AjusteInventarioService` |
| No ajustar más de lo disponible en una salida | `InventarioService::ajustarSalidaLote` |
| No sumar stock a lotes `baja` o `vencido` | `InventarioService::ajustarEntradaLote` |
| Conteo sin diferencia no genera movimiento | `AjusteInventarioService` |
| Ajuste inmutable | Sin rutas de edición, eliminación o anulación |
| Sin movimiento de caja | El ajuste solo afecta inventario |

---

## 7. Relación con lotes y kardex

```text
ajuste_inventario
  └─ detalle_ajuste_inventario
       ├─ producto_lote
       ├─ lote_ingreso (entrada)
       └─ kardex AJUSTE + lote_consumo (salida)
```

- `producto.stock` sigue siendo una caché.
- La fuente real sigue siendo `lote_ingreso.cantidad_disponible_base`.
- Las entradas por ajuste crean una capa nueva para no mezclar con compras.
- Las salidas consumen las capas del lote seleccionado.
- No se aplica FEFO: el ajuste es sobre un lote específico.

---

## 8. Endpoints

| Método | Ruta | Acción |
|---|---|---|
| GET | `/admin/ajustes` | Listado |
| GET | `/admin/ajustes/create` | Formulario |
| POST | `/admin/ajustes` | Registrar ajuste |
| GET | `/admin/ajustes/{ajuste}` | Detalle |

Todas usan el permiso `ajustes`.

---

## 9. Pruebas automatizadas

Archivo:

```text
tests/Feature/AjusteInventarioTest.php
```

Cubre:

1. Ajuste positivo: suma stock y crea capa.
2. Ajuste negativo: descuenta stock, lote y kardex.
3. Conteo físico hacia abajo.
4. Conteo físico hacia arriba.
5. Conteo sin diferencia no crea ajuste.
6. Salida mayor a lo disponible se rechaza.
7. Ajuste positivo en lote vencido se bloquea.
8. Ajuste positivo en lote dado de baja se bloquea.
9. Correlativo y total.
10. Listado y formularios renderizan.

Comando:

```bash
php artisan test tests/Feature/AjusteInventarioTest.php
```

Resultado esperado: **10 pruebas**.

---

## 10. Pruebas manuales

### 10.1. Ajuste positivo

1. Ir a **Ajustes → Nuevo ajuste**.
2. Tipo: **Ajuste positivo**.
3. Elegir un lote y una cantidad.
4. Registrar.
5. Verificar que el stock y el lote aumentaron.

### 10.2. Daño o vencimiento

1. Tipo: **Daño** o **Vencimiento**.
2. Elegir lote y cantidad a retirar.
3. Registrar.
4. Verificar que el stock bajó y que el kardex muestra `AJUSTE` negativo.

### 10.3. Conteo físico

1. Tipo: **Conteo físico**.
2. Ingresar el stock final contado.
3. Registrar.
4. Verificar que el stock quedó exactamente en la cantidad contada.

### 10.4. Inmutabilidad

1. Abrir un ajuste registrado.
2. Confirmar que no hay botones de editar, eliminar ni anular.
3. Para corregir, registrar un nuevo ajuste en sentido contrario.

---

## 11. Mejoras futuras

- Reporte específico de ajustes por tipo y período (hoy se consulta en Movimientos especiales).
- Cuentas por pagar, si el negocio habilita compras al crédito.

---

## 12. Archivos principales

| Función | Archivo |
|---|---|
| Controlador | `app/Http/Controllers/Admin/AjusteInventarioController.php` |
| Servicio | `app/Services/AjusteInventarioService.php` |
| Movimientos por lote | `app/Services/InventarioService.php` (`ajustarEntradaLote`, `ajustarSalidaLote`) |
| Modelos | `app/Models/AjusteInventario.php`, `DetalleAjusteInventario.php` |
| Request | `app/Http/Requests/Admin/StoreAjusteInventarioRequest.php` |
| Vistas | `resources/views/admin/ajustes/` |
| JS | `resources/js/admin/ajustes/index.js`, `create.js` |
| Migración | `database/migrations/2026_06_24_000001_create_ajuste_inventario_tables.php` |
| Pruebas | `tests/Feature/AjusteInventarioTest.php` |

---

*Si cambia un tipo, una validación o el efecto en lotes/kardex, actualizar este documento y `plan-mejora-compras-proveedores.md`.*
