# D1 — Inventario inicial por lotes y migración de existencias

> **Fecha:** 19/11/2026
> **Estado:** D1.1–D1.4 implementadas; D2 (núcleo) implementada; D1.5 (piloto con datos reales) y regularización de legados pendientes.
> **Referencias:** `plan-fase-8-mejoras.md`, `plan-lotes-fefo-v2.md`, `flujo-inventario-lotes-fefo.md`, `modulo-ajustes-inventario.md`, `modulo-conciliaciones.md`.

---

## 1. Decisión

**Sí conviene implementar D1**, pero no como un simple formulario: como un módulo de **Inventario inicial** con carga manual y **importación CSV** controlada.

Alcance aprobado:

```text
Inventario inicial
  ├─ cabecera + líneas en borrador
  ├─ carga manual
  ├─ importación CSV con vista previa
  ├─ validación y reporte de errores
  └─ confirmación transaccional
       ├─ producto_lote
       ├─ lote_ingreso (capa)
       ├─ producto.stock
       └─ kardex INGRESO (referencia InventarioInicial)
```

Reglas de la decisión:

- D1 se implementa **antes de D2**.
- El alta de producto deja de pedir stock: nace con `stock = 0`.
- La única vía para cargar existencias iniciales es el inventario inicial.
- No se usan compras, ajustes positivos ni SQL/seeders como vía principal de migración.
- D2 (eliminar `SIN-LOTE`, `asegurarLotes` y el campo legado) se ejecuta después de validar la migración.

---

## 2. Qué problema resuelve

Una botica que ya opera tiene existencias reales, pero casi nunca tiene lotes y vencimientos digitalizados. Si se carga el stock “suelto”:

- FEFO no puede funcionar.
- No se sabe qué mercadería vence primero.
- El costo real por lote se pierde.
- Aparecen lotes `SIN-LOTE` con vencimiento 2099, que son una falsa trazabilidad.
- La conciliación `stock == suma(lotes)` queda en evidencia.

D1 permite migrar el **inventario de apertura** con lote, vencimiento, cantidad y costo, sin inventar compras ni movimientos de caja.

---

## 3. Estado previo (antes de D1)

| Pieza | Comportamiento actual | Problema para migrar |
|---|---|---|
| Alta de producto | Pide `stock` y crea un lote `SIN-LOTE` si es mayor que cero | No hay lote/vencimiento real; no genera kardex `INGRESO` |
| `InventarioService::ingresar` | Crea lote, capa, stock y kardex `INGRESO` | Llama a `asegurarLotesInterno`, que puede crear `SIN-LOTE` |
| `InventarioService::asegurarLotesInterno` | Crea `SIN-LOTE` (2099) para stock sin lotes | Es exactamente lo que D1/D2 quieren eliminar |
| Compras (`CompraService::recibir`) | Crea lote/capa/stock/kardex y pago opcional | Distorsiona compras, proveedores y cuentas por pagar |
| Ajustes (`AjusteInventarioService`) | Ajuste por lote, kardex `AJUSTE`, sin caja | No crea lotes ni capas de inventario inicial; es para correcciones |
| Inventario inicial | No existe | No hay forma controlada de cargar existencias reales |

Conclusión: hace falta una pieza nueva; reutilizar las existentes sin ajustes genera datos sucios.

---

## 4. Cómo sí migrar (flujo recomendado)

### 4.1. Estrategia general: corte limpio

Para un cliente que viene de otro sistema o de planillas:

1. **No migrar la base de datos vieja.** Se crea una base limpia de la botica.
2. Migrar solo **datos maestros** (categorías, marcas, unidades, presentaciones, productos).
3. Migrar el **inventario de apertura** por lote y vencimiento.
4. Verificar con conteo físico y conciliación.
5. Poner en producción.
6. Dejar el sistema anterior en modo consulta para el histórico.

Así se evita arrastrar ventas, compras, saldos y lotes inconsistentes del sistema anterior.

### 4.2. Orden de carga

```text
1. Categorías y marcas
2. Unidades de medida y presentaciones base
3. Productos (stock 0)
4. Presentaciones adicionales (factor, precio, paso)
5. Lotes de inventario inicial (cantidad, vencimiento, costo)
6. Confirmación del inventario inicial
7. Conciliación stock vs. lotes
```

