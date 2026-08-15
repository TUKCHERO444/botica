# Plan de Implementación — Módulo de Auditoría

## 1. Objetivo

Implementar un sistema de auditoría que registre con exactitud todas las acciones de escritura (crear, actualizar, eliminar, toggle de estado) que los usuarios realizan sobre los registros de todos los módulos del panel administrativo, y proveer una interfaz visual para consultar dicho historial.

---

## 2. Stack y contexto

| Aspecto | Detalle |
|---------|---------|
| Framework | Laravel 12 |
| Frontend | Blade SPA (AJAX) con Tailwind v4 |
| Auth/RBAC | Spatie Laravel Permission |
| DB | MySQL |
| Patrón existente | Controladores con recursos + trait `CacheaPanel` |
| Sidebar | Definido en `layouts/admin.blade.php` |

---

## 3. Arquitectura del módulo

### 3.1. Componentes

```
database/migrations/
  └── 2026_06_04_000003_create_auditoria_table.php

app/Models/
  └── Auditoria.php

app/Http/Controllers/Admin/Traits/
  └── Audita.php

app/Http/Controllers/Admin/
  └── AuditoriaController.php

resources/views/admin/auditoria/
  ├── index.blade.php      (partial - listado con filtros)
  ├── show.blade.php       (partial - detalle con diff)
  └── full.blade.php       (vista completa con layout)

resources/js/admin/auditoria/
  └── index.js

routes/admin.php           (+ grupo de rutas auditoria)
resources/views/layouts/admin.blade.php   (+ link en sidebar)
database/seeders/PermissionSeeder.php     (+ permiso auditoria)
database/seeders/RoleSeeder.php           (+ permiso a Admin)
```

### 3.2. Diagrama de flujo

```
USUARIO (sidebar)                     SISTEMA
─────────────────────────             ─────────────────────────
1. Click "Auditoría"                  GET /admin/auditoria (AJAX)
                                      → renderiza index.blade.php
                                      → tabla paginada + filtros

2. Aplica filtros                     GET /admin/auditoria?modulo=productos&user_id=1&accion=crear
(módulo, usuario, acción, fechas)     → respuesta JSON o HTML filtrado

3. Click en un registro               GET /admin/auditoria/5 (AJAX)
                                      → renderiza show.blade.php
                                      → tabla comparativa old vs new

4. (En segundo plano)                 
   Usuario crea/edita/elimina         Controller → $this->auditarCrear(...)
   en cualquier módulo                → INSERT en auditoria
```

---

## 4. Diseño de base de datos

### Tabla `auditoria`

```sql
CREATE TABLE auditoria (
    id_auditoria    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    modulo          VARCHAR(50) NOT NULL,
    accion          VARCHAR(30) NOT NULL,       -- crear, actualizar, eliminar, toggle-estado
    registro_id     BIGINT UNSIGNED NULL,
    descripcion     TEXT NOT NULL,
    valores_anteriores JSON NULL,
    valores_nuevos  JSON NULL,
    ip_address      VARCHAR(45) NULL,
    created_at      TIMESTAMP NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX auditoria_modulo_index (modulo),
    INDEX auditoria_accion_index (accion),
    INDEX auditoria_modulo_accion_index (modulo, accion),
    INDEX auditoria_created_at_index (created_at)
);
```

### Columnas detalladas

| Columna | Propósito |
|---------|-----------|
| `user_id` | FK al usuario que ejecutó la acción |
| `modulo` | Nombre del módulo: `productos`, `ventas`, `usuarios`, etc. |
| `accion` | Tipo de acción: `crear`, `actualizar`, `eliminar`, `toggle-estado` |
| `registro_id` | ID del registro afectado (nullable para acciones sin registro puntual) |
| `descripcion` | Texto legible en español: *"Creó el producto 'Paracetamol 500mg'"* |
| `valores_anteriores` | JSON con los valores del registro antes del cambio (para `actualizar`/`eliminar`) |
| `valores_nuevos` | JSON con los valores después del cambio (para `crear`/`actualizar`) |
| `ip_address` | Dirección IP desde donde se ejecutó la acción |

