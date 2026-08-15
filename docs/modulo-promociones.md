# Módulo Promociones — Bloque de Promociones en Portada

## 1. Propósito

Gestionar promociones visuales y comerciales en la portada pública del sistema. El módulo permite crear promociones con diferentes propósitos comerciales: simples (imagen + enlace), descuentos sobre productos/marcas/categorías, y combos de productos con precio especial. Cada promoción se despliega en un grid responsivo de 12 columnas con tres tamaños posibles (principal, secundaria, terciaria) y cuenta con tracking de clicks.

---

## 2. Arquitectura General

```
PÚBLICO (sin auth)
  GET  /                           → HomeController@index
  GET  /combos/{combo}             → ComboController@show
  POST /promociones/{promocion}/click → incrementa contador de clicks

  welcome.blade.php:
    - Promocion::where('activo', true)->orderBy('orden')->get()
    - Grid 12 columnas
      - Principal:  col-span-12 (100%)
      - Secundaria: col-span-3  (25%)
      - Terciaria:  col-span-6  (50%)
    - Redirección dinámica según tipo_promocion:
        NULL       → enlace manual
        descuento  → /productos/{id} | /productos?categorias[]=X | /productos?marcas[]=Y
        combo      → /combos/{id}

ADMIN (con auth + permisos portada.promociones y combos)
  CRUD Promociones:
    GET    /admin/promociones                   → PromocionController@index
    GET    /admin/promociones/create            → PromocionController@create
    POST   /admin/promociones                   → PromocionController@store
    GET    /admin/promociones/{id}/edit         → PromocionController@edit
    PUT    /admin/promociones/{id}              → PromocionController@update
    DELETE /admin/promociones/{id}              → PromocionController@destroy
    POST   /admin/promociones/reordenar         → PromocionController@reordenar
    GET    /admin/promociones/buscar-entidad    → PromocionController@buscarEntidad (AJAX)

  CRUD Combos (submódulo en Inventario > Productos):
    GET    /admin/combos                        → ComboController@index
    GET    /admin/combos/create                 → ComboController@create
    POST   /admin/combos                        → ComboController@store
    GET    /admin/combos/{id}/edit              → ComboController@edit
    PUT    /admin/combos/{id}                   → ComboController@update
    DELETE /admin/combos/{id}                   → ComboController@destroy
    GET    /admin/combos/buscar-productos       → ComboController@buscarProductos (AJAX)
```

---

## 3. Base de Datos

### 3.1 Tabla `promocion` — Estado actual + extensiones

```php
Schema::create('promocion', function (Blueprint $table) {
    $table->id('id_promocion');
    $table->string('titulo', 255)->nullable();
    $table->string('subtitulo', 255)->nullable();
    $table->text('imagen_url');
    $table->string('public_id')->nullable();
    $table->string('enlace', 255)->nullable();

    // Layout visual (independiente del tipo de negocio)
    $table->enum('tipo_layout', ['principal', 'secundaria', 'terciaria'])->default('secundaria');

    // Nuevos campos de negocio (Fase 1)
    $table->enum('tipo_promocion', ['descuento', 'combo'])->nullable();
    $table->enum('ambito', ['producto', 'marca', 'categoria'])->nullable();
    $table->integer('ambito_id')->nullable();
    $table->enum('descuento_tipo', ['porcentaje', 'fijo'])->nullable();
    $table->decimal('descuento_valor', 10, 2)->nullable();
    $table->foreignId('combo_id_combo')->nullable()->constrained('combo', 'id_combo');

    $table->integer('orden')->default(0);
    $table->boolean('activo')->default(true);
    $table->integer('clicks')->default(0);
    $table->timestamps();
});
```

