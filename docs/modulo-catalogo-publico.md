# Módulo Catálogo Público — "Nuestros Productos"

## 1. Propósito

Crear una página pública de catálogo de productos accesible desde el navbar principal con el botón **"Nuestros Productos"**. Permite al usuario navegar, buscar y filtrar productos por categorías, así como acceder a la página de detalle de cada producto.

El módulo reemplaza los 3 botones placeholder actuales del navbar del layout público y centraliza la experiencia de exploración de productos del lado del cliente.

---

## 2. Arquitectura

### 2.1. Flujo General

```
NAVBAR (sin auth)
  "Nuestros Productos"  -->  GET /productos
                                |
                                |--> ProductoCatalogoController@index
                                |      |-- Categorias::where('estado', 1)->orderBy('nombre')
                                |      |-- Producto::visible()->filtered()->paginate(20)
                                |      |-- view('public.productos.index')
                                |
                                |--> AJAX GET /productos?categorias[]=X&categorias[]=Y&q=texto
                                       |-- ProductoCatalogoController@index (ajax=true)
                                       |-- return partial _grid (HTML de grilla)

  Click en card  -->  GET /productos/{producto}
                        |
                        |--> ProductoCatalogoController@show
                               |-- view('public.productos.show')
```

### 2.2. Criterio de visibilidad de productos

Un producto se muestra en el catálogo si cumple **cualquiera** de estas condiciones:

| Condición | Descripción |
|-----------|-------------|
| `estado = 1` | Producto activo (aunque no tenga stock) |
| `stock > 0` | Producto con unidades disponibles (aunque esté desactivado — casos de frontera) |

Se implementa mediante un **scope local** `visible()` en el modelo `Producto`.

### 2.3. Filtros vía query params

Todos los filtros se reflejan en la URL para permitir:

- URLs compartibles (`/productos?categorias[]=1&categorias[]=3&q=ibuprofeno`)
- Historial del navegador (back/forward con `replaceState`)
- Recarga de página manteniendo filtros

| Parámetro | Tipo | Ejemplo | Propósito |
|-----------|------|---------|-----------|
| `categorias[]` | array de int | `categorias[]=1&categorias[]=3` | Filtrar por una o más categorías |
| `q` | string | `q=ibuprofeno` | Búsqueda por nombre |
| `page` | int | `page=2` | Paginación |

---

## 3. Base de Datos

No se requieren nuevas tablas ni migraciones. El módulo reutiliza las tablas existentes:

| Tabla | Uso |
|-------|-----|
| `producto` | Listado y detalle de productos visibles |
| `categoria` | Sidebar de filtros y etiqueta en cards |
| `marca` | Información complementaria en detalle |

---

## 4. Modelo

### 4.1. `Producto.php` — Scope `visible`

Se agrega un scope local para centralizar el criterio de visibilidad:

```php
// app/Models/Producto.php

public function scopeVisible($query)
{
    return $query->where(function ($q) {
        $q->where('estado', 1)
          ->orWhere('stock', '>', 0);
    });
}
```

**Motivación**: El mismo criterio puede ser necesario en otros lugares del sistema (sliders, búsqueda AJAX, etc.). Un scope evita duplicar la lógica.

### 4.2. `Categoria.php` — Sin cambios

El modelo existente con su relación `productos()` es suficiente.

---

## 5. Controlador: `Public/ProductoCatalogoController.php`

### 5.1. Métodos

| Método | Ruta | Verbo | Descripción |
|--------|------|-------|-------------|
| `index()` | `/productos` | GET | Listado paginado con filtros por categoría y búsqueda |
| `show()` | `/productos/{producto}` | GET | Detalle completo del producto |

### 5.2. `index()`

```php
public function index()
{
    $categorias = Categoria::where('estado', 1)
        ->orderBy('nombre')
        ->get();

    $selectedCategorias = request()->input('categorias', []);
    $search = request()->input('q');

    $productos = Producto::visible()
        ->when($selectedCategorias, function ($q, $cats) {
            $q->whereIn('categoria_id_categoria', $cats);
        })
        ->when($search, function ($q, $s) {
            $q->where('nombre', 'like', "%{$s}%");
        })
        ->with('categoria', 'marca')
        ->orderBy('nombre')
        ->paginate(20)
        ->withQueryString();

    if (request()->ajax()) {
        return view('public.productos._grid', compact('productos'));
    }

    return view('public.productos.index', compact(
        'productos', 'categorias', 'selectedCategorias'
    ));
}
```

