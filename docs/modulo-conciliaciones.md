# Módulo de conciliaciones de inventario

> **Última actualización:** 19/11/2026
> **Estado:** implementado (tarea A3 del `plan-fase-8-mejoras.md`).
> **Referencias:** `flujo-inventario-lotes-fefo.md`, `modulo-ajustes-inventario.md`, `kardex-append-only.md`.

---

## 1. Idea general

La conciliación **compara** el stock con los lotes y deja evidencia persistida. No corrige nada, no toca stock, no crea capas y no escribe kardex. Las correcciones se hacen después con un ajuste de inventario o con inventario inicial.

```text
producto.stock (caché)
  vs. suma de lote_ingreso.cantidad_disponible_base

producto_lote.cantidad_disponible_base
  vs. suma de sus capas (lote_ingreso)
```

---

## 2. Qué revisa

| Nivel | Comparación | Se guarda en |
|---|---|---|
| Producto | `producto.stock` vs. suma de capas disponibles de sus lotes | `detalle_conciliacion_inventario` |
| Lote | `producto_lote.cantidad_disponible_base` vs. suma de sus capas | `detalle_conciliacion_lote` |

- Solo se guardan las líneas con diferencia; una conciliación sin diferencias no tiene detalles.
- `diferencia_total` suma el valor absoluto de las diferencias por lote cuando la línea tiene lotes inconsistentes; si no, usa la diferencia del producto.
- Una diferencia de lote no altera el stock del producto por sí sola: se reporta como inconsistencia del lote.

---

## 3. Cabecera guardada

Tabla `conciliacion_inventario`.

| Campo | Qué guarda |
|---|---|
| `fecha` | Momento de la corrida |
| `user_id` | Usuario que la ejecutó (nulo si fue el comando sin `--user`) |
| `origen` | `comando`, `manual` o `programada` |
| `total_productos` | Productos revisados |
| `productos_con_diferencia` | Productos con al menos una diferencia |
| `lotes_inconsistentes` | Total de lotes con diferencia |
| `diferencia_total` | Suma de diferencias absolutas (3 decimales) |
| `estado` | `ok` o `con_diferencias` |

---

## 4. Comando

```bash
php artisan inventario:conciliar
php artisan inventario:conciliar --dry-run
php artisan inventario:conciliar --user=1
php artisan inventario:conciliar --json
```

- Sin diferencias: guarda la corrida y devuelve código `0`.
- Con diferencias: guarda la corrida y devuelve código `1`.
- `--dry-run`: compara y muestra, pero no persiste.
- `--user=`: asocia la corrida a un usuario existente; si no existe, falla.
- `--json`: salida en JSON para automatización; no imprime la tabla.

El comando no está programado en el scheduler; puede agregarse cuando se defina una frecuencia.

---

## 5. Panel

| Ruta | Acción |
|---|---|
| `GET /admin/conciliaciones` | Historial de conciliaciones |
| `POST /admin/conciliaciones/ejecutar` | Ejecuta una conciliación manual |
| `GET /admin/conciliaciones/{id}` | Detalle por producto y lote (modal) |

- Permiso: `conciliaciones` (rol Administrador).
- El botón **Ejecutar conciliación** envía `POST` por AJAX, muestra un toast y recarga el parcial.
- El detalle muestra cabecera, diferencias por producto y diferencias por lote.
- El origen de las ejecuciones desde el panel es `manual` y el usuario es el autenticado.

---

## 6. Qué no hace

- No corrige `producto.stock`.
- No crea ni consume capas.
- No escribe `kardex_movimientos`.
- No modifica lotes ni sus estados.
- No reemplaza al conteo físico: es una alerta de consistencia interna.

Para corregir una diferencia detectada:

1. Revisar el lote y el stock físico.
2. Registrar un **ajuste de inventario** por lote (`conteo_fisico` o `ajuste_positivo`).
3. Volver a ejecutar `inventario:conciliar` para confirmar.

---

## 7. Pruebas

`tests/Feature/ConciliacionInventarioTest.php` cubre:

- Corrida consistente sin diferencias.
- Diferencia inducida en `producto.stock`.
- Diferencia inducida en `producto_lote`.
- `--dry-run` sin persistencia.
- `--user` guardado en la cabecera.
- Ejecución desde el panel con origen `manual`.
- Render del listado y del detalle.