#### Columnas detalladas

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id_promocion` | bigint PK | ID único |
| `titulo` | varchar(255) nullable | Título visible sobre la imagen |
| `subtitulo` | varchar(255) nullable | Subtítulo visible sobre la imagen |
| `imagen_url` | text | URL de Cloudinary (secure_url) |
| `public_id` | varchar nullable | Public ID de Cloudinary |
| `enlace` | varchar(255) nullable | URL manual (solo para promociones simples) |
| `tipo_layout` | enum | `principal` (100%), `secundaria` (25%), `terciaria` (50%) |
| `tipo_promocion` | enum nullable | `descuento` o `combo`. NULL = promoción simple |
| `ambito` | enum nullable | `producto`, `marca`, `categoria` (para descuentos) |
| `ambito_id` | int nullable | FK al producto/marca/categoría específico |
| `descuento_tipo` | enum nullable | `porcentaje` o `fijo` |
| `descuento_valor` | decimal(10,2) nullable | Valor del descuento |
| `combo_id_combo` | bigint nullable | FK → `combo.id_combo` |
| `orden` | integer | Orden de aparición (ascendente) |
| `activo` | boolean | Visible/invisible en portada |
| `clicks` | integer | Contador de clicks (incrementa vía AJAX) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### 3.2 Tabla `combo` (nueva)

```php
Schema::create('combo', function (Blueprint $table) {
    $table->id('id_combo');
    $table->string('nombre', 255);
    $table->text('descripcion')->nullable();
    $table->text('imagen_url')->nullable();
    $table->string('public_id')->nullable();
    $table->decimal('precio_sugerido', 10, 2);
    $table->boolean('activo')->default(true);
    $table->timestamps();
});
```

### 3.3 Tabla `combo_producto` (nueva)

```php
Schema::create('combo_producto', function (Blueprint $table) {
    $table->id('id_combo_producto');
    $table->foreignId('combo_id_combo')->constrained('combo', 'id_combo')->onDelete('cascade');
    $table->foreignId('producto_id_producto')->constrained('producto', 'id_producto');
    $table->integer('cantidad')->default(1);
});
```

---

## 4. Modelos

### 4.1 `Promocion.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promocion extends Model
{
    protected $table = 'promocion';
    protected $primaryKey = 'id_promocion';

    protected $fillable = [
        'titulo', 'subtitulo', 'imagen_url', 'public_id', 'enlace',
        'tipo_layout', 'tipo_promocion', 'ambito', 'ambito_id',
        'descuento_tipo', 'descuento_valor', 'combo_id_combo',
        'orden', 'activo', 'clicks',
    ];

    protected $casts = [
        'activo'          => 'boolean',
        'orden'           => 'integer',
        'clicks'          => 'integer',
        'descuento_valor' => 'decimal:2',
    ];

    public function combo()
    {
        return $this->belongsTo(Combo::class, 'combo_id_combo');
    }
}
```

### 4.2 `Combo.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Combo extends Model
{
    protected $table = 'combo';
    protected $primaryKey = 'id_combo';

    protected $fillable = [
        'nombre', 'descripcion', 'imagen_url', 'public_id',
        'precio_sugerido', 'activo',
    ];

    protected $casts = [
        'activo'          => 'boolean',
        'precio_sugerido' => 'decimal:2',
    ];

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'combo_producto',
            'combo_id_combo', 'producto_id_producto')
            ->withPivot('cantidad');
    }
}
```

---

## 5. Reglas de Negocio

### 5.1 Tipos de promoción

| `tipo_promocion` | Comportamiento | Link generado |
|---|---|---|
| `NULL` | Promoción simple: imagen + título + subtítulo + enlace manual | Usa `enlace` tal cual |
| `descuento` | Descuento sobre producto, marca o categoría. Requiere `ambito` + `ambito_id` + `descuento_tipo` + `descuento_valor` | Auto-generado según ámbito |
| `combo` | Referencia a un combo existente. Requiere `combo_id_combo` | Auto-generado → `/combos/{id}` |

### 5.2 Reglas de descuento

| `ambito` | `ambito_id` refiere a | Link generado |
|---|---|---|
| `producto` | `producto.id_producto` | `/productos/{ambito_id}` |
| `marca` | `marca.id_marca` | `/productos?marcas[]={ambito_id}` |
| `categoria` | `categoria.id_categoria` | `/productos?categorias[]={ambito_id}` |

- `descuento_tipo = 'porcentaje'`: el valor es un porcentaje (ej: 15 = 15% OFF)
- `descuento_tipo = 'fijo'`: el valor es un monto fijo en soles (ej: 5.00 = S/5 OFF)

### 5.3 Reglas de combo

- Un combo puede contener N productos, cada uno con su cantidad
- `precio_sugerido` se calcula automáticamente como `sum(precio_venta * cantidad) * 0.9` (10% OFF sugerido)
- El usuario administrador puede editar manualmente el `precio_sugerido`
- Un combo debe tener al menos 1 producto para ser válido
- La promoción que referencia un combo hereda el título/subtítulo del combo si no se especifican

### 5.4 Layout visual vs. lógica de negocio

Son conceptos **ortogonales**:

```
tipo_layout (cómo se ve):     principal | secundaria | terciaria
tipo_promocion (qué hace):    NULL | descuento | combo
```

Cualquier combinación es válida: una promoción puede ser "principal" y de tipo "descuento" a la vez.

### 5.5 Redirección

```
SI tipo_promocion IS NULL:
    → usa enlace manual (campo `enlace`)

