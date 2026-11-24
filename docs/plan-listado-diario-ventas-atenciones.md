# Fix rápido — Listado diario por usuario en Ventas y Atenciones

> **Fecha:** 23/11/2026
> **Estado:** implementado (23/11/2026). Suite completa en verde (391 tests, 2606 assertions) y `npm run build` correcto.
> **Alcance:** rutas, controladores, vistas y JS de `ventas` y `atenciones`.
> **Restricciones explícitas:** **sin cambios de base de datos** (sin migraciones, sin columnas, sin índices, sin seeders de permisos) y sin alterar inventario, caja, kardex ni los servicios de anulación.
> **Referencias:** [`modulo-ventas.md`](modulo-ventas.md), [`modulo-atenciones.md`](modulo-atenciones.md), [`panel-administrativo.md`](panel-administrativo.md), [`mapeo-paginacion-modulos.md`](mapeo-paginacion-modulos.md).

---

## 1. Resumen ejecutivo

Hoy `/admin/ventas` y `/admin/atenciones` listan **todos los registros históricos** de todos los usuarios, ordenados por id descendente. El cliente pide:

1. Que la **primera vista** del módulo muestre **solo las ventas/atenciones que el usuario logueado registró hoy**.
2. Que el listado total **no desaparezca**: se mueve a una **segunda vista** del mismo módulo ("Historial").
3. Que el paso de "hoy" a "historial" sea **automático** al terminar el día en `America/Lima`, sin job, sin cron y sin borrar ni mover filas: es un simple cambio de filtro de fecha.
4. Que el historial permita **filtrar por fecha exacta, rango de fechas y usuario**.

La solución es una **vista del día filtrada por `user_id` + `created_at`** y una **vista histórica con filtros**, reutilizando columnas que ya existen (`venta.user_id`, `venta.created_at`, `atencion.user_id`, `atencion.created_at`). **No se toca la base de datos.**

Efecto del "cierre del día": a las `00:00` de Lima, `today()` cambia y las ventas de ayer dejan de cumplir el filtro; al refrescar (o navegar), la vista diaria queda vacía y esos registros ya están en el historial. Es un comportamiento **derivado de la consulta**, no un proceso programado.

---

## 2. Situación actual

| Elemento | Ubicación | Comportamiento actual |
|---|---|---|
| Listado de ventas | `app/Http/Controllers/Admin/VentaController.php:43-58` | `Venta::with('cliente','user')->orderBy('id_venta','desc')->paginate(10)` — **sin filtros** |
| Parcial de ventas | `resources/views/admin/ventas/index.blade.php:21-84` | Tabla completa, paginada con `admin.partials.pagination` |
| Página completa de ventas | `resources/views/admin/ventas/full.blade.php` | `@extends('layouts.admin')` + `@include('admin.ventas.index')` |
| Listado de atenciones | `app/Http/Controllers/Admin/AtencionController.php:44-61` | `Atencion::with('cliente','user','productos','servicios')->orderBy('id_atencion','desc')->paginate(10)` — **sin filtros** |
| Parcial de atenciones | `resources/views/admin/atenciones/index.blade.php:21-96` | Tabla completa, con conteo de productos y servicios |
| Página completa de atenciones | `resources/views/admin/atenciones/full.blade.php` | `@extends('layouts.admin')` + `@include('admin.atenciones.index')` |
| Rutas | `routes/admin.php:163-203` | `Route::resource` con middleware `permission:ventas` / `permission:atenciones` |
| Navegación SPA | `resources/views/layouts/admin.blade.php:155-171` | Enlaces `data-panel="/admin/ventas"` y `/admin/atenciones` |
| Router | `resources/js/admin/router.js` | Carga parciales AJAX, `pushState`, re-ejecuta scripts inyectados |

### Problemas concretos

1. **Ruido operativo:** un cajero ve las ventas de todos y de todos los días; tiene que buscar las suyas.
2. **Riesgo de error:** desde el listado se puede **anular una venta ajena**; el dueño solo se distingue por una columna secundaria ("Registrado por") y no existe ninguna restricción ni advertencia.
3. **El listado mezcla días:** al paginar de 10 en 10 no hay un corte visible entre "hoy" y "ayer", y con más de 10 ventas diarias el día se reparte en varias páginas.
4. **No hay columna de fecha/hora** en ninguna de las dos tablas, así que ni siquiera se distingue lo de hoy de lo de la semana pasada.
5. **No hay filtros**: para ver "mis ventas del 20/11" hoy no existe forma desde el panel (solo reportes PDF/CSV).

### Lo que **no** se debe romper

- La inmutabilidad de ventas/atenciones (`edit/update/destroy` devuelven 422).
- El flujo de anulación con motivo y la recarga del panel tras anular (`resources/js/admin/modal.js:124-135`).
- El patrón de dos vistas por módulo: parcial sin `@extends` + `full.blade.php`.
- El criterio de 10 registros por página (`docs/mapeo-paginacion-modulos.md`).
- Los permisos existentes: **no se crea un permiso nuevo** (implicaría tocar seeders/BD).

---

## 3. Reglas de negocio (definiciones cerradas)

