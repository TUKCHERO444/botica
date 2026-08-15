# Módulo Multi-Imagen para Productos

## 1. Resumen Ejecutivo

Migrar el sistema de una imagen por producto (columna `foto` en `producto`) a un sistema de múltiples imágenes (hasta 4) gestionadas desde una tabla independiente `producto_imagen`, con soporte para ordenamiento drag-and-drop en el panel admin y galería interactiva en la vista pública.

---

## 2. Requisitos Funcionales

| ID | Requisito | Prioridad |
|----|-----------|-----------|
| RF01 | El usuario admin puede agregar 0, 1 o hasta 4 imágenes por producto | Alta |
| RF02 | Las imágenes se suben a Cloudinary (mismo patrón existente) | Alta |
| RF03 | Las imágenes se pueden reordenar por drag-and-drop dentro del modal de creación/edición | Alta |
| RF04 | La primera imagen (orden 0) es la miniatura principal: se usa en tabla admin, cards públicas, y vista de detalle pública | Alta |
| RF05 | Si no hay imágenes, se muestra placeholder (comportamiento actual) | Media |
| RF06 | En la vista pública de detalle, las imágenes se muestran en una galería con thumbnails laterales a la izquierda | Alta |
| RF07 | La galería pública cambia automáticamente cada 5 segundos | Media |
| RF08 | El usuario puede seleccionar manualmente cualquier thumbnail para ver la imagen | Alta |
| RF09 | Al editar, las imágenes existentes se muestran con su orden actual y se pueden reordenar, eliminar individualmente, o agregar nuevas hasta completar 4 | Alta |
| RF10 | Las imágenes huérfanas en Cloudinary se eliminan al quitar una imagen del producto | Media |

---

## 3. Diseño de Base de Datos

### Migración: `create_producto_imagen_table.php`

```php
Schema::create('producto_imagen', function (Blueprint $table) {
    $table->id('id_producto_imagen');
    $table->foreignId('producto_id_producto')
          ->constrained('producto', 'id_producto')
          ->onDelete('cascade');
    $table->text('url');
    $table->integer('orden')->default(0);
    $table->timestamps();

    $table->index(['producto_id_producto', 'orden']);
});
```

**Nota:** La columna `foto` existente en `producto` se migrará: los productos con `foto` no nula tendrán su imagen insertada en `producto_imagen` con `orden = 0`. Luego la columna `foto` será eliminada.

### Modelo `ProductoImagen.php`

```php
class ProductoImagen extends Model
{
    protected $table = 'producto_imagen';
    protected $primaryKey = 'id_producto_imagen';
    public $timestamps = true;

    protected $fillable = [
        'producto_id_producto',
        'url',
        'orden',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id_producto');
    }
}
```

### Cambios en `Producto.php`

```php
// Relación
public function imagenes()
{
    return $this->hasMany(ProductoImagen::class, 'producto_id_producto')
                ->orderBy('orden');
}

// Accessor: mantiene compatibilidad con código existente
public function getFotoUrlAttribute(): ?string
{
    $primera = $this->imagenes->first();
    return $primera?->url;
}
```

---

## 4. Arquitectura del Modal Admin

### Layout del Modal (Create/Edit)

El formulario existente se mantiene como columna izquierda. Se agrega una **columna derecha** dedicada a la gestión de imágenes:

```
┌─────────────────────────────────────────────────┐
│  [Modal]  Nuevo Producto                   [X]  │
├──────────────────────┬──────────────────────────┤
│  Columna Izquierda   │  Columna Derecha          │
│  (Formulario)        │  (Gestor de Imágenes)     │
│                      │                           │
│  Nombre              │  ┌───┐ ┌───┐ ┌───┐ ┌───┐ │
│  Descripción         │  │ 1 │ │ 2 │ │ 3 │ │ 4 │ │
│  Categoría           │  └───┘ └───┘ └───┘ └───┘ │
│  Marca               │  (Drag & Drop)            │
│  Precios             │  [+ Agregar imagen]       │
│  Stock/Inventario    │                           │
│  Estado              │  Si hay imagen selecc.    │
│                      │  → preview grande         │
│  [Cancelar] [Guardar]│                           │
└──────────────────────┴──────────────────────────┘
```

