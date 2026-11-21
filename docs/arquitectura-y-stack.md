# Arquitectura y Stack — Botica

> **Última actualización:** 18/11/2026
> **Estado verificado contra:** código base `ade7c19` más los ajustes de esta revisión, `composer.json`, `package.json`, `vite.config.js`, migraciones y `php artisan route:list` (185 rutas registradas).
>
> Este documento describe el sistema **tal como está implementado hoy**. La lógica vigente de lotes/FEFO está en [`plan-lotes-fefo-v2.md`](plan-lotes-fefo-v2.md); los planes de lotes anteriores son históricos.

---

## 1. Resumen

Sistema de gestión para botica/farmacia con dos áreas sobre un mismo Laravel:

| Área | Acceso | Rutas | Controladores |
|---|---|---|---|
| **Pública / tienda** | Sin autenticación (el carrito y el perfil requieren `clientes`) | `routes/web.php` | `App\Http\Controllers\Public` |
| **Panel administrativo** | Sesión de administrador + permisos Spatie | `routes/admin.php` | `App\Http\Controllers\Admin` |

A esto se suman dos endpoints JSON para consultas externas de identidad:

| Endpoint | Uso |
|---|---|
| `POST /api/reniec/consultar` | Consulta DNI (RENIEC) |
| `POST /api/sunat/consultar` | Consulta RUC (SUNAT) |

---

## 2. Stack tecnológico

| Tecnología | Versión / detalle | Uso |
|---|---|---|
| PHP | 8.2.12 | Backend |
| Laravel | 12.60.2 | Framework |
| Blade | — | Motor de vistas |
| Tailwind CSS | v4 (`@tailwindcss/vite`) | Estilos utilitarios |
| Vite | 7.x | Bundler de assets |
| JavaScript | Vanilla JS (ES Modules) + Axios 1.11 | SPA administrativa e interacciones públicas |
| MySQL | 8.x (local/producción) | Base de datos |
| SQLite | En memoria | Solo tests (`phpunit.xml`) |
| Spatie Laravel Permission | 6.25 | Roles y permisos |
| barryvdh/laravel-dompdf | — | PDF de carrito y reportes |
| cloudinary-labs/cloudinary-laravel | 3.0 | Almacenamiento de imágenes |
| Chart.js | 4.5 | Gráficos del dashboard |
| SortableJS | 1.15 | Reordenamiento drag-and-drop (promociones, textos del header) |
| Leaflet | 1.9 | Mapa del footer público |

> El `.env.example` todavía trae valores de plantilla (`APP_NAME=Laravel`, `DB_CONNECTION=sqlite`). El entorno local real usa MySQL (`DB_CONNECTION=mysql`, `DB_DATABASE=botica`). Ver "Deuda técnica" al final.

---

## 3. Estructura real del proyecto

