# Módulo Banner — Imágenes del Carrusel de Portada

## 1. Propósito

Permitir que un usuario autorizado desde el panel administrativo gestione las imágenes del carrusel (slider de imágenes) que se muestran en la página de inicio pública. Actualmente estas imágenes están hardcodeadas en `welcome.blade.php` con URLs de picsum.photos.

---

## 2. Arquitectura

Se crea una tabla independiente `banner` que almacena cada imagen del carrusel con su título, subtítulo, enlace opcional, orden y estado activo.

No depende de la tabla `producto` ni del sistema existente `slider_producto`. Es un módulo independiente dentro del bloque "Portada" del panel administrativo.

```
PUBLICO (sin auth)
  GET /  -->  HomeController@index
                |-- Banner::where('activo', true)->orderBy('orden')->get()
                |-- view('welcome', compact('banners', 'destacados', 'masVendidos', 'descuentos'))
                |     |-- Carrusel con @foreach($banners as $banner)
                |
                +-- JS: catalogo.js (carrusel, sin cambios)

ADMIN (con auth + permiso portada.banners)
  GET    /admin/banners                  -> BannerController@index
  GET    /admin/banners/create           -> BannerController@create   (modal)
  POST   /admin/banners                  -> BannerController@store
  GET    /admin/banners/{banner}/edit    -> BannerController@edit     (modal)
  PUT    /admin/banners/{banner}         -> BannerController@update
  DELETE /admin/banners/{banner}         -> BannerController@destroy
  POST   /admin/banners/reordenar        -> BannerController@reordenar
```

---

## 3. Base de Datos

### Migración: `create_banner_table.php`

```php
Schema::create('banner', function (Blueprint $table) {
    $table->id('id_banner');
    $table->string('titulo', 255)->nullable();
    $table->string('subtitulo', 255)->nullable();
    $table->text('imagen_url');
    $table->string('enlace', 255)->nullable();
    $table->integer('orden')->default(0);
    $table->boolean('activo')->default(true);
    $table->timestamps();
});
```

---

## 4. Modelo: `Banner.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $table = 'banner';

    protected $primaryKey = 'id_banner';

    protected $fillable = [
        'titulo',
        'subtitulo',
        'imagen_url',
        'enlace',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden'  => 'integer',
    ];
}
```

---

## 5. Permiso Spatie

Un solo permiso nuevo: `portada.banners`

Agregar en `database/seeders/PermissionSeeder.php`:

```php
Permission::firstOrCreate(['name' => 'portada.banners']);
```

---

## 6. Form Requests

### `StoreBannerRequest.php`

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('portada.banners') ?? false;
    }

    public function rules(): array
    {
        return [
            'imagen'   => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'titulo'   => 'nullable|string|max:255',
            'subtitulo'=> 'nullable|string|max:255',
            'enlace'   => 'nullable|url|max:255',
        ];
    }

    public function attributes(): array
    {
        return [
            'imagen'    => 'imagen del banner',
            'titulo'    => 'título',
            'subtitulo' => 'subtítulo',
            'enlace'    => 'enlace',
        ];
    }
}
```

### `UpdateBannerRequest.php`

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('portada.banners') ?? false;
    }

    public function rules(): array
    {
        return [
            'imagen'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'titulo'   => 'nullable|string|max:255',
            'subtitulo'=> 'nullable|string|max:255',
            'enlace'   => 'nullable|url|max:255',
        ];
    }

    public function attributes(): array
    {
        return [
            'imagen'    => 'imagen del banner',
            'titulo'    => 'título',
            'subtitulo' => 'subtítulo',
            'enlace'    => 'enlace',
        ];
    }
}
```

---

## 7. Controlador Admin: `Admin/BannerController.php`

### Métodos

| Método | Ruta | Verbo | Descripción |
|--------|------|-------|-------------|
| `index()` | `/admin/banners` | GET | Lista de banners ordenados, reordenables |
| `create()` | `/admin/banners/create` | GET | Modal con formulario de creación |
| `store()` | `/admin/banners` | POST | Crear banner con imagen subida a Cloudinary |
| `edit()` | `/admin/banners/{banner}/edit` | GET | Modal con formulario de edición |
| `update()` | `/admin/banners/{banner}` | PUT | Actualizar banner (imagen opcional) |
| `destroy()` | `/admin/banners/{banner}` | DELETE | Eliminar banner + imagen de Cloudinary |
| `reordenar()` | `/admin/banners/reordenar` | POST | Recibe array de ids y actualiza orden |

### Flujo del controlador

```
index():
    - Banner::orderBy('orden')->get()
    - if (request()->ajax()) → return partial
    - else → return view completa (full)

