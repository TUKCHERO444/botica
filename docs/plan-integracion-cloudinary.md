# Plan de Integración: Cloudinary para Fotos de Productos

## 1. Análisis de Diferencias Arquitectónicas

### 1.1 Sistema Documentado (flujo-cloudinary-productos.md)
- **Navegación:** Tradicional (páginas completas con `@extends`)
- **Controlador:** Plano en `App\Http\Controllers\`, sin namespace `Admin\`
- **Validación:** Directa en el controlador con `$request->validate()`
- **Respuestas:** Redirects (`redirect()->route(...)`)
- **JS:** `DOMContentLoaded` para inicializar preview
- **Carga de scripts:** `@push('scripts') @vite(...) @endpush`

### 1.2 Sistema Actual (panel-administrativo.md + código real)
- **Navegación:** SPA con modales AJAX (vistas parciales sin `@extends`)
- **Controlador:** `Admin\ProductoController` en `App\Http\Controllers\Admin\`
- **Validación:** Form Requests (`StoreProductoRequest`, `UpdateProductoRequest`)
- **Respuestas:** JSON (`{ success, message, producto }`) para AJAX; redirect como fallback
- **JS:** Scripts se re-ejecutan automáticamente al cargar el modal (modal.js línea 49-59)
- **Carga de scripts:** `@vite()` directo (sin `@push`) porque las partials no extienden layout
- **Cache:** Trait `CacheaPanel` cachea la vista `index` — hay que invalidar al cambiar foto

### 1.3 Puntos Críticos de Adaptación

| Aspecto | Documentado | Real (SPA) | Impacto |
|---------|-------------|------------|---------|
| `enctype="multipart/form-data"` | En el `<form>` HTML | `FormData` de Axios lo maneja automáticamente | ✅ No requiere acción extra |
| Initialize JS preview | `DOMContentLoaded` | Script se re-ejecuta al abrir modal (modal.js L49-59). DOM ya existe. | ✅ Llamar `initFotoPreview()` directo, sin `DOMContentLoaded` |
| Respuesta de store/update | Redirect con `->with('success')` | JSON `{ success, message, producto }` | ⚠️ Cloudinary debe ejecutarse ANTES de la transacción, devolviendo JSON |
| Destroy | Redirect | Soft delete (estado=0) + JSON | ⚠️ Ya no se elimina el registro, se desactiva. ¿Eliminar foto de Cloudinary igual? |
| Validación foto | `$request->validate()` inline | Form Request | ⚠️ Agregar regla `foto` a `StoreProductoRequest` y `UpdateProductoRequest` |
| Cache de index | No aplica | `CacheaPanel::olvidaIndex('productos')` | ⚠️ Llamar después de store/update/destroy (ya existe en controlador) |

---

## 2. Plan de Implementación

### Fase 0: Preparación (configuración global)

**Archivos a modificar:**

1. **`composer.json`** — Agregar dependencia
   ```
   composer require cloudinary-labs/cloudinary-laravel:^3.0
   ```

2. **`.env`** — Agregar credenciales
   ```env
   CLOUDINARY_URL=cloudinary://api_key:api_secret@cloud_name
   ```

3. **`config/filesystems.php`** — Agregar disco `cloudinary`
   ```php
   'cloudinary' => [
       'driver' => 'cloudinary',
       'url'    => env('CLOUDINARY_URL'),
   ],
   ```

4. **`vite.config.js`** — Agregar entradas JS
   ```js
   'resources/js/admin/productos/create.js',
   'resources/js/admin/productos/edit.js',
   ```

### Fase 1: Migración de base de datos

**Nuevo archivo:** `database/migrations/xxxx_xx_xx_xxxxxx_add_foto_to_producto_table.php`

```php
Schema::table('producto', function (Blueprint $table) {
    $table->text('foto')->nullable()->after('marca_id_marca');
});
```

### Fase 2: Modelo

**Archivo:** `app/Models/Producto.php`

Cambios:
- Agregar `'foto'` al `$fillable`
- Agregar `$casts = ['foto' => 'string']` (o dejarlo implícito)
- Agregar accessor `getFotoUrlAttribute(): ?string`
  ```php
  public function getFotoUrlAttribute(): ?string
  {
      if (!$this->foto) return null;
      if (str_starts_with($this->foto, 'http')) return $this->foto;
      return asset('storage/' . $this->foto);
  }
  ```

### Fase 3: Form Requests

**Archivo:** `app/Http/Requests/Admin/StoreProductoRequest.php`

Agregar regla:
```php
'foto' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
```

**Archivo:** `app/Http/Requests/Admin/UpdateProductoRequest.php`

Misma regla:
```php
'foto' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
```

### Fase 4: Controlador

**Archivo:** `app/Http/Controllers/Admin/ProductoController.php`

#### 4.1 Store — Flujo con Cloudinary

```
1. Validar (FormRequest)
2. SI hay foto:
   a. Subir a Cloudinary: Cloudinary::uploadApi()->upload($file->getRealPath())
   b. Asignar $fotoUrl = $result['secure_url']