SI tipo_promocion = 'descuento':
    → producto:  route('public.productos.show', ambito_id)
    → categoria: route('public.productos.index', ['categorias[]' => ambito_id])
    → marca:     route('public.productos.index', ['marcas[]' => ambito_id])

SI tipo_promocion = 'combo':
    → route('public.combos.show', combo_id_combo)
```

La redirección se resuelve en `HomeController@index` o en un helper y se pasa como variable a la vista `welcome.blade.php`.

### 5.6 Tracking de clicks

- Cada clic en una promoción (pública) envía un POST asíncrono a `/promociones/{id}/click`
- El servidor ejecuta `Promocion::increment('clicks')`
- El contador es visible en la tabla admin (columna "Clicks")
- No interrumpe la navegación (fire-and-forget vía `fetch`)

---

## 6. Casos de Uso

### 6.1 Promoción simple (imagen + enlace)

1. Admin crea promoción con tipo_layout = "principal", tipo_promocion = NULL
2. Sube imagen, escribe título, subtítulo y enlace manual
3. Se muestra en portada como imagen full-width con overlay
4. Al hacer clic, redirige al enlace configurado

### 6.2 Descuento en producto específico

1. Admin crea promoción con tipo_promocion = "descuento"
2. Selecciona ámbito = "producto", busca y selecciona el producto
3. Configura descuento: tipo = "porcentaje", valor = 20
4. Se muestra en portada con el título "20% OFF en [Producto]"
5. Al hacer clic, redirige a `/productos/{id}` del producto

### 6.3 Descuento en categoría

1. Admin selecciona ámbito = "categoria", busca y selecciona la categoría
2. Configura descuento: tipo = "fijo", valor = 5.00
3. Al hacer clic, redirige a `/productos?categorias[]={id}`
4. El catálogo muestra los productos de esa categoría filtrados

### 6.4 Combo de productos

1. Admin va a Productos > Combos y crea un combo:
   - Nombre: "Pack Ahorro Botiquín"
   - Agrega 3 productos con cantidades
   - Precio sugerido auto-calculado: S/ 45.00 (editable)
2. Admin va a Portada > Promociones y crea una promoción:
   - tipo_promocion = "combo"
   - Selecciona el combo creado
   - tipo_layout = "secundaria"
3. En portada se muestra la imagen del combo
4. Al hacer clic, redirige a `/combos/{id}` con detalle del combo

### 6.5 Reordenamiento drag & drop

1. Admin arrastra las filas en la tabla de promociones
2. SortableJS detecta el cambio y envía el nuevo orden al servidor
3. Las promociones se renderizan en la portada respetando el orden

---

## 7. Consideraciones de Diseño e Integración

### 7.1 Independencia de módulos

- `Promocion` es un modelo independiente que referencia opcionalmente a `Producto`, `Categoria`, `Marca` y `Combo`
- La eliminación de un producto, categoría o marca referenciado debe manejarse:
  - Al eliminar: se puede dejar la promoción con `ambito_id` huérfano (se muestra como simple sin enlace)
  - Alternativa: validar existencia al renderizar y ocultar si la entidad ya no existe
- La eliminación de un combo debe manejarse:
  - Las promociones que referencian al combo quedan con `combo_id_combo` = NULL
  - Se renderizan como promociones simples si tienen `enlace`, o se ocultan

### 7.2 Cloudinary

- Las imágenes de promociones se suben a la carpeta `promociones/`
- Las imágenes de combos se suben a la carpeta `combos/`
- Se reutiliza `App\Services\CloudinaryService` con el mismo patrón que banners y productos
- Al eliminar una promoción/combo, también se elimina su imagen de Cloudinary

### 7.3 Permisos Spatie

| Permiso | Recurso | Sección en sidebar |
|---|---|---|
| `portada.promociones` | CRUD de promociones + reordenar + click tracking | Portada > Promociones |
| `combos` | CRUD de combos | Inventario > Productos > Combos |

### 7.4 Auditoría (trait Audita)

- Todas las operaciones CRUD sobre `Promocion` quedan registradas en la tabla `auditoria`
- Todas las operaciones CRUD sobre `Combo` quedan registradas en la tabla `auditoria`

### 7.5 Cache

- No se requiere caché adicional. Las consultas de promociones y combos son simples y de baja frecuencia de cambio
- Si en el futuro se implementa el trait `CacheaPanel` para vistas admin, se debe invalidar al crear/editar/eliminar promociones y combos

### 7.6 Responsive

El grid de promociones se adapta a todos los tamaños de pantalla:

| Breakpoint | Principal | Terciaria | Secundaria |
|---|---|---|---|
| `<640px` | `col-span-12 h-48` | `col-span-12 h-32` | `col-span-6 h-32` |
| `≥640px` | `col-span-12 h-64` | `col-span-6 h-40` | `col-span-3 h-40` |
| `≥768px` | `col-span-12 h-80` | `col-span-6 h-48` | `col-span-3 h-48` |
| `≥1024px` | `col-span-12 h-96` | `col-span-6 h-48` | `col-span-3 h-48` |

---

## 8. Diseño Técnico Detallado

### 8.1 Controlador `Admin\PromocionController.php`

```
index():
    Promocion::orderBy('orden')->with('combo')->get()
    if (ajax) → partial 'admin.promociones.index'
    else      → full 'admin.promociones.full'

