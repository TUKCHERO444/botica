# Arquitectura y Stack del Proyecto — Botica Sistema

## Stack Tecnológico

| Tecnología       | Versión/Detalle          |
|------------------|--------------------------|
| PHP              | 8.2.12                   |
| Framework        | Laravel 12               |
| Motor de Vistas  | Blade                    |
| CSS              | Tailwind CSS v4          |
| Bundler          | Vite                     |
| JS               | Vanilla JS + Axios       |
| Autenticación    | Spatie Laravel Permission |
| Base de datos    | MySQL                    |

---

## Estructura del Proyecto (Laravel estándar)

```
.
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/           # Controladores del panel administrativo
│   │   │   └── Public/          # Controladores del área pública
│   │   ├── Requests/            # Form Request validation
│   │   └── Middleware/           # Middleware personalizado
│   ├── Models/                   # Modelos Eloquent
│   ├── Providers/                # Service Providers
│   └── Services/                 # Clases de lógica de negocio (opcional)
├── config/                       # Configuraciones (spatie-permission.php, etc.)
├── database/
│   ├── migrations/               # Migraciones
│   └── seeders/                  # Seeders + Roles y permisos
├── resources/
│   ├── css/
│   │   └── app.css               # Entrypoint Tailwind v4 (importa tailwind)
│   ├── js/
│   │   ├── app.js                # Entrypoint JS principal (importa bootstrap.js)
│   │   ├── bootstrap.js          # Configura Axios, CSRF, etc.
│   │   ├── admin/                # Módulos JS del panel administrativo
│   │   │   ├── productos/
│   │   │   │   ├── index.js
│   │   │   │   ├── create.js
│   │   │   │   └── edit.js
│   │   │   ├── usuarios/
│   │   │   │   └── index.js
│   │   │   └── dashboard.js
│   │   └── public/               # Módulos JS del área pública
│   │       ├── tienda/
│   │       │   ├── catalogo.js
│   │       │   └── producto.js
│   │       └── carrito.js
│   └── views/
│       ├── layouts/              # Layouts base (admin.blade.php, public.blade.php)
│       ├── admin/                # Vistas del panel administrativo
│       │   ├── productos/
│       │   ├── usuarios/
│       │   └── dashboard.blade.php
│       └── public/               # Vistas del área pública
│           ├── tienda/
│           └── categorias/
├── routes/
│   ├── web.php                   # Rutas públicas
│   ├── admin.php                 # Rutas administrativas (protegidas con Spatie)
│   └── api.php                   # Endpoints AJAX
└── public/
    └── build/                    # Assets compilados por Vite
```

---

## Organización de JavaScript

Cada módulo funcional tiene su propia carpeta dentro de `resources/js/admin/` o `resources/js/public/`. Los archivos JS se importan únicamente en las vistas que los necesitan usando `@vite()` desde el layout.

### Reglas

1. **Archivo por vista o funcionalidad**: Si una vista necesita lógica JS, se crea un archivo dentro de la carpeta del módulo correspondiente.
2. **AJAX centralizado con Axios**: `bootstrap.js` ya configura Axios con la cabecera `X-CSRF-TOKEN`. Todas las peticiones asíncronas usan Axios.
3. **Carga dinámica**: Las vistas cargan únicamente los JS que necesitan usando `@vite('resources/js/<ruta>/<archivo>.js')`.
4. **Eventos DOM**: Se usa `document.addEventListener('DOMContentLoaded', ...)` o se delega con `document.addEventListener('click', ...)` para acciones dinámicas.

### Carga en vistas de página completa (con layout)

Las vistas que extienden un layout (`@extends`) usan `@push('scripts')` porque el layout tiene `@stack('scripts')` que renderiza el contenido al final del `<body>`.

```blade
{{-- resources/views/admin/productos/index.blade.php --}}
@extends('layouts.admin')

@section('content')
    {{-- contenido de la vista --}}
@endsection

@push('scripts')
    @vite('resources/js/admin/productos/index.js')
@endpush
```

### Carga en vistas parciales (modales AJAX — SPA)

El sistema funciona como SPA: los formularios de crear/editar se cargan vía AJAX dentro de un modal. Estas vistas **no extienden el layout** (`@extends` ausente), por lo que `@push('scripts')` empujaría el JS a una pila que nunca se renderiza.

En su lugar, se usa `@vite()` directamente, sin `@push`:

```blade
{{-- resources/views/admin/ventas/create.blade.php --}}
<form ...>
    {{-- contenido del formulario --}}
</form>

@vite('resources/js/admin/ventas/create.js')
```

El `<script type="module" src="...">` se emite directamente en la respuesta HTML. El sistema de modales (`resources/js/admin/modal.js`) detecta los scripts en el HTML cargado y los re-ejecuta automáticamente con cache-busting para garantizar su funcionamiento.

**Regla práctica**:
- Vista con `@extends` → `@push('scripts') @vite(...) @endpush`
- Vista parcial (modal AJAX) → `@vite(...)` directo

---

