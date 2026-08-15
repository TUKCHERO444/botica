# Módulo: Carrito de Compras

## 1. Descripción General

El módulo de carrito de compras permite a los **clientes registrados** (`guard: clientes`) seleccionar productos y combos del catálogo público, almacenarlos de forma persistente en base de datos, gestionar cantidades, visualizar subtotales y totales, descargar un PDF con el detalle y cotizar el carrito vía WhatsApp.

**Requisito previo:** El cliente debe haber iniciado sesión (`auth:clientes`). Si no ha iniciado sesión, al hacer clic en el ícono del carrito o en "Añadir al carrito" se redirige a `/iniciar-sesion`.

---

## 2. Estructura de Base de Datos

### Tabla `carrito`

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id_carrito` | BIGINT PK AUTO_INCREMENT | Identificador único del carrito |
| `cliente_id_cliente` | INT NOT NULL | FK → `cliente(id_cliente)` |
| `created_at` | TIMESTAMP NULL | Fecha de creación |
| `updated_at` | TIMESTAMP NULL | Fecha de última modificación |

**Restricciones:**
- UNIQUE (`cliente_id_cliente`) — un carrito por cliente.
- FK → `cliente(id_cliente)` ON DELETE CASCADE.

### Tabla `carrito_item`

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id_carrito_item` | BIGINT PK AUTO_INCREMENT | Identificador único del item |
| `carrito_id_carrito` | BIGINT NOT NULL | FK → `carrito(id_carrito)` ON DELETE CASCADE |
| `item_type` | VARCHAR(20) NOT NULL | Tipo: `'producto'` o `'combo'` |
| `item_id` | INT NOT NULL | ID del producto o combo |
| `cantidad` | INT NOT NULL DEFAULT 1 | Cantidad seleccionada |
| `created_at` | TIMESTAMP NULL | |
| `updated_at` | TIMESTAMP NULL | |

**Restricciones:**
- UNIQUE (`carrito_id_carrito`, `item_type`, `item_id`) — evita duplicados del mismo item.
- FK → `carrito(id_carrito)` ON DELETE CASCADE.

---

## 3. Modelos

### `App\Models\Carrito`

```php
class Carrito extends Model
{
    protected $table = 'carrito';
    protected $primaryKey = 'id_carrito';

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id_cliente', 'id_cliente');
    }

    public function items()
    {
        return $this->hasMany(CarritoItem::class, 'carrito_id_carrito', 'id_carrito');
    }
}
```

### `App\Models\CarritoItem`

```php
class CarritoItem extends Model
{
    protected $table = 'carrito_item';
    protected $primaryKey = 'id_carrito_item';

    protected $fillable = ['carrito_id_carrito', 'item_type', 'item_id', 'cantidad'];

    public function carrito()
    {
        return $this->belongsTo(Carrito::class, 'carrito_id_carrito', 'id_carrito');
    }

    public function item()
    {
        return $this->morphTo('item', 'item_type', 'item_id');
    }

    public function getSubtotalAttribute(): float
    {
        $precio = match ($this->item_type) {
            'producto' => $this->item?->precio_venta ?? 0,
            'combo'    => $this->item?->precio_sugerido ?? 0,
            default    => 0,
        };
        return $precio * $this->cantidad;
    }
}
```

### `App\Models\Cliente` — relación agregada

```php
public function carrito()
{
    return $this->hasOne(Carrito::class, 'cliente_id_cliente', 'id_cliente');
}
```

---

## 4. Controlador — `App\Http\Controllers\Public\CarritoController`

### Métodos

| Método HTTP | Ruta | Controlador | Descripción |
|-------------|------|-------------|-------------|
| GET | `/carrito` | `index()` | Muestra la página del carrito con todos los items |
| POST | `/carrito` | `store()` | Agrega un producto o combo al carrito (AJAX) |
| PUT | `/carrito/{item}` | `update()` | Actualiza la cantidad de un item (AJAX) |
| DELETE | `/carrito/{item}` | `destroy()` | Elimina un item del carrito (AJAX) |
| GET | `/carrito/pdf` | `pdf()` | Descarga PDF con el detalle del carrito |
| GET | `/carrito/whatsapp` | `whatsapp()` | Redirige a `wa.me` con mensaje preformateado |
| GET | `/carrito-count` | `count()` | Retorna JSON con el conteo total de items (para el badge) |

### `store(Request)` — flujo

1. Valida `item_type` (producto|combo) e `item_id`.
2. Verifica que la entidad exista y esté activa/visible.
3. Obtiene o crea el `Carrito` del cliente autenticado.
4. Busca si ya existe un `CarritoItem` con mismo `item_type` e `item_id`; si existe, incrementa la cantidad; si no, crea uno nuevo.
5. Retorna JSON con: `success`, `message`, `count` (total de items).