**Comportamiento dual**:
- Petición normal → devuelve la vista completa con sidebar
- Petición AJAX (`X-Requested-With: XMLHttpRequest`) → devuelve solo el partial `_grid` (HTML de la grilla de productos)

### 5.3. `show()`

```php
public function show(Producto $producto)
{
    abort_unless(
        $producto->estado === 1 || $producto->stock > 0,
        404,
        'Producto no disponible'
    );

    $producto->load('categoria', 'marca');

    return view('public.productos.show', compact('producto'));
}
```

**Validación**: Si el producto no cumple el criterio de visibilidad, se retorna 404.

---

## 6. Rutas — `routes/web.php`

Agregar después de `Route::get('/', ...)` y antes de las rutas guest:

```php
use App\Http\Controllers\Public\ProductoCatalogoController;

Route::get('/productos', [ProductoCatalogoController::class, 'index'])->name('public.productos.index');
Route::get('/productos/{producto}', [ProductoCatalogoController::class, 'show'])->name('public.productos.show');
```

Ambas rutas son públicas (sin middleware de auth).

---

## 7. Vistas

### 7.1. `resources/views/public/productos/index.blade.php`

```blade
@extends('layouts.public')

@section('title', 'Nuestros Productos — Botica')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 md:py-8 flex flex-col md:flex-row gap-6 md:gap-8">

    {{-- Sidebar Categorías (Desktop) --}}
    <aside class="w-full md:w-56 lg:w-64 shrink-0 hidden md:block">
        <div class="md:sticky md:top-20">
            <h3 class="text-lg font-bold text-stone-800 mb-4">Categorías</h3>
            <div class="space-y-1.5" id="categorias-list">
                @foreach ($categorias as $cat)
                    @php
                        $count = $cat->productos()->visible()->count();
                    @endphp
                    <label class="flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-stone-100 cursor-pointer transition {{ in_array($cat->id_categoria, $selectedCategorias) ? 'bg-emerald-50' : '' }}">
                        <input type="checkbox" name="categorias[]" value="{{ $cat->id_categoria }}"
                               class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                               {{ in_array($cat->id_categoria, $selectedCategorias) ? 'checked' : '' }}>
                        <span class="text-sm text-stone-700 flex-1">{{ $cat->nombre }}</span>
                        <span class="text-xs text-stone-400 tabular-nums">({{ $count }})</span>
                    </label>
                @endforeach
            </div>

            <button type="button" id="limpiar-filtros"
                class="mt-4 text-sm text-emerald-600 hover:text-emerald-700 font-medium transition {{ empty($selectedCategorias) && !request('q') ? 'hidden' : '' }}">
                Limpiar filtros
            </button>
        </div>
    </aside>

    {{-- Botón filtros móvil --}}
    <button id="btn-filtros-mobile" type="button"
        class="md:hidden flex items-center gap-2 text-sm text-stone-600 bg-white border border-stone-300 rounded-lg px-4 py-2.5 shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
        </svg>
        Filtros
        @if(!empty($selectedCategorias))
            <span class="bg-emerald-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">{{ count($selectedCategorias) }}</span>
        @endif
    </button>

    {{-- Contenido principal --}}
    <div class="flex-1 min-w-0">

        {{-- Barra de búsqueda --}}
        <div class="flex items-center gap-3 mb-6">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-stone-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" id="search-productos" value="{{ request('q') }}"
                       placeholder="Buscar productos por nombre..."
                       class="w-full rounded-lg border border-stone-300 pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
            </div>
            <span class="text-sm text-stone-500 tabular-nums whitespace-nowrap" id="result-count">
                {{ $productos->total() }} {{ $productos->total() === 1 ? 'producto' : 'productos' }}
            </span>
        </div>

        {{-- Grilla de productos --}}
        <div id="productos-grid">
            @include('public.productos._grid')
        </div>
    </div>
</div>

{{-- Modal de filtros móvil --}}
<div id="mobile-filters-overlay" class="fixed inset-0 z-50 bg-black/50 hidden md:hidden">
    <div id="mobile-filters-panel" class="absolute right-0 top-0 h-full w-72 bg-white shadow-xl overflow-y-auto p-6 translate-x-0 transition-transform">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-stone-800">Filtros</h3>
            <button id="cerrar-filtros-mobile" type="button" class="text-stone-400 hover:text-stone-600 p-1">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div id="categorias-list-mobile">
            @foreach ($categorias as $cat)
                @php $count = $cat->productos()->visible()->count(); @endphp
                <label class="flex items-center gap-2.5 py-2 cursor-pointer">
                    <input type="checkbox" name="categorias[]" value="{{ $cat->id_categoria }}"
                           class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                           {{ in_array($cat->id_categoria, $selectedCategorias) ? 'checked' : '' }}>
                    <span class="text-sm text-stone-700 flex-1">{{ $cat->nombre }}</span>
                    <span class="text-xs text-stone-400">({{ $count }})</span>
                </label>
            @endforeach
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @vite('resources/js/public/tienda/productos.js')
@endpush
```