```
app/
├── Helpers/
│   ├── CantidadHelper.php            # Conversión y comparación decimal con bcmath
│   └── KardexHelper.php              # Registro/eliminación de movimientos de kardex
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php        # Login/logout del panel (guard web)
│   │   ├── Admin/                    # 27 controladores del panel
│   │   │   └── Traits/
│   │   │       ├── Audita.php        # Helpers de auditoría
│   │   │       └── CacheaPanel.php   # NO-OP intencional (ver §9)
│   │   ├── Api/                      # RENIEC / SUNAT (JSON)
│   │   └── Public/                   # Tienda, carrito, registro, perfil
│   ├── Requests/
│   │   ├── Admin/                    # Validación de módulos del panel
│   │   └── Public/                   # Registro y perfil de cliente
│   └── (sin middleware propio; alias Spatie en bootstrap/app.php)
├── Models/                           # 33 modelos Eloquent
├── Providers/AppServiceProvider.php  # Morph map, route model bindings, view composer
└── Services/
    ├── CloudinaryService.php         # upload / delete / carpetas
    ├── ReniecService.php             # DNI con caché y reintentos
    ├── SunatService.php              # RUC con caché y reintentos
    ├── InventarioService.php         # Consumo/ingreso de stock con locks y kardex
    ├── CompraService.php             # Borrador, recepción, pago y anulación de compras
    ├── DevolucionProveedorService.php # Devolución por capa de lote y reembolso
    ├── AjusteInventarioService.php   # Ajustes por lote: conteo, daño, vencimiento, etc.
    ├── ReporteService.php            # Datos de los 30 reportes PDF/CSV
    └── ReporteCsvService.php         # Exportación CSV

config/
├── cloudinary.php                    # Mapa de carpetas (banners, productos; default general)
├── permission.php                    # Spatie
└── services.php                      # whatsapp, reniec, sunat

database/
├── migrations/                       # 51 migraciones
└── seeders/
    ├── PermissionSeeder.php
    ├── RoleSeeder.php
    ├── TablasBaseSeeder.php          # Datos demo
    └── DatabaseSeeder.php

resources/
├── css/app.css                       # Tailwind v4 + Leaflet CSS
├── js/
│   ├── app.js                        # Entrypoint admin: router + modal + toast + toggle
│   ├── bootstrap.js                  # Axios global + CSRF
│   ├── admin/                        # Módulos JS del panel
│   └── public/                       # Catálogo, carrito, galería, footer, etc.
└── views/
    ├── layouts/{admin,public}.blade.php
    ├── admin/<modulo>/               # index (partial) + full (layout) + create/edit/show
    ├── public/                       # Tienda, carrito, combos, perfil, auth
    └── components/                   # Product card, sliders, footer, WhatsApp, etc.

routes/
├── web.php                           # Tienda, auth de clientes, carrito, perfil
├── admin.php                         # Panel (auth + permission:<modulo>)
├── api.php                           # RENIEC/SUNAT con throttle:60,1
└── console.php                       # Solo comando demo `inspire`
```

---

## 4. Autenticación y autorización

### 4.1. Dos guards

| Guard | Provider / modelo | Tabla | Uso |
|---|---|---|---|
| `web` | `App\Models\User` | `users` | Administradores |
| `clientes` | `App\Models\Cliente` | `cliente` | Clientes de la tienda |

- El login administrativo valida además `activo = true`.
- El login de clientes registra `ultimo_login`.
- Las sesiones de ambos guards son independientes.

### 4.2. Permisos

Seeder de permisos (`PermissionSeeder`):

**Módulos (22):** `dashboard`, `productos`, `categorias`, `marcas`, `unidades-medida`, `presentaciones`, `proveedores`, `clientes`, `ventas`, `servicios`, `atenciones`, `ingresos`, `devoluciones`, `ajustes`, `kardex`, `lotes`, `usuarios`, `roles`, `caja`, `sliders`, `auditoria`, `reportes`.

**Permisos de portada / extras (5):** `portada.banners`, `portada.header-text`, `portada.categorias`, `portada.promociones`, `combos`.

Cada ruta administrativa lleva `middleware('permission:<modulo>')`. Los permisos se asignan por rol; no hay permisos por acción (quien tiene el módulo puede ver, crear, editar y eliminar).

**Roles por defecto (`RoleSeeder`):**

| Rol | Permisos |
|---|---|
| **Administrador** | Todos los módulos + portada + combos |
| **Vendedor** | `ventas`, `atenciones` |

El rol **Administrador** no puede editarse ni eliminarse desde la interfaz. Un usuario no puede cambiarse su propio rol ni desactivarse a sí mismo.

---

## 5. Panel administrativo (SPA sobre Blade)

- `resources/views/layouts/admin.blade.php` es el **shell** y se carga una sola vez. Contiene sidebar, header, `#panel-content`, overlay de modal y `@stack('scripts')`.
- Cada módulo tiene:
  - `admin/<modulo>/index.blade.php` → **partial** (sin `@extends`) que se inyecta vía AJAX.
  - `admin/<modulo>/full.blade.php` → vista completa (`@extends('layouts.admin')` + `@include` del partial) para carga directa/F5.