### `update(Request, CarritoItem)` — flujo

1. Verifica que el item pertenezca al carrito del cliente autenticado.
2. Valida `cantidad` >= 1.
3. Actualiza la cantidad.
4. Retorna JSON con: `subtotal` (del item), `total` (del carrito), `count`.

### `destroy(CarritoItem)` — flujo

1. Verifica pertenencia al cliente.
2. Elimina el item.
3. Retorna JSON con: `success`, `count`.

### `pdf()` — flujo

1. Obtiene carrito del cliente autenticado.
2. Genera PDF con `barryvdh/laravel-dompdf` usando la vista `public.carrito.pdf`.
3. Retorna el PDF para descarga.

### `whatsapp()` — flujo

1. Obtiene carrito del cliente autenticado.
2. Construye mensaje de texto con lista de items, subtotales, total y datos del cliente.
3. Redirige a `https://wa.me/{phone}?text={mensaje_codificado}`.

### `count()` — flujo

1. Obtiene carrito del cliente autenticado (si existe).
2. Retorna JSON: `{ count: N }`.

---

## 5. Rutas — `routes/web.php`

Se agregan dentro del grupo `middleware('auth:clientes')`:

```php
use App\Http\Controllers\Public\CarritoController;

// Dentro del grupo auth:clientes:
Route::prefix('carrito')->name('public.carrito.')->group(function () {
    Route::get('/', [CarritoController::class, 'index'])->name('index');
    Route::post('/', [CarritoController::class, 'store'])->name('store');
    Route::put('/{item}', [CarritoController::class, 'update'])->name('update');
    Route::delete('/{item}', [CarritoController::class, 'destroy'])->name('destroy');
    Route::get('/pdf', [CarritoController::class, 'pdf'])->name('pdf');
    Route::get('/whatsapp', [CarritoController::class, 'whatsapp'])->name('whatsapp');
});
Route::get('/carrito-count', [CarritoController::class, 'count'])->name('public.carrito.count');
```

---

## 6. Vistas

### `resources/views/public/carrito/index.blade.php`

Página principal del carrito. Contiene:

- **Encabezado**: título "Mi Carrito" + conteo de items.
- **Lista de items**: cada item muestra:
  - Imagen del producto/combo (o placeholder).
  - Nombre, tipo (Producto / Combo), precio unitario.
  - Control de cantidad: botón `−`, input numérico, botón `+`.
  - Subtotal del item.
  - Botón eliminar (icono papelera).
- **Resumen final**: total general.
- **Acciones**:
  - Botón "Descargar PDF" → `route('public.carrito.pdf')`.
  - Botón "Cotizar por WhatsApp" → `route('public.carrito.whatsapp')`.
  - Botón "Seguir comprando" → `route('public.productos.index')`.
- **Estado vacío**: si no hay items, muestra mensaje "Tu carrito está vacío" con enlace al catálogo.

### `resources/views/public/carrito/pdf.blade.php`

Template para generar el PDF con `dompdf`. Contiene:

- Logo/nombre de la botica y fecha de emisión.
- **Datos del cliente**: nombre completo, DNI, teléfono, email.
- **Tabla de items**:
  | # | Producto/Combo | Cantidad | Precio Unit. | Subtotal |
- **Total general** en negrita.
- Nota: "Este documento es una cotización generada por el cliente."

---

## 7. Modificaciones al Layout Público

### Navbar — ícono del carrito

Se agrega un ícono de carrito de compras en el navbar, **antes del menú de usuario** (tanto en vista desktop como mobile):

- Si el cliente **no está autenticado**: el link apunta a `route('public.login')`.
- Si el cliente **está autenticado**: el link apunta a `route('public.carrito.index')`.
- Muestra un **badge rojo** con el número total de items (suma de cantidades de todos los items). El badge se oculta si el conteo es 0.
- El badge se actualiza dinámicamente vía JavaScript (`/carrito-count`).

### Compartir `$cartCount` con el layout

En `App\Providers\AppServiceProvider::boot()`:

```php
use Illuminate\Support\Facades\View;

View::composer('layouts.public', function ($view) {
    $count = 0;
    if (auth()->guard('clientes')->check()) {
        $carrito = auth()->guard('clientes')->user()->carrito;
        $count = $carrito?->items->sum('cantidad') ?? 0;
    }
    $view->with('cartCount', $count);
});
```

---

## 8. Modificaciones a Vistas de Producto y Combo

### `public/productos/show.blade.php`

Después del componente `<x-whatsapp-button />`, se agrega:

```blade
@auth('clientes')
    <button type="button"
            data-item-type="producto"
            data-item-id="{{ $producto->id_producto }}"
            class="add-to-cart-btn w-full mt-3 px-6 py-3 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-lg transition shadow-sm flex items-center justify-center gap-2">
        <svg class="w-5 h-5" ...>ícono carrito</svg>
        Añadir al carrito
    </button>
@else
    <a href="{{ route('public.login') }}"
       class="w-full mt-3 px-6 py-3 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-lg transition shadow-sm flex items-center justify-center gap-2">
        <svg class="w-5 h-5" ...>ícono carrito</svg>
        Inicia sesión para añadir al carrito
    </a>
@endauth
```

### `public/combos/show.blade.php`

Mismo patrón, con `data-item-type="combo"` y `data-item-id="{{ $combo->id_combo }}"`.

---

## 9. JavaScript — `resources/js/public/carrito.js`

Funcionalidades:

1. **Añadir al carrito** (`.add-to-cart-btn`):
   - Escucha clic en todos los botones con clase `.add-to-cart-btn`.
   - Obtiene `item_type` e `item_id` de los atributos `data-*`.
   - Envía POST a `/carrito` con `fetch`.
   - Muestra notificación toast de éxito/error.
   - Actualiza el badge `#cart-badge`.

2. **Actualizar badge**:
   - Función `updateCartBadge()` que hace GET a `/carrito-count` y actualiza `#cart-badge`.
   - Se llama después de cada operación de agregar/eliminar/actualizar.

3. **Página del carrito** (solo se ejecuta si existe `#cart-page`):
   - Botones `+` y `−`: modifican el input de cantidad y envían PUT a `/carrito/{id}`.
   - Actualizan subtotal del item y total general con la respuesta JSON.
   - Botón eliminar: confirma y envía DELETE a `/carrito/{id}`.

---

## 10. PDF — Generación

Se utiliza `barryvdh/laravel-dompdf` (ya instalado en el proyecto).

```php
use Barryvdh\DomPDF\Facade\Pdf;

public function pdf()
{
    $carrito = auth()->guard('clientes')->user()->carrito;

    if (!$carrito || $carrito->items->isEmpty()) {
        return redirect()->route('public.carrito.index')
            ->withErrors('El carrito está vacío.');
    }

    $cliente = auth()->guard('clientes')->user();
    $items = $carrito->items->load('item');
    $total = $items->sum('subtotal');

    $pdf = Pdf::loadView('public.carrito.pdf', compact('items', 'cliente', 'total'));

    return $pdf->download('carrito-' . now()->format('Ymd-His') . '.pdf');
}
```

---

## 11. WhatsApp — Mensaje

El mensaje generado en `whatsapp()` tiene el siguiente formato:

```
Hola, quisiera cotizar los siguientes productos:

1. [Producto A] x2 — S/ 20.00
2. [Combo B] x1 — S/ 35.00
...

Total: S/ 55.00

Mis datos:
- Nombre: Juan Pérez
- DNI: 12345678
- Teléfono: 987654321
- Email: juan@email.com

Puede ver el detalle completo en el PDF adjunto.
```

---

## 12. Resumen de Archivos

### Archivos a crear (8)

| # | Archivo | Descripción |
|---|---------|-------------|
| 1 | `database/migrations/2026_06_13_000001_create_carrito_table.php` | Migración tabla `carrito` |
| 2 | `database/migrations/2026_06_13_000002_create_carrito_item_table.php` | Migración tabla `carrito_item` |
| 3 | `app/Models/Carrito.php` | Modelo `Carrito` |
| 4 | `app/Models/CarritoItem.php` | Modelo `CarritoItem` |
| 5 | `app/Http/Controllers/Public/CarritoController.php` | Controlador del carrito |
| 6 | `resources/views/public/carrito/index.blade.php` | Vista del carrito |
| 7 | `resources/views/public/carrito/pdf.blade.php` | Template PDF |
| 8 | `resources/js/public/carrito.js` | JavaScript del carrito |

### Archivos a modificar (5)

| # | Archivo | Cambio |
|---|---------|--------|
| 1 | `app/Models/Cliente.php` | Agregar relación `carrito()` |
| 2 | `routes/web.php` | Agregar rutas del carrito |
| 3 | `resources/views/layouts/public.blade.php` | Agregar ícono del carrito en navbar |
| 4 | `resources/views/public/productos/show.blade.php` | Agregar botón "Añadir al carrito" |
| 5 | `resources/views/public/combos/show.blade.php` | Agregar botón "Añadir al carrito" |
| 6 | `app/Providers/AppServiceProvider.php` | Compartir `$cartCount` con el layout |

---

## 13. Dependencias

- `barryvdh/laravel-dompdf` — ya instalado en el proyecto.
- Tailwind CSS — ya configurado.
- Vite — ya configurado.