### Comportamiento Drag & Drop

- Cada slot muestra la miniatura de la imagen cargada o un placeholder "+"
- Se pueden arrastrar las miniaturas entre slots para reordenar
- El slot 1 (orden 0) define la miniatura principal
- Al arrastrar, se actualiza el orden visual y el orden interno
- Si hay < 4 imágenes, los slots vacíos muestran botón para agregar

### Flujo de Subida

```
1. Usuario hace clic en slot vacío o en [+ Agregar imagen]
2. File picker se abre (acepta jpg, jpeg, png, webp, max 2MB cada una)
3. Se genera preview local con FileReader (mismo patrón shared.js)
4. La imagen se agrega al array interno del JS
5. Al guardar, se envían todas las imágenes como array en FormData
```

### Manejo en Edición

```
1. Cargar producto_imagen existente ordenadas por 'orden'
2. Mostrar en slots según su orden
3. Si el usuario agrega nueva imagen → reemplaza o completa slot
4. Si el usuario elimina imagen → se marca para borrar de Cloudinary
5. Si el usuario reordena → se actualiza el orden visual
6. Al guardar:
   a. Nuevas imágenes → subir a Cloudinary
   b. Imágenes eliminadas → destroy en Cloudinary
   c. Reordenar → actualizar 'orden' en BD
```

---

## 5. Vista Pública — Galería de Detalle

### Layout

```
┌──────────────────────────────────────────┐
│  Breadcrumb: Inicio / Productos / Prod   │
├──────────────────────┬───────────────────┤
│  Columna Izquierda   │  Columna Derecha   │
│  (Galería)           │  (Info Producto)   │
│                      │                    │
│  ┌────────────────┐  │  Categoría         │
│  │                │  │  Nombre            │
│  │  Imagen        │  │  Marca             │
│  │  Principal     │  │  Precio            │
│  │                │  │  Stock             │
│  └────────────────┘  │  Descripción       │
│                      │                    │
│  [img1] [img2]       │                    │
│  [img3] [img4]       │                    │
│  (thumbnails)        │                    │
└──────────────────────┴───────────────────┘
```

### Comportamiento

- **Imagen principal**: Ocupa el área grande a la izquierda, muestra la imagen seleccionada
- **Thumbnails**: Tira vertical de miniaturas debajo de la imagen principal (o al lado en desktop)
  - La miniatura activa tiene borde destacado
  - Click en thumbnail → cambia la imagen principal
- **Auto-rotación**: Cada 5 segundos avanza a la siguiente imagen, en loop infinito
  - Se pausa al hacer hover sobre la galería
  - Se reanuda al quitar el hover
  - Se reinicia el contador al hacer clic manual en un thumbnail
- **Si solo hay 1 imagen**: No se muestran thumbnails ni auto-rotación (comportamiento estático)
- **Si no hay imágenes**: Placeholder actual (SVG)

---

## 6. Plan de Implementación

### Fase 0: Preparación

| Paso | Archivos | Descripción |
|------|----------|-------------|
| 0.1 | `docs/modulo-multi-imagen-producto.md` | Este documento |

### Fase 1: Base de Datos

| Paso | Archivos | Descripción | Verificación |
|------|----------|-------------|--------------|
| 1.1 | Migración `create_producto_imagen_table` | Crear tabla `producto_imagen` | `php artisan migrate` |
| 1.2 | Migración `migrate_foto_to_producto_imagen` | Migrar datos de `producto.foto` a `producto_imagen`, luego dropear columna `foto` | Verificar datos |

### Fase 2: Modelos

| Paso | Archivos | Descripción |
|------|----------|-------------|
| 2.1 | `app/Models/ProductoImagen.php` | Nuevo modelo con fillable, relación a Producto |
| 2.2 | `app/Models/Producto.php` | Agregar relación `imagenes()`, modificar accessor `foto_url`, quitar `'foto'` de fillable |

### Fase 3: Backend