### 7.2. `resources/views/public/productos/_grid.blade.php` (Partial)

```blade
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4">
    @forelse ($productos as $prod)
        <x-product-card :producto="$prod" />
    @empty
        <div class="col-span-full text-center py-16">
            <svg class="w-12 h-12 mx-auto text-stone-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            <p class="text-stone-400 font-medium">No se encontraron productos</p>
            <p class="text-stone-400 text-sm mt-1">Intentá con otros filtros o términos de búsqueda</p>
        </div>
    @endforelse
</div>

{{ $productos->links() }}
```

### 7.3. `resources/views/public/productos/show.blade.php`

```blade
@extends('layouts.public')

@section('title', $producto->nombre . ' — Botica')
@section('meta_description', Str::limit($producto->descripcion ?? 'Producto disponible en Botica', 160))

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 md:py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-stone-500 mb-6" aria-label="Breadcrumb">
        <ol class="flex items-center gap-1.5 flex-wrap">
            <li><a href="/" class="hover:text-emerald-700 transition">Inicio</a></li>
            <li class="text-stone-300">/</li>
            <li><a href="{{ route('public.productos.index') }}" class="hover:text-emerald-700 transition">Productos</a></li>
            <li class="text-stone-300">/</li>
            @if($producto->categoria)
                <li><a href="{{ route('public.productos.index', ['categorias[]' => $producto->categoria->id_categoria]) }}"
                       class="hover:text-emerald-700 transition">{{ $producto->categoria->nombre }}</a></li>
                <li class="text-stone-300">/</li>
            @endif
            <li class="text-stone-800 font-medium truncate max-w-[200px]">{{ $producto->nombre }}</li>
        </ol>
    </nav>

    {{-- Contenido --}}
    <div class="grid md:grid-cols-2 gap-8 lg:gap-12">

        {{-- Columna izquierda: imagen --}}
        <div>
            @if($producto->foto_url)
                <img src="{{ $producto->foto_url }}"
                     alt="{{ $producto->nombre }}"
                     class="w-full aspect-square object-cover rounded-xl shadow-sm bg-stone-100">
            @else
                <div class="w-full aspect-square bg-stone-100 rounded-xl flex flex-col items-center justify-center text-stone-400">
                    <svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-sm">Sin imagen disponible</span>
                </div>
            @endif
        </div>

        {{-- Columna derecha: información --}}
        <div class="flex flex-col">
            @if($producto->categoria)
                <a href="{{ route('public.productos.index', ['categorias[]' => $producto->categoria->id_categoria]) }}"
                   class="text-sm text-emerald-600 hover:text-emerald-700 font-medium transition mb-1">
                    {{ $producto->categoria->nombre }}
                </a>
            @endif

            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-stone-800 leading-tight">{{ $producto->nombre }}</h1>

            @if($producto->marca)
                <p class="text-sm text-stone-500 mt-2">
                    Marca: <span class="text-stone-700 font-medium">{{ $producto->marca->nombre }}</span>
                </p>
            @endif

            {{-- Precio --}}
            <div class="mt-6">
                <span class="text-3xl sm:text-4xl font-bold text-emerald-700">
                    S/ {{ number_format($producto->precio_venta, 2) }}
                </span>
            </div>

            {{-- Stock --}}
            <div class="mt-4 p-4 bg-stone-50 rounded-lg border border-stone-200">
                @if($producto->stock > 0)
                    <div class="flex items-center gap-2 text-emerald-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="font-medium">En stock — {{ $producto->stock }} {{ $producto->stock === 1 ? 'unidad' : 'unidades' }} disponibles</span>
                    </div>
                @else
                    <div class="flex items-center gap-2 text-amber-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <span class="font-medium">Producto sin stock actualmente</span>
                    </div>
                @endif
            </div>

            {{-- Descripción --}}
            @if($producto->descripcion)
                <div class="mt-6">
                    <h2 class="text-sm font-semibold text-stone-700 uppercase tracking-wider mb-2">Descripción</h2>
                    <p class="text-stone-600 leading-relaxed">{{ $producto->descripcion }}</p>
                </div>
            @endif

            {{-- Precio compra (solo referencia visual, oculto para cliente) --}}
            {{-- Se omite deliberadamente --}}
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @vite('resources/js/public/tienda/productos.js')
@endpush
```