3. Crear producto CON foto
4. Invalidar caché
5. Responder JSON o redirect
```

```php
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

public function store(StoreProductoRequest $request)
{
    $data = $request->validated();

    if ($request->hasFile('foto')) {
        $result = Cloudinary::uploadApi()->upload(
            $request->file('foto')->getRealPath()
        );
        $data['foto'] = $result['secure_url'];
    }

    $producto = Producto::create($data);
    $this->olvidaIndex('productos');

    if ($request->ajax()) {
        return response()->json([
            'success' => true,
            'message' => 'Producto creado correctamente',
            'producto' => $producto,
        ]);
    }
    return redirect()->route('admin.productos.index')
        ->with('success', 'Producto creado correctamente');
}
```

#### 4.2 Update — Flujo con Cloudinary

```
1. Validar (FormRequest)
2. SI nueva foto:
   a. SI producto tiene foto anterior en Cloudinary → extraer public_id → destroy()
   b. Subir nueva foto → obtener secure_url
   c. Incluir 'foto' en $data
3. SI NO hay nueva foto → NO incluir 'foto' en $data
4. Actualizar producto
5. Invalidar caché
6. Responder JSON o redirect
```

```php
public function update(UpdateProductoRequest $request, Producto $producto)
{
    $data = $request->validated();

    if ($request->hasFile('foto')) {
        // Eliminar imagen anterior de Cloudinary
        if ($producto->foto && str_starts_with($producto->foto, 'http')) {
            $parts = explode('/', $producto->foto);
            $publicId = pathinfo(end($parts), PATHINFO_FILENAME);
            Cloudinary::uploadApi()->destroy($publicId);
        }
        // Subir nueva imagen
        $result = Cloudinary::uploadApi()->upload(
            $request->file('foto')->getRealPath()
        );
        $data['foto'] = $result['secure_url'];
    }

    $producto->update($data);
    $this->olvidaIndex('productos');

    if ($request->ajax()) {
        return response()->json([
            'success' => true,
            'message' => 'Producto actualizado correctamente',
            'producto' => $producto,
        ]);
    }
    return redirect()->route('admin.productos.index')
        ->with('success', 'Producto actualizado correctamente');
}
```

#### 4.3 Destroy — Flujo con Cloudinary

```
NOTA: El destroy actual hace soft-delete (estado=0), no borra el registro.

OPCIÓN B (Implementada): No eliminar foto de Cloudinary al desactivar.
Si el producto se reactiva, la foto sigue disponible en la BD y en
Cloudinary. La función destroy() del controlador NO toca Cloudinary,
solo cambia el estado a 0 e invalida la caché.
```

```php
// destroy() NO elimina foto de Cloudinary (Opción B)
// Solo desactiva el producto lógicamente
public function destroy(Producto $producto)
{
    $producto->update(['estado' => 0]);
    $this->olvidaIndex('productos');

    if (request()->ajax()) {
        return response()->json([
            'success' => true,
            'message' => 'Producto desactivado correctamente',
        ]);
    }
    return redirect()->route('admin.productos.index')
        ->with('success', 'Producto desactivado correctamente');
}
```

### Fase 5: Vistas

#### 5.1 Vista create.blade.php

Cambios:
- Agregar `enctype="multipart/form-data"` al `<form>` (necesario para el FileReader preview, aunque FormData de Axios no lo requiere)
- Agregar campo `foto` con preview
- Cargar `@vite('resources/js/admin/productos/create.js')`

```blade
<form action="{{ route('admin.productos.store') }}" method="POST" class="p-6" enctype="multipart/form-data">
    @csrf
    {{-- ... encabezado ... --}}

    <div class="space-y-4">
        {{-- ... campos existentes ... --}}

        {{-- NUEVO: campo foto --}}
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Foto del producto</label>
            <input type="file" name="foto" id="foto" accept="image/jpeg,image/jpg,image/png,image/webp"
                class="w-full text-sm text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
            <div id="bloque-preview" class="hidden mt-3">
                <img id="preview-foto" src="#" alt="Preview" class="w-32 h-32 object-cover rounded-lg border border-stone-200">
            </div>
        </div>
    </div>

    {{-- ... botones ... --}}