create():
    - if (request()->ajax()) → return modal form
    - else → redirect a index

store(StoreBannerRequest):
    - Subir imagen a Cloudinary
    - Banner::create([...datos + 'imagen_url' => $secure_url])
    - if (request()->ajax()) → JSON { success, message }
    - else → redirect con mensaje

edit(Banner):
    - if (request()->ajax()) → return modal form con $banner
    - else → redirect a index

update(UpdateBannerRequest, Banner):
    - SI nueva imagen: eliminar anterior de Cloudinary + subir nueva
    - Actualizar banner
    - if (request()->ajax()) → JSON { success, message }
    - else → redirect con mensaje

destroy(Banner):
    - Eliminar imagen de Cloudinary
    - $banner->delete()
    - if (request()->ajax()) → JSON { success, message }
    - else → redirect con mensaje

reordenar():
    - request()->validate([...])
    - foreach items: Banner::where('id_banner', $id)->update(['orden' => $i])
    - JSON { success }
```

### Cloudinary Integration

Sigue el mismo patrón que `Admin/ProductoController` (ver `docs/plan-integracion-cloudinary.md`):

```php
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

// Subir imagen:
$result = Cloudinary::uploadApi()->upload(
    $request->file('imagen')->getRealPath(),
    ['folder' => 'banners']
);
$imagenUrl = $result['secure_url'];

// Eliminar imagen anterior:
$publicId = 'banners/' . pathinfo($banner->imagen_url, PATHINFO_FILENAME);
Cloudinary::uploadApi()->destroy($publicId);
```

---

## 8. Vistas Admin: `resources/views/admin/banners/`

### `index.blade.php` (partial — SPA)

```
- Encabezado: "Banners — Portada" + botón "+ Nuevo banner"
- Tabla de banners con columnas:
  | Arrastrar/Orden | Miniatura | Título | Subtítulo | Activo | Enlace | Acciones |

- Cada fila muestra:
  - Input numérico de orden (cambio → reordenar vía debounce)
  - <img> con la miniatura (w-16 h-10 object-cover)
  - Título del banner
  - Subtítulo (truncado)
  - Badge "Activo"/"Inactivo"
  - Enlace (truncado o "—")
  - Botones: Editar, Eliminar (con confirmación)
- Mensaje de empty state si no hay banners
```

### `create.blade.php` (partial — modal)

```html
<form action="{{ route('admin.banners.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <!-- Encabezado: "Nuevo banner" + botón cerrar -->

    <!-- Campo: Imagen (file input + preview) -->
    <!-- Campo: Título (text) -->
    <!-- Campo: Subtítulo (text) -->
    <!-- Campo: Enlace (url) -->

    <!-- Botones: Cancelar + Guardar -->
</form>

@vite('resources/js/admin/banners/index.js')
```

### `edit.blade.php` (partial — modal)

```html
<form action="{{ route('admin.banners.update', $banner) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <!-- Preview de imagen actual -->
    <!-- Campo: Imagen (file input + preview para nueva) -->
    <!-- Campo: Título (text, valor actual) -->
    <!-- Campo: Subtítulo (text, valor actual) -->
    <!-- Campo: Enlace (url, valor actual) -->
    <input type="hidden" name="activo" value="0">
    <label><input type="checkbox" name="activo" value="1" ...> Activo</label>

    <!-- Botones: Cancelar + Guardar -->
</form>

@vite('resources/js/admin/banners/index.js')
```

### `full.blade.php` (vista completa)

```blade
@extends('layouts.admin')

@section('title', 'Banners — Portada')

@section('content')
    @include('admin.banners.index')
@endsection
```

---

## 9. JavaScript: `resources/js/admin/banners/index.js`

```js
import axios from 'axios';

const BannerManager = {
    init() {
        this.bindEvents();
    },

    bindEvents() {
        // Delegación: submit de forms de banner
        document.addEventListener('submit', (e) => {
            const form = e.target.closest('#modal-container form[action*="banners"]');
            if (!form) return;
            e.preventDefault();
            this.submitForm(form);
        });

        // Delegación: click en editar
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-editar-banner]');
            if (btn) {
                e.preventDefault();
                this.editar(btn.dataset.editarBanner);
            }
        });

        // Delegación: click en eliminar
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-eliminar-banner]');
            if (btn) {
                e.preventDefault();
                this.eliminar(btn.dataset.eliminarBanner);
            }
        });

        // Delegación: crear nuevo
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-crear-banner]');
            if (btn) {
                e.preventDefault();
                this.crear();
            }
        });

        // Reordenar con debounce en inputs de orden
        // Preview de imagen en modales
    },

    async crear() { ... },
    async editar(id) { ... },
    async eliminar(id) { ... },
    async submitForm(form) { ... },
    async reordenar() { ... },
};