---

## 8. Componente: `product-card.blade.php`

```blade
{{-- resources/views/components/product-card.blade.php --}}
@props(['producto'])

<a href="{{ route('public.productos.show', $producto) }}"
   class="group bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden hover:shadow-md transition-all duration-200 flex flex-col">

    {{-- Imagen --}}
    <div class="relative overflow-hidden bg-stone-100 aspect-square">
        @if($producto->foto_url)
            <img src="{{ $producto->foto_url }}"
                 alt="{{ $producto->nombre }}"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
        @else
            <div class="w-full h-full flex items-center justify-center text-stone-300">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        @endif

        {{-- Badge sin stock --}}
        @if($producto->stock === 0)
            <span class="absolute top-2 left-2 bg-amber-500 text-white text-[10px] font-semibold px-2 py-0.5 rounded-full">
                Sin stock
            </span>
        @endif
    </div>

    {{-- Info --}}
    <div class="p-3 flex flex-col flex-1">
        @if($producto->categoria)
            <p class="text-[11px] text-emerald-600 font-medium truncate">{{ $producto->categoria->nombre }}</p>
        @endif
        <h3 class="text-sm font-semibold text-stone-800 truncate mt-0.5 group-hover:text-emerald-700 transition-colors">
            {{ $producto->nombre }}
        </h3>
        <p class="text-emerald-700 font-bold mt-auto pt-2">
            S/ {{ number_format($producto->precio_venta, 2) }}
        </p>
    </div>
</a>
```

---

## 9. JavaScript: `resources/js/public/tienda/productos.js`