| # | Regla | Detalle |
|---|---|---|
| **R1** | "Hoy" es el día calendario en `America/Lima` | `00:00:00.000` a `23:59:59.999` de `America/Lima`. `config/app.php:68` ya define `'timezone' => env('APP_TIMEZONE', 'America/Lima')` y `.env.example` fija `APP_TIMEZONE=America/Lima`. |
| **R2** | "Usuario" = quien **registró** la venta/atención | `venta.user_id` / `atencion.user_id`. **No** es `anulada_user_id` (quien anuló) ni el usuario de caja. |
| **R3** | El criterio de fecha es `created_at` | No `id_venta`, no la fecha de apertura de caja, no `anulada_at`. |
| **R4** | La vista diaria muestra **solo** lo del usuario logueado | Incluye sus ventas anuladas del día (trazabilidad e impresión del ticket). |
| **R5** | El historial contiene **todo**, incluidas las del día | Las vistas no son mutuamente excluyentes por dato, solo por filtro por defecto. Ver §3.2. |
| **R6** | Las anuladas **sí aparecen**, pero **no suman** | El contador "Total del día" suma solo `estado = confirmada`. La tabla muestra el badge de estado. |
| **R7** | Caja no define el día | Si una caja queda abierta pasada la medianoche, una venta de las `00:10` pertenece al **día nuevo** (por `created_at`). Se documenta como decisión explícita; ver §9. |
| **R8** | Nada se mueve físicamente | No hay tabla de archivo, ni columna nueva, ni proceso que "traslade" registros. El historial es la misma tabla con otros filtros. |
| **R9** | El historial es de solo lectura + acciones existentes | Ticket, ver detalle y anular (con las mismas validaciones de siempre). |

### 3.1 Por qué el "cierre del día" es automático

```text
23/11/2026 23:59  → index: created_at ∈ [2026-11-23 00:00, 2026-11-24 00:00) ∧ user_id = 7
24/11/2026 00:00  → index: created_at ∈ [2026-11-24 00:00, 2026-11-25 00:00) ∧ user_id = 7  → vacío
                    historial: sin filtros (o fecha=2026-11-23) → aparecen las del 23
```

No hace falta job, comando, `schedule:run` ni columna de "cerrado". Es el mismo mecanismo que ya usa el dashboard: `DashboardController.php:28-42` (`whereDate('created_at', $hoy)`).

### 3.2 Solapamiento intencional

Un administrador que quiere ver **el total del día de todos** no necesita una tercera vista: entra al historial con `fecha = hoy` y sin filtro de usuario. Esto evita duplicar vistas y mantiene "el listado total" como fuente única de consulta histórica.

---

## 4. Propuesta funcional

### 4.1 Vista principal — "Mis ventas de hoy"

```text
┌───────────────────────────────────────────────────────────────────────────────┐
│ Ventas                                    [Hoy: 23/11/2026]   [+ Nueva venta] │
│ Solo se muestran tus ventas de hoy.  [Ver historial completo →]               │
├───────────────────────────────────────────────────────────────────────────────┤
│ Hoy: 8 ventas confirmadas · S/ 342.50  (2 anuladas no incluidas en el total)   │
├────┬───────────────┬──────────────┬──────────┬────────────┬─────────┬─────────┤
│ #  │ Correlativo   │ Cliente      │ Total    │ Hora       │ Estado  │ Acciones│
├────┼───────────────┼──────────────┼──────────┼────────────┼─────────┼─────────┤
│ 41 │ T001-00000041 │ Ana Pérez    │ S/ 24.50 │ 09:12      │Confirmada│ 🖨 👁 ⊘ │
│ 40 │ T001-00000040 │ —            │ S/ 12.00 │ 08:55      │Anulada   │ 🖨 👁   │
└────┴───────────────┴──────────────┴──────────┴────────────┴─────────┴─────────┘
```

- Título y subtítulo explican el filtro (evita el "¿dónde están mis ventas de ayer?").
- Botón **"Ver historial completo"** → `route('admin.ventas.historial')` con `data-panel` (navegación SPA).
- Contadores del día calculados con la **misma** consulta filtrada.
- Se mantiene el aviso "Caja cerrada" y el CTA de nueva venta tal como hoy.
- La columna "Registrado por" se oculta aquí (siempre es el usuario actual); se conserva en el historial.

### 4.2 Vista secundaria — "Historial de ventas"

```text
┌───────────────────────────────────────────────────────────────────────────────┐
│ Historial de ventas                     [← Volver a mis ventas de hoy]        │
├───────────────────────────────────────────────────────────────────────────────┤
│ Fecha [____]  Desde [____]  Hasta [____]  Usuario [Todos ▾]                   │
│ Estado [Todas ▾]  Buscar [correlativo o cliente____]   [Filtrar] [Limpiar]    │
├────┬───────────────┬──────────────┬──────────┬────────────────┬─────────┬─────┤
│ #  │ Correlativo   │ Cliente      │ Total    │ Fecha y hora   │Registró │ ... │
└────┴───────────────┴──────────────┴──────────┴────────────────┴─────────┴─────┘
```

