# Mapeo de módulos, tablas y paginación

> **Fecha:** 23/11/2026
> **Objetivo:** identificar qué listados del panel no tienen paginación y estandarizar un manejador global.
> **Regla inicial:** listados administrativos de **10 registros por página**; los listados técnicos existentes (auditoría, kardex, lotes) quedan en 50 hasta revisarlos.

---

## 1. Manejador global

Componente:

```text
resources/views/admin/partials/pagination.blade.php
```

Uso:

```blade
@include('admin.partials.pagination', ['items' => $productos])
```

- Usa `$items->hasPages()`, `firstItem()`, `lastItem()`, `total()`.
- Genera enlaces con `data-panel`, por lo que `AdminRouter` los carga dentro del panel sin recargar la página.
- `resources/js/admin/router.js` también intercepta enlaces dentro de `[data-pagination]` y `.pagination` como respaldo.
- Los controladores deben usar `paginate(10)->withQueryString()` para conservar filtros y búsqueda.

Implementado primero en:

```text
Productos → ProductoController@index → paginate(10)
```

---

## 2. Mapeo general

| Módulo | Ruta | Tablas principales | Paginación actual | Estado |
|---|---|---|---|---|
| Dashboard | `/admin/dashboard` | `venta`, `atencion`, `producto` | No aplica (cards/charts) | — |
| Productos | `/admin/productos` | `producto`, `producto_presentacion`, `producto_imagen` | **Sí, 10** | Implementado |
| Categorías | `/admin/categorias` | `categoria` | **Sí, 10** | Implementado |
| Marcas | `/admin/marcas` | `marca` | **Sí, 10** | Implementado |
| Unidades de medida | `/admin/unidades-medida` | `unidad_medida`, `presentacion` | **Sí, 10** | Implementado |
| Presentaciones | `/admin/presentaciones` | `presentacion` | **Sí, 10** | Implementado |
| Combos | `/admin/combos` | `combo`, `combo_producto` | **Sí, 10** | Implementado |
| Compras | `/admin/ingresos` | `ingreso`, `detalle_ingreso` | **Sí, 10** | Implementado |
| Devoluciones a proveedor | `/admin/devoluciones` | `devolucion_proveedor`, detalle | **Sí, 10** | Implementado |
| Kardex | `/admin/kardex` | `kardex_movimientos` | **Sí, 10** | Implementado |
| Lotes y vencimientos | `/admin/lotes` | `producto_lote`, `lote_ingreso` | **Sí, 10** | Implementado |
| Ajustes de inventario | `/admin/ajustes` | `ajuste_inventario`, detalle | **Sí, 10** | Implementado |
| Conciliaciones | `/admin/conciliaciones` | `conciliacion_inventario`, detalles | **Sí, 10** | Implementado |
| Inventario inicial | `/admin/inventario-inicial` | `inventario_inicial`, detalle | **Sí, 10** | Implementado |
| Clientes | `/admin/clientes` | `cliente` | **Sí, 10** | Implementado |
| Ventas | `/admin/ventas` | `venta`, `detalle_venta` | **Sí, 10** | Implementado |
| Historial de ventas | `/admin/ventas/historial` | `venta`, `detalle_venta` | **Sí, 10** | Implementado |
| Atenciones | `/admin/atenciones` | `atencion`, `atencion_producto`, `atencion_servicio` | **Sí, 10** | Implementado |
| Historial de atenciones | `/admin/atenciones/historial` | `atencion`, `atencion_producto`, `atencion_servicio` | **Sí, 10** | Implementado |
| Servicios | `/admin/servicios` | `servicio` | **Sí, 10** | Implementado |
| Proveedores | `/admin/proveedores` | `proveedor` | **Sí, 10** | Implementado |
| Usuarios | `/admin/usuarios` | `users`, `model_has_roles` | **Sí, 10** | Implementado |
| Roles | `/admin/roles` | `roles`, `permissions` | **Sí, 10** | Implementado |
| Auditoría | `/admin/auditoria` | `auditoria` | **Sí, 10** | Implementado |
| Caja | `/admin/caja` | `caja`, `caja_movimiento` | **Sí, movimientos y cerradas 10** | Implementado |
| Sliders | `/admin/sliders` | `slider_producto` | **Sí, 10 por tab** | Implementado |
| Textos del header | `/admin/header-text` | `header_text` | **Sí, 10** | Implementado |
| Banners | `/admin/banners` | `banner` | **Sí, 10** | Implementado |
| Categorías en portada | `/admin/portada-categorias` | `portada_categoria` | **Sí, 10** | Implementado |
| Promociones | `/admin/promociones` | `promocion`, pivotes | **Sí, 10** | Implementado |
| Reportes | `/admin/reportes` | Consultas a ventas/compras/inventario | No aplica (PDF/filtros) | — |
| Catálogo público | `/productos` | `producto` | Sí, 20 | Fuera del panel |

---

## 3. Verificación

- Productos: página 1 muestra 10 registros; página 2 muestra los restantes; la búsqueda se conserva al paginar.
- Se verificó la carga de todos los listados administrativos sin errores.
- Categorías, marcas, presentaciones, kardex, lotes y portada muestran paginación cuando superan 10 registros.
- Caja pagina movimientos y cajas cerradas con parámetros independientes (`mov_page`, `caja_page`).
- Sliders pagina por tab con su propio manejador AJAX.

---

## 4. Estado final

Todos los listados administrativos usan 10 registros por página con el parcial global y `paginate(10)->withQueryString()`.

Excepciones:

- Dashboard y reportes no son listados paginables.
- Catálogo público mantiene 20 por página.
- El kardex por producto dentro de modal usa paginación con `data-modal`.
- Los listados con formularios o grids (banners, portada, sliders) conservan su diseño y agregan paginación al pie.
