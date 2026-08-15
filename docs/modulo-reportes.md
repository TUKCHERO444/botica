# Módulo de Reportes PDF

## Descripción

Módulo de administración que permite generar reportes en PDF con estilo corporativo, abarcando las 10 áreas clave del sistema: ventas, atenciones, ingresos, caja, inventario, kardex, vencimientos, clientes, reporte general consolidado y auditoría.

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
└── Services/
    └── ReporteService.php

resources/
├── views/admin/reportes/
│   ├── full.blade.php
│   ├── index.blade.php
│   └── pdf/
│       ├── ventas.blade.php
│       ├── atenciones.blade.php
│       ├── ingresos.blade.php
│       ├── caja.blade.php
│       ├── inventario.blade.php
│       ├── kardex.blade.php
│       ├── vencimientos.blade.php
│       ├── clientes.blade.php
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
| 3 | **Ingresos** | Compras a proveedores, productos recibidos, lotes | Fecha, proveedor, usuario |
| 4 | **Caja** | Resumen de cierre: inicial, ingresos, egresos, movimientos | Caja o fecha |
| 5 | **Inventario** | Stock actual valorizado, bajo stock, por categoría/marca | Categoría, marca, umbral |
| 6 | **Kardex** | Movimientos de un producto (entradas/salidas) | Producto, fecha, tipo |
| 7 | **Vencimientos** | Lotes vencidos, críticos, próximos y vigentes | Producto, rango días |
| 8 | **Clientes** | Top clientes por volumen de compras | Fecha, límite |
| 9 | **General** | Resumen consolidado del período | Mes/Año |
| 10 | **Auditoría** | Actividad de usuarios por módulo | Módulo, usuario, acción, fecha |

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

## Consumo de stock

Este módulo es **solo de lectura/consulta**. No modifica ningún dato del sistema.