- **Sin filtros = listado total** (comportamiento actual preservado, orden `id desc`).
- Presets rápidos (enlaces, sin JS): `Hoy`, `Ayer`, `Últimos 7 días`, `Este mes`. Se implementan con `data-panel` y query string (`?fecha=...` o `?desde=...&hasta=...`) generado en Blade con `today()`; son opcionales (mejora de UX de bajo costo).
- Contadores del resultado filtrado (cantidad + suma de confirmadas), útiles para conciliar con caja/reportes.
- Misma tabla y mismas acciones que hoy (ticket, detalle, anular).

### 4.3 Especificación de filtros (historial)

| Parámetro | Tipo | Default | Validación / normalización | Efecto |
|---|---|---|---|---|
| `fecha` | `date` (`Y-m-d`) | vacío | Formato inválido ⇒ se ignora (se trata como `null`); nunca lanza 422 | Día exacto: `created_at ∈ [fecha 00:00, fecha+1 00:00)` |
| `desde` | `date` (`Y-m-d`) | vacío | Formato inválido ⇒ `null`; si `hasta < desde`, se **intercambian** | Inicio del rango, inclusive desde `00:00` |
| `hasta` | `date` (`Y-m-d`) | vacío | Formato inválido ⇒ `null`; si `hasta < desde`, se **intercambian** | Fin del rango, inclusive hasta `23:59:59.999` |
| `user_id` | entero | vacío (todos) | Entero inválido ⇒ `null`; un id inexistente devuelve lista vacía (no se consulta `users`) | `user_id = ?` |
| `estado` | `confirmada` \| `anulada` | vacío (todos) | Valor fuera de catálogo ⇒ se ignora | `estado = ?` |
| `q` (opcional) | texto (2-40) | vacío | `trim`; menos de 2 caracteres ⇒ ignorar | `correlativo like %q%` **o** nombre/apellido del cliente |
| `page` | entero | 1 | Laravel | Paginación de 10 |

**Precedencia:** si llega `fecha`, **manda** y `desde`/`hasta` se ignoran (y la UI deshabilita el rango). Si no hay `fecha`, se aplica el rango. Nunca se combinan ambos para evitar resultados vacíos por error del usuario.

**Decisión sobre validación:** no usar un `FormRequest` con `422` para estos filtros. El panel es una SPA y `resources/js/admin/router.js:72-74` solo hace `console.error` ante error, dejando el panel con el contenido anterior: un `422` por una fecha mal escrita se vería como "no pasó nada". Se normaliza en el controlador con un guard explícito de formato (`Carbon::hasFormat($valor, 'Y-m-d')`) y, si hubo descarte, se muestra un aviso suave (`AdminToast` o texto en la vista).

> **Ojo:** `$request->date('fecha')` **no** devuelve `null` ante basura (`fecha=abc`): internamente usa `Date::parse()` y lanza excepción. Hay que validar el formato antes, como se muestra en §5.2.

**Orden:** `orderByDesc('id_venta')` / `orderByDesc('id_atencion')` (equivalente a orden cronológico por ser autoincremental, y conserva el criterio actual). No se cambia a `created_at` para no alterar el orden esperado en registros migrados/legados.

---

## 5. Diseño técnico

### 5.1 Rutas

`routes/admin.php`, **antes** del `Route::resource` correspondiente (crítico: si se declara después, `ventas/{venta}` captura `historial` y falla el *route model binding* con 404).

```php
// routes/admin.php — junto a las rutas custom de ventas (línea ~163)
Route::get('ventas/historial', [VentaController::class, 'historial'])
    ->middleware('permission:ventas')
    ->name('admin.ventas.historial');

// ... el resto de rutas custom de ventas ...

// routes/admin.php — junto a las de atenciones (línea ~186)
Route::get('atenciones/historial', [AtencionController::class, 'historial'])
    ->middleware('permission:atenciones')
    ->name('admin.atenciones.historial');
```

- Solo `GET`: no hay store/update/delete nuevos.
- Se reutilizan los permisos existentes; **no** se agrega permiso ni se toca `PermissionSeeder`.
- URLs resultantes: `/admin/ventas`, `/admin/ventas/historial`, `/admin/atenciones`, `/admin/atenciones/historial`.

### 5.2 Controladores

Se mantiene el patrón actual: `getIndex()`/`olvidaIndex()` (no-op documentado en `docs/cache-panel-administrativo.md`) y respuesta parcial vs. `full`. No se introduce un método `queryListado()` nuevo: cada acción arma su query con los scopes del modelo.

**`VentaController`**