- `AdminRouter` (`resources/js/admin/router.js`) intercepta `[data-panel]`, hace `GET` con Axios, reemplaza el HTML, re-ejecuta los `<script>` inyectados, actualiza el historial y marca el enlace activo.
- `AdminModal` (`resources/js/admin/modal.js`) gestiona `[data-modal]`, `[data-close-modal]`, `[data-delete]`, submit de formularios, errores 422 y recarga del panel tras el éxito.
- `AdminToast` muestra notificaciones flotantes.
- `toggle.js` maneja `[data-toggle-status]` con `PATCH` y recarga.
- `autocomplete.js` provee búsquedas con debounce de 300 ms.

Detalle completo en [`panel-administrativo.md`](panel-administrativo.md).

### 5.1. Módulos del panel

| Grupo del sidebar | Módulos |
|---|---|
| Información | Dashboard |
| Inventario | Productos, Combos, Categorías, Marcas, Unidades de medida, Presentaciones, Ingresos, Kardex |
| Clientes | Clientes |
| Operaciones | Ventas, Atenciones |
| Administración | Servicios, Proveedores, Usuarios, Roles, Auditoría, Reportes |
| Finanzas | Caja |
| Portada | Sliders, Textos del Header, Banners, Categorías en Portada, Promociones |

---

## 6. Modelo de datos

### 6.1. Tablas principales

| Tabla | PK | Propósito |
|---|---|---|
| `users` | `id` | Administradores |
| `cliente` | `id_cliente` | Clientes del negocio y de la web (`registro_web`) |
| `categoria` | `id_categoria` | Categorías |
| `marca` | `id_marca` | Marcas |
| `proveedor` | `id_proveedor` | Proveedores |
| `servicio` | `id_servicio` | Servicios facturables |
| `producto` | `id_producto` | Productos (`stock`, `inventario`, `stock_minimo`, unidad base, precios) |
| `producto_imagen` | `id_producto_imagen` | Hasta 4 imágenes por producto, con `orden` |
| `unidad_medida` | `id_unidad_medida` | Catálogo global de unidades base y su presentación base |
| `presentacion` | `id_presentacion` | Catálogo global de presentaciones (Caja, Blíster, Frasco, etc.) |
| `producto_presentacion` | `id_producto_presentacion` | Presentaciones asignadas a un producto con factor, precio y paso |
| `producto_lote` | `id` | Lote físico con correlativo autogenerado, código, vencimiento, estado y disponible base |
| `lote_ingreso` | `id` | Capa de recepción de un lote, con costo y disponible base |
| `lote_consumo` | `id` | Consumo exacto por venta/atención/baja |
| `ingreso` | `id_ingreso` | Compras/entradas (`ING-XXXXXXXX`) |
| `detalle_ingreso` | — | Productos y cantidades del ingreso |
| `venta` | `id_venta` | Ventas (`T001-XXXXXXXX`) |
| `detalle_venta` | — | Productos, cantidad y subtotal |
| `atencion` | `id_atencion` | Atenciones (productos y/o servicios) |
| `atencion_producto` / `atencion_servicio` | — | Detalle de atención |
| `caja` | `id_caja` | Turnos de caja (`abierta` / `cerrada`) |
| `caja_movimiento` | `id_movimiento` | Ingresos por venta/atención y egresos manuales |
| `kardex_movimientos` | `id` | Trazabilidad de stock (`INGRESO`/`VENTA`/`ATENCION`) |
| `auditoria` | `id_auditoria` | Bitácora de acciones administrativas |
| `banner` | `id_banner` | Carrusel de portada |
| `promocion` | `id_promocion` | Bloques promocionales (layout, descuento o combo) |
| `combo` / `combo_producto` | `id_combo` | Combos y productos que los componen |
| `header_text` | `id_header_text` | Textos del marquee superior |
| `portada_categoria` | `id_portada_categoria` | Categorías visibles en portada y su orden |
| `slider_producto` | `id_slider_producto` | Productos asignados a los 3 sliders |
| `carrito` / `carrito_item` | `id_carrito` | Carrito persistente de clientes web |
| `permissions`, `roles`, `model_has_*` | — | Spatie |