```js
import axios from 'axios';

const ProductosCatalogo = {
    debounceTimer: null,
    selectedCategorias: new Set(),

    init() {
        this.parseQueryParams();
        this.cacheDom();
        this.bindEvents();
    },

    parseQueryParams() {
        const params = new URLSearchParams(window.location.search);
        params.getAll('categorias[]').forEach(id => this.selectedCategorias.add(id));
    },

    cacheDom() {
        this.$grid = document.getElementById('productos-grid');
        this.$search = document.getElementById('search-productos');
        this.$count = document.getElementById('result-count');
        this.$btnLimpiar = document.getElementById('limpiar-filtros');
        this.$btnFiltrosMobile = document.getElementById('btn-filtros-mobile');
        this.$overlayFiltros = document.getElementById('mobile-filters-overlay');
        this.$panelFiltros = document.getElementById('mobile-filters-panel');
        this.$btnCerrarFiltros = document.getElementById('cerrar-filtros-mobile');
    },

    bindEvents() {

        if (this.$search) {
            this.$search.addEventListener('input', (e) => {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => this.filtrar(), 400);
            });
        }

        document.addEventListener('change', (e) => {
            if (e.target.matches('[name="categorias[]"]')) {
                if (e.target.checked) {
                    this.selectedCategorias.add(e.target.value);
                } else {
                    this.selectedCategorias.delete(e.target.value);
                }
                this.filtrar();
            }
        });

        if (this.$btnLimpiar) {
            this.$btnLimpiar.addEventListener('click', () => {
                this.selectedCategorias.clear();
                if (this.$search) this.$search.value = '';
                this.filtrar();
            });
        }

        if (this.$btnFiltrosMobile) {
            this.$btnFiltrosMobile.addEventListener('click', () => {
                this.$overlayFiltros.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            });
        }

        const cerrar = () => {
            this.$overlayFiltros.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        };

        if (this.$btnCerrarFiltros) {
            this.$btnCerrarFiltros.addEventListener('click', cerrar);
        }

        if (this.$overlayFiltros) {
            this.$overlayFiltros.addEventListener('click', (e) => {
                if (e.target === this.$overlayFiltros) cerrar();
            });
        }

        // Sincronizar checkboxes del panel móvil con los del sidebar desktop
        document.addEventListener('change', (e) => {
            if (e.target.matches('#categorias-list-mobile [name="categorias[]"]')) {
                const val = e.target.value;
                const chk = document.querySelector(`#categorias-list [name="categorias[]"][value="${val}"]`);
                if (chk) chk.checked = e.target.checked;
            }
            if (e.target.matches('#categorias-list [name="categorias[]"]')) {
                const val = e.target.value;
                const chk = document.querySelector(`#categorias-list-mobile [name="categorias[]"][value="${val}"]`);
                if (chk) chk.checked = e.target.checked;
            }
        });
    },

    async filtrar() {
        const params = new URLSearchParams();

        this.selectedCategorias.forEach(id => params.append('categorias[]', id));

        const q = this.$search ? this.$search.value.trim() : '';
        if (q) params.set('q', q);

        const newUrl = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
        window.history.replaceState({}, '', newUrl);

        if (this.$btnLimpiar) {
            this.$btnLimpiar.classList.toggle('hidden', !params.toString());
        }

        try {
            const res = await axios.get(`/productos?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (this.$grid) this.$grid.innerHTML = res.data;

            // Actualizar contador desde el HTML recibido
            const temp = document.createElement('div');
            temp.innerHTML = res.data;
            const links = temp.querySelector('.pagination');
            const total = links?.dataset?.total || '—';
            if (this.$count) this.$count.textContent = `${total} productos`;

        } catch (err) {
            console.error('Error al filtrar productos:', err);
        }
    },
};

document.addEventListener('DOMContentLoaded', () => ProductosCatalogo.init());
```

---

## 10. Vite — `vite.config.js`

Agregar entrada:

```js
'resources/js/public/tienda/productos.js',
```

---

## 11. Navbar — `resources/views/layouts/public.blade.php`

### 11.1. Desktop

Reemplazar el bloque de 3 botones placeholder (líneas 31-35):

```blade
<div class="hidden md:flex items-center gap-1">
    <a href="{{ route('public.productos.index') }}"
       class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition rounded-lg hover:bg-emerald-50">
        Nuestros productos
    </a>
</div>
```

### 11.2. Mobile

Reemplazar los 3 botones placeholder del menú móvil (líneas 45-49):

```blade
<div id="mobile-menu" class="hidden md:hidden bg-white border-b border-stone-200 px-4 py-3">
    <div class="flex flex-col gap-1">
        <a href="{{ route('public.productos.index') }}"
           class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition rounded-lg hover:bg-emerald-50 w-full text-left">
            Nuestros productos
        </a>
    </div>
</div>
```

---

## 12. Consideraciones

### 12.1. Paginación
- 20 productos por página usando `paginate()` de Laravel.
- `withQueryString()` asegura que los filtros se preserven en los links de paginación.
- Para la recarga AJAX, la paginación se recibe como HTML dentro del partial y se reemplaza por completo.

### 12.2. Rendimiento
- La consulta de cantidad de productos por categoría (`$cat->productos()->visible()->count()`) se ejecuta una vez por categoría. Si hay muchas categorías, esto genera N+1 queries. **Optimización futura**: calcular counts en una sola query:
  ```php
  $counts = Producto::visible()
      ->selectRaw('categoria_id_categoria, COUNT(*) as total')
      ->groupBy('categoria_id_categoria')
      ->pluck('total', 'categoria_id_categoria');
  ```
  Luego en la vista: `$counts[$cat->id_categoria] ?? 0`.

### 12.3. Sin caché
- Por ahora no se aplica `CacheaPanel`. Las consultas son simples y paginadas. Si el volumen de productos crece (> 5000), considerar cachear el listado index con invalidación al crear/actualizar/desactivar productos.

### 12.4. Mobile
- Sidebar de categorías visible solo en `md:` en adelante.
- En móvil se muestra un botón "Filtros" que abre un panel lateral (slide-in) con las categorías.
- La grilla usa `grid-cols-2` en móvil y escala hasta `xl:grid-cols-5` en desktop grande.

### 12.5. Productos sin foto
- Se muestra un placeholder con icono SVG en lugar de la imagen.
- Consistente con el comportamiento del componente `product-slider`.

### 12.6. Sin stock pero activo
- Se muestra normalmente con badge "Sin stock" en la card.
- En la página de detalle se muestra un mensaje informativo en lugar del indicador de stock disponible.
- No se impide la navegación al detalle.

### 12.7. URLs amigables
- `Route::get('/productos/{producto}')` usa el bind implícito de Laravel por `id_producto`.
- Se puede extender para usar slug si se requiere en el futuro.
- Los filtros se reflejan en query params, no en el path.

---

## 13. Inventario Completo de Archivos

| Archivo | Acción |
|---------|--------|
| `app/Http/Controllers/Public/ProductoCatalogoController.php` | **Nuevo** |
| `app/Models/Producto.php` | Modificar (agregar scope `visible`) |
| `resources/views/public/productos/index.blade.php` | **Nuevo** |
| `resources/views/public/productos/_grid.blade.php` | **Nuevo** |
| `resources/views/public/productos/show.blade.php` | **Nuevo** |
| `resources/views/components/product-card.blade.php` | **Nuevo** |
| `resources/js/public/tienda/productos.js` | **Nuevo** |
| `resources/views/layouts/public.blade.php` | Modificar (navbar) |
| `routes/web.php` | Modificar (2 rutas) |
| `vite.config.js` | Modificar (1 entrada JS) |

---

## 14. Orden de Implementación

| Paso | Archivos | Descripción | Verificación |
|------|----------|-------------|--------------|
| 1 | `app/Models/Producto.php` | Agregar scope `visible()` | — |
| 2 | `app/Http/Controllers/Public/ProductoCatalogoController.php` | Crear controlador con index + show | — |
| 3 | `resources/views/components/product-card.blade.php` | Componente de card reutilizable | — |
| 4 | `resources/views/public/productos/_grid.blade.php` | Partial de grilla (para AJAX) | — |
| 5 | `resources/views/public/productos/index.blade.php` | Vista completa con sidebar + búsqueda | — |
| 6 | `resources/views/public/productos/show.blade.php` | Vista de detalle de producto | — |
| 7 | `resources/js/public/tienda/productos.js` | JS de filtros, búsqueda, móvil | — |
| 8 | `routes/web.php` | Agregar 2 rutas públicas | `php artisan route:list \| grep productos` |
| 9 | `resources/views/layouts/public.blade.php` | Reemplazar botones placeholder | Visual |
| 10 | `vite.config.js` | Agregar entrada JS | `npm run build` |
| 11 | Build + test | Compilar assets y probar flujo | `npm run build` + navegar `/productos` |

---

## 15. Posibles Extensiones Futuras

| Funcionalidad | Impacto |
|---------------|---------|
| **Slug en URL** | Agregar columna `slug` a `producto`, usar `Route::get('/productos/{producto:slug}')` |
| **Filtro por marca** | Agregar checkbox de marcas en sidebar, similar a categorías |
| **Filtro por rango de precio** | Inputs min/max, query param `precio_min` y `precio_max` |
| **Ordenamiento** | Select con opciones: menor precio, mayor precio, A-Z, Z-A |
| **Vista rápida (quick view)** | Modal con info del producto desde la grilla sin navegar al detalle |
| **Vista de galería / lista** | Toggle entre grid y lista |
| **Productos relacionados** | Sección en detalle con productos de la misma categoría |
| **Infinite scroll** | Reemplazar paginación con carga al hacer scroll |
| **Cache de counts** | Almacenar counts de productos por categoría para evitar N+1 |