```php
public function index()
{
    $query = Venta::with('cliente', 'user')
        ->where('user_id', auth()->id())
        ->delDia();                       // scope nuevo, ver §5.3

    $ventas = $query->clone()->orderByDesc('id_venta')->paginate(10)->withQueryString();
    $resumen = [
        'cantidad' => $query->clone()->where('estado', Venta::ESTADO_CONFIRMADA)->count(),
        'total'    => $query->clone()->where('estado', Venta::ESTADO_CONFIRMADA)->sum('total'),
    ];
    $cajaAbierta = Caja::abierta()->exists();
    $hoy = today();

    if (request()->ajax()) {
        return $this->getIndex('ventas', fn () => view(
            'admin.ventas.index',
            compact('ventas', 'cajaAbierta', 'resumen', 'hoy')
        )->render());
    }

    return view('admin.ventas.full', compact('ventas', 'cajaAbierta', 'resumen', 'hoy'));
}

public function historial(Request $request)
{
    [$fecha, $desde, $hasta] = $this->rangoFechas($request);

    $base = Venta::query()
        ->deUsuario($request->integer('user_id') ?: null)
        ->entreFechas($fecha, $desde, $hasta)
        ->deEstado($request->get('estado'))
        ->buscar($request->get('q'));

    $ventas = (clone $base)
        ->with('cliente', 'user')
        ->orderByDesc('id_venta')
        ->paginate(10)
        ->withQueryString();

    // Totales del resultado filtrado (no de la página): una sola query agregada.
    $resumen = (clone $base)
        ->where('estado', Venta::ESTADO_CONFIRMADA)
        ->selectRaw('count(*) as cantidad, coalesce(sum(total), 0) as total')
        ->first();

    $usuarios = User::orderBy('name')->get(['id', 'name']);
    $filtros  = compact('fecha', 'desde', 'hasta');

    if ($request->ajax()) {
        return $this->getIndex('ventas', fn () => view(
            'admin.ventas.historial',
            compact('ventas', 'usuarios', 'filtros', 'resumen')
        )->render());
    }

    return view('admin.ventas.historial-full', compact('ventas', 'usuarios', 'filtros', 'resumen'));
}
```

> **Totales:** siempre con `clone $base` + agregado; **nunca** sobre `$ventas->getCollection()`, que solo contiene la página actual. El mismo `$base` sirve para la tabla y para el resumen, de modo que ambos reflejan exactamente el mismo filtro.
>
> **No** se debe cachear: `CacheaPanel` es no-op a propósito.

`AtencionController` replica exactamente la misma estructura (`historial`, `resumen`, `usuarios`, `filtros`, `view('admin.atenciones.*')`).

**Helper compartido (recomendado e implementado):** un trait `App\Http\Controllers\Admin\Traits\FiltraHistorial` con `fechaFiltro()` y `rangoFechas()` para no duplicar la lógica de fechas entre ambos controladores. Se usa junto a `Audita`/`CacheaPanel`.

```php
// app/Http/Controllers/Admin/Traits/FiltraHistorial.php
private function fechaFiltro(Request $request, string $campo): ?Carbon
{
    $valor = $request->input($campo);

    if (! is_string($valor) || ! Carbon::hasFormat($valor, 'Y-m-d')) {
        return null;
    }

    return Carbon::createFromFormat('Y-m-d', $valor)->startOfDay();
}

private function rangoFechas(Request $request): array
{
    $fecha = $this->fechaFiltro($request, 'fecha');
    $desde = $this->fechaFiltro($request, 'desde');
    $hasta = $this->fechaFiltro($request, 'hasta');

    if ($fecha) {
        return [$fecha, null, null];
    }

    if ($desde && $hasta && $hasta->lt($desde)) {
        [$desde, $hasta] = [$hasta, $desde];
    }

    return [null, $desde, $hasta];
}
```

> Imports del trait: `Carbon\Carbon` e `Illuminate\Http\Request`. El trait se usa en ambos controladores junto a `Audita` y `CacheaPanel`.

### 5.3 Scopes en los modelos (sin tocar BD)

Se agregan scopes de consulta en `app/Models/Venta.php` y `app/Models/Atencion.php`. Son métodos Eloquent: **no** crean columnas ni índices.

```php
// Venta.php / Atencion.php
public function scopeDelDia($query)
{
    return $query
        ->where('created_at', '>=', today()->startOfDay())
        ->where('created_at', '<', today()->addDay()->startOfDay());
}

public function scopeDeUsuario($query, ?int $userId)
{
    return $query->when($userId, fn ($q) => $q->where('user_id', $userId));
}

public function scopeEntreFechas($query, $fecha = null, $desde = null, $hasta = null)
{
    if ($fecha) {
        return $query->where('created_at', '>=', $fecha->copy()->startOfDay())
                     ->where('created_at', '<', $fecha->copy()->addDay()->startOfDay());
    }

    return $query
        ->when($desde, fn ($q) => $q->where('created_at', '>=', $desde->copy()->startOfDay()))
        ->when($hasta, fn ($q) => $q->where('created_at', '<', $hasta->copy()->addDay()->startOfDay()));
}

public function scopeDeEstado($query, ?string $estado)
{
    return $query->when(
        in_array($estado, [self::ESTADO_CONFIRMADA, self::ESTADO_ANULADA], true),
        fn ($q) => $q->where('estado', $estado)
    );
}

// Opcional (filtro de texto del historial)
public function scopeBuscar($query, ?string $texto)
{
    $texto = trim((string) $texto);

    if (mb_strlen($texto) < 2) {
        return $query;
    }

    return $query->where(function ($q) use ($texto) {
        $q->where('correlativo', 'like', "%{$texto}%")
          ->orWhereHas('cliente', function ($c) use ($texto) {
              $c->where('nombre', 'like', "%{$texto}%")
                ->orWhere('apellido_paterno', 'like', "%{$texto}%")
                ->orWhere('apellido_materno', 'like', "%{$texto}%");
          });
    });
}
```