### 6.2. Convenciones

- Tablas y PK en español con nombres singulares (`producto`, `categoria`, `venta`).
- Producto usa `id_producto`; venta `id_venta`; atención `id_atencion`; etc.
- `Producto` no usa timestamps.
- El carrito usa relación polimórfica `item_type` (`producto`/`combo`) mapeada en `AppServiceProvider` con `Relation::morphMap`.

---

## 7. Lógica de stock, unidades, lotes y kardex

El stock se controla en la **unidad base** del producto, puede venderse por presentaciones globales y se descuenta por **lotes con FEFO**.

```
Producto: Ibuprofeno 400mg
  unidad_base: TAB
  stock: 250.000 TAB
  presentaciones:
    Caja x 100     factor 100   precio S/ 50.00   paso 1
    Blíster x 10  factor 10    precio S/ 6.00    paso 1
    Tableta       factor 1     precio S/ 0.80    paso 0.5

Lotes:
  A   vence 10/12/2026   disponible 60 TAB
  B   vence 10/03/2027   disponible 190 TAB

INGRESO    → crea/actualiza un lote y una capa `lote_ingreso`
             stock += cantidad * factor
             + kardex INGRESO con lote

VENTA      → FEFO consume primero A y luego B
             stock -= cantidad * factor
             + lote_consumo por capa
             + kardex VENTA por capa

ATENCIÓN   → igual que venta, con referencia Atencion

EDICIÓN    → se revierte el consumo exacto de las capas, se eliminan sus
             consumos/kardex y se vuelve a consumir con FEFO.

ELIMINACIÓN→ se restauran las capas consumidas y el stock.

BAJA       → lote vencido/bloqueado se retira con kardex BAJA
```

- `stock`, `inventario` y `stock_minimo` son `decimal(12,3)`. `inventario` es legado: se conserva en BD y se sincroniza con `stock`, pero no se usa en formularios, reportes ni alertas.
- **Precisión de entrada:** precios y costos (`precio_compra`, `precio_venta`, `costo_unitario`, `monto`, `descuento`) aceptan máximo **2 decimales**; cantidades, factores, pasos y `stock_minimo` aceptan máximo **3 decimales**. Se valida en servidor con `decimal:0,2` / `decimal:0,3` y en el navegador con `step="0.01"` / `step="0.001"`. Los cálculos internos pueden usar 4 decimales con `CantidadHelper`.
- `producto_lote.cantidad_disponible_base` es la fuente real; `producto.stock` es una caché que debe coincidir.
- `lote_ingreso` guarda la cantidad inicial/disponible por recepción y el costo.
- `lote_consumo` registra qué capa consumió cada venta/atención/baja.
- `InventarioService` bloquea producto/lotes con `lockForUpdate()` y usa `CantidadHelper` (bcmath).
- `CompraService` maneja el ciclo `borrador → recibida → anulada`: el borrador no mueve stock; la recepción crea lotes/capas, stock, kardex y pago opcional; la anulación revierte todo.
- `DevolucionProveedorService` descuenta una **capa específica** de lote (no FEFO), registra kardex `DEVOLUCION`, reembolso opcional a caja y restaura todo al anular.
- `AjusteInventarioService` corrige stock por lote en unidad base, registra kardex `AJUSTE` y es inmutable: no se edita, elimina ni anula.
- Los lotes vencidos o en estado `baja` **no se venden**.
- El alta de producto nace con stock 0 y no crea `SIN-LOTE`. `InventarioService::validarStockContraLotes()` bloquea compras/ventas/atenciones si `producto.stock` no coincide con la suma de lotes. Los `SIN-LOTE` heredados se auditan con `inventario:sin-lote` y se regularizan manualmente.
- `kardex_movimientos` registra lote, vencimiento y cantidad base. Es **append-only**: las reversiones se registran como `REVERSION` y nunca se eliminan movimientos.