No se debe cargar stock antes de tener producto, unidad y presentación.

### 4.3. Escenarios según los datos del cliente

| Lo que tiene el cliente | Cómo se migra |
|---|---|
| Catálogo en Excel + stock por lote y vencimiento | Importación CSV de productos + lotes. Escenario ideal |
| Catálogo en Excel + stock total, sin lotes | **No se importa el stock agregado.** Hacer conteo físico por lote/vencimiento y cargar por CSV |
| Solo inventario físico en papel | Planilla de conteo por categoría; luego carga manual o CSV |
| Sistema anterior con base de datos | Exportar catálogo y lotes; no copiar tablas; corte limpio |
| Productos sin lote/vencimiento | Decisión pendiente (ver §11). Por defecto, no se cargan como disponibles |
| Stock vencido | No cargarlo como disponible; se registra aparte como vencido/baja |
| Varias sucursales | Fuera de alcance (E6 multi-almacén); D1 asume un solo almacén |

### 4.4. Plantilla CSV implementada

Archivo único, una fila por lote:

| Columna | Obligatoria | Ejemplo |
|---|---|---|
| `nombre` | Sí | `Paracetamol 500 mg Tableta` |
| `unidad_base` | Sí | `TAB` |
| `categoria` | No | `Analgésicos` |
| `marca` | No | `Farmex` |
| `descripcion` | No | `Tableta` |
| `precio_venta` | No | `0.52` |
| `precio_compra` | No | `0.21` |
| `stock_minimo` | No | `100` |
| `lote` | Sí | `LOTE-0001` |
| `vencimiento` | Sí | `2027-12-31` |
| `cantidad_base` | Sí | `600` |
| `costo_unitario_base` | No | `0.21` |
| `presentacion` | No | `Caja` |
| `factor` | No | `100` |
| `precio_presentacion` | No | `46.80` |

Reglas de formato:

- UTF-8, sin fórmulas.
- Fechas `AAAA-MM-DD`.
- Decimales con punto.
- Cantidades y costos en **unidad base** para el CSV. La **carga manual** sí permite elegir cualquier presentación y el sistema convierte con su factor.
- **Carga manual:** precios y costos con más de **2 decimales** se rechazan; cantidades/factores/pasos con más de **3 decimales** también.
- **CSV:** si un precio/costo trae más de 2 decimales, se **redondea a 2** y se reporta una advertencia; si una cantidad/factor trae más de 3, se redondea a 3. Así se pueden importar archivos viejos sin editar todo a mano.
- La columna `presentacion`/`factor` crea una presentación adicional para venta, pero la cantidad del lote sigue expresada en unidad base.
- No se incluye `sku` ni `codigo_barras`: quedan fuera de D1. El cruce de productos es por **nombre + unidad base**.
- `categoria` y `marca` se crean con el nombre indicado si no existen.

### 4.5. Validaciones antes de confirmar

| Regla | Acción |
|---|---|
| Producto existe o se va a crear | Error si no hay match |
| Unidad/presentación/factor válidos | Error |
| `cantidad_base > 0` | Error |
| `lote` no vacío | Error |
| `vencimiento` válido y futuro | Error si es pasado (o advertencia si se decide aceptar vencidos) |
| `costo_unitario_base >= 0` | Error |
| Precio/costo con más de 2 decimales | Error |
| Cantidad/factor/paso con más de 3 decimales | Error |
| Duplicado producto+lote+vencimiento | Error o suma controlada |
| Producto con stock o lotes previos | Bloquear; requiere regularización |
| Producto con `SIN-LOTE` | Bloquear; no mezclar |
| Categoría/marca/unidad inexistente | Error o creación explícita, nunca silenciosa |
| Total de stock vs. suma de líneas | Verificar antes de confirmar |

La importación debe tener **dry-run obligatorio**: muestra filas válidas, errores y advertencias sin escribir en la base.

### 4.6. Confirmación transaccional

Al confirmar:

1. Se bloquea la cabecera (`borrador → confirmado`).
2. Por cada línea:
   - crea `producto_lote` (`es_inicial = true`);
   - crea `lote_ingreso` con `ingreso_id = null` y `detalle_ingreso_id = null`;
   - suma `cantidad_disponible_base` al lote;
   - suma `producto.stock` y sincroniza `producto.inventario` (legado);
   - registra kardex `INGRESO` con referencia `InventarioInicial` y código `INI-00000001`.
3. Si una línea falla, se revierte **todo**.
4. Al terminar, el stock debe ser igual a la suma de capas.
5. No se mueve caja, no se crea proveedor, no se crea compra.

### 4.7. Cutover (puesta en marcha)

| Momento | Acción |
|---|---|
| T-7 | Auditoría de datos, plantilla, plan de conteo físico |
| T-3 | Importación en ambiente de prueba; corregir errores |
| T-1 | Backup, congelar ventas/compras, conteo final |
| T-0 | Importar, confirmar, conciliar, revisar top 20 productos |
| T-0 | Recién entonces habilitar ventas |
| T+7 | Primera revisión post-migración y ajustes |

Checklist mínimo:

- [ ] Backup de base y archivos.
- [ ] Ventas/compras bloqueadas durante la carga.
- [ ] Dry-run sin errores.
- [ ] `stock == suma(lotes)` por producto.
- [ ] `php artisan inventario:conciliar` sin diferencias.
- [ ] Total de costo del inventario inicial cuadrado con el conteo.
- [ ] Top 20 productos verificados físicamente.
- [ ] Archivo fuente y reporte de importación archivados.

---

## 5. Cómo NO migrar

| Anti-patrón | Por qué no |
|---|---|
| Insertar stock con SQL o seeders | Salta validaciones, lotes, kardex y auditoría |
| Crear compras para ingresar stock | Distorsiona reportes de compras, proveedores y cuentas por pagar |
| Usar `ajuste_positivo` como vía principal | Genera kardex `AJUSTE`, no crea lote y crea una capa nueva; es para correcciones |
| Cargar stock agregado sin lote/vencimiento | FEFO y vencimientos quedan sin sentido |
| Usar `SIN-LOTE` con vencimiento 2099 | Falsa trazabilidad; D2 lo elimina |
| Cargar vencidos como disponibles | Se venderían productos no aptos |
| Mezclar precios de caja con costos base | Costos y márgenes incorrectos |
| Importar ventas/compras históricas al sistema operativo | Arrastra saldos y movimientos que no se necesitan para operar |
| Importar duplicados de producto/lote | Rompe stock y conciliación |
| Importar durante la operación normal | Riesgo de ventas con stock a medio cargar |
| Corregir errores borrando kardex | Rompe la regla append-only |
| Confiar solo en el nombre del producto | Usar nombre completo + unidad base y validar duplicados dentro del archivo |

---

## 6. Diseño técnico propuesto

### 6.1. Tablas

**`inventario_inicial`**

| Campo | Uso |
|---|---|
| `id_inventario_inicial` | PK |
| `correlativo` | `INI-00000001` |
| `fecha` | Fecha del conteo |
| `user_id` | Responsable |
| `estado` | `borrador`, `confirmado` |
| `observaciones` | Notas |
| `archivo_nombre` | CSV original |
| `archivo_hash` | Evita reimportar el mismo archivo |
| `total_lineas` | Resumen |
| `total_costo` | Resumen |
| `timestamps` | Auditoría |

**`detalle_inventario_inicial`**

| Campo | Uso |
|---|---|
| `id_detalle_inventario_inicial` | PK |
| `inventario_inicial_id` | FK |
| `producto_id` | Producto |
| `producto_presentacion_id` | Presentación usada para cargar (base, caja, blíster, etc.) |
| `cantidad_presentacion` | Cantidad en esa presentación |
| `factor` | Factor de la presentación al momento de cargar |
| `codigo_lote` | Lote real |
| `fecha_vencimiento` | Vencimiento real |
| `cantidad_base` | Cantidad convertida a unidad base |
| `costo_unitario_base` | Costo por unidad base |
| `costo_unitario_presentacion` | Costo por presentación |
| `costo_total` | Calculado |
| `producto_lote_id` | Se llena al confirmar |
| `lote_ingreso_id` | Se llena al confirmar |
| `timestamps` | Auditoría |