document.addEventListener('DOMContentLoaded', () => BannerManager.init());
```

Funcionalidades:
- Abrir modal de creación (`data-crear-banner`)
- Abrir modal de edición (`data-editar-banner="{id}"`)
- Confirmar y eliminar (`data-eliminar-banner="{id}"`)
- Submit de formulario vía AJAX (con FormData para incluir archivo)
- Preview de imagen al seleccionar archivo
- Reordenar al cambiar input de orden (debounce 500ms)
- Recargar tabla después de operaciones exitosas

---

## 10. Vite

Agregar entrada en `vite.config.js`:

```js
'resources/js/admin/banners/index.js',
```

---

## 11. Sidebar Admin

Agregar en `resources/views/layouts/admin.blade.php` dentro de la sección "Portada":

```blade
<p class="text-xs font-semibold text-emerald-300 uppercase tracking-wider px-3 pt-3 pb-1">Portada</p>
@can('sliders.gestionar')
<a href="#" data-panel="/admin/sliders" class="...">Sliders (Portada)</a>
@endcan
@can('portada.banners')
<a href="#" data-panel="/admin/banners" class="flex items-center gap-3 px-3 py-2 rounded-lg text-emerald-100 hover:bg-emerald-700 hover:text-white transition text-sm font-medium">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
    </svg>
    Banners
</a>
@endcan
```

---

## 12. Rutas Admin

Agregar en `routes/admin.php`:

```php
use App\Http\Controllers\Admin\BannerController;

Route::middleware('permission:portada.banners')
    ->prefix('banners')
    ->name('admin.banners.')
    ->group(function () {
        Route::get('/', [BannerController::class, 'index'])->name('index');
        Route::get('/create', [BannerController::class, 'create'])->name('create');
        Route::post('/', [BannerController::class, 'store'])->name('store');
        Route::get('/{banner}/edit', [BannerController::class, 'edit'])->name('edit');
        Route::put('/{banner}', [BannerController::class, 'update'])->name('update');
        Route::delete('/{banner}', [BannerController::class, 'destroy'])->name('destroy');
        Route::post('/reordenar', [BannerController::class, 'reordenar'])->name('reordenar');
    });
```

---

## 13. Controlador Público

Modificar `app/Http/Controllers/Public/HomeController.php`:

```php
<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Producto;
use App\Models\SliderProducto;

class HomeController extends Controller
{
    public function index()
    {
        $banners = Banner::where('activo', true)->orderBy('orden')->get();

        $sliders = SliderProducto::all()->groupBy('slider_tipo');

        $destacados  = $this->productosDeSlider($sliders, 'buscados');
        $masVendidos = $this->productosDeSlider($sliders, 'vendidos');
        $descuentos  = $this->productosDeSlider($sliders, 'descuentos');

        return view('welcome', compact('banners', 'destacados', 'masVendidos', 'descuentos'));
    }

    private function productosDeSlider($sliders, string $tipo)
    {
        $items = collect($sliders[$tipo] ?? [])->sortBy('orden');
        $ids   = $items->pluck('producto_id_producto');

        $productos = Producto::whereIn('id_producto', $ids)
            ->where('estado', 1)
            ->get()
            ->keyBy('id_producto');

        return $items->map(fn($s) => $productos->get($s->producto_id_producto))
            ->filter()
            ->values();
    }
}
```

---

## 14. Vista Pública `welcome.blade.php`

Reemplazar el bloque `@php $slides = [...]` y el `@foreach` que它 usa por el nuevo `$banners`:

```blade
{{-- Antes: --}}
@php
    $slides = [
        ['img' => 'https://picsum.photos/seed/botica1/1200/500', 'alt' => 'Oferta 1'],
        // ... 6 slides hardcodeados
    ];
@endphp
@foreach ($slides as $slide)
    <div class="min-w-full" role="listitem">
        <img src="{{ $slide['img'] }}" alt="{{ $slide['alt'] }}" ...>
    </div>
@endforeach

{{-- Después: --}}
@foreach ($banners as $banner)
    <div class="min-w-full" role="listitem">
        @if($banner->enlace)
            <a href="{{ $banner->enlace }}" target="_blank" rel="noopener noreferrer">
        @endif
        <img src="{{ $banner->imagen_url }}" alt="{{ $banner->titulo ?? 'Banner' }}"
            class="w-full h-64 sm:h-80 md:h-96 object-cover">
        @if($banner->titulo || $banner->subtitulo)
            <div class="absolute inset-0 flex flex-col items-center justify-center bg-black/20 text-white">
                @if($banner->titulo)
                    <h2 class="text-2xl sm:text-4xl font-bold drop-shadow-lg">{{ $banner->titulo }}</h2>
                @endif
                @if($banner->subtitulo)
                    <p class="text-sm sm:text-lg mt-2 drop-shadow-md">{{ $banner->subtitulo }}</p>
                @endif
            </div>
        @endif
        @if($banner->enlace)
            </a>
        @endif
    </div>