---

## 8. Integraciones y servicios externos

| Servicio | Clase / configuración | Notas |
|---|---|---|
| **Cloudinary** | `App\Services\CloudinaryService` + `config/cloudinary.php` | Carpetas configuradas: `banners`, `productos`; carpeta por defecto `general`. Subida con `secure_url` + `public_id`; al reemplazar/eliminar se borra el asset. |
| **RENIEC** | `App\Services\ReniecService` + `App\Services\JsonPe\JsonPeClient` | **Proveedor json.pe**. DNI de 8 dígitos, timeout 8 s, 2 reintentos, caché positiva 60 min y negativa 10 min. Los formularios verifican primero la BD local (`/admin/clientes/verificar-dni` o `/api/reniec/verificar`) y solo entonces habilitan la API. `ApiExternaGuard` impide llamadas desde migraciones/seeders. Usado en clientes, ventas y atenciones. |
| **SUNAT** | `App\Services\SunatService` + `App\Services\JsonPe\JsonPeClient` | **Proveedor json.pe**. RUC de 11 dígitos, timeout 8 s, 2 reintentos, caché positiva 60 min y negativa 10 min. Verificación local previa (`/admin/proveedores/verificar-ruc` o `/api/sunat/verificar`). `estado` texto se normaliza a 0/1. Usado en proveedores. |
| **WhatsApp** | `config/services.php` → `WHATSAPP_PHONE_NUMBER` | Botón de consulta en producto/combo y cotización del carrito (`wa.me`). Si no hay número, el botón no se renderiza. |
| **DomPDF** | `barryvdh/laravel-dompdf` | Carrito PDF y los 30 reportes administrativos (también exportables a CSV). |

Variables relevantes: `WHATSAPP_PHONE_NUMBER`, `JSONPE_API_TOKEN`, `JSONPE_API_URL`, y las credenciales de Cloudinary. Las APIs externas nunca se consumen desde migraciones ni seeders (`ApiExternaGuard` + test de regresión).

**Zona horaria:** la aplicación usa `APP_TIMEZONE=America/Lima` (UTC-5, sin horario de verano) y la conexión MySQL/MariaDB fija `DB_TIMEZONE=-05:00`. Así, `now()`, los `created_at`/`updated_at` de Eloquent y los `CURRENT_TIMESTAMP` de la base guardan y muestran la hora real del Perú. Los registros creados antes de este ajuste quedaron almacenados con el desfase anterior (UTC) y solo se corrigen si se normalizan una vez.

---

## 9. Rendimiento y caché

- `App\Http\Controllers\Admin\Traits\CacheaPanel` es un **no-op intencional**: `getIndex()` ejecuta el callback y `olvidaIndex()` no hace nada. No se cachea HTML del panel.
- Motivo y análisis: [`cache-panel-administrativo.md`](cache-panel-administrativo.md). **No reintroducir** caché de vistas sin una estrategia por usuario e invalidación por eventos.
- `CACHE_STORE=database` en local; los tests usan `array`.
- El panel hace consultas Eloquent simples; no hay capa de caché de datos.

---

## 10. Frontend y Vite

### 10.1. Regla de oro

Todo archivo JS que se cargue con `@vite('resources/js/...')` **debe estar registrado** en el arreglo `input` de `vite.config.js`; de lo contrario el build de producción no genera la entrada y la vista falla al resolver el manifest.

- Vista con `@extends` → `@push('scripts') @vite(...) @endpush`.
- Vista partial cargada por AJAX → `@vite(...)` directo (el router/modal re-ejecuta los scripts).

### 10.2. Entradas registradas actualmente