| Paso | Archivos | Descripción |
|------|----------|-------------|
| 3.1 | `app/Http/Requests/Admin/StoreProductoRequest.php` | Cambiar validación `foto` → `imagenes` (array, max:4, cada una image, max:2048) |
| 3.2 | `app/Http/Requests/Admin/UpdateProductoRequest.php` | Mismo cambio |
| 3.3 | `app/Http/Controllers/Admin/ProductoController.php` | Store: procesar array de imágenes, subir a Cloudinary, crear registros. Update: subir nuevas, eliminar removidas, reordenar |
| 3.4 | `app/Http/Controllers/Public/ProductoCatalogoController.php` | En show(), cargar `producto.imagenes` |

### Fase 4: Vistas Admin

| Paso | Archivos | Descripción |
|------|----------|-------------|
| 4.1 | `resources/views/admin/productos/create.blade.php` | Agregar columna derecha con gestor de imágenes (4 slots drag-and-drop) |
| 4.2 | `resources/views/admin/productos/edit.blade.php` | Mismo layout, cargando imágenes existentes |
| 4.3 | `resources/views/admin/productos/index.blade.php` | Cambiar `$p->foto_url` → `$p->foto_url` (sigue funcionando por accessor) |

### Fase 5: Vistas Públicas

| Paso | Archivos | Descripción |
|------|----------|-------------|
| 5.1 | `resources/views/components/product-card.blade.php` | Usar `$producto->foto_url` (compatible) |
| 5.2 | `resources/views/components/product-slider.blade.php` | Usar `$prod->foto_url` (compatible) |
| 5.3 | `resources/views/public/productos/show.blade.php` | Rediseñar columna izquierda con galería (imagen principal + thumbnails + auto-rotación) |

### Fase 6: JavaScript

| Paso | Archivos | Descripción |
|------|----------|-------------|
| 6.1 | `resources/js/admin/productos/shared.js` | Refactorizar: exportar función para múltiples imágenes con drag-and-drop |
| 6.2 | `resources/js/admin/productos/create.js` | Inicializar gestor de imágenes multi-slots |
| 6.3 | `resources/js/admin/productos/edit.js` | Inicializar gestor con datos existentes |
| 6.4 | `resources/js/public/tienda/galeria.js` (nuevo) | Módulo de galería pública con auto-rotación y selección manual |

### Fase 7: Configuración

| Paso | Archivos | Descripción |
|------|----------|-------------|
| 7.1 | `vite.config.js` | Agregar entrada `resources/js/public/tienda/galeria.js` |

### Fase 8: Tests

| Paso | Archivos | Descripción |
|------|----------|-------------|
| 8.1 | `tests/Feature/ProductoMultiImagenTest.php` | Tests de subida múltiple, eliminación, reorden, galería |

---

## 7. Tests

### `tests/Feature/ProductoMultiImagenTest.php`

```php
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductoMultiImagenTest extends TestCase
{
    use RefreshDatabase;

    // Escenarios de store
    public function test_creates_producto_without_images();
    public function test_creates_producto_with_single_image();
    public function test_creates_producto_with_multiple_images_up_to_4();
    public function test_rejects_more_than_4_images();

    // Escenarios de update
    public function test_adds_new_images_to_existing_producto();
    public function test_removes_image_and_deletes_from_cloudinary();
    public function test_reorders_images_on_update();
    public function test_replaces_all_images();

    // Escenarios de galería pública
    public function test_shows_first_image_as_thumbnail_in_cards();
    public function test_shows_gallery_only_when_multiple_images_exist();
    public function test_shows_placeholder_when_no_images();

    // Migración
    public function test_migrates_existing_foto_to_producto_imagen();
}
```

---

## 8. Consideraciones

- **Compatibilidad hacia atrás**: El accessor `getFotoUrlAttribute()` en Producto devuelve `$this->imagenes->first()?->url`, manteniendo compatibilidad total con vistas existentes (index, cards, sliders, navbar-search)
- **Límite de 4 imágenes**: Se valida tanto en Frontend (JS evita agregar más) como en Backend (FormRequest valida `max:4`)
- **Drag & drop nativo**: Se usa HTML5 Drag & Drop API, sin librerías externas
- **Auto-rotación**: Usa `setInterval` con pausa en hover, se reinicia al hacer clic manual
- **Cache**: Se invalida `admin.productos.index` en store/update (ya implementado)
- **Orden en slider**: La galería pública muestra las imágenes ordenadas por el campo `orden` definido en el admin