- `estado = borrador`: editable (cabecera y líneas), no afecta stock.
- `estado = confirmado`: inmutable.
- No se permite confirmar dos veces.
- Si se necesita corregir después, se usa un ajuste.

### 6.2. Servicio

`App\Services\InventarioInicialService`:

- `previsualizar(array $filas): array` — valida y devuelve errores/advertencias sin escribir.
- `crearBorrador(array $data): InventarioInicial`.
- `confirmar(InventarioInicial $inventario): InventarioInicial` — transacción única.
- `importarCsv(UploadedFile $archivo): array` — parsea y valida.

No usar `InventarioService::ingresar` tal cual: llama a `asegurarLotesInterno` y podría crear `SIN-LOTE`. Opciones:

1. Agregar un parámetro `bool $asegurarLotes = true` a `ingresar` y pasar `false` desde el inventario inicial.
2. Crear un método `ingresarInicial` que reutilice la creación de lote/capa/kardex sin `asegurarLotesInterno`.

Recomendado: opción 2 (más explícita) o refactor interno controlado.

### 6.3. UI

| Ruta | Acción |
|---|---|
| `GET /admin/inventario-inicial` | Historial |
| `GET /admin/inventario-inicial/create` | Cabecera + carga manual o CSV |
| `POST /admin/inventario-inicial` | Guardar borrador |
| `POST /admin/inventario-inicial/importar` | Previsualizar CSV |
| `GET /admin/inventario-inicial/{id}` | Detalle |
| `POST /admin/inventario-inicial/{id}/confirmar` | Confirmar |

- Permiso nuevo: `inventario-inicial` (rol Administrador).
- El botón de confirmar debe pedir confirmación explícita y mostrar resumen (productos, lotes, unidades, costo).
- El detalle confirmado muestra usuario, fecha, archivo y líneas.

### 6.4. Alta de producto

- Quitar `stock` del formulario y de `StoreProductoRequest`.
- `producto.stock = 0` e `inventario = 0` al crear.
- No llamar a `asegurarLotes` en el alta.
- El stock se carga después con inventario inicial.
- En edición, `stock` sigue solo lectura.

Esto es un subconjunto de D2; D2 elimina después `SIN-LOTE`, `asegurarLotesInterno` y el campo legado.

---

## 7. Correcciones posteriores a la confirmación

El inventario inicial confirmado **no se edita ni elimina**. Para corregir:

| Error | Corrección |
|---|---|
| Cantidad mal contada | Ajuste `conteo_fisico` por lote |
| Faltante | Ajuste negativo (`otro`/`conteo_fisico`) |
| Sobrante | `ajuste_positivo` |
| Lote vencido | `vencimiento` |
| Producto dañado | `dano` |

Si el error es de lote o vencimiento y todavía no hubo ventas, evaluar una reversión controlada antes de implementarla; por defecto, se corrige con ajustes para no romper el kardex append-only.

---

## 8. Plan de implementación

| Fase | Entregable | Riesgo |
|---|---|---|
| D1.1 | Tablas, modelos, servicio, borrador y confirmación manual | Medio |
| D1.2 | Importación CSV, dry-run, errores y vista previa | Medio |
| D1.3 | Alta de producto con stock 0 y ajustes de tests | Medio |
| D1.4 | Plantilla, export de productos, conciliación y checklist de cutover | Bajo |
| D1.5 | Prueba piloto con datos reales del cliente | Alto |
| D2 | Eliminar `SIN-LOTE`, `asegurarLotes` y campo legado | Muy alto |

D1 no debe mezclarse con D2 en el mismo despliegue: primero se migra y se valida; después se limpia la lógica antigua.

---

## 9. Pruebas

### Automatizadas

- Borrador no mueve stock, lotes ni kardex.
- Confirmación crea lote, capa, stock y kardex `INGRESO` con referencia `InventarioInicial`.
- Producto con varios lotes: stock = suma de capas.
- Rollback completo si una línea falla.
- No se confirma dos veces.
- No se acepta cantidad cero/negativa, lote vacío o vencimiento inválido.
- Duplicados detectados.
- Producto con `SIN-LOTE` o stock previo se bloquea.
- Permiso `inventario-inicial`.
- Importación CSV: archivo válido, errores por fila, encoding y columnas faltantes.
- Después de confirmar, `inventario:conciliar` no reporta diferencias.