create():
    if (ajax) → modal 'admin.promociones.create'
    else      → redirect index

store(StorePromocionRequest):
    imagen → Cloudinary::upload('promociones')
    Promocion::create([... + tipo_layout, tipo_promocion, ambito, etc.])
    Audita::crear(...)
    if (ajax) → JSON { success, message }

edit(Promocion):
    if (ajax) → modal 'admin.promociones.edit' + $promocion->load('combo')
    else      → redirect index

update(UpdatePromocionRequest, Promocion):
    if nueva_imagen → Cloudinary::delete(antigua) + Cloudinary::upload(nueva)
    Promocion::update([...])
    Audita::actualizar(...)
    if (ajax) → JSON { success, message }

destroy(Promocion):
    Cloudinary::delete(public_id)
    Promocion::delete()
    Audita::eliminar(...)
    if (ajax) → JSON { success, message }

reordenar():
    validate items[]
    foreach items: update orden = index
    JSON { success }

buscarEntidad():
    ambito = request('ambito')  // 'producto' | 'marca' | 'categoria'
    q = request('q')
    según ambito, busca y devuelve JSON con id + nombre
```

### 8.2 Controlador `Admin\ComboController.php`

```
index():
    Combo::withCount('productos')->orderBy('nombre')->get()
    if (ajax) → partial 'admin.combos.index'
    else      → full 'admin.combos.full'

create():
    if (ajax) → modal 'admin.combos.create'
    else      → redirect index

store(StoreComboRequest):
    Combo::create([...])
    sync productos con cantidades (combo_producto)
    Audita::crear(...)
    if (ajax) → JSON { success, message }

edit(Combo):
    Combo::load('productos')
    if (ajax) → modal 'admin.combos.edit'
    else      → redirect index

update(UpdateComboRequest, Combo):
    Combo::update([...])
    sync productos (detach all + attach new)
    Audita::actualizar(...)
    if (ajax) → JSON { success, message }

destroy(Combo):
    combo_producto cascade delete
    Combo::delete()
    Audita::eliminar(...)
    if (ajax) → JSON { success, message }

