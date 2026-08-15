# Módulo Categorías en Portada

## 1. Propósito

Permitir que un usuario autorizado seleccione qué categorías del sistema se muestran en la página de inicio pública (sobre el carrusel de banners) y en qué orden. Actualmente estas categorías están hardcodeadas como 6 botones en `welcome.blade.php`.

A futuro, estas categorías enlazarán a una página de listado de productos filtrados por esa categoría.

---

## 2. Arquitectura

Se crea una **tabla pivote** `portada_categoria` que relaciona categorías existentes con la portada, similar al patrón `slider_producto` para productos.

```
PUBLICO (sin auth)
  GET /  -->  HomeController@index
                |-- PortadaCategoria::orderBy('orden')->with('categoria')->get()
                |-- view('welcome', compact('categoriasPortada', 'banners', ...))

ADMIN (con auth + permiso portada.categorias)
  GET  /admin/portada-categorias                    -> PortadaCategoriaController@index
  POST /admin/portada-categorias/toggle/{categoria}  -> PortadaCategoriaController@toggle
  POST /admin/portada-categorias/reordenar           -> PortadaCategoriaController@reordenar
```

---

## 3. Base de Datos

### Migración: `create_portada_categoria_table.php`

```php
Schema::create('portada_categoria', function (Blueprint $table) {
    $table->id('id_portada_categoria');
    $table->foreignId('categoria_id_categoria')
          ->constrained('categoria', 'id_categoria')
          ->onDelete('cascade');
    $table->integer('orden')->default(0);
    $table->timestamps();

    $table->unique('categoria_id_categoria');
});
```

La unique en `categoria_id_categoria` garantiza que una categoría solo aparezca una vez en la portada.

---

## 4. Modelo: `PortadaCategoria.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortadaCategoria extends Model
{
    protected $table = 'portada_categoria';

    protected $primaryKey = 'id_portada_categoria';

    protected $fillable = [
        'categoria_id_categoria',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id_categoria');
    }
}
```

---

## 5. Permiso Spatie

Un solo permiso nuevo: `portada.categorias`

Agregar en `database/seeders/PermissionSeeder.php`:

```php
Permission::firstOrCreate(['name' => 'portada.categorias']);
```

Agregar en `database/seeders/RoleSeeder.php` al rol Administrador:

```php
'portada.categorias',
```

---

## 6. Controlador Admin: `Admin/PortadaCategoriaController.php`

### Métodos

| Método | Ruta | Verbo | Descripción |
|--------|------|-------|-------------|
| `index()` | `/admin/portada-categorias` | GET | Lista de categorías con indicador de selección y orden |
| `toggle(categoria)` | `/admin/portada-categorias/toggle/{categoria}` | POST | Agrega/quita una categoría de la portada |
| `reordenar()` | `/admin/portada-categorias/reordenar` | POST | Recibe array de ids y actualiza orden |

### Flujo del controlador

```php
index():
    $todas = Categoria::where('estado', 1)->orderBy('nombre')->get()
    $seleccionadas = PortadaCategoria::orderBy('orden')->pluck('categoria_id_categoria')->toArray()
    if (request()->ajax()) → return partial view
    else → return view completa

toggle(Categoria $categoria):
    $existe = PortadaCategoria::where('categoria_id_categoria', $categoria->id_categoria)->exists()
    SI existe → delete
    SI no existe → create con orden = max + 1
    JSON { success, message, activa: boolean }

reordenar():
    request()->validate([
        'items'   => 'required|array',
        'items.*' => 'required|integer|exists:portada_categoria,id_portada_categoria',
    ])
    foreach items as $i => $id
        PortadaCategoria::where('id_portada_categoria', $id)->update(['orden' => $i])
    JSON { success, message }
```

---

## 7. Vistas Admin: `resources/views/admin/portada-categorias/`

### `index.blade.php` (partial — SPA)

```
- Encabezado: "Categorías en Portada"
- Grilla de tarjetas, una por cada categoría activa del sistema:

  ┌──────────────────────────────────────┐
  │  [toggle switch]  Nombre categoría   │
  │                     Orden: [   ]     │
  └──────────────────────────────────────┘

- Cada card muestra:
  - Toggle switch con estilo (activo/inactivo en portada)
  - Nombre de la categoría
  - Input de orden visible solo si la categoría está activa en portada

- Las categorías con estado=0 no se muestran.
- Mensaje si no hay categorías activas en el sistema.