## Arquitectura de Módulos

La aplicación se divide en dos áreas principales:

### 1. Área Pública

- Sin autenticación requerida.
- Catálogo de productos, consulta de precios, visualización de información.
- Carrito de compras (opcional, según alcance).
- Rutas definidas en `routes/web.php`.

### 2. Panel Administrativo

- Acceso mediante autenticación con **Spatie Laravel Permission**.
- Gestión de: productos, categorías, usuarios, roles, ventas, etc.
- Rutas definidas en `routes/admin.php` protegidas con middleware `auth` y `role/permission`.
- Middleware personalizados para verificar permisos específicos.
- Los permisos se asignan mediante seeders en `database/seeders/`.

---

## Patrón de Construcción

1. **Modelo + Migración**: Se crea el modelo Eloquent con su migración.
2. **Controlador**: Se crea en `app/Http/Controllers/Admin/` o `Public/` según corresponda.
3. **Request**: Se crea un Form Request para validación si es necesario.
4. **Ruta**: Se registra en `routes/web.php` o `routes/admin.php`.
5. **Vista Blade**: Se crea dentro de `resources/views/admin/` o `resources/views/public/`.
6. **JS**: Si la vista necesita interactividad, se crea en la carpeta JS correspondiente.
7. **Estilos**: Se usan clases utilitarias de Tailwind directamente en las vistas (sin CSS personalizado salvo excepciones).

### Flujo de trabajo para una nueva funcionalidad

```
php artisan make:model Producto -m
php artisan make:controller Admin/ProductoController --resource
php artisan make:request Admin/StoreProductoRequest
```

Luego se editan:
- `routes/admin.php` → ruta resource
- `resources/views/admin/productos/` → vistas
- `resources/js/admin/productos/` → JS (si aplica)
- `database/seeders/` → permisos de Spatie (si aplica)

---

## Spatie — Roles y Permisos

- Los roles se asignan por seeders (`RoleSeeder`, `PermissionSeeder`).
- Cada controlador administrativo verifica permisos mediante `Gate` o `$this->authorize()`.
- Las rutas administrativas usan middleware `\Spatie\Permission\Middleware\RoleMiddleware` o `PermissionMiddleware`.

---

## Diseño Responsive — Mobile First

| Dispositivo de referencia | Viewport     |
|---------------------------|--------------|
| iPhone 14 Pro Max         | 430 × 932 px |

### Estrategia

El desarrollo es **mobile-first**: se escribe el CSS base para móvil (viewport < 640px) y se añaden variantes `sm:`, `md:`, `lg:`, `xl:` para pantallas más grandes usando los breakpoints por defecto de Tailwind:

| Breakpoint | Min-width | Target                        |
|------------|-----------|-------------------------------|
| *base*     | 0px       | Móviles (iPhone 14 Pro Max)   |
| `sm`       | 640px     | Tablets pequeños              |
| `md`       | 768px     | Tablets grandes               |
| `lg`       | 1024px    | Laptops / Desktop             |
| `xl`       | 1280px    | Desktop grandes               |
| `2xl`      | 1536px    | Pantallas ultra-wide          |

### Reglas

1. **Sin clase `sm:` o superior = comportamiento móvil**. Primero se diseña para 430px, luego se expande.
2. **Uso obligatorio de `viewport`** con `width=device-width, initial-scale=1` (ya incluido en el layout).
3. **Navbar**: los botones de navegación se ocultan en móvil (`hidden md:flex`) y se acceden mediante un menú hamburguesa.
4. **Carrusel y slider**: la cantidad de cards/imágenes visibles se adapta dinámicamente al ancho del contenedor (`getVisibleCount()` en JS), mostrando 2 cards en móvil, 3 en tablet y 5 en desktop.
5. **Promociones**: layout en columna en móvil (`flex-col`) y fila en desktop (`sm:flex-row`).
6. **Categorías**: usan `flex flex-wrap` para que fluyan naturalmente en cualquier ancho.
7. **Imágenes**: siempre con `object-cover` y altura responsive (`h-48 sm:h-64`).
8. **Textos**: tamaños base pequeños (`text-sm`) que escalan con `sm:text-base` donde sea necesario.
9. **Touch targets**: botones y enlaces mínimo 44×44 px (WCAG).
10. **Sliders con drag**: soporte táctil nativo vía eventos `mousedown/mousemove/mouseup` que también funcionan en dispositivos táctiles.

### Validación

- Abrir DevTools → modo dispositivo → seleccionar **iPhone 14 Pro Max** (430×932)
- Verificar que no haya scroll horizontal y que todos los elementos sean legibles y tocables.
- Probar en `sm` (640px), `md` (768px) y `lg` (1024px) para confirmar transiciones suaves.

---

## Comandos útiles

```bash
# Iniciar servidor de desarrollo + Vite
composer run dev

# Compilar assets para producción
npm run build

# Migrar + seedear
php artisan migrate:fresh --seed
```

---

*Documento de referencia para mantener consistencia arquitectónica en el proyecto.*
