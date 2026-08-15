# Panel Administrativo — SPA + AJAX

## Arquitectura General

El panel administrativo funciona como una **Single Page Application (SPA)** sobre Laravel Blade:

- El layout `layouts.admin` se renderiza una sola vez y actúa como **shell**.
- Cada "página" del panel es una **vista parcial** (sin `@extends`) que se carga vía AJAX dentro del `<main>` del layout.
- Los modales se inyectan al DOM dinámicamente para las operaciones CRUD.
- Todas las peticiones AJAX usan **Axios** (ya configurado en `bootstrap.js`).

```
Estado inicial:     GET /dashboard → layout completo + vista partial del dashboard
Navegación SPA:     click en link del menú → GET /admin/productos (AJAX) → HTML reemplaza contenido de <main>
CRUD con modal:     click en "Nuevo" → GET /admin/productos/create (AJAX) → modal con formulario
                    submit del form → POST /admin/productos (AJAX) → cierra modal, refresca tabla
```

---

## 1. Layout como Shell

El layout `resources/views/layouts/admin.blade.php` se mantiene casi igual, pero el `<main>` tendrá un `id="panel-content"` para que JS pueda reemplazar su contenido.

El layout **no se recarga nunca** durante la navegación SPA. Solo la primera carga es una petición completa al servidor.

```blade
<main class="flex-1 p-6" id="panel-content">
    @yield('content')
</main>
```

A cada link del menú lateral se le agrega el atributo `data-panel` con la URL del endpoint AJAX:

```blade
<a href="/admin/productos" data-panel="/admin/productos" class="...">
    Productos
</a>
```

---

## 2. Convención de Vistas Parciales

Cada módulo del panel tiene dos tipos de vistas:

| Tipo | Propósito | Ubicación | ¿Extiende layout? |
|------|-----------|-----------|-------------------|
| **Partial** | Contenido cargado vía AJAX | `resources/views/admin/<modulo>/index.blade.php` | **NO** |
| **Completa** | Renderizado directo (fallback/SEO) | `resources/views/admin/<modulo>/full.blade.php` | Sí, `@extends('layouts.admin')` e incluye el partial |

La vista partial **nunca** extiende layout. Solo contiene el HTML del contenido del panel (tablas, botones, etc.).

```blade
{{-- resources/views/admin/productos/index.blade.php (partial) --}}
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-stone-800">Productos</h1>
    <button type="button" class="..." data-modal="productos-create">
        + Nuevo producto
    </button>
</div>

<div id="productos-table">
    {{-- tabla con productos --}}
</div>
```

La vista completa se usa solo para recarga directa de página o SEO:

```blade
{{-- resources/views/admin/productos/full.blade.php --}}
@extends('layouts.admin')

@section('title', 'Productos')

@section('content')
    @include('admin.productos.index')
@endsection
```

---

## 3. Sistema de Rutas AJAX

Se agrega un archivo `routes/admin.php` para las rutas del panel. Cada ruta resource tiene dos respuestas posibles según el header `X-Requested-With`:

### 3.1. Rutas definidas en `routes/admin.php`

```php
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('productos', ProductoController::class)->names([
        'index'   => 'admin.productos.index',
        'create'  => 'admin.productos.create',
        'store'   => 'admin.productos.store',
        'edit'    => 'admin.productos.edit',
        'update'  => 'admin.productos.update',
        'destroy' => 'admin.productos.destroy',
    ]);
});
```

### 3.2. Comportamiento del controlador

Cada controlador detecta si la petición es AJAX y devuelve solo el partial o la vista completa:

```php
// app/Http/Controllers/Admin/ProductoController.php
class ProductoController extends Controller
{
    public function index()
    {
        $productos = Producto::all();

        if (request()->ajax()) {
            return view('admin.productos.index', compact('productos'));
        }

        return view('admin.productos.full', compact('productos'));
    }

    public function create()
    {
        if (request()->ajax()) {
            return view('admin.productos.create'); // solo el formulario del modal
        }

        return redirect()->route('admin.productos.index');
    }

    public function store(StoreProductoRequest $request)
    {
        $producto = Producto::create($request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success'  => true,
                'message'  => 'Producto creado correctamente',
                'producto' => $producto,
            ]);
        }

        return redirect()->route('admin.productos.index')->with('success', 'Producto creado correctamente');
    }

    public function edit(Producto $producto)
    {
        if (request()->ajax()) {
            return view('admin.productos.edit', compact('producto'));
        }

        return redirect()->route('admin.productos.index');
    }

    public function update(UpdateProductoRequest $request, Producto $producto)
    {
        $producto->update($request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success'  => true,
                'message'  => 'Producto actualizado correctamente',
                'producto' => $producto,
            ]);
        }

        return redirect()->route('admin.productos.index')->with('success', 'Producto actualizado correctamente');
    }

    public function destroy(Producto $producto)
    {
        $producto->delete();

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado correctamente',
            ]);
        }

        return redirect()->route('admin.productos.index')->with('success', 'Producto eliminado correctamente');
    }
}
```