</form>

@vite('resources/js/admin/productos/create.js')
```

#### 5.2 Vista edit.blade.php

Cambios:
- Agregar `enctype="multipart/form-data"`
- Mostrar preview de imagen actual
- Agregar campo file para nueva foto con preview
- Cargar `@vite('resources/js/admin/productos/edit.js')`

```blade
<form action="{{ route('admin.productos.update', $producto->id_producto) }}" method="POST" class="p-6" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    {{-- ... encabezado ... --}}

    <div class="space-y-4">
        {{-- ... campos existentes ... --}}

        {{-- NUEVO: foto actual + carga --}}
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Foto del producto</label>

            @if($producto->foto_url)
                <div class="mb-3">
                    <p class="text-xs text-stone-500 mb-1">Foto actual:</p>
                    <img src="{{ $producto->foto_url }}" alt="Foto actual"
                        class="w-32 h-32 object-cover rounded-lg border border-stone-200">
                </div>
            @endif

            <input type="file" name="foto" id="foto" accept="image/jpeg,image/jpg,image/png,image/webp"
                class="w-full text-sm text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
            <div id="bloque-preview" class="hidden mt-3">
                <img id="preview-foto" src="#" alt="Nueva foto"
                    class="w-32 h-32 object-cover rounded-lg border border-stone-200">
            </div>
        </div>
    </div>

    {{-- ... botones ... --}}
</form>

@vite('resources/js/admin/productos/edit.js')
```

#### 5.3 Vista index.blade.php

Cambios:
- Agregar columna de miniatura en la tabla

```blade
<thead>
    <tr class="bg-stone-50 text-stone-600 text-left">
        <th class="px-4 py-3 font-medium">Foto</th> {{-- NUEVA --}}
        <th class="px-4 py-3 font-medium">ID</th>
        <th class="px-4 py-3 font-medium">Nombre</th>
        {{-- ... resto ... --}}
    </tr>
</thead>
<tbody>
    @forelse ($productos as $p)
        <tr class="hover:bg-stone-50 transition">
            <td class="px-4 py-3">
                @if($p->foto_url)
                    <img src="{{ $p->foto_url }}" alt="{{ $p->nombre }}"
                        class="w-10 h-10 object-cover rounded-lg border border-stone-200">
                @else
                    <span class="w-10 h-10 flex items-center justify-center bg-stone-100 rounded-lg text-stone-400 text-xs">—</span>
                @endif
            </td>
            {{-- ... resto ... --}}
        </tr>
    @endforelse
</tbody>
```

### Fase 6: JavaScript

#### 6.1 shared.js (nuevo)

**Archivo:** `resources/js/admin/productos/shared.js`

```js
export function initFotoPreview(inputId, previewId, bloqueId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (event) {
            const preview = document.getElementById(previewId);
            const bloque = document.getElementById(bloqueId);
            if (preview) preview.src = event.target.result;
            if (bloque) bloque.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });
}
```

#### 6.2 create.js (nuevo)

**Archivo:** `resources/js/admin/productos/create.js`

```js
import { initFotoPreview } from './shared.js';

initFotoPreview('foto', 'preview-foto', 'bloque-preview');
```

**Nota:** No se usa `DOMContentLoaded` porque el script se re-ejecuta al abrir el modal, momento en el que el DOM ya existe.

#### 6.3 edit.js (nuevo)

**Archivo:** `resources/js/admin/productos/edit.js`

```js
import { initFotoPreview } from './shared.js';