@endforeach
```

Nota: El contenedor del carrusel actualmente usa `relative` en `#carousel`, por lo que el overlay de título/subtítulo con `absolute` funcionará correctamente.

Los dots y botones de navegación siguen igual: se generan con `$banners->count()`.

---

## 15. Consideraciones

- **Cloudinary**: Las imágenes se suben a una carpeta `banners/` dentro de Cloudinary para mantenerlas separadas de las fotos de productos.
- **Eliminación**: Al eliminar un banner, también se elimina la imagen de Cloudinary usando el `public_id` extraído de la URL.
- **Orden**: Los banners se ordenan por el campo `orden` (ascendente). El admin permite reordenar arrastrando o editando el número.
- **Activo/inactivo**: Permite desactivar banners sin eliminarlos (útil para campañas temporales).
- **Sin cache**: El módulo es simple y no requiere el trait `CacheaPanel` (los banners cambian con poca frecuencia, pero la consulta es mínima).
- **Responsive**: El carrusel público ya es responsive gracias a `catalogo.js` y las clases Tailwind existentes. No requiere cambios.
- **Sin enlace opcional**: Si `enlace` es null, la imagen se muestra sin `<a>` envolvente.
- **JS existente**: `catalogo.js` no necesita modificaciones porque solo opera sobre IDs (`#carousel-track`, `#carousel-prev`, `#carousel-next`, `.carousel-dot`) que se mantienen igual.

---

## 16. Inventario Completo de Archivos

| Archivo | Acción |
|---------|--------|
| `database/migrations/xxxx_xx_xx_create_banner_table.php` | **Nuevo** |
| `app/Models/Banner.php` | **Nuevo** |
| `database/seeders/PermissionSeeder.php` | Modificar |
| `app/Http/Requests/Admin/StoreBannerRequest.php` | **Nuevo** |
| `app/Http/Requests/Admin/UpdateBannerRequest.php` | **Nuevo** |
| `app/Http/Controllers/Admin/BannerController.php` | **Nuevo** |
| `resources/views/admin/banners/index.blade.php` | **Nuevo** |
| `resources/views/admin/banners/create.blade.php` | **Nuevo** |
| `resources/views/admin/banners/edit.blade.php` | **Nuevo** |
| `resources/views/admin/banners/full.blade.php` | **Nuevo** |
| `resources/js/admin/banners/index.js` | **Nuevo** |
| `vite.config.js` | Modificar |
| `resources/views/layouts/admin.blade.php` | Modificar |
| `routes/admin.php` | Modificar |
| `app/Http/Controllers/Public/HomeController.php` | Modificar |
| `resources/views/welcome.blade.php` | Modificar |

---

## 17. Orden de Implementación

| Paso | Archivos | Descripción | Verificación |
|------|----------|-------------|--------------|
| 1 | Migración + `Banner.php` | Crear tabla `banner` y modelo | `php artisan migrate` |
| 2 | `PermissionSeeder.php` | Agregar `portada.banners` | `php artisan db:seed --class=PermissionSeeder` |
| 3 | `StoreBannerRequest.php` | Validación de creación | — |
| 4 | `UpdateBannerRequest.php` | Validación de actualización | — |
| 5 | `BannerController.php` | CRUD completo con Cloudinary | — |
| 6 | `index.blade.php` | Partial con tabla reordenable | — |
| 7 | `create.blade.php` | Modal de creación | — |
| 8 | `edit.blade.php` | Modal de edición | — |
| 9 | `full.blade.php` | Vista completa | — |
| 10 | `index.js` | Lógica del gestor de banners | — |
| 11 | `vite.config.js` | Agregar entrada JS | `npm run build` |
| 12 | `layouts/admin.blade.php` | Link en sidebar | Visual |
| 13 | `routes/admin.php` | Grupo de rutas | — |
| 14 | `HomeController.php` | Cargar banners activos | — |
| 15 | `welcome.blade.php` | Reemplazar datos mock | Visual |
| 16 | Migrar + Build | Ejecutar migración y compilar | `php artisan migrate` + `npm run build` |
| 17 | Verificación | Probar flujo completo | Login admin → crear banner → ver portada |
