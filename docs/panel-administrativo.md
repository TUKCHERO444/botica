# Panel Administrativo — SPA + AJAX

> **Última actualización:** 18/11/2026
> **Implementación vigente:** Blade + Vanilla JS + Axios. No hay framework SPA (React/Vue/Inertia) ni router de Laravel distinto al descrito aquí.

---

## 1. Arquitectura general

El panel es una **Single Page Application sobre Blade**:

- `resources/views/layouts/admin.blade.php` se renderiza **una sola vez** y actúa como shell.
- Cada módulo es una **vista parcial** (sin `@extends`) que se inyecta dentro de `<main id="panel-content">`.
- Los formularios CRUD se abren en un **modal** común (`#modal-overlay` / `#modal-container`).
- Toda petición AJAX usa **Axios**, configurado globalmente en `resources/js/bootstrap.js` con `X-Requested-With` y `X-CSRF-TOKEN`.
- La navegación actualiza el historial (`pushState`) y responde a atrás/adelante (`popstate`).

```
Carga inicial        GET /admin/dashboard        → layout completo + partial dashboard
Navegación SPA       click [data-panel]          → GET /admin/productos → reemplaza #panel-content
Abrir modal          click [data-modal]          → GET /admin/productos/create → modal
Guardar              submit del form             → POST/PUT/DELETE → JSON → cierra modal + recarga + toast
Eliminar/cambiar     click [data-delete] o       → DELETE/PATCH → JSON → recarga + toast
estado               [data-toggle-status]
```

---

## 2. Layout y shell

`resources/views/layouts/admin.blade.php` contiene:

- `<head>` con `@vite(['resources/css/app.css', 'resources/js/app.js'])`, meta CSRF y `@stack('head')`.
- Sidebar fijo (`#sidebar`) con grupos: **Información, Inventario, Clientes, Operaciones, Administración, Finanzas, Portada**.
- Backdrop móvil (`#sidebar-backdrop`) y botones `#sidebar-toggle` / `#sidebar-close`.
- Header con el nombre del usuario autenticado.
- `<main id="panel-content">` donde se inyectan los partials.
- Overlay de modal único y `@stack('scripts')`.
- Al final carga `@vite('resources/js/admin/sidebar.js')`.

El sidebar usa `[data-panel="/admin/..."]` para la navegación SPA. Algunos enlaces están envueltos en `@can(...)` para ocultarse según permisos; aun así, la autorización real está en el middleware de cada ruta.

---

## 3. Convención de vistas

Cada módulo tiene dos tipos de vista:

| Tipo | Archivo | ¿Extiende layout? | Uso |
|---|---|---|---|
| **Partial** | `admin/<modulo>/index.blade.php` | **No** | Contenido inyectado por AJAX |
| **Completa** | `admin/<modulo>/full.blade.php` | Sí (`@extends('layouts.admin')`) | Carga directa, F5, enlaces externos |
| **Formulario** | `admin/<modulo>/create.blade.php`, `edit.blade.php` | No | Contenido del modal |
| **Detalle** | `admin/<modulo>/show.blade.php` | No | Modal de solo lectura (ventas, atenciones, compras, auditoría); kardex usa `producto.blade.php` |

Ejemplo de vista completa:

```blade
{{-- resources/views/admin/productos/full.blade.php --}}
@extends('layouts.admin')

@section('title', 'Productos')

@section('content')
    @include('admin.productos.index')
@endsection
```

Regla: una vista partial **nunca** debe usar `@extends`; si lo hace, el layout se duplicaría dentro de `#panel-content`.

---

## 4. Patrón de controladores

Los controladores distinguen peticiones AJAX de peticiones normales:

```php
public function index()
{
    if (request()->ajax()) {
        return $this->getIndex('productos', function () {
            $productos = Producto::with('categoria', 'marca')->get();
            return view('admin.productos.index', compact('productos'))->render();
        });
    }

    $productos = Producto::with('categoria', 'marca')->get();
    return view('admin.productos.full', compact('productos'));
}

public function create()
{
    if (request()->ajax()) {
        return view('admin.productos.create', [...]);
    }

    return redirect()->route('admin.productos.index');
}

public function store(StoreProductoRequest $request)
{
    $producto = Producto::create($request->validated());

    if ($request->ajax()) {
        return response()->json([
            'success' => true,
            'message' => 'Producto creado correctamente',
        ]);
    }

    return redirect()->route('admin.productos.index')->with('success', 'Producto creado correctamente');
}
```

- `create`/`edit` solo responden con el formulario si la petición es AJAX; si se abren directamente, redirigen al listado.
- `store`/`update`/`destroy` devuelven JSON `{ success, message }` en AJAX y redirect con flash en navegación normal.
- Los errores de validación se devuelven como 422 y los renderiza el modal.
- La mayoría de controladores usan `CacheaPanel` (no-op) y `Audita` (bitácora).

---

## 5. Router SPA — `resources/js/admin/router.js`

Se importa desde `app.js` y se inicializa en `DOMContentLoaded`.