initFotoPreview('foto', 'preview-foto', 'bloque-preview');
```

### Fase 7: Tests

**Archivo:** `tests/Feature/ProductoCloudinaryTest.php`

```php
<?php

namespace Tests\Feature;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductoCloudinaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_producto_with_cloudinary_image(): void
    {
        $mockResponse = \Mockery::mock(\Cloudinary\Api\ApiResponse::class);
        $mockResponse->shouldReceive('offsetGet')
            ->with('secure_url')
            ->andReturn('https://res.cloudinary.com/demo/image/upload/v1/test_product.jpg');

        Cloudinary::shouldReceive('uploadApi->upload')
            ->once()
            ->andReturn($mockResponse);

        $response = $this->postJson(route('admin.productos.store'), [
            'nombre' => 'Test Producto',
            'precio_venta' => 29.99,
            'estado' => 1,
            'inventario' => 10,
            'stock' => 5,
            'categoria_id_categoria' => 1,
            'marca_id_marca' => 1,
            '_token' => csrf_token(),
        ]);

        $response->assertJson(['success' => true]);
    }

    // Tests adicionales: update con nueva foto, destroy con foto
}
```

---

## 3. Orden de Implementación

| Paso | Archivos | Depende de |
|------|----------|------------|
| 1. `composer require cloudinary-labs/cloudinary-laravel` | `composer.json` | — |
| 2. Configurar `.env` | `.env` | Paso 1 |
| 3. Agregar disco cloudinary | `config/filesystems.php` | Paso 2 |
| 4. Crear migración `foto` | `database/migrations/` | — |
| 5. Ejecutar migración | — | Paso 4 |
| 6. Actualizar Modelo `Producto` | `app/Models/Producto.php` | Paso 5 |
| 7. Actualizar Form Requests | `StoreProductoRequest`, `UpdateProductoRequest` | — |
| 8. Actualizar Controlador | `Admin/ProductoController.php` | Pasos 1-7 |
| 9. Crear JS `shared.js` | `resources/js/admin/productos/shared.js` | — |
| 10. Crear JS `create.js` | `resources/js/admin/productos/create.js` | Paso 9 |
| 11. Crear JS `edit.js` | `resources/js/admin/productos/edit.js` | Paso 9 |
| 12. Actualizar `vite.config.js` | `vite.config.js` | Pasos 10-11 |
| 13. Actualizar vista `create.blade.php` | `resources/views/admin/productos/create.blade.php` | Paso 10 |
| 14. Actualizar vista `edit.blade.php` | `resources/views/admin/productos/edit.blade.php` | Paso 10 |
| 15. Actualizar vista `index.blade.php` | `resources/views/admin/productos/index.blade.php` | — |
| 16. Crear tests | `tests/Feature/ProductoCloudinaryTest.php` | Pasos 1-15 |
| 17. Compilar assets | `npm run build` | Pasos 9-12 |

---

## 4. Riesgos y Consideraciones

### 4.1 Script re-ejecución en modales
El modal.js (línea 49-59) ya re-ejecuta scripts. `create.js` y `edit.js` se ejecutarán correctamente al abrir el modal. No hay riesgo.

### 4.2 `DOMContentLoaded` no disponible
Los scripts cargados vía AJAX no reciben `DOMContentLoaded`. Por eso `create.js` y `edit.js` llaman a `initFotoPreview()` directamente, sin envolverlo en el evento.

### 4.3 Soft-delete en destroy
El destroy actual hace `update(['estado' => 0])`, no `delete()`. Se implementó la **Opción B**: no se elimina la foto de Cloudinary al desactivar. Si el producto se reactiva, la foto sigue disponible.

### 4.4 Cache de índice
El trait `CacheaPanel` cachea la vista `index`. Ya se invalida en store/update/destroy existentes. Al agregar la columna foto, la cache se invalidará automáticamente en estas operaciones.

### 4.5 Tamaño de foto
El límite de 2048 KB (2 MB) es consistente con el sistema documentado. Puede ajustarse según necesidad.

### 4.6 Foto huérfana en Cloudinary
Si la transacción de BD falla después de subir la foto, la imagen queda huérfana en Cloudinary. Es el mismo trade-off aceptado en el sistema documentado.