El `input` de Vite incluye: `app.css`, `app.js`, los módulos admin de categorías, marcas, proveedores (index y create), clientes (index y create), servicios, atenciones, productos, ventas, sliders, banners, portada-categorías, promociones, combos, ingresos, devoluciones, ajustes, dashboard, caja, auditoría, reportes, header-text y sidebar; y los públicos de catálogo, sliders de productos, productos, menú, búsqueda del navbar, footer, header-text, galería, carrito y registro.

### 10.3. Dependencias JS destacadas

- **Chart.js**: dashboard (ingresos semanales, distribución mensual, top productos).
- **SortableJS**: reordenamiento de promociones y textos del header.
- **Leaflet**: mapa del footer público.
- **Axios**: todas las llamadas AJAX; `bootstrap.js` configura `X-Requested-With` y `X-CSRF-TOKEN`.

### 10.4. Flujo para una nueva funcionalidad

1. Crear migración y modelo Eloquent.
2. Crear controlador en `Admin/` o `Public/` según el área.
3. Crear Form Request para validación si aplica.
4. Registrar rutas en `routes/admin.php` o `routes/web.php` con el middleware `permission:<modulo>`.
5. Crear `index.blade.php` (partial) y `full.blade.php` (layout); formularios `create`/`edit` si hay CRUD.
6. Crear el JS del módulo y **registrarlo en `vite.config.js`**.
7. Añadir el permiso en `PermissionSeeder` y asignarlo al rol correspondiente.
8. Actualizar la documentación del módulo y este documento si cambia la arquitectura.

### 10.5. Diseño responsive

El desarrollo es **mobile-first**, tomando como referencia iPhone 14 Pro Max (430 × 932 px):

| Breakpoint | Min-width | Target |
|---|---|---|
| base | 0 | Móviles |
| `sm` | 640 px | Tablets pequeñas |
| `md` | 768 px | Tablets |
| `lg` | 1024 px | Laptops / desktop |
| `xl` | 1280 px | Desktop grande |
| `2xl` | 1536 px | Ultra-wide |

Reglas prácticas:

- Escribir primero el estilo móvil y añadir variantes `sm:`, `md:`, `lg:`.
- Evitar scroll horizontal; imágenes con `object-cover` y alturas responsive.
- Sidebar y menús colapsables en móvil; filtros del catálogo en panel deslizante.
- Objetivos táctiles de al menos 44 × 44 px.
- Probar en 430 px, 640 px, 768 px y 1024 px antes de cerrar un cambio.

---

## 11. Testing

- Framework: PHPUnit 11.5.
- `phpunit.xml` fuerza: SQLite `:memory:`, caché/sesión `array`, cola `sync`, mail `array`.
- La mayoría de los tests de feature usa `RefreshDatabase` y crean permisos manualmente en `setUp()` (los seeders **no** se ejecutan); llamar `PermissionRegistrar::forgetCachedPermissions()` al crear permisos.
- Las llamadas externas se mockean con `Http::fake()`.

**Estado actual (18/11/2026):**

```
php artisan test --compact
Tests: 122 passed (423 assertions)
```

---

## 12. Comandos

```bash
# Instalación completa (deps, .env, key, migrate, npm, build)
composer run setup

# Desarrollo: servidor + queue worker + logs + Vite
composer run dev

# Assets de producción
npm run build

# Migraciones + datos demo
php artisan migrate:fresh --seed

# Tests
composer run test
php artisan test --filter=nombre_del_test
php artisan test tests/Feature/CajaTest.php

# Formato
./vendor/bin/pint
```

> `composer run dev` requiere `QUEUE_CONNECTION=database` y **no** ejecuta migraciones.

---

## 13. Deuda técnica y brechas conocidas

Detectadas al verificar el estado actual. No bloquean el análisis funcional, pero conviene atenderlas:

1. **`.env.example` desalineado.** Apunta a SQLite y `APP_NAME=Laravel`; no documenta credenciales de Cloudinary. El entorno real es MySQL.