Responsabilidades:

1. Detectar clics en `[data-panel]`.
2. Hacer `GET` con Axios a la URL indicada.
3. Reemplazar `#panel-content` con el HTML recibido.
4. **Re-ejecutar los `<script>`** del HTML inyectado (añade cache-busting `?_t=...` a los `src` y fuerza `type="module"`).
5. Actualizar `history.pushState` y resaltar el enlace activo.
6. Cerrar el sidebar móvil, hacer scroll al inicio y emitir el evento `panel-loaded`.
7. Interceptar enlaces de paginación (`[data-pagination] a` y `.pagination a`) para que también se carguen dentro del panel.

Los listados administrativos usan el parcial global:

```blade
@include('admin.partials.pagination', ['items' => $productos])
```

con `paginate(10)->withQueryString()` en el controlador. Ver `docs/mapeo-paginacion-modulos.md`.

Comportamiento resumido:

```js
const AdminRouter = {
    init() {
        this.$panel = document.getElementById('panel-content');
        if (!this.$panel) return;
        this.bindEvents();
        this.highlightActiveLink();
    },

    navigate(url) {
        this.load(url, true);
    },

    async load(url, pushState = true) {
        const res = await axios.get(url);
        this.$panel.innerHTML = res.data;
        // Re-ejecuta scripts del partial
        // ...
        if (pushState) window.history.pushState({ url }, '', url);
        this.highlightActiveLink();
        window.scrollTo(0, 0);
        document.dispatchEvent(new CustomEvent('panel-loaded'));
    },
};
```

> El evento `panel-loaded` lo usa el módulo de reportes para reinicializar su lógica tras cada navegación (también el helper legacy `search.js`).

### Tamaño de modales

Los modales declaran su ancho con `data-modal-size` en la raíz:

```blade
<form data-modal-size="3xl" ...>
<div data-modal-size="4xl" ...>
```

`admin/modal.js` aplica `max-w-lg`, `max-w-2xl`, `max-w-3xl`, `max-w-4xl`, `max-w-5xl`, `max-w-6xl` o `max-w-7xl`. Al cerrar vuelve a `max-w-4xl`. Ver `docs/mapeo-modales-ui.md`.

---

## 6. Sistema de modales — `resources/js/admin/modal.js`

El layout define un único overlay:

```blade
<div id="modal-overlay" class="fixed inset-0 z-50 hidden bg-black/50 flex items-center justify-center">
    <div id="modal-container" class="bg-white rounded-xl shadow-xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
    </div>
</div>
```

Triggers:

| Atributo | Acción |
|---|---|
| `data-modal="/admin/productos/1/edit"` | `GET` remoto y apertura del modal |
| `data-close-modal` | Cierra el modal |
| `data-delete="/admin/productos/1"` | Confirmación + `DELETE` AJAX |
| `Escape` | Cierra el modal |

Comportamiento del driver:

1. `open(url)`: `GET` con Axios, inyecta HTML y **re-ejecuta scripts** con cache-busting.
2. Bloquea el scroll del body mientras el modal está abierto.
3. `submitForm(form)`:
   - Detecta `_method` para method spoofing (envía `POST` con override).
   - Envía `FormData` para soportar archivos.
   - Deshabilita el botón y muestra "Guardando...".
   - En éxito: cierra, recarga el panel actual (`AdminRouter.load(window.location.href, false)`) y muestra un toast.
   - En 422: pinta los errores junto a cada campo. Los nombres anidados (`productos.0.cantidad`) se convierten a `productos[0][cantidad]`.
   - En otros errores: toast de error.
4. `confirmDelete(url)`: confirma con `confirm()` y ejecuta `DELETE`; en éxito recarga y muestra toast.

**No** se permite abrir un modal desde otro modal: los triggers dentro de `#modal-container` están contemplados, pero la convención es cerrar el anterior antes de abrir uno nuevo.

---

## 7. Otros módulos JS del panel

| Archivo | Función |
|---|---|
| `app.js` | Importa `bootstrap`, `toast`, `toggle`, `autocomplete`, `router` y `modal`; inicializa router y modal |
| `bootstrap.js` | `window.axios`, headers `X-Requested-With` y `X-CSRF-TOKEN` |
| `toast.js` | Notificaciones flotantes (`success`, `error`, `info`, `warning`), una a la vez |
| `toggle.js` | `PATCH [data-toggle-status]` y recarga del panel |
| `autocomplete.js` | Sugerencias con debounce de 300 ms sobre `[data-autocomplete]`; al limpiar, recarga `data-search-url` |
| `sidebar.js` | Abrir/cerrar sidebar móvil |
| `search.js` | Búsqueda con debounce de 350 ms sobre `[data-search]` (helper legacy; no se importa en `app.js`) |
| `client-search.js` | Buscador de clientes y botón "cliente genérico" en ventas/atenciones |
| `producto-search.js` | Buscador dinámico de producto/presentación con precio y stock en el formulario de venta |
| `reniec-search.js` | Consulta DNI y autocompletado |
| `sunat-search.js` | Consulta RUC y autocompletado |
| `productos/shared.js` | Gestión de imágenes (preview, orden, eliminación) |
| `productos/presentaciones.js` | Repeater de presentaciones del producto (agregar/quitar filas) |
| `ventas/venta-form.js`, `atenciones/atencion-form.js`, `ingresos/ingreso-form.js` | Lógica compartida de líneas de detalle, presentación, cantidad y totales |