Comportamiento:
- Toggle → POST /admin/portada-categorias/toggle/{id}
- Si se activa → aparece input de orden con valor asignado
- Si se desactiva → input de orden se oculta
- Input de orden cambia → debounce 500ms → POST /admin/portada-categorias/reordenar
```

### `full.blade.php` (vista completa)

```blade
@extends('layouts.admin')

@section('title', 'Categorías en Portada')

@section('content')
    @include('admin.portada-categorias.index')
@endsection
```

---

## 8. JavaScript: `resources/js/admin/portada-categorias/index.js`

```js
import axios from 'axios';

const PortadaCategoriasManager = {
    init() {
        this.bindEvents();
    },

    bindEvents() {
        document.addEventListener('click', (e) => {
            const toggle = e.target.closest('[data-toggle-portada]');
            if (toggle) {
                e.preventDefault();
                this.toggle(toggle.dataset.togglePortada);
            }
        });

        document.addEventListener('change', (e) => {
            const input = e.target.closest('.input-orden-portada');
            if (input) {
                this.reordenar();
            }
        });
    },

    async toggle(categoriaId) {
        try {
            const res = await axios.post(`/admin/portada-categorias/toggle/${categoriaId}`);
            if (res.data.success) {
                await this.recargar();
                if (window.AdminToast) {
                    AdminToast.show(res.data.message, 'success');
                }
            }
        } catch (err) {
            if (window.AdminToast) {
                AdminToast.show('Error al cambiar estado', 'error');
            }
        }
    },

    async reordenar() {
        const inputs = document.querySelectorAll('.input-orden-portada');
        const items = Array.from(inputs)
            .map(input => ({
                id: parseInt(input.dataset.pcId),
                orden: parseInt(input.value) || 0,
            }))
            .sort((a, b) => a.orden - b.orden)
            .map(item => item.id);

        if (items.length === 0) return;

        try {
            await axios.post('/admin/portada-categorias/reordenar', { items });
        } catch (err) {
            console.error('Error al reordenar:', err);
        }
    },

    async recargar() {
        try {
            const res = await axios.get('/admin/portada-categorias');
            const panel = document.getElementById('panel-content');
            if (panel) panel.innerHTML = res.data;
        } catch (err) {
            console.error('Error al recargar:', err);
        }
    },
};

document.addEventListener('DOMContentLoaded', () => PortadaCategoriasManager.init());
```

---

## 9. Vite

Agregar entrada en `vite.config.js`:

```js
'resources/js/admin/portada-categorias/index.js',
```

---

## 10. Sidebar Admin

Agregar en `resources/views/layouts/admin.blade.php` dentro de la sección "Portada", después del link de Banners:

```blade
@can('portada.categorias')
<a href="#" data-panel="/admin/portada-categorias" class="flex items-center gap-3 px-3 py-2 rounded-lg text-emerald-100 hover:bg-emerald-700 hover:text-white transition text-sm font-medium">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
    </svg>
    Categorías en Portada
</a>
@endcan
```

---

## 11. Rutas Admin

Agregar en `routes/admin.php`:

```php
use App\Http\Controllers\Admin\PortadaCategoriaController;

Route::middleware('permission:portada.categorias')
    ->prefix('portada-categorias')
    ->name('admin.portada-categorias.')
    ->group(function () {
        Route::get('/', [PortadaCategoriaController::class, 'index'])->name('index');
        Route::post('/toggle/{categoria}', [PortadaCategoriaController::class, 'toggle'])->name('toggle');
        Route::post('/reordenar', [PortadaCategoriaController::class, 'reordenar'])->name('reordenar');
    });
```

---

## 12. Controlador Público

Modificar `app/Http/Controllers/Public/HomeController.php`:

```php
use App\Models\PortadaCategoria;

public function index()
{
    $categoriasPortada = PortadaCategoria::orderBy('orden')
        ->with('categoria')
        ->get()
        ->map(fn($pc) => $pc->categoria)
        ->filter()
        ->values();

    $banners = Banner::where('activo', true)->orderBy('orden')->get();

    $sliders = SliderProducto::all()->groupBy('slider_tipo');

    $destacados  = $this->productosDeSlider($sliders, 'buscados');
    $masVendidos = $this->productosDeSlider($sliders, 'vendidos');
    $descuentos  = $this->productosDeSlider($sliders, 'descuentos');

    return view('welcome', compact(
        'categoriasPortada', 'banners',
        'destacados', 'masVendidos', 'descuentos'
    ));
}
```

---

## 13. Vista Pública `welcome.blade.php`

Reemplazar la sección de categorías hardcodeada (líneas 11-32):

```blade
{{-- Antes (hardcodeado) --}}
<section class="max-w-7xl mx-auto px-4 py-6" aria-label="Categorías">
    <div class="flex flex-wrap justify-center gap-3">
        <button class="bg-emerald-100 hover:bg-emerald-200 text-emerald-800 text-sm font-medium px-5 py-2 rounded-full transition">
            Categoría 1
        </button>
        ... 5 más ...
    </div>