---

## 5. Modelo Eloquent

```php
class Auditoria extends Model
{
    protected $table = 'auditoria';
    protected $primaryKey = 'id_auditoria';
    public $timestamps = false; // Solo usamos created_at manual

    protected $fillable = [
        'user_id', 'modulo', 'accion', 'registro_id',
        'descripcion', 'valores_anteriores', 'valores_nuevos', 'ip_address',
    ];

    protected $casts = [
        'valores_anteriores' => 'array',
        'valores_nuevos' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

---

## 6. Trait `Audita`

Se crea en `app/Http/Controllers/Admin/Traits/Audita.php` siguiendo el mismo patrón que el trait existente `CacheaPanel`.

### Métodos

```php
trait Audita
{
    /**
     * Registrar acción de creación
     */
    protected function auditarCrear(Model $modelo, string $modulo, string $descripcion): void
    {
        Auditoria::create([
            'user_id'           => auth()->id(),
            'modulo'            => $modulo,
            'accion'            => 'crear',
            'registro_id'       => $modelo->getKey(),
            'descripcion'       => $descripcion,
            'valores_nuevos'    => $modelo->getAttributes(),
            'ip_address'        => request()->ip(),
        ]);
    }

    /**
     * Registrar acción de actualización
     */
    protected function auditarActualizar(Model $modelo, string $modulo, string $descripcion): void
    {
        Auditoria::create([
            'user_id'           => auth()->id(),
            'modulo'            => $modulo,
            'accion'            => 'actualizar',
            'registro_id'       => $modelo->getKey(),
            'descripcion'       => $descripcion,
            'valores_anteriores' => $modelo->getOriginal(),
            'valores_nuevos'    => $modelo->getChanges(),
            'ip_address'        => request()->ip(),
        ]);
    }

    /**
     * Registrar acción de eliminación/desactivación
     */
    protected function auditarEliminar(Model $modelo, string $modulo, string $descripcion): void
    {
        Auditoria::create([
            'user_id'           => auth()->id(),
            'modulo'            => $modulo,
            'accion'            => 'eliminar',
            'registro_id'       => $modelo->getKey(),
            'descripcion'       => $descripcion,
            'valores_anteriores' => $modelo->getOriginal(),
            'ip_address'        => request()->ip(),
        ]);
    }

    /**
     * Registrar acción genérica (para toggle-status y otros casos especiales)
     */
    protected function auditar(
        string $modulo,
        string $accion,
        ?int $registroId,
        string $descripcion,
        ?array $valoresAnteriores = null,
        ?array $valoresNuevos = null
    ): void {
        Auditoria::create([
            'user_id'           => auth()->id(),
            'modulo'            => $modulo,
            'accion'            => $accion,
            'registro_id'       => $registroId,
            'descripcion'       => $descripcion,
            'valores_anteriores' => $valoresAnteriores,
            'valores_nuevos'    => $valoresNuevos,
            'ip_address'        => request()->ip(),
        ]);
    }
}
```

### Uso en controladores

```php
use App\Http\Controllers\Admin\Traits\Audita;

class ProductoController extends Controller
{
    use CacheaPanel, Audita;

    public function store(StoreProductoRequest $request)
    {
        $producto = Producto::create($request->validated());
        $this->auditarCrear($producto, 'productos', "Creó el producto '{$producto->nombre}'");
        // ...
    }

    public function update(UpdateProductoRequest $request, Producto $producto)
    {
        $producto->update($request->validated());
        $this->auditarActualizar($producto, 'productos', "Actualizó el producto '{$producto->nombre}'");
        // ...
    }

    public function destroy(Producto $producto)
    {
        $nombre = $producto->nombre;
        $producto->update(['estado' => 0]);
        $this->auditarEliminar($producto, 'productos', "Desactivó el producto '{$nombre}'");
        // ...
    }