> Los formularios de producto usan unidad base y presentaciones; la venta usa un buscador dinámico de producto/presentación (nombre o código de barras) con precio, stock y paso, mientras que atención y compra mantienen el select combinado “Producto / presentación”. Las compras agregan documento, lote, vencimiento, costo y estados borrador/recibida; las ventas/atenciones aplican FEFO automáticamente y muestran los lotes consumidos.

---

## 8. Carga de JavaScript y Vite

### 8.1. Reglas

1. Cada vista que necesite JS debe cargarlo con `@vite()`.
2. **Todo archivo referenciado con `@vite` debe estar registrado en `vite.config.js`** (`input`). Si no, `npm run build` no genera la entrada y la vista falla en producción al resolver el manifest.
3. Vistas con `@extends` → `@push('scripts') @vite(...) @endpush`.
4. Partials AJAX → `@vite(...)` directo, porque `@push` no se renderiza en una respuesta partial.
5. El router y el modal re-ejecutan los scripts inyectados; por eso los partials pueden cargar su propio JS.

### 8.2. Scripts por módulo

Los módulos con lógica propia cargan su entrypoint desde el partial o el formulario:

- Productos: `index.js`, `create.js`, `edit.js` (incluye repeater de presentaciones).
- Ventas: `index.js`, `create.js` (bloque de pago con `pagos.js`).
- Atenciones: `index.js`, `create.js` (bloque de pago con `pagos.js`). Las atenciones no se editan; se anulan con motivo.
- Compras: `ingresos/index.js`, `ingresos/create.js`, `ingresos/edit.js`.
- Caja, dashboard, banners, sliders, combos, promociones, header-text, portada-categorías, auditoría, reportes.
- Formularios de clientes/proveedores: consulta RENIEC/SUNAT.

Todas esas entradas están registradas en `vite.config.js`.

---

## 9. Caché del panel

**No se cachea HTML del panel.**

El trait `App\Http\Controllers\Admin\Traits\CacheaPanel` es un no-op deliberado:

```php
protected function getIndex(string $modulo, callable $callback, int $ttl = 3600): mixed
{
    return $callback();
}

protected function olvidaIndex(string $modulo): void
{
    // No-op
}
```

Los controladores siguen llamando a `getIndex()` / `olvidaIndex()` por compatibilidad, pero siempre se sirven datos frescos. El análisis del bug que motivó esta decisión está en [`cache-panel-administrativo.md`](cache-panel-administrativo.md). No reintroducir caché de vistas sin:

1. Incluir el usuario en la clave.
2. Invalidar por eventos del modelo.
3. Usar TTL corto.
4. Cachear datos, no HTML renderizado.

---

## 10. Flujo completo de una operación

```
1. Usuario entra a /admin/productos
   → layout completo + partial productos/index

2. Click en "+ Nuevo producto" [data-modal]
   → GET /admin/productos/create
   → modal con formulario + @csrf + @vite(productos/create.js)

3. Completa y guarda
   → POST /admin/productos (FormData, X-Requested-With)
   → 200 { success: true, message }
   → cierra modal, recarga /admin/productos, toast

4. Error de validación
   → 422 { errors: { nombre: [...] } }
   → mensajes bajo cada campo, modal permanece abierto

5. Click en el switch de estado [data-toggle-status]
   → PATCH /admin/productos/1/toggle-status
   → recarga + toast

6. Click en eliminar [data-delete]
   → confirm()
   → DELETE /admin/productos/1
   → recarga + toast
```

---

## 11. Consideraciones y límites

- **Delegación de eventos:** los listeners viven en `document`, porque el contenido se reemplaza dinámicamente.
- **CSRF:** Axios toma el token de `<meta name="csrf-token">`; los formularios incluyen `@csrf`.
- **Fallback sin JS:** los enlaces del sidebar son `<a href="...">` funcionales; sin JS se cargan las vistas `full`.
- **Historial:** `pushState`/`popstate` mantienen atrás/adelante.
- **Título del documento:** el router no actualiza `<title>` al navegar; solo cambia al recargar.
- **Modales:** no anidados; un solo overlay global.
- **Cierre por backdrop:** no está implementado; solo `Escape` o `[data-close-modal]`.
- **Errores de red:** se registran en consola y, en el caso de modales, se muestra un toast genérico.
- **Producción:** ejecutar `npm run build` y asegurarse de que todas las entradas usadas por las vistas estén en el manifest.

---

*Documento de referencia del panel administrativo. Para el estado funcional de cada módulo, ver [`funcionalidades-del-sistema.md`](funcionalidades-del-sistema.md).*