---

## 4. Motor JS del SPA — `admin/router.js`

Archivo único que orquesta la navegación SPA. Se carga una vez desde el layout.

```js
// resources/js/admin/router.js
import axios from 'axios';

const AdminRouter = {
    init() {
        this.cacheDom();
        this.bindEvents();
        this.loadInitialContent();
    },

    cacheDom() {
        this.$panel = document.getElementById('panel-content');
        this.$links = document.querySelectorAll('[data-panel]');
    },

    bindEvents() {
        document.addEventListener('click', (e) => {
            const link = e.target.closest('[data-panel]');
            if (link) {
                e.preventDefault();
                this.navigate(link.getAttribute('data-panel'));
            }
        });

        window.addEventListener('popstate', (e) => {
            if (e.state?.url) {
                this.load(e.state.url, false);
            }
        });
    },

    loadInitialContent() {
        // El contenido ya está renderizado por el servidor en la primera carga
    },

    navigate(url) {
        this.load(url, true);
    },

    async load(url, pushState = true) {
        try {
            const res = await axios.get(url);
            this.$panel.innerHTML = res.data;
            if (pushState) {
                window.history.pushState({ url }, '', url);
            }
        } catch (err) {
            console.error('Error al cargar panel:', err);
        }
    },
};

export default AdminRouter;
```

### Registro en `app.js`

```js
// resources/js/app.js
import './bootstrap';
import AdminRouter from './admin/router';

document.addEventListener('DOMContentLoaded', () => {
    AdminRouter.init();
});
```

---

## 5. Sistema de Modales

Los modales se manejan con un sistema genérico basado en el atributo `data-modal`. Se inyectan al DOM como fragmentos HTML devueltos por los endpoints AJAX de `create` / `edit`.

### 5.1. Modal genérico en el layout

```blade
{{-- Dentro de layouts/admin.blade.php, antes del cierre de </body> --}}
<div id="modal-overlay" class="fixed inset-0 z-50 hidden bg-black/50 flex items-center justify-center">
    <div id="modal-container" class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
    </div>
</div>
```

### 5.2. Modal JS driver — `admin/modal.js`

```js
// resources/js/admin/modal.js
const AdminModal = {
    init() {
        this.$overlay = document.getElementById('modal-overlay');
        this.$container = document.getElementById('modal-container');
        this.bindEvents();
    },

    bindEvents() {
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-modal]');
            if (trigger) {
                e.preventDefault();
                const url = trigger.getAttribute('data-modal');
                this.open(url);
            }
        });

        this.$overlay.addEventListener('click', (e) => {
            if (e.target === this.$overlay) this.close();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.close();
        });

        // Delegación para submit de formularios dentro del modal
        document.addEventListener('submit', async (e) => {
            const form = e.target.closest('#modal-container form');
            if (!form) return;
            e.preventDefault();
            await this.submitForm(form);
        });
    },

    async open(url) {
        try {
            const res = await axios.get(url);
            this.$container.innerHTML = res.data;
            this.$overlay.classList.remove('hidden');
        } catch (err) {
            console.error('Error al abrir modal:', err);
        }
    },

    close() {
        this.$overlay.classList.add('hidden');
        this.$container.innerHTML = '';
    },

    async submitForm(form) {
        const method = (form.querySelector('input[name="_method"]')?.value || form.method).toUpperCase();
        const url = form.action;
        const data = new FormData(form);

        try {
            const res = await axios({
                method,
                url,
                data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (res.data.success) {
                this.close();
                // Recarga el contenido actual del panel para reflejar cambios
                await AdminRouter.load(window.location.href, false);
                // Mostrar mensaje toast
                AdminToast.show(res.data.message, 'success');
            }
        } catch (err) {
            if (err.response?.status === 422) {
                AdminModal.renderErrors(err.response.data.errors);
            } else {
                AdminToast.show('Error inesperado', 'error');
            }
        }
    },

    renderErrors(errors) {
        // Limpiar errores anteriores
        document.querySelectorAll('.field-error').forEach(el => el.remove());
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

        for (const [field, messages] of Object.entries(errors)) {
            const input = document.querySelector(`[name="${field}"]`);
            if (input) {
                input.classList.add('is-invalid');
                const errorEl = document.createElement('p');
                errorEl.className = 'field-error text-red-600 text-xs mt-1';
                errorEl.textContent = messages[0];
                input.parentNode.appendChild(errorEl);
            }
        }
    },
};

export default AdminModal;
```