2. **Documentos históricos.** `plan-kardex.md`, `plan-control-vencimientos-lote.md`, `kardex.txt`, `plan-eliminacion-logica-lotes.md` y parte de `modulo-reportes.md` describen la lógica anterior (lotes eliminados o reporte de vencimientos inexistente). La lógica vigente de lotes/FEFO está en [`plan-lotes-fefo-v2.md`](plan-lotes-fefo-v2.md).

3. **Criterio de visibilidad del catálogo.** `Producto::visible()` filtra únicamente `estado = 1`. `modulo-catalogo-publico.md` describe un criterio más amplio (`estado = 1` **o** `stock > 0`) que no coincide con el código actual.

4. **Login del rol Vendedor.** `AuthController` redirige siempre a `/admin/dashboard`, pero el rol Vendedor solo tiene `ventas` y `atenciones`; por tanto, un vendedor que inicia sesión sin una URL previa recibe 403. Corregir con un redirect según permisos o asignando `dashboard` al rol.

5. **Sidebar no filtra todos los enlaces por permiso.** Varios enlaces (Productos, Categorías, Marcas, Ingresos, Clientes, Ventas, Atenciones, Servicios, Caja, Dashboard) son visibles para cualquier usuario autenticado, aunque la ruta responda 403 si falta el permiso. Envolverlos en `@can(...)` o generar el menú desde los permisos.

6. **Combos sin presentación.** `combo_producto` ya admite `producto_presentacion_id`, pero el admin y el detalle público de combos siguen usando la presentación base. Pendiente de la fase 6.

7. **Código de barras por presentación.** La columna `producto_presentacion.codigo_barras` existe, pero no hay lector ni búsqueda por código.

8. **Migraciones reescritas.** Como no había datos productivos, se modificaron migraciones originales. En cualquier entorno con datos hay que ejecutar `php artisan migrate:fresh --seed` o preparar una migración aditiva con backfill.

9. **Lotes: adopción y datos.** D2 eliminó la creación automática de `SIN-LOTE` y bloquea operaciones si el stock no cuadra con los lotes. Las existencias se cargan con **Inventario inicial**. Los `SIN-LOTE` heredados se listan con `php artisan inventario:sin-lote` y se regularizan manualmente. Queda pendiente eliminar la columna legado `producto.inventario` y los reportes de costo/margen por lote.

---

## 14. Documentación relacionada

| Documento | Contenido |
|---|---|
| [`despliegue-vps.md`](despliegue-vps.md) | Requisitos de producción, Nginx/PHP-FPM/MySQL, `.env`, backups y checklist |
| [`funcionalidades-del-sistema.md`](funcionalidades-del-sistema.md) | Funcionalidades actuales del sitio público y del panel |
| [`flujo-inventario-lotes-fefo.md`](flujo-inventario-lotes-fefo.md) | Flujo operativo de producto, ingreso, lotes, FEFO y venta fraccionada |
| [`panel-administrativo.md`](panel-administrativo.md) | Arquitectura SPA, router, modales, toasts, Vite |
| [`cache-panel-administrativo.md`](cache-panel-administrativo.md) | Decisión de eliminar el caché del panel |
| [`flujo-cloudinary-productos.md`](flujo-cloudinary-productos.md) | Subida y borrado de imágenes |
| [`modulo-reportes.md`](modulo-reportes.md) | Reportes PDF/CSV: 30 tipos, filtros y pruebas |
| [`plan-auditoria.md`](plan-auditoria.md) | Diseño del módulo de auditoría |
| [`plan-venta-fraccionada.md`](plan-venta-fraccionada.md) | Plan propuesto de venta fraccionada y presentaciones |
| [`plan-lotes-fefo-v2.md`](plan-lotes-fefo-v2.md) | Rediseño implementado de lotes/vencimientos/FEFO |
| [`plan-eliminacion-logica-lotes.md`](plan-eliminacion-logica-lotes.md) | Decisión histórica de eliminar lotes (reemplazada por v2) |
| [`modulo-*.md`](.) | Especificaciones por módulo |

---

*Documento de referencia para mantener consistencia arquitectónica en el proyecto.*