### Manuales

- Cargar 10 productos y 20 lotes; verificar stock, kardex y reporte de lotes.
- Importar un CSV con errores; verificar que no se escriba nada.
- Corregir y reimportar; confirmar; verificar total de costo.
- Intentar vender un producto antes de confirmar: stock 0, no debe venderse.
- Verificar que no se creó compra, proveedor ni movimiento de caja.

---

## 10. Decisiones abiertas

1. **Productos sin vencimiento:** ¿se exige fecha para todos o se agrega `sin_vencimiento`? Recomendación: exigir vencimiento en D1 y tratar los productos sin vencimiento como excepción separada.
2. **Stock vencido:** ¿se rechaza o se carga con estado `vencido` para trazabilidad? Recomendación: rechazar como disponible; si se carga, que sea con estado `vencido` y decisión explícita.
3. **Stock agregado sin lotes:** no se acepta; exigir conteo físico por lote/vencimiento.
4. **Creación automática de categorías/marcas/unidades:** recomendación: no crear silenciosamente; error con lista de faltantes.
5. **Clave de cruce de productos:** `nombre + unidad base`. SKU y código de barras quedan fuera de D1 y se evaluarán en una etapa posterior.
6. **CSV único o dos archivos:** recomendación: dos (catálogo y lotes).
7. **Permiso:** `inventario-inicial` nuevo, separado de `productos` y `ajustes`.
8. **Reversión antes de ventas:** evaluar si se permite anular un inventario inicial confirmado sin movimientos; por defecto, no.
9. **Multi-almacén:** fuera de alcance.

---

## 11. Decisión final

| Pregunta | Decisión |
|---|---|
| ¿Se implementa D1? | **Sí** |
| ¿Alcanza con formulario manual? | No; incluye importación CSV |
| ¿Se usa compra/ajuste/SQL para migrar? | No |
| ¿Se permite stock sin lote/vencimiento? | No |
| ¿Se permite `SIN-LOTE` en la migración? | No |
| ¿Se implementa D2 ahora? | No; después de validar D1 |
| ¿Se migran ventas/compras históricas? | No; solo catálogo e inventario de apertura |
| ¿Se puede cargar stock agregado? | No; requiere conteo por lote/vencimiento |

**Resultado esperado:** una botica existente puede cargar su inventario real por lote y vencimiento, con kardex `INGRESO`, sin inventar compras ni movimientos de caja, y con conciliación verificable antes de vender.

---

## 12. Estado de implementación (19/11/2026)

| Fase | Estado | Entregable |
|---|---|---|
| D1.1 | Implementada | Tablas `inventario_inicial` y `detalle_inventario_inicial`; modelos; servicio; borrador y confirmación manual |
| D1.2 | Implementada | Importación CSV con dry-run, validación por fila, resumen y creación de productos/presentaciones |
| D1.3 | Implementada | Alta de producto con stock 0; sin `SIN-LOTE` al crear; sin campo stock en el formulario |
| D1.4 | Implementada | Plantilla CSV, exportación de productos para conteo, conciliación y pantalla de historial/detalle |
| D1.5 | Pendiente | Piloto con datos reales del cliente |

Piezas creadas:

- `app/Services/InventarioInicialService.php`
- `app/Http/Controllers/Admin/InventarioInicialController.php`
- `app/Http/Requests/Admin/StoreInventarioInicialRequest.php`
- `app/Models/InventarioInicial.php` y `DetalleInventarioInicial.php`
- Vistas en `resources/views/admin/inventario-inicial/`
- JS `resources/js/admin/inventario-inicial/index.js`
- Permiso `inventario-inicial` (rol Administrador)
- Rutas `/admin/inventario-inicial`
- Pruebas `tests/Feature/InventarioInicialTest.php`

Lo que **no** se implementó todavía: la regularización automática de datos heredados. D2 ya eliminó la creación de `SIN-LOTE` y agregó el bloqueo por inconsistencia; cada base con datos debe regularizar sus lotes `SIN-LOTE` con conteo físico. Usar `php artisan inventario:sin-lote` para listarlos.