**Por qué rango y no `whereDate`:** `whereDate('created_at', ...)` aplica una función sobre la columna (no es *sargable* y no aprovecha el índice `venta_estado_fecha_idx (estado, created_at)`). El rango `[inicio, día siguiente)` es equivalente, más rápido y correcto con timestamps almacenados en zona local. El dashboard seguirá usando `whereDate`; no es necesario cambiarlo en este fix.

**Equivalencia SQL de referencia:**

```sql
-- Vista diaria (por usuario)
SELECT * FROM venta
WHERE user_id = :auth_id
  AND created_at >= '2026-11-23 00:00:00'
  AND created_at <  '2026-11-24 00:00:00'
ORDER BY id_venta DESC;

-- Historial (fecha exacta + usuario + estado)
SELECT * FROM venta
WHERE created_at >= :fecha_inicio AND created_at < :fecha_fin
  AND user_id = :user_id
  AND estado = :estado
ORDER BY id_venta DESC;
```

### 5.4 Vistas

Convención del proyecto: parcial **sin** `@extends` + página `full` que extiende el layout.

| Archivo nuevo | Rol |
|---|---|
| `resources/views/admin/ventas/historial.blade.php` | Parcial del historial (sin `@extends`), con filtros + tabla |
| `resources/views/admin/ventas/historial-full.blade.php` | `@extends('layouts.admin')` + `@include('admin.ventas.historial')` |
| `resources/views/admin/atenciones/historial.blade.php` | Ídem atenciones |
| `resources/views/admin/atenciones/historial-full.blade.php` | Ídem atenciones |
| `resources/views/admin/ventas/_tabla.blade.php` (recomendado) | Tabla compartida entre `index` y `historial` |
| `resources/views/admin/atenciones/_tabla.blade.php` (recomendado) | Ídem atenciones |

**Tabla compartida con bandera de modo:**

```blade
{{-- resources/views/admin/ventas/index.blade.php (vista del día) --}}
@include('admin.ventas._tabla', ['ventas' => $ventas, 'modo' => 'dia'])

{{-- resources/views/admin/ventas/historial.blade.php (historial) --}}
@include('admin.ventas._tabla', ['ventas' => $ventas, 'modo' => 'historial'])
```

Dentro de `_tabla.blade.php`:
- Columna **Fecha y hora** (`$v->created_at->format('d/m/Y H:i')`): se agrega en ambos modos (hoy no existe).
- Columna **Registrado por**: solo si `$modo === 'historial'`.
- Mismas acciones (ticket, detalle, anular) y mismo `@include('admin.partials.pagination', ['items' => $ventas])`.
- `@empty`: mensaje distinto según modo ("Aún no registraste ventas hoy" + CTA de nueva venta + enlace al historial, vs. "No hay resultados para los filtros aplicados" + "Limpiar filtros").

**`index.blade.php`** pasa a ser la vista del día: encabezado con la fecha, subtítulo explicativo, resumen, botón "Ver historial completo", y el `@include` de la tabla en modo `dia`. **No** se agregan filtros aquí.

**`historial.blade.php`**: encabezado con "Volver a mis ventas de hoy" (`data-panel="{{ route('admin.ventas.index') }}"`), formulario de filtros, tabla en modo `historial` y paginación.

Esquema del formulario (mismos estilos que `admin/kardex/index.blade.php` y `admin/auditoria/index.blade.php`):

```blade
<form id="ventas-historial-filtros" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3" autocomplete="off">
    <input type="date" name="fecha"  value="{{ $filtros['fecha']?->format('Y-m-d') }}">
    <input type="date" name="desde"  value="{{ $filtros['desde']?->format('Y-m-d') }}">
    <input type="date" name="hasta"  value="{{ $filtros['hasta']?->format('Y-m-d') }}">
    <select name="user_id">…</select>
    <select name="estado">
        <option value="">Todos</option>
        <option value="confirmada" @selected(request('estado') === 'confirmada')>Confirmadas</option>
        <option value="anulada"    @selected(request('estado') === 'anulada')>Anuladas</option>
    </select>
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Correlativo o cliente">
    <div class="lg:col-span-6 flex gap-2">
        <button type="submit">Filtrar</button>
        <a href="{{ route('admin.ventas.historial') }}" data-panel="{{ route('admin.ventas.historial') }}">Limpiar</a>
    </div>
</form>

<div id="ventas-historial-tabla">
    @include('admin.ventas._tabla', ['ventas' => $ventas, 'modo' => 'historial'])
</div>

@vite('resources/js/admin/ventas/historial.js')
```

### 5.5 JavaScript de filtros

Requisitos:
1. Debe funcionar tanto en carga completa (`historial-full`) como inyectado por el router SPA.
2. Debe actualizar la URL (`pushState`) para que atrás/adelante y el refresco conserven los filtros.
3. Debe evitar el patrón muerto `@push('scripts')` dentro de un parcial (no se renderiza en respuestas AJAX) y el evento `panel:navigate` (hoy **nadie lo escucha**: `resources/views/admin/kardex/index.blade.php:165`).

Estrategia recomendada (misma que `auditoria`): `axios.get(url)` → reemplazar solo el contenedor de la tabla → `history.pushState`. Así no se re-inicializan scripts ni se pierde el foco del input.