buscarProductos():
    q = request('q')
    Producto::where('nombre', 'like', "%{$q}%")->limit(20)
    → JSON [{ id_producto, nombre, precio_venta, foto_url }]
```

### 8.3 Controlador `Public\ComboController.php`

```
show(Combo):
    Combo::load('productos.categoria')
    return view('public.combos.show', compact('combo'))
```

### 8.4 Form Request — `StorePromocionRequest.php`

```php
public function rules(): array
{
    return [
        'imagen'           => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        'titulo'           => 'nullable|string|max:255',
        'subtitulo'        => 'nullable|string|max:255',
        'enlace'           => 'nullable|url|max:255',
        'tipo_layout'      => 'required|in:principal,secundaria,terciaria',
        'tipo_promocion'   => 'nullable|in:descuento,combo',
        'ambito'           => 'nullable|required_if:tipo_promocion,descuento|in:producto,marca,categoria',
        'ambito_id'        => 'nullable|required_if:tipo_promocion,descuento|integer',
        'descuento_tipo'   => 'nullable|required_if:tipo_promocion,descuento|in:porcentaje,fijo',
        'descuento_valor'  => 'nullable|required_if:tipo_promocion,descuento|numeric|min:0|max:999999',
        'combo_id_combo'   => 'nullable|required_if:tipo_promocion,combo|integer|exists:combo,id_combo',
    ];
}
```

### 8.5 Form Request — `StoreComboRequest.php`

```php
public function rules(): array
{
    return [
        'nombre'            => 'required|string|max:255',
        'descripcion'       => 'nullable|string|max:500',
        'imagen'            => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        'precio_sugerido'   => 'required|numeric|min:0',
        'activo'            => 'boolean',
        'productos'         => 'required|array|min:1',
        'productos.*.id'    => 'required|integer|exists:producto,id_producto',
        'productos.*.cantidad' => 'required|integer|min:1',
    ];
}
```

### 8.6 JavaScript — `resources/js/admin/promociones/index.js`

Funcionalidades adicionales a las ya existentes (SortableJS, CRUD, preview):

```
- Selector de tipo_promocion en modal create/edit:
  - "Simple"   → muestra campos: enlace manual, oculta ámbito/descuento/combo
  - "Descuento" → muestra: selector ámbito + buscador entidad + tipo valor descuento
  - "Combo"    → muestra: buscador de combos

- Búsqueda AJAX de entidades (productos/marcas/categorías):
  - Input de búsqueda con debounce 300ms
  - GET /admin/promociones/buscar-entidad?ambito=X&q=...
  - Dropdown de resultados seleccionables

- Búsqueda AJAX de combos:
  - Input de búsqueda con debounce 300ms
  - GET /admin/combos/buscar-combos?q=...
  - Dropdown de resultados seleccionables
```

### 8.7 JavaScript — `resources/js/admin/combos/index.js`

```
- CRUD via AJAX (mismo patrón que banners/promociones)
- Búsqueda de productos (modal create/edit):
  - Input con debounce 300ms
  - GET /admin/combos/buscar-productos?q=...
  - Resultados en tabla seleccionable
  - Al seleccionar: se agrega a lista con campo cantidad y precio_venta (readonly)

- Cálculo de precio sugerido:
  - const total = suma(precio_venta * cantidad)
  - const sugerido = total * 0.9
  - Se muestra en campo editable
  - Al cambiar cantidades o agregar/quitar productos: recalcular
  - Usuario puede sobrescribir manualmente el valor
```

### 8.8 Vista pública — `welcome.blade.php` (lógica de redirección)

```blade
@php
    $enlacePromocion = match ($promocion->tipo_promocion) {
        'descuento' => match ($promocion->ambito) {
            'producto'  => route('public.productos.show', $promocion->ambito_id),
            'categoria' => route('public.productos.index', ['categorias[]' => $promocion->ambito_id]),
            'marca'     => route('public.productos.index', ['marcas[]' => $promocion->ambito_id]),
            default     => $promocion->enlace,
        },
        'combo'     => $promocion->combo ? route('public.combos.show', $promocion->combo) : null,
        default     => $promocion->enlace,
    };