</section>

{{-- Después (dinámico) --}}
<section class="max-w-7xl mx-auto px-4 py-6" aria-label="Categorías">
    <div class="flex flex-wrap justify-center gap-3">
        @forelse ($categoriasPortada as $categoria)
            <a href="{{ route('home') }}?categoria={{ $categoria->id_categoria }}"
               class="bg-emerald-100 hover:bg-emerald-200 text-emerald-800 text-sm font-medium px-5 py-2 rounded-full transition">
                {{ $categoria->nombre }}
            </a>
        @empty
            <span class="text-sm text-stone-400">No hay categorías disponibles</span>
        @endforelse
    </div>
</section>
```

---

## 14. Consideraciones

- **Sin imágenes, sin Cloudinary**: El módulo es solo selección + orden. No requiere subida de archivos.
- **FK con cascade**: Si se elimina una categoría del sistema, su registro en `portada_categoria` se limpia automáticamente.
- **Categorías inactivas**: No se muestran en la vista admin ni en la portada. El controlador admin solo lista categorías con `estado = 1`.
- **Unique constraint**: Garantiza que cada categoría aparezca solo una vez en la portada.
- **Toggle simple**: Un solo endpoint `toggle` que agrega si no existe o elimina si ya existe. Sin modal, sin formularios complejos.
- **Sin cache**: Consultas mínimas, no requiere el trait `CacheaPanel`.
- **Responsive**: Las categorías usan `flex flex-wrap justify-center gap-3`, funciona en todos los viewports.
- **Ruta futura**: El `href` de cada categoría apunta temporalmente a la home con query param. Cuando se desarrolle la página de categoría, se reemplazará por la ruta real.

---

## 15. Inventario Completo de Archivos

| Archivo | Acción |
|---------|--------|
| `database/migrations/xxxx_xx_xx_create_portada_categoria_table.php` | **Nuevo** |
| `app/Models/PortadaCategoria.php` | **Nuevo** |
| `database/seeders/PermissionSeeder.php` | Modificar |
| `database/seeders/RoleSeeder.php` | Modificar |
| `app/Http/Controllers/Admin/PortadaCategoriaController.php` | **Nuevo** |
| `resources/views/admin/portada-categorias/index.blade.php` | **Nuevo** |
| `resources/views/admin/portada-categorias/full.blade.php` | **Nuevo** |
| `resources/js/admin/portada-categorias/index.js` | **Nuevo** |
| `vite.config.js` | Modificar |
| `resources/views/layouts/admin.blade.php` | Modificar |
| `routes/admin.php` | Modificar |
| `app/Http/Controllers/Public/HomeController.php` | Modificar |
| `resources/views/welcome.blade.php` | Modificar |

---

## 16. Orden de Implementación

| Paso | Archivos | Descripción | Verificación |
|------|----------|-------------|--------------|
| 1 | Migración + `PortadaCategoria.php` | Crear tabla pivote y modelo | `php artisan migrate` |
| 2 | `PermissionSeeder.php` + `RoleSeeder.php` | Agregar permiso y asignar a Admin | Ejecutar seeders |
| 3 | `PortadaCategoriaController.php` | 3 métodos: index, toggle, reordenar | — |
| 4 | `index.blade.php` + `full.blade.php` | Vistas admin con toggles y orden | — |
| 5 | `index.js` | Lógica del gestor | — |
| 6 | `vite.config.js` | Agregar entrada JS | `npm run build` |
| 7 | `layouts/admin.blade.php` | Link en sidebar | Visual |
| 8 | `routes/admin.php` | Grupo de rutas | — |
| 9 | `HomeController.php` | Cargar categorías de portada | — |
| 10 | `welcome.blade.php` | Reemplazar botones mock | Visual |
| 11 | `php artisan migrate` + `php artisan db:seed` + `npm run build` | Ejecutar | Flujo completo |
