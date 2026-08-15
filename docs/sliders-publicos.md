# Gestor de Sliders Públicos — Módulo Administrativo

## 1. Propósito

Permitir que un usuario autorizado desde el panel administrativo gestione qué productos aparecen en los 3 sliders del área pública (portada), su orden y su asignación, sin modificar el esquema de la tabla `producto`.

---

## 2. Arquitectura

Se crea una **tabla pivote** `slider_producto` que relaciona productos con sliders. Los sliders son 3 tipos fijos:

| Tipo | Slider |
|------|--------|
| `buscados` | Más buscados |
| `vendidos` | Más vendidos |
| `descuentos` | En descuento |

No se agregan columnas a `producto`. No se tocan los Form Requests ni las vistas existentes de productos.

---

## 3. Base de Datos

### Migración: `create_slider_producto_table.php`

```php
Schema::create('slider_producto', function (Blueprint $table) {
    $table->id('id_slider_producto');
    $table->enum('slider_tipo', ['buscados', 'vendidos', 'descuentos']);
    $table->foreignId('producto_id_producto')
          ->constrained('producto', 'id_producto')
          ->onDelete('cascade');
    $table->integer('orden')->default(0);
    $table->timestamps();

    $table->unique(['slider_tipo', 'producto_id_producto']);
});
```

---

## 4. Modelo: `SliderProducto.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SliderProducto extends Model
{
    protected $table = 'slider_producto';
    protected $primaryKey = 'id_slider_producto';

    protected $fillable = [
        'slider_tipo',
        'producto_id_producto',
        'orden',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id_producto');
    }
}
```

---

## 5. Controlador Admin: `Admin/SliderController.php`

| Método | Ruta | Verbo | Descripción |
|--------|------|-------|-------------|
| `index()` | `/admin/sliders` | GET | Vista principal con los 3 sliders |
| `listar(tipo)` | `/admin/sliders/productos/{tipo}` | GET | Partial con productos asignados a un slider |
| `asignar(tipo)` | `/admin/sliders/asignar/{tipo}` | POST | Asigna un producto al slider |
| `quitar(tipo, producto)` | `/admin/sliders/quitar/{tipo}/{producto}` | DELETE | Remueve producto del slider |
| `reordenar(tipo)` | `/admin/sliders/reordenar/{tipo}` | POST | Recibe array y actualiza orden |
| `buscarProductos()` | `/admin/sliders/buscar-productos` | GET | Búsqueda AJAX de productos |

### Flujo del controlador

```
index():
    - Renderiza vista con los 3 tabs
    - No carga productos (se cargan por AJAX al hacer clic en cada tab)

listar(tipo):
    - SliderProducto::where('slider_tipo', $tipo)
        ->orderBy('orden')
        ->with('producto.categoria')
        ->get()
    - return view('admin.sliders._lista', compact('items', 'tipo'))

asignar(tipo):
    - Valida: producto_id existe, no duplicado en ese slider_tipo
    - Crea SliderProducto con orden = max + 1
    - return JSON { success }

quitar(tipo, producto):
    - SliderProducto::where('slider_tipo', $tipo)
        ->where('producto_id_producto', $producto)
        ->delete()
    - return JSON { success }

reordenar(tipo):
    - Recibe array [{producto_id, orden}]
    - upsert por cada uno
    - return JSON { success }

buscarProductos():
    - Producto::where('estado', 1)
        ->where('nombre', 'like', "%{$q}%")
        ->limit(20)
        ->get()
        ->map(fn($p) => [
            'id_producto'   => $p->id_producto,
            'nombre'        => $p->nombre,
            'precio_venta'  => $p->precio_venta,
            'foto_url'      => $p->foto_url,
            'categoria'     => $p->categoria?->nombre,
        ])
    - return JSON
```

---

## 6. Form Request: `AsignarSliderRequest.php`

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AsignarSliderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('sliders.gestionar') ?? false;
    }

    public function rules(): array
    {
        return [
            'producto_id' => 'required|integer|exists:producto,id_producto',
        ];
    }
}
```

La validación de unicidad (`slider_tipo + producto_id`) se hace en el controlador.

---

## 7. Vistas Admin: `resources/views/admin/sliders/`

### `index.blade.php`