@endphp
```

### 8.9 Vista pública — `public/combos/show.blade.php`

```
@extends('layouts.public')

@section('content')
    <section class="max-w-4xl mx-auto px-4 py-8">
        <h1>{{ $combo->nombre }}</h1>
        @if($combo->imagen_url)
            <img src="{{ $combo->imagen_url }}" alt="{{ $combo->nombre }}">
        @endif
        <p>{{ $combo->descripcion }}</p>

        <h2>Productos incluidos</h2>
        <table>
            @foreach($combo->productos as $producto)
            <tr>
                <td>{{ $producto->nombre }}</td>
                <td>{{ $producto->pivot->cantidad }}x</td>
                <td>S/ {{ number_format($producto->precio_venta, 2) }}</td>
                <td>S/ {{ number_format($producto->precio_venta * $producto->pivot->cantidad, 2) }}</td>
            </tr>
            @endforeach
        </table>

        @php
            $sumaIndividual = $combo->productos->sum(fn($p) => $p->precio_venta * $p->pivot->cantidad);
        @endphp
        <p>Precio regular: S/ {{ number_format($sumaIndividual, 2) }}</p>
        <p>Precio combo: S/ {{ number_format($combo->precio_sugerido, 2) }}</p>
        <p>Ahorras: S/ {{ number_format($sumaIndividual - $combo->precio_sugerido, 2) }}</p>

        <a href="{{ route('public.productos.index') }}">Ver todos los productos</a>
    </section>
@endsection
```

### 8.10 Sidebar Admin

En `resources/views/layouts/admin.blade.php`:

```
Portada
├── Sliders (Portada)
├── Banners
├── Categorías en Portada
└── Promociones             ← nuevo (permiso: portada.promociones)

Inventario > Productos
└── Combos                  ← nuevo (permiso: combos)
```

---

## 9. Rutas Completa

### `routes/admin.php`

```php
use App\Http\Controllers\Admin\PromocionController;
use App\Http\Controllers\Admin\ComboController;

// Promociones (Portada)
Route::middleware('permission:portada.promociones')
    ->prefix('promociones')
    ->name('admin.promociones.')
    ->group(function () {
        Route::get('/', [PromocionController::class, 'index'])->name('index');
        Route::get('/create', [PromocionController::class, 'create'])->name('create');
        Route::post('/', [PromocionController::class, 'store'])->name('store');
        Route::get('/{promocion}/edit', [PromocionController::class, 'edit'])->name('edit');
        Route::put('/{promocion}', [PromocionController::class, 'update'])->name('update');
        Route::delete('/{promocion}', [PromocionController::class, 'destroy'])->name('destroy');
        Route::post('/reordenar', [PromocionController::class, 'reordenar'])->name('reordenar');
        Route::get('/buscar-entidad', [PromocionController::class, 'buscarEntidad'])->name('buscar-entidad');
    });

// Combos (Inventario > Productos)
Route::middleware('permission:combos')
    ->prefix('combos')
    ->name('admin.combos.')
    ->group(function () {
        Route::get('/', [ComboController::class, 'index'])->name('index');
        Route::get('/create', [ComboController::class, 'create'])->name('create');
        Route::post('/', [ComboController::class, 'store'])->name('store');
        Route::get('/{combo}/edit', [ComboController::class, 'edit'])->name('edit');
        Route::put('/{combo}', [ComboController::class, 'update'])->name('update');
        Route::delete('/{combo}', [ComboController::class, 'destroy'])->name('destroy');
        Route::get('/buscar-productos', [ComboController::class, 'buscarProductos'])->name('buscar-productos');
    });
```

### `routes/web.php`

```php
use App\Http\Controllers\Public\ComboController;

