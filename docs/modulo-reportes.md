# Módulo de Reportes PDF

## Descripción

Módulo de administración que permite generar reportes en PDF o CSV con estilo corporativo. Cubre 30 tipos: documentos operativos (ventas, atenciones, compras, caja, kardex, movimientos), reportes de gestión (ventas por producto, diario, vendedor, anulaciones, compras por proveedor, costos, devoluciones, merma, rotación, margen, servicios, clientes RFM, caja por origen) y reportes consolidados (resumen gerencial, auditoría, cobros por método).

## Stack técnico

- **Backend**: Laravel 12 + PHP 8.2+
- **PDF Engine**: barryvdh/laravel-dompdf (HTML + CSS → PDF)
- **Frontend**: Blade + Tailwind CSS v4 + JavaScript vanilla (SPA)
- **Permisos**: Spatie Laravel Permission (`reportes`)

## Estructura de archivos

```
app/
├── Http/Controllers/Admin/
│   └── ReporteController.php
├── Http/Requests/Admin/
│   └── ReporteRequest.php
└── Services/
    ├── ReporteService.php
    └── ReporteCsvService.php

resources/
├── views/admin/reportes/
│   ├── full.blade.php
│   ├── index.blade.php
│   └── pdf/
│       ├── partials/
│       │   ├── negocio.blade.php
│       │   ├── paginacion.blade.php
│       │   └── reporte-tabla.blade.php
│       ├── ventas.blade.php
│       ├── atenciones.blade.php
│       ├── ingresos.blade.php
│       ├── caja.blade.php
│       ├── inventario.blade.php
│       ├── lotes.blade.php
│       ├── kardex.blade.php
│       ├── movimientos.blade.php
│       ├── rentabilidad.blade.php
│       ├── clientes.blade.php
│       ├── pagos.blade.php
│       ├── ventas_producto.blade.php
│       ├── ventas_diario.blade.php
│       ├── ventas_vendedor.blade.php
│       ├── anulaciones.blade.php
│       ├── compras_proveedor.blade.php
│       ├── costos_producto.blade.php
│       ├── devoluciones_proveedor.blade.php
│       ├── merma.blade.php
│       ├── rotacion.blade.php
│       ├── margen_agrupado.blade.php
│       ├── servicios.blade.php
│       ├── clientes_rfm.blade.php
│       ├── clientes_web.blade.php
│       ├── cumpleanos.blade.php
│       ├── sugerido_compra.blade.php
│       ├── caja_origen.blade.php
│       ├── resumen_gerencial.blade.php
│       ├── general.blade.php
│       └── auditoria.blade.php
└── js/admin/reportes/
    └── index.js

routes/
└── admin.php  (rutas del módulo)
```

## Permisos

Se agrega el permiso `reportes` al sistema. Asignado por defecto al rol **Administrador**.

## Rutas

| Método | URL | Middleware | Método Controller | Descripción |
|--------|-----|------------|-------------------|-------------|
| GET | `/admin/reportes` | `permission:reportes` | `index` | Panel con selector de tipo y filtros |
| GET | `/admin/reportes/generate` | `permission:reportes` | `generate` | Descarga PDF |
| GET | `/admin/reportes/preview` | `permission:reportes` | `preview` | Vista previa HTML |

## Reportes disponibles