```
- 3 tabs: "Más buscados" | "Más vendidos" | "En descuento"
- Cada tab carga vía AJAX el contenido de _lista.blade.php
- Botón "Agregar producto" en cada tab
- Botón "Agregar producto" abre modal con _modal-agregar.blade.php
```

### `_lista.blade.php` (partial)

Tabla con columnas:

| Columna | Contenido |
|---------|-----------|
| Orden | Input numérico (cambio → reordenar vía debounce) |
| Foto | `<img src="{{ $item->producto->foto_url }}" class="w-10 h-10 object-cover rounded">` |
| Nombre | `{{ $item->producto->nombre }}` |
| Categoría | `{{ $item->producto->categoria?->nombre }}` |
| Precio | `S/ {{ $item->producto->precio_venta }}` |
| Acción | Botón "Quitar" con confirmación |

### `_modal-agregar.blade.php` (partial)

- Input de búsqueda con autocomplete (debounce 300ms)
- Resultados en tabla seleccionable
- Botón "Agregar seleccionados"

---

## 8. JavaScript: `resources/js/admin/sliders/index.js`

```js
import axios from 'axios';

// Cambio de tabs → carga AJAX del listado
// Búsqueda de productos → debounce 300ms → Axios a buscarProductos
// Asignar → selecciona producto → POST a asignar
// Quitar → confirmación → DELETE a quitar
// Reordenar → onChange inputs orden → debounce 500ms → POST a reordenar

const SliderManager = {
    init() { ... },
    cargarTab(tipo) { ... },
    buscarProductos(q) { ... },
    asignar(tipo, productoId) { ... },
    quitar(tipo, productoId) { ... },
    reordenar(tipo) { ... },
};

document.addEventListener('DOMContentLoaded', () => SliderManager.init());
```

---

## 9. Controlador Público: `Public/HomeController.php`

```php
<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\SliderProducto;

class HomeController extends Controller
{
    public function index()
    {
        $sliders = SliderProducto::all()->groupBy('slider_tipo');

        $destacados  = $this->productosDeSlider($sliders, 'buscados');
        $masVendidos = $this->productosDeSlider($sliders, 'vendidos');
        $descuentos  = $this->productosDeSlider($sliders, 'descuentos');

        return view('welcome', compact('destacados', 'masVendidos', 'descuentos'));
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

## 10. Ruta Pública

```php
// routes/web.php

use App\Http\Controllers\Public\HomeController;

Route::get('/', [HomeController::class, 'index'])->name('home');
```

---

## 11. Rutas Admin

```php
// routes/admin.php

use App\Http\Controllers\Admin\SliderController;

Route::middleware('permission:sliders.gestionar')
    ->prefix('sliders')
    ->name('admin.sliders.')
    ->group(function () {

    Route::get('/', [SliderController::class, 'index'])->name('index');
    Route::get('/productos/{tipo}', [SliderController::class, 'listar'])->name('listar');
    Route::post('/asignar/{tipo}', [SliderController::class, 'asignar'])->name('asignar');
    Route::delete('/quitar/{tipo}/{producto}', [SliderController::class, 'quitar'])->name('quitar');
    Route::post('/reordenar/{tipo}', [SliderController::class, 'reordenar'])->name('reordenar');
    Route::get('/buscar-productos', [SliderController::class, 'buscarProductos'])->name('buscar');
});
```

---

## 12. Permisos Spatie

Un solo permiso nuevo: `sliders.gestionar`

Agregar en `database/seeders/RolePermissionSeeder.php`:

```php
$permiso = Permission::create(['name' => 'sliders.gestionar']);
$adminRole = Role::where('name', 'Administrador')->first();
$adminRole->givePermissionTo($permiso);
```

---

## 13. Componente `product-slider.blade.php`

Actualizar para recibir objetos Eloquent en lugar de arrays asociativos:

```blade
{{-- Cambiar $prod['img'] por $prod->foto_url --}}
{{-- Cambiar $prod['nombre'] por $prod->nombre --}}
{{-- Cambiar $prod['precio'] por $prod->precio_venta --}}
```

Asegurar que la card maneje el caso sin foto (`$prod->foto_url ?? asset('img/placeholder.jpg')`).

---

## 14. Vista `welcome.blade.php`

Reemplazar los 3 bloques `@php` con arrays hardcodeados por:

```blade
<x-product-slider id="buscados" titulo="Más buscados" :productos="$destacados" />
<x-product-slider id="vendidos" titulo="Más vendidos" :productos="$masVendidos" />
<x-product-slider id="descuentos" titulo="En descuento" :productos="$descuentos" />
```

Se puede eliminar el `@php` con datos mock.

---

## 15. Sidebar Admin — Link

Agregar enlace en `resources/views/layouts/admin.blade.php`:

```blade
<a href="/admin/sliders" data-panel="/admin/sliders"
   class="flex items-center gap-3 px-4 py-2 text-stone-600 hover:bg-stone-100 rounded-lg transition">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
    Sliders (Portada)
