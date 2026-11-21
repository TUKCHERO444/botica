# Ejemplo CSV — Inventario inicial con 100 productos

> **Archivo:** [`ejemplo-inventario-inicial-100-productos.csv`](ejemplo-inventario-inicial-100-productos.csv)
> **Uso:** datos ficticios para probar la importación CSV del módulo **Inventario inicial**.
> **Encoding:** UTF-8 · **Delimitador:** coma · **Una fila por lote**

---

## 1. Resultado esperado

Validación con el CSV actual:

| Métrica | Valor |
|---|---:|
| Líneas | 100 |
| Productos nuevos | 100 |
| Productos existentes | 0 |
| Errores | 0 |
| Advertencias | 0 |
| Costo total estimado | S/ 5,889.90 |

Después de importar, se crea un **borrador** con 100 líneas y 100 productos con `stock = 0`. El stock recién aparece al **confirmar** el inventario inicial.

---

## 2. Convención de generación

Se generaron **20 principios activos × 5 formas = 100 productos**:

| Forma | Unidad base | Cantidad base | Stock mínimo | Presentación | Factor |
|---|---|---:|---:|---|---:|
| Tableta | `TAB` | 500–800 | 100 | Caja | 100 |
| Cápsula | `CAP` | 300–600 | 80 | Caja | 100 |
| Jarabe | `ML` | 240–600 | 300 | Frasco | 120 |
| Crema | `G` | 60–150 | 50 | Tubo | 30 |
| Suspensión | `ML` | 120–300 | 200 | Frasco | 60 |

- Cada producto tiene **un lote** y un vencimiento futuro entre 2027 y 2030.
- Los lotes van de `LOTE-0001` a `LOTE-0100`.
- Se usan categorías y marcas ficticias pero coherentes.
- Los nombres incluyen la forma farmacéutica para que sean únicos.

### Precios y costos

| Columna | Convención |
|---|---|
| `precio_compra` | Costo por **unidad base**; máximo 2 decimales |
| `costo_unitario_base` | Igual a `precio_compra`; máximo 2 decimales |
| `precio_venta` | Precio de venta por **unidad base**; máximo 2 decimales |
| `precio_presentacion` | Precio de venta por **presentación**; máximo 2 decimales. Se calculó como `precio_venta × factor × 0.90` |

Ejemplo Tableta:

```text
costo base        = S/ 0.21 por tableta
precio base       = S/ 0.52 por tableta
presentación      = Caja x 100
precio caja       = 0.52 × 100 × 0.90 = S/ 46.80
cantidad cargada  = 600 tabletas = 6 cajas
costo total línea = 600 × 0.21 = S/ 126.00
```

---

## 3. Precisión aceptada

- **Carga manual:** precios y costos con más de **2 decimales** se rechazan; cantidades/factores con más de **3 decimales** también.
- **CSV:** los precios/costos con más de 2 decimales se redondean a 2 y se informa una advertencia; las cantidades/factores con más de 3 se redondean a 3.
- El CSV y la carga manual comparten la misma precisión final, pero el CSV normaliza archivos heredados con decimales extra.
- Internamente el sistema puede calcular costos base con más precisión, pero los valores que se ingresan y validan se limitan a 2 o 3 decimales según corresponda.

---

## 4. Columnas del CSV

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

---

## 5. Cómo probar el flujo

1. Usar una base descartable:
   ```bash
   php artisan migrate:fresh --seed
   ```
2. Entrar al panel como `admin@sistema.com` / `admin123`.
3. Ir a **Inventario inicial → Nuevo inventario → Importar CSV**.
4. Subir `docs/ejemplo-inventario-inicial-100-productos.csv`.
5. Pulsar **Validar solamente**:
   - debe mostrar `100 líneas`, `100 productos nuevos`, `0 errores`.
6. Pulsar **Importar como borrador**:
   - se crea `INI-00000001` en estado **Borrador**;
   - los 100 productos existen con `stock = 0`.
7. Abrir el detalle y pulsar **Confirmar inventario**:
   - se crean lotes, capas, stock y kardex `INGRESO`.
8. Verificar:
   ```bash
   php artisan inventario:conciliar --dry-run
   ```
   Debe reportar `0` diferencias.
9. Revisar en **Lotes y vencimientos** que aparezcan los 100 lotes.
10. Revisar **Kardex** con referencia `InventarioInicial`.

---

## 6. Advertencias

- Son datos **ficticios** para pruebas; no usarlos en producción.
- Si se importa sobre una base que ya tiene productos con el mismo nombre y unidad, el sistema puede intentar reutilizarlos y bloquear la carga si ya tienen stock o lotes.
- El CSV carga cantidades en **unidad base**. La carga manual sí permite elegir caja, blíster u otra presentación.
- Las presentaciones adicionales del CSV usan nombres existentes: `Caja`, `Frasco`, `Tubo`.
- Las fechas de vencimiento son futuras a la fecha de generación. Si se prueba mucho después, ajustar el archivo.
- El costo total puede variar unos céntimos por redondeo de presentación; la previsualización es la referencia real.