    public function toggleStatus(Producto $producto)
    {
        $nuevoEstado = $producto->estado ? 0 : 1;
        $verbo = $nuevoEstado ? 'Activó' : 'Desactivó';
        $producto->update(['estado' => $nuevoEstado]);
        $this->auditar('productos', 'toggle-estado', $producto->id_producto, "{$verbo} el producto '{$producto->nombre}'");
        // ...
    }
}
```

---

## 7. Controlador del panel (`AuditoriaController`)

### Métodos

#### `index()`
- Obtiene todos los registros de auditoría paginados (50 por página)
- Aplica filtros por query params: `modulo`, `user_id`, `accion`, `desde`, `hasta`, `q` (búsqueda en descripción)
- Si es AJAX → devuelve `index.blade.php`
- Si es request normal → devuelve `full.blade.php`
- Carga relaciones: `user` para mostrar nombre del usuario

#### `show(Auditoria $auditorium)`
- Muestra detalle completo de una entrada de auditoría
- Decodifica y renderiza `valores_anteriores` vs `valores_nuevos` en tabla comparativa
- Si es AJAX → devuelve `show.blade.php`

---

## 8. Vistas

### `index.blade.php` — Listado con filtros

```
┌──────────────────────────────────────────────────────────────┐
│  Auditoría                                          [Filtros] │
├──────────────────────────────────────────────────────────────┤
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────────────┐│
│  │ Módulo ▼ │ │ Usuario ▼│ │ Acción ▼│ │  Fecha desde - ► ││
│  └──────────┘ └──────────┘ └──────────┘ └──────────────────┘│
│                                                              │
│  ┌─────┬──────────┬────────┬──────────────────┬──────────┬──┐│
│  │ #   │ Usuario  │ Módulo │ Descripción      │ Fecha     │  ││
│  ├─────┼──────────┼────────┼──────────────────┼──────────┼──┤│
│  │ 1   │ admin    │ ventas │ Creó la venta... │ 04/06... │ ►││
│  │ 2   │ admin    │ prod.. │ Actualizó el...  │ 04/06... │ ►││
│  └─────┴──────────┴────────┴──────────────────┴──────────┴──┘│
│                                                              │
│  Páginas: ◀ 1 2 3 ... ▶                                     │
└──────────────────────────────────────────────────────────────┘
```

**Campos del filtro:**
- **Módulo**: `<select>` con todos los módulos + "Todos"
- **Usuario**: `<select>` con usuarios del sistema + "Todos"
- **Acción**: `<select>` con crear/actualizar/eliminar/toggle-estado + "Todas"
- **Fecha**: dos inputs `type="date"` (desde/hasta)
- **Búsqueda**: input text para buscar en `descripcion`

### `show.blade.php` — Detalle con diff

```
┌──────────────────────────────────────────────────────────────┐
│  Detalle de Auditoría                              [Cerrar]  │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  Usuario:    Administrador (admin@sistema.com)               │
│  Acción:     Actualizar producto                             │
│  Módulo:     productos                                       │
│  Registro:   #5 - Paracetamol 500mg                          │
│  Fecha:      04/06/2026 15:30:22                             │
│  IP:         192.168.1.100                                   │
│                                                              │
│  ──── Cambios realizados ────                                │
│                                                              │
│  ┌──────────────────┬──────────────────┬──────────────────┐  │
│  │ Campo            │ Valor anterior   │ Valor nuevo      │  │
│  ├──────────────────┼──────────────────┼──────────────────┤  │
│  │ precio_venta     │ 12.50            │ 15.00            │  │
│  │ stock            │ 10               │ 8                │  │
│  └──────────────────┴──────────────────┴──────────────────┘  │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## 9. Permisos

### Nuevo permiso: `auditoria`

En `PermissionSeeder.php`:
```php
'modulos' = [..., 'auditoria'];
```

En `RoleSeeder.php`:
```php
$admin->syncPermissions([..., 'auditoria']);
```

Solo el rol **Administrador** tendrá acceso a auditoría.

---

## 10. Sidebar

El link se agrega en el bloque **Administración**, después de Roles:

```blade
@can('auditoria')
<a href="#" data-panel="/admin/auditoria" class="flex items-center gap-3 px-3 py-2 rounded-lg text-emerald-100 hover:bg-emerald-700 hover:text-white transition text-sm font-medium">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 004 0v-4h6v4a2 2 0 004 0V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
    </svg>
    Auditoría
</a>
@endcan
```