### 5.3. Vista de formulario para modal

```blade
{{-- resources/views/admin/productos/create.blade.php (partial - sin layout) --}}
<form action="{{ route('admin.productos.store') }}" method="POST" class="p-6">
    @csrf
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-stone-800">Nuevo producto</h2>
        <button type="button" onclick="AdminModal.close()" class="text-stone-400 hover:text-stone-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Nombre</label>
            <input type="text" name="nombre" required
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Precio</label>
            <input type="number" name="precio" step="0.01" required
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
    </div>

    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-stone-200">
        <button type="button" onclick="AdminModal.close()"
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Guardar</button>
    </div>
</form>
```

---

## 6. Sistema de Toast (Notificaciones)

```js
// resources/js/admin/toast.js
const AdminToast = {
    show(message, type = 'success', duration = 3000) {
        const colors = {
            success: 'bg-emerald-600',
            error: 'bg-red-600',
            info: 'bg-blue-600',
        };
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 z-50 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg text-sm transition-all duration-300 translate-y-[-100%] opacity-0`;
        toast.textContent = message;
        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.remove('translate-y-[-100%]', 'opacity-0');
        });

        setTimeout(() => {
            toast.classList.add('translate-y-[-100%]', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },
};

window.AdminToast = AdminToast;
```

---

## 7. Estructura de Archivos Resultante

```
resources/
├── js/
│   ├── app.js                          # Entry point (importa bootstrap + admin/router)
│   ├── bootstrap.js                    # Axios + CSRF
│   └── admin/
│       ├── router.js                   # Navegación SPA
│       ├── modal.js                    # Sistema de modales CRUD
│       ├── toast.js                    # Notificaciones toast
│       ├── dashboard.js                # Lógica del dashboard
│       └── productos/
│           ├── index.js                # Lógica de la tabla de productos
│           ├── create.js               # Lógica del formulario de creación
│           └── edit.js                 # Lógica del formulario de edición
└── views/
    ├── layouts/
    │   └── admin.blade.php             # Shell SPA (se carga una vez)
    ├── admin/
    │   ├── dashboard/
    │   │   └── index.blade.php         # Partial - stats, actividad reciente
    │   ├── productos/
    │   │   ├── index.blade.php         # Partial - tabla + botón nuevo
    │   │   ├── create.blade.php        # Partial - formulario para modal
    │   │   ├── edit.blade.php          # Partial - formulario para modal
    │   │   └── full.blade.php          # Vista completa (extends admin.layout)
    │   └── ...
    └── ...

routes/
├── web.php                            # Rutas públicas + login/logout
├── admin.php                          # Rutas del panel (protegidas con auth)
└── api.php                            # Endpoints de datos (JSON puro)
```

---

## 8. Vite — Entradas JS

Cada módulo que tenga interacciones JS específicas se declara en `vite.config.js`:

```js
laravel({
    input: [
        'resources/css/app.css',
        'resources/js/app.js',              // router + modal + toast (globales)
        'resources/js/admin/dashboard.js',
        'resources/js/admin/productos/index.js',
        'resources/js/admin/productos/create.js',
        'resources/js/admin/productos/edit.js',
    ],
    refresh: true,
}),
```

En las vistas parciales se cargan con `@vite()` dentro de `@push('scripts')`:

```blade
@push('scripts')
    @vite('resources/js/admin/productos/index.js')
@endpush
```

---

## 9. Flujo de Navegación Completo

```
USUARIO                                     SISTEMA
─────────────────────────────               ─────────────────────────────
1. Ingresa /dashboard                       Renderiza layouts.admin + partial dashboard
2. Click en "Productos" (data-panel)        Axios GET /admin/productos → reemplaza #panel-content
3. Click en "+ Nuevo producto"              Axios GET /admin/productos/create → modal con form
4. Llena form y click "Guardar"             Axios POST /admin/productos (FormData)
                                               → success → cierra modal → recarga #panel-content
                                               → 422 errors → muestra errores en el modal
5. Click en ✏️ de un producto               Axios GET /admin/productos/1/edit → modal con form
6. Modifica y click "Guardar"               Axios PUT /admin/productos/1 (con _method: PUT)
                                               → success → cierra modal → recarga #panel-content
7. Click en 🗑️ de un producto               Confirmación JS → Axios DELETE /admin/productos/1
                                               → success → recarga #panel-content + toast
```

---

## 10. Estrategia de Caché

### 10.1. Driver

| Driver actual | Recomendado | Motivo                        |
|---------------|-------------|-------------------------------|
| `database`    | `file`      | File evita una consulta MySQL extra por cada caché |

Cambiar en `.env`:

```env
CACHE_STORE=file
```

Si hay Redis disponible (`REDIS_HOST` ya configurado), `redis` es aún más rápido.

### 10.2. ¿Qué se guarda en caché?

| Tipo | ¿Cacheable? | Clave ejemplo | TTL |
|------|------------|---------------|-----|
| HTML de vista `index` (AJAX) | ✅ Sí | `admin.categorias.index` | 3600s |
| Query de listado (registros) | ✅ Sí (incluido en el HTML) | — | — |
| Vistas `create` / `edit` | ❌ No (contienen `@csrf` + datos específicos) | — | — |
| Respuestas POST/PUT/DELETE | ❌ No | — | — |

### 10.3. Invalidación (cache bust)

Se invalida la clave `admin.{modulo}.index` después de cualquier operación de escritura:

| Operación | Acción |
|-----------|--------|
| `store()` | `Cache::forget('admin.{modulo}.index')` |
| `update()` | `Cache::forget('admin.{modulo}.index')` |
| `destroy()` | `Cache::forget('admin.{modulo}.index')` |

### 10.4. Implementación

Trait `App\Http\Controllers\Admin\Traits\CacheaPanel`:

```php
trait CacheaPanel
{
    protected function keyIndex(string $modulo): string
    {
        return "admin.{$modulo}.index";
    }

    protected function getIndex(string $modulo, callable $callback, int $ttl = 3600): mixed
    {
        return Cache::remember($this->keyIndex($modulo), $ttl, $callback);
    }

    protected function olvidaIndex(string $modulo): void
    {
        Cache::forget($this->keyIndex($modulo));
    }
}
```

Uso en el controlador:

```php
public function index()
{
    if (request()->ajax()) {
        return $this->getIndex('categorias', function () {
            $categorias = Categoria::orderBy('id_categoria', 'desc')->get();
            return view('admin.categorias.index', compact('categorias'))->render();
        });
    }

    $categorias = Categoria::orderBy('id_categoria', 'desc')->get();
    return view('admin.categorias.full', compact('categorias'));
}

public function store(StoreCategoriaRequest $request)
{
    $categoria = Categoria::create($request->validated());
    $this->olvidaIndex('categorias'); // ← invalida caché
    // ...
}
```

### 10.5. CSRF y Caché

- El layout incluye `<meta name="csrf-token" content="{{ csrf_token() }}">`.
- Axios envía `X-CSRF-TOKEN` automáticamente en cada petición (configurado en `bootstrap.js`).
- Las vistas `create`/`edit` no se cachean, por lo que el `@csrf` del formulario siempre es fresco.
- Las vistas `index` no contienen `@csrf`, por lo que se cachean sin problema.

---

## 11. Consideraciones

- **Event Delegation**: Todos los eventos se delegan en `document` porque el contenido cambia dinámicamente.
- **CSRF**: Axios ya tiene el token configurado en `bootstrap.js`; los formularios dentro del modal incluyen `@csrf`.
- **Fallback sin JS**: Si JS falla, los links del menú siguen siendo links `<a href="...">` funcionales que cargan la página completa.
- **Historial del navegador**: Se usa `pushState`/`popstate` para que los botones de atrás/adelante funcionen.
- **Modales anidados**: No se permiten; al abrir un modal se cierra cualquier modal previo.
- **Responsive**: El layout admin ya es mobile-first con sidebar colapsable en móvil (extensión futura).