```js
// resources/js/admin/ventas/historial.js
import axios from 'axios';

function initHistorialVentas() {
    const form = document.getElementById('ventas-historial-filtros');
    const contenedor = document.getElementById('ventas-historial-tabla');
    if (!form || !contenedor) return;

    let timer;

    const aplicar = async () => {
        // `fecha` manda: deshabilita (y vacía) el rango antes de serializar.
        const fecha = form.querySelector('input[name="fecha"]');
        form.querySelectorAll('input[name="desde"], input[name="hasta"]').forEach((i) => {
            i.disabled = !!fecha?.value;
            if (fecha?.value) i.value = '';
        });

        const params = new URLSearchParams(new FormData(form));
        [...params.entries()].forEach(([k, v]) => { if (!v) params.delete(k); });
        const url = `${window.location.pathname}${params.toString() ? '?' + params : ''}`;

        try {
            const res = await axios.get(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const doc = new DOMParser().parseFromString(res.data, 'text/html');
            const nueva = doc.getElementById('ventas-historial-tabla');
            if (nueva) contenedor.innerHTML = nueva.innerHTML;
            window.history.pushState({ url }, '', url);
        } catch (e) {
            window.AdminToast?.show('No se pudo aplicar el filtro.', 'error');
        }
    };

    form.addEventListener('submit', (e) => { e.preventDefault(); aplicar(); });
    form.querySelectorAll('select, input[type="date"]').forEach((el) => {
        el.addEventListener('change', aplicar);
    });
    form.querySelector('input[name="q"]')?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(aplicar, 500);
    });
}

// Patrón vigente del proyecto para scripts inyectados por el router:
// resources/js/admin/autocomplete.js:183-185
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHistorialVentas);
} else {
    initHistorialVentas();
}
```

> **Gotcha obligatorio (AGENTS.md):** registrar los entrypoints en `vite.config.js` → `input`. Si no, `@vite()` rompe el build. Entradas implementadas:
> ```js
> 'resources/js/admin/ventas/historial.js',
> 'resources/js/admin/atenciones/historial.js',
> ```
>
> La lógica vive en `resources/js/admin/historial/filtros.js` (helper compartido); los dos entrypoints solo lo inicializan con su prefijo. **No usar** `DOMContentLoaded` sin el chequeo de `readyState`: el router inyecta el parcial después de que el evento ya ocurrió (`resources/js/admin/router.js:48-62`).

**Detalle de UX (implementado):** el router resalta el ítem del menú comparando el `pathname` con `data-panel` por prefijo y solo dentro de `#sidebar`, de modo que "Ventas" y "Atenciones" siguen marcados en sus vistas de historial y los enlaces de filtros/paginación no reciben estilos de menú (`resources/js/admin/router.js:35-47`).

### 5.6 Paginación

- `paginate(10)->withQueryString()` en ambas vistas (conserva `?fecha=&desde=&hasta=&user_id=&estado=&q=`).
- `admin/partials/pagination.blade.php` emite `data-panel` en los enlaces; el router los intercepta (`router.js:21-26`) y mantiene la SPA.
- No se toca el parcial global ni el criterio de 10 registros.

### 5.7 Zona horaria y consistencia

- `config/app.php:68` → `America/Lima` (o `APP_TIMEZONE`). `today()` y `created_at` se escriben con esa zona.
- `config/database.php:62,83` → `'timezone' => env('DB_TIMEZONE', '-05:00')`, que coincide con Lima (sin DST). Esto evita el desfase clásico de columnas `timestamp` en MySQL.
- **Verificación obligatoria antes de cerrar:** con `Carbon::setTestNow('2026-11-23 23:55:00')` crear una venta y comprobar que:
  - aparece en la vista diaria del 23;
  - con `setTestNow('2026-11-24 00:05:00')` ya **no** aparece en la diaria;
  - en el historial con `fecha=2026-11-23` sí aparece.
- Si el servidor de producción usa otra `DB_TIMEZONE`, este fix **no** debe ajustarla (fuera de alcance), pero hay que reportarlo: afectaría también al dashboard y a caja.

### 5.8 Permisos y auditoría

- `permission:ventas` / `permission:atenciones` en las rutas nuevas; ningún cambio en `PermissionSeeder` ni en roles.
- No se registran eventos nuevos en auditoría: consultar no se audita hoy y agregarlo sería otro alcance.
- Anular desde el historial usa el mismo endpoint y el mismo modal; al responder `success`, `modal.js:133` recarga la URL actual, por lo que la fila se actualiza **en el historial** sin saltar a la vista diaria. Correcto y sin cambios.

### 5.9 Rendimiento

- No se crean índices (restricción del cliente). `venta_estado_fecha_idx` `(estado, created_at)` y `atencion_estado_fecha_idx` existen, pero **no cubren `user_id`**.
- Con el volumen esperado de una botica (decenas de ventas/día) el filtro por `user_id` sobre un rango de fechas es irrelevante. El historial sin filtros ya es el peor caso actual y no empeora.
- Si en el futuro se vuelve lento, la mitigación correcta sería una migración con índice `(user_id, created_at)`; queda **explícitamente fuera de este fix** por la restricción de no tocar BD. Anotarlo como deuda técnica en `docs/arquitectura-y-stack.md` al implementar.
- Evitar N+1: `Venta` ya usa `with('cliente','user')`. En `Atencion`, `with('productos','servicios')` carga colecciones completas solo para contar; se puede sustituir por `withCount(['productos','servicios'])` sin cambiar la vista si se ajusta `->count()`. Mejora opcional incluida en el mismo paso, sin BD.