---

## 11. Rutas

```php
Route::middleware('permission:auditoria')
    ->prefix('auditoria')
    ->name('admin.auditoria.')
    ->group(function () {
        Route::get('/', [AuditoriaController::class, 'index'])->name('index');
        Route::get('/{auditorium}', [AuditoriaController::class, 'show'])->name('show');
    });
```

---

## 12. Integración en controladores (16 total)

Para cada controlador se aplica el mismo patrón:

1. Agregar `use Audita;` en la clase
2. En `store()`: llamar `$this->auditarCrear(...)` después de crear
3. En `update()`: llamar `$this->auditarActualizar(...)` después de actualizar
4. En `destroy()`: llamar `$this->auditarEliminar(...)` después de eliminar/desactivar
5. En `toggleStatus()`: llamar `$this->auditar(...)` con acción `toggle-estado`

### Tabla de integración

| Controller | store | update | destroy | toggleStatus | Observaciones |
|-----------|-------|--------|---------|-------------|---------------|
| ProductoController | ✅ | ✅ | ✅ | ✅ | destroy = desactivar |
| CategoriaController | ✅ | ✅ | ✅ | ✅ | destroy = desactivar |
| MarcaController | ✅ | ✅ | ✅ | ✅ | destroy = desactivar |
| ProveedorController | ✅ | ✅ | ✅ | ✅ | destroy = desactivar |
| ClienteController | ✅ | ✅ | ✅ | — | No tiene toggleStatus |
| VentaController | ✅ | ✅ | ✅ | — | destroy = eliminar real |
| AtencionController | ✅ | ✅ | ✅ | — | destroy = eliminar real |
| IngresoController | ✅ | ✅ | ✅ | — | destroy = reversión |
| ServicioController | ✅ | ✅ | ✅ | — | No tiene toggleStatus |
| UserController | ✅ | ✅ | ✅ | ✅ | destroy = desactivar |
| RoleController | ✅ | ✅ | ✅ | — | No tiene toggleStatus |
| CajaController | ✅ (apertura) | — | — | — | Solo apertura y egreso crean registros |
| BannerController | ✅ | ✅ | ✅ | — | Reordenar no es CRUD |
| SliderController | ✅ (asignar) | — | ✅ (quitar) | — | Acciones atómicas |
| PortadaCategoriaController | — | — | — | ✅ (toggle) | Solo toggle en portada |

---

## 13. JS del módulo

`resources/js/admin/auditoria/index.js`

Funcionalidades:
- Envío de filtros vía AJAX al cambiar selects/inputs
- Debounce en búsqueda por texto
- Actualización de la tabla sin recargar toda la página
- Soporte para `popstate` (navegación con histórico)

---

## 14. Orden de implementación

| Fase | Tarea | Dependencias |
|------|-------|-------------|
| 1 | Crear migración `auditoria` | — |
| 2 | Crear modelo `Auditoria` | migración ejecutada |
| 3 | Crear trait `Audita` | modelo Auditoria |
| 4 | Agregar permiso a seeders | — |
| 5 | Agregar rutas a `routes/admin.php` | — |
| 6 | Crear `AuditoriaController` | modelo, trait |
| 7 | Crear vistas Blade | controlador |
| 8 | Crear JS del módulo | vistas |
| 9 | Agregar link en sidebar | — |
| 10 | Integrar trait en controladores (16) | trait Audita |
| 11 | Ejecutar migraciones + seeders | todo lo anterior |
| 12 | Compilar assets y verificar | — |

---

## 15. Verificación

1. `php artisan migrate` → crear tabla `auditoria`
2. `php artisan db:seed --class=PermissionSeeder` → nuevo permiso
3. `php artisan db:seed --class=RoleSeeder` → permiso asignado a Admin
4. Navegar al panel → sidebar debe mostrar "Auditoría"
5. Crear/editar/eliminar registros en cualquier módulo → debe aparecer en auditoría
6. Filtrar por módulo, usuario, acción, fecha → resultados correctos
7. Ver detalle → diff old vs new correcto