| # | Tipo | Datos | Filtros |
|---|------|-------|---------|
| 1 | **Ventas** | Ventas por período con cliente, productos, montos, total general | Fecha, usuario, cliente |
| 2 | **Atenciones** | Atenciones con cliente, productos, servicios, totales | Fecha, usuario, cliente |
| 3 | **Compras** | Compras recibidas, devoluciones y anuladas; total bruto y neto | Fecha, proveedor, usuario, forma de pago |
| 4 | **Caja** | Resumen consolidado y detalle por caja; monto final solo en cerradas | Caja o fecha |
| 5 | **Inventario** | Stock actual valorizado a costo real de capas, con comparativo a precio de compra | Categoría, marca, umbral, activos/inactivos |
| 6 | **Lotes y costos** | Lotes con stock, costo promedio, valor, vencimiento y valor en riesgo | Categoría, marca, vencidos o próximos 30/90/180/365 días |
| 7 | **Kardex** | Movimientos valorizados de un producto | Producto, fecha, tipo real (`INGRESO`, `VENTA`, etc.) |
| 8 | **Movimientos especiales** | `REVERSION`, `AJUSTE`, `BAJA`, `DEVOLUCION`, `INGRESO`, `VENTA`, `ATENCION` | Fecha, tipo, producto, usuario |
| 9 | **Rentabilidad** | Ingresos, costo y margen por producto (ventas + atenciones) | Fecha, categoría, marca |
| 10 | **Clientes** | Top clientes por volumen real (sin duplicar ventas y atenciones) | Fecha, límite |
| 11 | **General** | Resumen consolidado con compras valorizadas y separación ventas/caja | Mes/Año |
| 12 | **Auditoría** | Actividad de usuarios con aviso de truncamiento y filtro por módulo/acción | Módulo, usuario, acción, fecha |
| 13 | **Cobros por método de pago** | Desglose de ventas y atenciones por método, incluidos pagos mixtos | Fecha, método, origen |
| 14 | **Ventas por producto** | Unidades, ingresos, costo real, margen y participación por producto/presentación | Fecha, categoría, marca |
| 15 | **Resumen diario** | Tickets, unidades, ingresos, costo, margen y ticket promedio por día | Fecha, usuario |
| 16 | **Ventas por vendedor y hora** | Tickets, ingresos, margen y ticket promedio por usuario y hora | Fecha |
| 17 | **Anulaciones** | Ventas anuladas, monto, motivo y usuario que anuló | Fecha de anulación, usuario |
| 18 | **Compras por proveedor** | Monto, frecuencia, ticket promedio, participación y última compra | Fecha |
| 19 | **Evolución de costos** | Costo por compra, costo anterior y variación por producto/proveedor | Fecha, proveedor, producto |
| 20 | **Devoluciones a proveedor** | Motivo, líneas, monto, reembolso y usuario | Fecha, proveedor |
| 21 | **Ajustes y merma** | Documentos, líneas, productos afectados y costo por tipo de ajuste | Fecha, tipo |
| 22 | **Rotación y cobertura** | Ventas de la ventana, stock, días de cobertura y clasificación | Categoría, marca, ventana 30/60/90/180/365 |
| 23 | **Margen agrupado** | Ingresos, costo, margen y participación por categoría, marca o vendedor | Fecha, agrupación |
| 24 | **Servicios** | Cantidad, atenciones, ingresos, ticket promedio y participación | Fecha |
| 25 | **Clientes RFM** | Última compra, días sin comprar, frecuencia, monto, ticket y segmento | Fecha, segmento, límite |
| 26 | **Caja por origen** | Ingresos, egresos, neto y movimientos por categoría de caja | Fecha |
| 27 | **Resumen gerencial** | Ingreso total, margen, compras, merma, caja, ticket, clientes, top productos y alertas | Fecha |
| 28 | **Clientes nuevos y adopción web** | Altas del período, origen web/presencial y último login | Fecha |
| 29 | **Sugerido de compra** | Consumo, stock, mínimo, máximo, lead time y cantidad sugerida | Categoría, marca, ventana de consumo |
| 30 | **Cumpleaños** | Clientes con cumpleaños próximos, contacto y edad que cumplen | Próximos 7/30/60/90 días |

> **Actualización 20/11/2026:** el sistema incorporó métodos de pago dinámicos y el desglose `pago` por venta/atención. El reporte 13 lo explota; los reportes 14 a 27 usan la misma lógica de costo real y pagos por método.

## Flujo de uso

1. Usuario ingresa a `/admin/reportes`
2. Selecciona tipo de reporte en el dropdown
3. Aparecen filtros contextuales según el tipo
4. Puede previsualizar en HTML o descargar PDF
5. El PDF se genera con Dompdf usando plantillas Blade

## Diseño del PDF

- **Header**: Título del reporte, período, fecha de emisión
- **Cuerpo**: Tablas con bordes, colores corporativos (emerald), datos agrupados, subtotales
- **Footer**: Paginación, usuario generador
- **Estilo**: Fuente sans-serif, 10pt, colores #065f46 (emerald-800) para encabezados

## Reportes de la fase 7

### Rentabilidad por producto

- Combina líneas de ventas y atenciones.
- Calcula por producto:
  - cantidad base vendida;
  - ingresos;
  - costo real por lote;
  - margen y margen porcentual.
- Filtros: fechas, categoría y marca.
- Resumen: total ingresos, total costo y total margen.
- Los productos con margen negativo se resaltan en rojo.

### Lotes y costos

- Lista los lotes con stock disponible.
- Muestra producto, lote, vencimiento, días restantes, estado, disponible, costo promedio y valor.
- Filtros: categoría, marca y próximos `30/90/180/365` días.
- Resumen: cantidad de lotes, disponible total y valor total.
- Reemplaza al antiguo reporte de vencimientos, que no existía en el código.

### Movimientos especiales

- Lista movimientos de kardex por período.
- Tipos disponibles: `REVERSION`, `AJUSTE`, `BAJA`, `DEVOLUCION`, `INGRESO`, `VENTA`, `ATENCION`.
- Filtros: fecha, tipo, producto y usuario.
- Resumen: cantidad de movimientos, entradas y salidas.
- Sirve para auditar reversiones, ajustes y devoluciones.

### Advertencia de costo mayor al precio de venta