---

## 6. Plan de implementación

| # | Paso | Archivos |
|---|---|---|
| 1 | Agregar scopes `delDia`, `deUsuario`, `entreFechas`, `deEstado` (y `buscar` opcional) | `app/Models/Venta.php`, `app/Models/Atencion.php` |
| 2 | Agregar trait de normalización de fechas | `app/Http/Controllers/Admin/Traits/FiltraHistorial.php` (nuevo) |
| 3 | Refactorizar `VentaController::index` a vista diaria + agregar `historial` | `app/Http/Controllers/Admin/VentaController.php` |
| 4 | Ídem en atenciones | `app/Http/Controllers/Admin/AtencionController.php` |
| 5 | Extraer tabla compartida y adaptar la vista diaria (fecha/hora, resumen, enlace al historial, empty state) | `resources/views/admin/ventas/_tabla.blade.php`, `.../index.blade.php` |
| 6 | Crear historial + full | `.../historial.blade.php`, `.../historial-full.blade.php` |
| 7 | Repetir 5-6 en atenciones | `resources/views/admin/atenciones/*` |
| 8 | JS de filtros + registro en Vite | `resources/js/admin/historial/filtros.js`, `resources/js/admin/ventas/historial.js`, `resources/js/admin/atenciones/historial.js`, `vite.config.js` |
| 9 | Rutas (antes del `Route::resource`) | `routes/admin.php` |
| 10 | Tests | `tests/Feature/ListadoDiarioVentaTest.php`, `tests/Feature/ListadoDiarioAtencionTest.php` |
| 11 | Actualizar docs | `docs/modulo-ventas.md`, `docs/modulo-atenciones.md`, `docs/README.md`, `docs/mapeo-paginacion-modulos.md` |

**Estimación:** ~1 jornada de desarrollo + ~2 horas de pruebas manuales (incluye el corte de medianoche simulado). Sin migraciones ni despliegue de BD: el deploy es solo código + `npm run build`.

**Orden sugerido de PRs:** (1) rutas + controladores + scopes con tests; (2) vistas + JS. Permite revisar backend y UI por separado.

---

## 7. Pruebas

### 7.1 Tests automatizados nuevos

`tests/Feature/ListadoDiarioVentaTest.php` (usa `RefreshDatabase`, permisos creados en `setUp` como el resto de la suite, y `Carbon::setTestNow`):

1. `test_index_muestra_solo_ventas_del_usuario_autenticado_de_hoy`
   - usuario A: 1 venta hoy; usuario B: 1 venta hoy ⇒ el parcial contiene el correlativo de A y **no** el de B.
2. `test_index_no_muestra_ventas_de_ayer`
   - venta de A ayer (`setTestNow` o `created_at` forzado) ⇒ no aparece; el historial la muestra.
3. `test_index_incluye_ventas_anuladas_del_dia`
   - anulada de hoy visible con badge "Anulada"; el resumen `cantidad/total` no la cuenta.
4. `test_historial_sin_filtros_muestra_todos_los_usuarios_y_fechas`
5. `test_historial_filtra_por_fecha_exacta`
6. `test_historial_filtra_por_rango_de_fechas` (incluye límites: `desde` y `hasta` inclusive).
7. `test_historial_filtra_por_usuario`
8. `test_historial_ignora_fechas_invalidas_y_rango_invertido` (no devuelve 422 y responde 200).
9. `test_historial_combina_usuario_estado_y_fecha`
10. `test_historial_funciona_en_carga_completa_y_ajax` (200 + vista `historial-full` / parcial).

`tests/Feature/ListadoDiarioAtencionTest.php`: los mismos casos adaptados (productos/servicios, `A001-*`).

**Tests existentes a revisar (no deberían romperse):**
- `tests/Feature/AnulacionVentaTest.php:267-276` crea la venta con el mismo usuario y hoy ⇒ sigue pasando.
- `tests/Feature/AuthRedirectTest.php` usa `admin.ventas.index` como destino de redirección ⇒ sin cambios.
- Tests de atenciones que consulten el index: revisar que creen datos con el usuario autenticado y fecha actual.

### 7.2 Pruebas manuales (checklist)

- [ ] Login como vendedor A: `/admin/ventas` muestra solo A-hoy y el resumen coincide con los ingresos de ese usuario en caja para el día.
- [ ] Login como vendedor B: no ve nada de A.
- [ ] Crear venta y volver: aparece en la diaria; ticket y anulación funcionan.
- [ ] Anular desde la diaria: badge cambia, el resumen descuenta el total.
- [ ] Entrar al historial: aparecen todos; filtro por fecha/usuario/rango funciona y la URL queda con query string.
- [ ] Paginar en el historial: los filtros se conservan.
- [ ] Atrás/adelante del navegador: no rompe la SPA.
- [ ] Refrescar (F5) `/admin/ventas/historial?fecha=...`: carga completa correcta.
- [ ] Anular desde el historial: la tabla se recarga **en el historial**.
- [ ] Corte de día simulado: `Carbon::setTestNow` a las 23:59 y 00:01 (o cambiar la fecha del sistema en un entorno de prueba).
- [ ] Sin ventas hoy: empty state y CTA de nueva venta.
- [ ] Usuario sin permiso `ventas`: no accede a ninguna de las dos rutas (403).
- [ ] `npm run build` pasa; no hay errores de script inyectado en consola.

