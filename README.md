# Botica — Sistema de Gestión Farmacéutica

Aplicación Laravel 12 con dos áreas: una **tienda pública** (catálogo, combos, carrito-cotización y perfil de cliente) y un **panel administrativo SPA** (inventario, compras, ventas, atenciones, caja, contenido de portada, auditoría y reportes PDF). Incluye **venta fraccionada** por unidades/presentaciones y **lotes con FEFO** (vencimientos, capas de recepción y bajas auditadas).

> Documentación funcional y técnica: [`docs/README.md`](docs/README.md)

---

## Stack

- PHP 8.2 · Laravel 12
- Blade + Tailwind CSS v4 + Vite 7
- JavaScript vanilla (ES Modules) + Axios
- MySQL 8 (desarrollo/producción) · SQLite en memoria para tests
- Spatie Laravel Permission (roles y permisos por módulo)
- Cloudinary (imágenes) · DomPDF (reportes) · Chart.js · Leaflet

---

## Puesta en marcha

```bash
composer run setup      # deps, .env, key, migrate, npm install, build
composer run dev        # servidor + queue worker + logs + Vite
```

`composer run setup` no siembra datos. Para cargar el demo:

```bash
php artisan migrate:fresh --seed
```

> Las migraciones del esquema de venta fraccionada y lotes/FEFO se integraron a las migraciones originales porque el proyecto no tiene datos productivos. En un entorno existente hay que ejecutar `migrate:fresh` o preparar una migración aditiva.

### Accesos demo

| Email | Contraseña | Rol |
|---|---|---|
| `admin@sistema.com` | `admin123` | Administrador |
| `vendedor@sistema.com` | `vendedor123` | Vendedor |

### Variables de entorno relevantes

```env
DB_CONNECTION=mysql
DB_DATABASE=botica

WHATSAPP_PHONE_NUMBER=51XXXXXXXXX
JSONPE_API_TOKEN=
JSONPE_API_URL=https://api.json.pe/api
```

Cloudinary se configura con las credenciales del paquete `cloudinary-labs/cloudinary-laravel`. Sin `WHATSAPP_PHONE_NUMBER`, los botones de WhatsApp no se muestran.

---

## Rutas principales

| URL | Área |
|---|---|
| `/` | Portada pública |
| `/productos` | Catálogo |
| `/productos/{id}` | Detalle de producto |
| `/combos/{id}` | Detalle de combo |
| `/registro`, `/iniciar-sesion`, `/mi-perfil`, `/carrito` | Área de cliente |
| `/login` | Login del panel |
| `/admin/dashboard` | Panel administrativo |

---

## Estructura

```
app/
├── Helpers/{KardexHelper,CantidadHelper}.php
├── Http/Controllers/{Admin,Public,Api}
├── Http/Requests/{Admin,Public}
├── Models/
├── Services/{Cloudinary,Reniec,Sunat,Reporte,Inventario}Service.php
└── Providers/AppServiceProvider.php

resources/
├── js/{admin,public}/
└── views/{admin,public,layouts,components}/

routes/{web,admin,api}.php
docs/                  # especificaciones y decisiones
```

Reglas de arquitectura, permisos, stock/kardex y convenciones de JS: [`docs/arquitectura-y-stack.md`](docs/arquitectura-y-stack.md).

---

## Pruebas y formato

```bash
composer run test
php artisan test --filter=nombre_del_test
./vendor/bin/pint
```

Estado actual: **122 pruebas pasan** (423 aserciones).

---

## Estado y deuda técnica

Hallazgos conocidos al 18/11/2026:

- `.env.example` aún apunta a SQLite y `APP_NAME=Laravel`.
- El login del Vendedor redirige al dashboard, para el que su rol no tiene permiso.
- Los combos todavía usan la presentación base del producto; falta elegir presentación dentro del combo.
- El código de barras por presentación está preparado en BD, pero sin lector.
- Los productos sin lotes usan un lote transitorio `SIN-LOTE`; para una botica real debe reemplazarse por inventario físico por lote. Quedan pendientes los reportes de costo/margen por lote.

Detalle completo en [`docs/arquitectura-y-stack.md`](docs/arquitectura-y-stack.md).