En el formulario de compra, cada línea compara el costo ingresado con el precio de venta de la presentación. Si el costo es mayor, muestra una advertencia en ámbar, pero no bloquea el registro.

## Pruebas automatizadas

Archivo:

```text
tests/Feature/ReporteAvanzadoTest.php
```

Cubre:

1. Rentabilidad con ventas y atenciones.
2. Filtro por categoría.
3. Lotes: valor, costo promedio y filtro por vencimiento.
4. Movimientos: filtros por `DEVOLUCION` y `AJUSTE`.
5. Vistas previas de los tres reportes.
6. Hook de advertencia de costo en el formulario de compra.

Comando:

```bash
php artisan test tests/Feature/ReporteAvanzadoTest.php
```

Resultado esperado: **6 pruebas**.

Además, `tests/Feature/ReporteMejorasTest.php` cubre:

1. Clientes sin duplicar montos.
2. Valorización de inventario por capas.
3. Filtro de stock 0.
4. Lotes vencidos.
5. Cobros por método y exclusión de ventas anuladas.
6. Validación de tipos y del filtro de kardex.
7. Generación de PDF y CSV.

Comando:

```bash
php artisan test tests/Feature/ReporteMejorasTest.php
```

`tests/Feature/ReporteGestionTest.php` cubre los reportes de gestión: ventas por producto, diario, vendedor, compras por proveedor, rotación, merma, caja por origen, clientes RFM, márgenes, servicios, resumen gerencial, PDF y CSV.

## Pruebas manuales

### Rentabilidad

1. Vender productos y registrar una atención con productos.
2. Ir a **Reportes → Rentabilidad**.
3. Elegir el período y generar la vista previa.
4. Verificar ingresos, costo, margen y margen %.
5. Filtrar por categoría o marca.

### Lotes y costos

1. Ir a **Reportes → Lotes y costos**.
2. Verificar el valor total y el costo promedio.
3. Probar el filtro de vencimiento a 30/90/180 días.

### Movimientos especiales

1. Anular una compra, registrar una devolución o un ajuste.
2. Ir a **Reportes → Movimientos especiales**.
3. Filtrar por `REVERSION`, `AJUSTE` o `DEVOLUCION`.
4. Verificar entradas, salidas y referencias.

### Advertencia de costo

1. Crear una compra.
2. Elegir una presentación e ingresar un costo mayor al precio de venta.
3. Verificar la advertencia en ámbar.
4. Confirmar que la compra se puede guardar igual.

## Consumo de stock

Este módulo es **solo de lectura/consulta**. No modifica ningún dato del sistema.

## Mejoras implementadas (20/11/2026)

- **Clientes:** se corrigió el producto cartesiano entre ventas y atenciones; el total ya no se duplica.
- **Kardex:** el filtro usa los tipos reales (`INGRESO`, `VENTA`, `ATENCION`, `AJUSTE`, `BAJA`, `DEVOLUCION`, `REVERSION`).
- **Inventario:** valorización a costo real de capas, comparativo con precio de compra y filtro de activos/inactivos.
- **Lotes:** opción "Vencidos con stock" y tarjetas de valor vencido y riesgo a 90 días.
- **Movimientos:** tarjetas por cantidad de movimientos y valor movido; se agregaron costo unitario y valor por fila.
- **Kardex:** se agregaron costo unitario y valor por movimiento.
- **Compras:** totales, compras netas (menos devoluciones y anuladas) y detalle de devoluciones/anuladas.
- **Caja:** resumen consolidado del período y "—" para cajas abiertas.
- **General:** se separó "ingreso por ventas + atenciones" de "movimientos de caja" y se agregó el monto de compras.
- **Auditoría:** filtro por módulo, acciones reales y aviso cuando se muestran solo 500 registros.
- **Cobros por método de pago:** nuevo reporte 13.
- **Reportes de gestión (14 a 30):** ventas por producto, diario, vendedor/hora, anulaciones, compras por proveedor, evolución de costos, devoluciones, merma, rotación, margen agrupado, servicios con costo/margen, clientes RFM, clientes nuevos/web, caja por origen, resumen gerencial, sugerido de compra y cumpleaños.
- **Formato:** PDF y CSV; encabezado con identidad del negocio; numeración de páginas; vista previa aislada en iframe.
- **Validación:** lista blanca de tipos, fechas, mes, año, IDs y rango máximo de 2 años.
- **Pruebas:** `tests/Feature/ReporteMejorasTest.php`.

## Análisis y evolución

El diagnóstico completo, los hallazgos de cálculo y la propuesta de nuevos reportes están en [`analisis-reportes-y-recomendaciones.md`](analisis-reportes-y-recomendaciones.md). El estado de implementación está en [`roadmap-mejora-reportes.md`](roadmap-mejora-reportes.md).