---

## 8. Criterios de aceptación

1. `/admin/ventas` y `/admin/atenciones` muestran **únicamente** registros de `auth()->id()` con `created_at` dentro del día actual en `America/Lima`.
2. El listado total histórico sigue disponible en `/admin/ventas/historial` y `/admin/atenciones/historial`, accesible desde la vista diaria.
3. El historial filtra por **fecha exacta**, **rango de fechas** y **usuario** (además de estado/q), combinables, con paginación que conserva filtros.
4. Al cambiar el día, la vista diaria se vacía sola (sin job ni intervención) y los registros de ayer están en el historial.
5. Ningún cambio en esquema, migraciones, seeders, inventario, kardex, caja o servicios de anulación.
6. Los permisos y flujos existentes (ticket, detalle, anular) siguen funcionando desde ambas vistas.
7. Tests nuevos verdes y suite completa en verde (`composer run test`).

---

## 9. Decisiones, supuestos y alternativas descartadas

| Tema | Decisión | Motivo |
|---|---|---|
| Cómo se "mueven" los registros | No se mueven: el filtro por fecha los excluye | Cero riesgo, cero BD, sin procesos programados |
| Criterio de día | `created_at` en `America/Lima` | Es el dato real de la operación; coincide con dashboard |
| Caja abierta pasada la medianoche | La venta pertenece al día de `created_at` | Es lo que el cliente describe ("una vez que ese día acabe") |
| Quién ve qué en la diaria | Cada usuario ve solo lo suyo, incluido el Administrador | Requerimiento literal; el Administrador usa el historial con `fecha=hoy` sin filtro de usuario |
| Alcance de anulación | Igual que hoy (cualquiera con permiso puede anular) | Cambiar reglas de autorización sería otro alcance y otro permiso (BD) |
| Filtro por usuario en el historial | Lista completa de usuarios | Es una vista de supervisión; alternativa: limitar a `can('usuarios')`, que se puede activar después sin BD |
| `whereDate` vs. rango | Rango semiabierto | Sargable y consistente con timestamps locales |
| Job/cron de cierre | **Descartado** | Innecesario: el paso al historial es un cambio de fecha, no un estado |
| Columna `fecha_cierre` / tabla archivo | **Descartado** | Prohibido tocar BD y no aporta nada |
| Borrar/limpiar ventas antiguas | **Descartado** | Viola la inmutabilidad y la trazabilidad |
| Nueva vista "todas las ventas" separada de "historial" | **Descartado** | Duplicaría la misma tabla; el historial sin filtros ya es el total |
| Exportar CSV del historial | **Fuera de alcance** | Ya existe en reportes; no duplicar |

### Riesgos identificados

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Desfase de zona horaria en MySQL de producción (`DB_TIMEZONE`) | Ventas de 23:xx podrían caer en el día equivocado | Verificación con `23:55`/`00:05` en staging; ya existe `DB_TIMEZONE=-05:00` por defecto |
| `user_id` sin índice | Lentitud si el volumen crece mucho | Aceptado; documentar deuda técnica (índice futuro, fuera de alcance) |
| Usuario no ve sus ventas de ayer y cree que se perdieron | Confusión operativa | Subtítulo explícito + enlace visible al historial + empty state con CTA |
| Página abierta durante el corte de medianoche | La lista no se refresca sola | Aceptado; al navegar/refrescar se actualiza. Mejora futura opcional: detectar cambio de fecha con JS |
| Filtro inválido en SPA | El router no muestra errores (solo `console.error`) | Normalizar a `null` y responder 200 |
| Ítem del menú no resaltado en `/historial` | Cosmético | Corrección opcional en `router.js` (prefijo) |

---

## 10. Documentos a actualizar al implementar

1. `docs/modulo-ventas.md` — nueva sección "Listado del día e historial" (criterio `user_id + created_at`, rutas, reglas R1-R8).
2. `docs/modulo-atenciones.md` — ídem.
3. `docs/mapeo-paginacion-modulos.md` — registrar las dos nuevas vistas con paginación de 10 y `withQueryString`.
4. `docs/README.md` — añadir este documento al índice de planes (estado "Implementado" cuando corresponda).
5. `docs/arquitectura-y-stack.md` — nota de deuda técnica: índice `(user_id, created_at)` si el volumen lo exige.

---

## 11. Fuera de alcance

- Cambios de esquema, migraciones, seeders o permisos nuevos.
- Totales de caja por usuario o por turno.
- Restricción de anulación por dueño de la venta.
- Exportación CSV/PDF del historial (existe en reportes).
- Limpieza o archivado de registros históricos.
- Cambios en `/admin/reportes` o en el dashboard.