</a>
```

---

## 16. Vite — Entry JS

```js
// vite.config.js
laravel({
    input: [
        // ... entradas existentes ...
        'resources/js/admin/sliders/index.js',
    ],
    refresh: true,
}),
```

Cargar en la vista admin con:

```blade
@vite('resources/js/admin/sliders/index.js')
```

---

## 17. Inventario de Archivos

| Archivo | Acción |
|---------|--------|
| `database/migrations/xxxx_xx_xx_create_slider_producto_table.php` | **Nuevo** |
| `app/Models/SliderProducto.php` | **Nuevo** |
| `app/Http/Controllers/Admin/SliderController.php` | **Nuevo** |
| `app/Http/Requests/Admin/AsignarSliderRequest.php` | **Nuevo** |
| `resources/views/admin/sliders/index.blade.php` | **Nuevo** |
| `resources/views/admin/sliders/_lista.blade.php` | **Nuevo** |
| `resources/views/admin/sliders/_modal-agregar.blade.php` | **Nuevo** |
| `resources/js/admin/sliders/index.js` | **Nuevo** |
| `app/Http/Controllers/Public/HomeController.php` | **Nuevo** |
| `routes/web.php` | Modificar |
| `routes/admin.php` | Modificar |
| `database/seeders/RolePermissionSeeder.php` | Modificar |
| `resources/views/welcome.blade.php` | Modificar |
| `resources/views/components/product-slider.blade.php` | Modificar |
| `resources/views/layouts/admin.blade.php` | Modificar |
| `vite.config.js` | Modificar |

---

## 18. Orden de Implementación

| Paso | Archivos | Descripción |
|------|----------|-------------|
| 1 | Migración | Crear tabla `slider_producto` |
| 2 | `SliderProducto.php` | Modelo con relaciones |
| 3 | `RolePermissionSeeder.php` | Agregar permiso `sliders.gestionar` |
| 4 | `AsignarSliderRequest.php` | Form Request |
| 5 | `SliderController.php` | Controlador con 6 métodos |
| 6 | Vistas admin | `index.blade.php`, `_lista.blade.php`, `_modal-agregar.blade.php` |
| 7 | `index.js` | Lógica del gestor de sliders |
| 8 | `vite.config.js` | Agregar entrada JS |
| 9 | `layouts/admin.blade.php` | Sidebar link |
| 10 | `HomeController.php` | Controlador público |
| 11 | `routes/web.php` | Ruta raíz |
| 12 | `routes/admin.php` | Grupo de rutas sliders |
| 13 | `product-slider.blade.php` | Adaptar a objetos Eloquent |
| 14 | `welcome.blade.php` | Reemplazar datos mock |
| 15 | `php artisan migrate` | Ejecutar migración |
| 16 | `npm run build` | Compilar assets |
| 17 | Verificar | Probar flujo completo |

---

## 19. Consideraciones

- **Sin cambios a `producto`**: La tabla pivote evita migraciones y cambios en la lógica existente de productos.
- **Cache**: Si `CacheaPanel` está en uso para vistas admin, el módulo sliders no se ve afectado. Si se desea, se puede cachear la vista `index` de sliders e invalidar al asignar/quitar/reordenar.
- **Productos desactivados**: Si un producto se desactiva (estado=0) desde el módulo de productos, seguirá apareciendo en `slider_producto` pero el controlador público lo filtrará con `where('estado', 1)`.
- **Eliminación en cascada**: La FK con `onDelete('cascade')` limpia automáticamente `slider_producto` si un producto se elimina físicamente.
- **Responsive**: Los sliders públicos ya funcionan con el componente existente y su JS de drag/autoplay/responsive.