Route::get('/combos/{combo}', [ComboController::class, 'show'])->name('public.combos.show');
Route::post('/promociones/{promocion}/click', function (Promocion $promocion) {
    $promocion->increment('clicks');
    return response()->json(['success' => true]);
})->name('public.promociones.click');
```

---

## 10. Fase 2 — Integración con Ventas (plan futuro)

### 10.1 Precio efectivo con descuento

Se creará un service `App\Services\PrecioPromocionService`:

```php
class PrecioPromocionService
{
    public function precioEfectivo(Producto $producto): float
    {
        $promocion = Promocion::where('activo', true)
            ->where('tipo_promocion', 'descuento')
            ->where(function ($q) use ($producto) {
                $q->where(function ($q) use ($producto) {
                    // Descuento directo al producto
                    $q->where('ambito', 'producto')
                      ->where('ambito_id', $producto->id_producto);
                })->orWhere(function ($q) use ($producto) {
                    // Descuento a la categoría del producto
                    $q->where('ambito', 'categoria')
                      ->where('ambito_id', $producto->categoria_id_categoria);
                })->orWhere(function ($q) use ($producto) {
                    // Descuento a la marca del producto
                    $q->where('ambito', 'marca')
                      ->where('ambito_id', $producto->marca_id_marca);
                });
            })->first();

        if (!$promocion) return $producto->precio_venta;

        return match ($promocion->descuento_tipo) {
            'porcentaje' => $producto->precio_venta * (1 - $promocion->descuento_valor / 100),
            'fijo'       => max(0, $producto->precio_venta - $promocion->descuento_valor),
            default      => $producto->precio_venta,
        };
    }
}
```

### 10.2 Combo como línea de venta

- Nueva columna `combo_id_combo` nullable en `detalle_venta`
- El formulario de venta permitirá seleccionar un combo y agregarlo como línea
- Al agregar un combo, se expanden los productos individuales con el precio del combo prorrateado
- Se descuenta stock de cada producto individual

### 10.3 Precio editable en formulario de venta

Actualmente `precio` es readonly. Para soportar descuentos reales:
- El campo `precio` se poblará con `PrecioPromocionService::precioEfectivo($producto)`
- El cajero podrá ver el precio original tachado y el precio descontado

---

## 11. Implicaciones y Consideraciones

### 11.1 Ortofonalidad de tipos

`tipo_layout` (principal/secundaria/terciaria) es **independiente** de `tipo_promocion` (descuento/combo). Esto permite tener:
- Una promoción "principal" que sea un descuento en una categoría
- Una promoción "secundaria" que sea un combo
- Una promoción "terciaria" que sea simple

### 11.2 Objetos huérfanos

- Si se elimina un producto/marca/categoría referenciada por una promoción, la promoción se renderiza sin enlace
- Si se elimina un combo referenciado, la promoción se renderiza sin enlace (campo enlace como fallback)
- Se recomienda agregar validación en la vista: si no hay enlace y no hay combo, ocultar el item

### 11.3 Prioridad de descuentos

Si un producto tiene múltiples descuentos activos (directo + categoría + marca):
- Se aplica el descuento más beneficioso para el cliente (menor precio)
- Esto se implementará en Fase 2 con `PrecioPromocionService`

### 11.4 Consistencia con SliderProducto

El módulo de promociones es independiente de `SliderProducto` (sliders de productos "Más buscados", "Más vendidos", "En descuento"). Una promoción de descuento en un producto no lo agrega automáticamente al slider "En descuento".

### 11.5 Manejo de errores en formularios

Los formularios modales de create/edit deben validar:
- Al seleccionar "Descuento": `ambito`, `ambito_id`, `descuento_tipo`, `descuento_valor` son requeridos
- Al seleccionar "Combo": `combo_id_combo` es requerido
- Al seleccionar "Simple": solo `enlace` (opcional)
- `tipo_layout` siempre requerido

---

## 12. Inventario Completo de Archivos

### Archivos existentes que se modifican (Fase 1 ya implementada)

| Archivo | Cambio |
|---|---|
| `app/Models/Promocion.php` | + fillable, casts, relación combo |
| `app/Http/Controllers/Admin/PromocionController.php` | + método `buscarEntidad()` |
| `app/Http/Requests/Admin/StorePromocionRequest.php` | + validación campos negocio |
| `app/Http/Requests/Admin/UpdatePromocionRequest.php` | + validación campos negocio |
| `resources/views/admin/promociones/create.blade.php` | + selector tipo_promocion + campos condicionales |
| `resources/views/admin/promociones/edit.blade.php` | + selector tipo_promocion + campos condicionales |
| `resources/views/admin/promociones/index.blade.php` | + columna "Tipo Prom." |
| `resources/js/admin/promociones/index.js` | + show/hide dinámico + búsqueda entidades |
| `resources/views/welcome.blade.php` | + lógica redirección dinámica |
| `resources/views/layouts/admin.blade.php` | + link Combos + link Promociones |
| `routes/admin.php` | + rutas promociones + combos |
| `routes/web.php` | + ruta click + ruta combo público |
| `vite.config.js` | + entries combos |
| `database/seeders/PermissionSeeder.php` | + `portada.promociones`, `combos` |
| `database/seeders/RoleSeeder.php` | + permisos a admin |
| `app/Http/Controllers/Public/HomeController.php` | + carga promociones + resolución enlaces |

### Archivos nuevos (Fase 1 ya implementada)

| Archivo | Propósito |
|---|---|
| `database/migrations/2026_06_11_000001_create_promocion_table.php` | Tabla promocion |
| `database/migrations/2026_06_11_000002_add_clicks_to_promocion_table.php` | Columna clicks |
| `app/Models/Promocion.php` | Modelo |
| `app/Http/Controllers/Admin/PromocionController.php` | CRUD |
| `app/Http/Requests/Admin/StorePromocionRequest.php` | Validación |
| `app/Http/Requests/Admin/UpdatePromocionRequest.php` | Validación |
| `resources/views/admin/promociones/*.blade.php` | 4 vistas |
| `resources/js/admin/promociones/index.js` | JS |

### Archivos nuevos (extensión Fase 1 — combos + lógica negocio)

| Archivo | Propósito |
|---|---|
| `database/migrations/xxxx_create_combo_tables.php` | Tablas combo + combo_producto |
| `database/migrations/xxxx_add_business_fields_to_promocion.php` | Columnas negocio en promocion |
| `app/Models/Combo.php` | Modelo combo |
| `app/Http/Controllers/Admin/ComboController.php` | CRUD combos |
| `app/Http/Requests/Admin/StoreComboRequest.php` | Validación combo |
| `app/Http/Requests/Admin/UpdateComboRequest.php` | Validación combo |
| `resources/views/admin/combos/full.blade.php` | Layout admin combos |
| `resources/views/admin/combos/index.blade.php` | Tabla combos |
| `resources/views/admin/combos/create.blade.php` | Modal crear combo |
| `resources/views/admin/combos/edit.blade.php` | Modal editar combo |
| `resources/js/admin/combos/index.js` | JS combos |
| `app/Http/Controllers/Public/ComboController.php` | Vista pública combo |
| `resources/views/public/combos/show.blade.php` | Página pública combo |

---

## 13. Orden de Implementación

| Paso | Archivos | Descripción | Verificación |
|---|---|---|---|
| 1 | Migraciones combos + business fields | Crear tablas `combo`, `combo_producto`, nuevas columnas en `promocion` | `php artisan migrate` |
| 2 | `Combo.php` | Modelo | — |
| 3 | `PermissionSeeder` + `RoleSeeder` | + `combos` | `db:seed` |
| 4 | `StoreComboRequest`, `UpdateComboRequest` | Validaciones | — |
| 5 | `ComboController.php` | CRUD | — |
| 6 | Vistas combos | `full`, `index`, `create`, `edit` | — |
| 7 | `resources/js/admin/combos/index.js` | JS con búsqueda + cálculo precio | `npm run build` |
| 8 | `routes/admin.php` | + rutas combos | — |
| 9 | `layouts/admin.blade.php` | + link Combos | Visual |
| 10 | Actualizar Promocion model + requests + controller | + lógica negocio | — |
| 11 | Actualizar vistas promociones | + campos condicionales | — |
| 12 | Actualizar JS promociones | + show/hide + búsqueda entidades | — |
| 13 | `ComboController.php` (público) + `show.blade.php` | Página pública combo | Visual |
| 14 | `routes/web.php` | + ruta combo + búsqueda entidad | — |
| 15 | `HomeController.php` + `welcome.blade.php` | Resolución enlaces dinámicos | Visual portada |
| 16 | `npm run build` | Compilar assets | — |
| 17 | Verificación completa | Crear combo → crear promo → ver portada → click | — |