---

## 13. Revisión manual recomendada

Usar una base descartable (`php artisan migrate:fresh --seed`) y el usuario `admin@sistema.com`.

### 13.1. Alta de producto

1. Ir a **Productos → Nuevo producto**.
2. Verificar que **no exista campo Stock** y que el aviso indique que se carga en Inventario inicial.
3. Crear un producto con unidad base, precio y stock mínimo.
4. Verificar en la base o en el listado que nace con `stock = 0` y sin lote `SIN-LOTE`.

### 13.2. Carga manual

1. Ir a **Inventario inicial → Nuevo inventario**.
2. En **Carga manual**, buscar el producto por nombre o código (buscador dinámico), seleccionar la **presentación** (unidad base, caja, blíster, etc.) y completar lote, vencimiento, cantidad y costo **en esa presentación**.
3. Guardar borrador.
4. Si hay un error, abrir **Editar borrador**, corregir líneas y guardar.
5. Verificar que el stock del producto sigue en `0` y que no hay kardex.
6. Abrir el detalle y pulsar **Confirmar inventario**.
7. Verificar:
   - stock = cantidad cargada;
   - lote creado con `es_inicial = true`;
   - kardex `INGRESO` con referencia `InventarioInicial`;
   - lote/capa visibles en el detalle.

### 13.3. Importación CSV

1. Descargar la **Plantilla CSV** o usar el ejemplo masivo [`ejemplo-inventario-inicial-100-productos.csv`](ejemplo-inventario-inicial-100-productos.csv).
2. Cargar un archivo con dos lotes del mismo producto (mismo nombre/unidad).
3. Pulsar **Validar solamente**: debe mostrar líneas, productos nuevos, costo total y no guardar nada.
4. Pulsar **Importar como borrador**.
5. Verificar que el producto se creó con stock `0` y que el inventario quedó en borrador.
6. Abrir el detalle y confirmar.
7. Verificar stock = suma de lotes y ejecutar:

```bash
php artisan inventario:conciliar --dry-run
```

Debe reportar `0` diferencias.

### 13.4. Casos de error

- Vencimiento pasado: debe rechazar la fila.
- Unidad base inexistente: debe rechazar la fila.
- Lote duplicado en el mismo archivo: debe rechazar la fila.
- Producto con stock o lotes previos: debe bloquear el borrador.
- Usuario sin permiso `inventario-inicial`: debe recibir `403`.

### 13.5. Después de la prueba

Si fue una prueba descartable, volver a `php artisan migrate:fresh --seed` para dejar la base limpia.

### 13.6. Regularización de `SIN-LOTE` heredados (D2)

1. Ejecutar:
   ```bash
   php artisan inventario:sin-lote
   ```
   Sale con código `1` si encuentra lotes heredados y muestra producto, lote, vencimiento, cantidad y stock.
2. Hacer conteo físico por lote y vencimiento.
3. Registrar un ajuste negativo sobre el lote `SIN-LOTE` hasta dejarlo en `0` (tipo `otro`, motivo `Regularización SIN-LOTE`).
4. Cargar el inventario inicial real con lote, vencimiento, cantidad y costo.
5. Confirmar y verificar `stock == suma(lotes)` con `inventario:conciliar --dry-run`.

Si el producto tiene lotes reales mezclados con `SIN-LOTE`, regularizar primero el `SIN-LOTE` y usar ajustes para el resto; no borrar lotes ni kardex.

### 13.7. Revisión manual D2

1. Ejecutar:
   ```bash
   php artisan inventario:sin-lote
   ```
   En una base limpia debe decir “No hay lotes SIN-LOTE” y salir con `0`.
2. Crear o dejar un producto con `stock > 0` pero sin lotes.
3. Intentar venderlo o recibir una compra:
   - debe responder `422`;
   - el mensaje debe indicar “Inventario inconsistente… Regularice con inventario inicial o un ajuste”;
   - no debe crear venta/compra, stock ni kardex.
4. Crear manualmente un lote `SIN-LOTE` de prueba y ejecutar el comando:
   - debe listar el producto y salir con `1`;
   - luego regularizar con conteo físico, ajuste negativo e inventario inicial.
