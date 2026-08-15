# Módulo de Perfil de Cliente Web

## 1. Visión General

Se implementa una página de perfil autogestionable en el lado público de la Botica, permitiendo que los clientes registrados desde la web puedan visualizar y editar sus datos personales, cambiar su contraseña, y consultar su historial de compras y atenciones realizadas en el local.

La página está protegida por el guard `clientes` y solo accesible cuando el cliente ha iniciado sesión.

---

## 2. Entidades y Módulos Involucrados

### 2.1 Entidades Existentes (Modificadas)

| Entidad | Tabla | Cambio |
|---|---|---|
| `Cliente` | `cliente` | Se agrega columna `ultimo_login` (timestamp nullable) para registrar la fecha/hora del último inicio de sesión. |

### 2.2 Módulos del Sistema que Intervienen

| Módulo | Rol en esta feature |
|---|---|
| **Public (Catálogo)** | Provee el layout y la navegación donde se inserta el enlace "Mi Perfil" |
| **Public (Auth)** | `SesionController` se modifica para registrar `ultimo_login` al iniciar sesión |
| **Modelo Cliente** | Relaciones `ventas()` y `atenciones()` ya existen y se usan para el historial |

### 2.3 Entidades Nuevas

Ninguna tabla nueva. Solo se agrega una columna a `cliente` y se crean controlador + vistas nuevos.

---

## 3. Base de Datos — Migración

### 3.1 Archivo

`database/migrations/2026_06_13_000001_add_ultimo_login_to_cliente_table.php`

### 3.2 Columna Agregada a `cliente`

| Columna | Tipo | Nullable | Default | Índice |
|---|---|---|---|---|
| `ultimo_login` | `timestamp` | Sí | `null` | — |

### 3.3 Notas sobre la Migración

- `ultimo_login` se actualiza cada vez que el cliente inicia sesión exitosamente desde `SesionController::login()`.
- Es nullable porque los clientes existentes (creados antes de esta migración) nunca han iniciado sesión.
- No afecta a clientes creados desde el panel admin (sin contraseña, no pueden loguearse).

### 3.4 Rollback

```php
$table->dropColumn('ultimo_login');
```

---

## 4. Rutas

### 4.1 En `routes/web.php`

Se agregan dentro del grupo `middleware('auth:clientes')` (rutas protegidas para clientes autenticados):

```php
Route::middleware('auth:clientes')->group(function () {
    Route::post('/cerrar-sesion', [SesionController::class, 'logout'])->name('public.logout');
    Route::get('/mi-perfil', [\App\Http\Controllers\Public\PerfilController::class, 'show'])->name('public.perfil');
    Route::put('/mi-perfil', [\App\Http\Controllers\Public\PerfilController::class, 'updateInfo'])->name('public.perfil.update');
    Route::put('/mi-perfil/password', [\App\Http\Controllers\Public\PerfilController::class, 'updatePassword'])->name('public.perfil.password');
});
```

### 4.2 Resumen de Endpoints

| Método | Ruta | Nombre | Controlador | Propósito |
|---|---|---|---|---|
| GET | `/mi-perfil` | `public.perfil` | `PerfilController@show` | Mostrar página de perfil con datos, historial y formularios |
| PUT | `/mi-perfil` | `public.perfil.update` | `PerfilController@updateInfo` | Actualizar nombre, apellidos, email y teléfono |
| PUT | `/mi-perfil/password` | `public.perfil.password` | `PerfilController@updatePassword` | Cambiar contraseña (requiere confirmación de actual) |

---

## 5. Modelo

### 5.1 `Cliente` (Modificado)

Se agrega `ultimo_login` a `$fillable` y al cast `'datetime'`:

```php
protected $fillable = [
    'dni',
    'nombre',
    'apellido_paterno',
    'apellido_materno',
    'email',
    'telefono',
    'password',
    'registro_web',
    'ultimo_login',
];

protected function casts(): array
{
    return [
        'password' => 'hashed',
        'registro_web' => 'boolean',
        'ultimo_login' => 'datetime',
    ];
}
```

### 5.2 Relaciones Existentes Utilizadas

```php
// Ya existen en el modelo:
public function atenciones()
{
    return $this->hasMany(Atencion::class, 'cliente_id_cliente', 'id_cliente');
}

public function ventas()
{
    return $this->hasMany(Venta::class, 'cliente_id_cliente', 'id_cliente');
}
```

Ambas se usan en `PerfilController@show` para cargar el historial.

---

## 6. Controlador

### 6.1 `PerfilController` (Nuevo)

**Namespace:** `App\Http\Controllers\Public`

#### `show()`

- Obtiene el cliente autenticado: `Auth::guard('clientes')->user()`
- Carga relaciones `ventas` (últimas 10, ordenadas por fecha descendente) y `atenciones` (últimas 10, ordenadas por fecha descendente)
- Renderiza vista `public.perfil.show` con los datos del cliente y su historial
- No requiere parámetros adicionales

#### `updateInfo(PerfilInfoRequest $request)`

- Recibe datos validados desde `PerfilInfoRequest`
- Actualiza solo los campos: `nombre`, `apellido_paterno`, `apellido_materno`, `email`, `telefono`
- **El DNI nunca se envía ni se modifica** (inmutable)
- Redirige a `route('public.perfil')` con mensaje flash `'success'`: "Datos actualizados correctamente"
- En caso de error de validación, retorna back con errores y old input

#### `updatePassword(PerfilPasswordRequest $request)`

- Recibe datos validados desde `PerfilPasswordRequest`
- Verifica que `Hash::check($request->current_password, $cliente->password)` sea true
- Si la contraseña actual no coincide: redirige back con error en campo `current_password`
- Si coincide: actualiza `$cliente->password = $request->password` (el cast `'hashed'` lo hashea automáticamente)
- Redirige a `route('public.perfil')` con mensaje flash `'success'`: "Contraseña actualizada correctamente"

### 6.2 `SesionController` (Modificado)

En el método `login()`, después de un `attempt()` exitoso, se registra `ultimo_login`:

```php
public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (Auth::guard('clientes')->attempt($credentials)) {
        $request->session()->regenerate();

        // Registrar último login
        $cliente = Auth::guard('clientes')->user();
        $cliente->ultimo_login = now();
        $cliente->save();

        return redirect()->route('home');
    }

    return back()->withErrors([
        'email' => 'Las credenciales no coinciden.',
    ])->onlyInput('email');
}
```

---

## 7. Form Requests

### 7.1 `PerfilInfoRequest` (Nuevo)

**Namespace:** `App\Http\Requests\Public`

**Reglas de validación:**

| Campo | Regla | Mensaje personalizado |
|---|---|---|
| `nombre` | `required`, `string`, `max:50` | — |
| `apellido_paterno` | `required`, `string`, `max:25` | — |
| `apellido_materno` | `required`, `string`, `max:25` | — |
| `email` | `required`, `email`, `max:255`, `unique:cliente,email,{id_cliente},id_cliente` | Este correo ya está registrado |
| `telefono` | `required`, `string`, `size:9`, `regex:/^9[0-9]{8}$/` | Ingrese un celular peruano válido (9 dígitos empezando con 9) |

**Nota:** La regla `unique` ignora el ID del cliente autenticado para permitir que conserve su propio email. Se implementa así:

```php
use Illuminate\Validation\Rule;

public function rules(): array
{
    return [
        'nombre' => 'required|string|max:50',
        'apellido_paterno' => 'required|string|max:25',
        'apellido_materno' => 'required|string|max:25',
        'email' => [
            'required',
            'email',
            'max:255',
            Rule::unique('cliente', 'email')->ignore($this->user()->id_cliente, 'id_cliente'),
        ],
        'telefono' => ['required', 'string', 'size:9', 'regex:/^9[0-9]{8}$/'],
    ];
}
```

### 7.2 `PerfilPasswordRequest` (Nuevo)

**Namespace:** `App\Http\Requests\Public`

**Reglas de validación:**

| Campo | Regla | Mensaje personalizado |
|---|---|---|
| `current_password` | `required`, `string` | Debe ingresar su contraseña actual |
| `password` | `required`, `string`, `min:8`, `confirmed` | La contraseña debe tener al menos 8 caracteres |
| `password_confirmation` | `required` | — |

**Nota:** La verificación de que `current_password` coincide con la contraseña almacenada se realiza en el controlador (`Hash::check`), no en el Form Request.

---

## 8. Vistas

### 8.1 Vista Nueva

#### `resources/views/public/perfil/show.blade.php`

- Extiende `layouts.public`
- Título: "Mi Perfil"
- Estructura de **cards apiladas** (una sola página, sin tabs):

**Card 1 — Bienvenida:**
```
┌─────────────────────────────────────────────┐
│  Avatar circular con inicial del nombre     │
│  ¡Hola, {nombre_completo}!                  │
│  Cliente desde: {created_at->format('d/m/Y')}│
│  Último acceso: {ultimo_login?->format(...)} │
│  o "—" si nunca inició sesión               │
└─────────────────────────────────────────────┘
```

**Card 2 — Mis Datos Personales:**
```
┌─────────────────────────────────────────────┐
│  <form method="POST" action="/mi-perfil">   │
│   @csrf @method('PUT')                      │
│                                              │
│   DNI: 12345678   (texto plano, sin input)  │
│                                              │
│   Nombres:       [input text, required]      │
│   Ap. Paterno:   [input text, required]      │
│   Ap. Materno:   [input text, required]      │
│   Correo:        [input email, required]     │
│   Teléfono:      [input text, required,      │
│                   max 9, placeholder: 9...]  │
│                                              │
│   [💾 Guardar cambios]                       │
│  </form>                                     │
└─────────────────────────────────────────────┘
```

**Card 3 — Cambiar Contraseña:**
```
┌─────────────────────────────────────────────┐
│  <form method="POST" action="/mi-perfil/    │
│         password">                          │
│   @csrf @method('PUT')                      │
│                                              │
│   Contraseña actual:    [input password]     │
│   Nueva contraseña:     [input password]     │
│   Confirmar contraseña: [input password]     │
│                                              │
│   [🔒 Actualizar contraseña]                │
│  </form>                                     │
└─────────────────────────────────────────────┘
```

**Card 4 — Historial:**
```
┌─────────────────────────────────────────────┐
│  <h3>📋 Últimas Compras</h3>                │
│  <table>                                    │
│   <thead> Código | Fecha       | Total   │  │
│   <tbody>                                   │
│    V-XXX  | 10/06/2026 | S/ 45.50        │  │
│    V-XXX  | 08/06/2026 | S/ 32.00        │  │
│    ...                                     │  │
│   (o "No has realizado compras aún.")      │  │
│  </table>                                   │
│                                              │
│  <h3 class="mt-6">📋 Últimas Atenciones</h3> │
│  <table>                                    │
│   <thead> Código | Fecha       | Total   │  │
│   <tbody>                                   │
│    A-XXX  | 05/06/2026 | S/ 70.00        │  │
│    ...                                     │  │
│   (o "No has recibido atenciones aún.")    │  │
│  </table>                                   │
└─────────────────────────────────────────────┘
```

**Estilo:** Tailwind CSS, colores emerald, consistente con el catálogo público.

**Comportamiento:** Cada formulario muestra errores de validación individuales bajo los campos respectivos. Los mensajes flash de éxito se muestran en la parte superior.

### 8.2 Layout Modificado

#### `layouts/public.blade.php`

En el nav (desktop y mobile), dentro del bloque `@else` (cuando el cliente está autenticado), se agrega un enlace "Mi Perfil" antes del saludo:

**Desktop** (`<div class="hidden md:flex items-center gap-1">`):
```html
@else
    <a href="{{ route('public.perfil') }}"
       class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition rounded-lg hover:bg-emerald-50">
        Mi Perfil
    </a>
    <span class="text-sm text-stone-600">Hola, {{ Auth::guard('clientes')->user()->nombre }}</span>
    <form method="POST" action="{{ route('public.logout') }}" class="inline">
        @csrf
        <button type="submit"
            class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition rounded-lg hover:bg-emerald-50">
            Cerrar sesión
        </button>
    </form>
@endguest
```

**Mobile** (`#mobile-menu`):
```html
@else
    <a href="{{ route('public.perfil') }}"
       class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition rounded-lg hover:bg-emerald-50 w-full text-left">
        Mi Perfil
    </a>
    <span class="text-stone-600 px-3 py-2 text-sm w-full text-left">Hola, {{ Auth::guard('clientes')->user()->nombre }}</span>
    <form method="POST" action="{{ route('public.logout') }}">
        @csrf
        <button type="submit"
            class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition rounded-lg hover:bg-emerald-50 w-full text-left">
            Cerrar sesión
        </button>
    </form>
@endguest
```

---

## 9. Diseño de Interfaz

Layout de una sola columna con cards apiladas verticalmente sobre un fondo claro (`bg-stone-50`). Cada card es blanca con bordes redondeados y sombra sutil.

### 9.1 Card de Bienvenida

- **Avatar:** Círculo con fondo emerald y la inicial del nombre en blanco, tamaño `w-16 h-16`
- **Nombre completo:** Texto grande y bold
- **Metadatos:** Fecha de registro y último acceso en texto secundario con iconos pequeños

### 9.2 Card de Datos Personales

- Formulario en grid de 2 columnas (en desktop) para nombre completo, 1 columna en mobile
- Cada campo con su label arriba y el input debajo
- **DNI** se muestra como texto plano con estilo `bg-stone-100` (fondo gris claro) indicando que no es editable
- Botón de guardar con color emerald-600, ancho completo en mobile, auto en desktop

### 9.3 Card de Cambiar Contraseña

- Tres campos en una sola columna
- Cada campo con su label y input
- Botón de actualizar con color emerald-600

### 9.4 Card de Historial

- Dos tablas separadas con títulos "Últimas Compras" y "Últimas Atenciones"
- Tablas responsive: en mobile se convierten en cards (stacked layout) usando Tailwind `overflow-x-auto`
- Estado vacío con texto gris cuando no hay registros
- Las ventas muestran: correlativo, fecha de creación, total (formateado como S/ X.XX)
- Las atenciones muestran: ID, fecha de creación, total (formateado como S/ X.XX)

---

## 10. Casos de Uso

### 10.1 Visualización de Perfil

1. Cliente autenticado hace clic en "Mi Perfil" en la navegación
2. Se muestra la página con sus datos personales cargados
3. Ve su DNI (no editable), nombre completo, email, teléfono
4. Ve su fecha de registro y último inicio de sesión
5. Ve su historial de compras y atenciones

### 10.2 Edición de Datos Personales

1. Cliente modifica uno o más campos en "Mis Datos Personales"
2. Hace clic en "Guardar cambios"
3. Se validan los datos:
   - Éxito: se actualiza el registro, muestra mensaje flash verde "Datos actualizados correctamente"
   - Error: muestra errores bajo los campos correspondientes, conserva los valores ingresados

### 10.3 Cambio de Contraseña

1. Cliente ingresa su contraseña actual, nueva contraseña y confirmación
2. Hace clic en "Actualizar contraseña"
3. Se valida:
   - Contraseña actual incorrecta: muestra error "La contraseña actual no es correcta"
   - Nueva contraseña muy corta (< 8): muestra error de validación
   - Confirmación no coincide: muestra error
   - Éxito: actualiza la contraseña, muestra mensaje flash verde "Contraseña actualizada correctamente"

### 10.4 Consulta de Historial

1. Cliente visualiza automáticamente sus últimas 10 compras y últimas 10 atenciones
2. Si no tiene compras: muestra "No has realizado compras aún."
3. Si no tiene atenciones: muestra "No has recibido atenciones aún."

---

## 11. Casos Borde y Edge Cases

| Caso | Comportamiento |
|---|---|
| **Cliente sin `ultimo_login`** | Muestra "—" en el campo "Último acceso". Ocurre en clientes que nunca iniciaron sesión o fueron creados antes de la migración. |
| **Cliente intenta cambiar DNI** | El campo DNI no está en el formulario de edición (solo texto). No hay forma de enviarlo. Si se intenta por request manual, el controlador solo actualiza los campos permitidos. |
| **Email duplicado (de otro cliente)** | `PerfilInfoRequest` valida unique ignorando el propio ID. Si otro cliente ya usa ese email, muestra error. |
| **Contraseña actual incorrecta** | `Hash::check()` falla. Se redirige back con error en `current_password`. No se revela si el email existe o no. |
| **Cliente sin ventas ni atenciones** | Las tablas muestran filas vacías con mensaje "No has realizado compras aún." y "No has recibido atenciones aún." respectivamente. |
| **Cliente no autenticado accede a `/mi-perfil`** | El middleware `auth:clientes` redirige a la página de login público (`/iniciar-sesion`). |
| **Cliente con sesión admin intenta acceder** | No hay conflicto. El middleware `auth:clientes` verifica el guard `clientes`, no el guard `web`. |
| **Teléfono inválido** | Regex `/^9[0-9]{8}$/` rechaza formatos inválidos (letras, menos de 9 dígitos, que no empiecen con 9). |
| **Campos vacíos en edición** | Validación `required` impide vacíos. El servidor rechaza y muestra errores. |

---

## 12. Consideraciones de Seguridad

| Aspecto | Medida |
|---|---|
| **Hash de contraseña** | Cast `'hashed'` de Laravel (bcrypt) — automático al asignar |
| **Protección CSRF** | `@csrf` en todos los formularios |
| **Sesión separada** | Guard `clientes` independiente del admin |
| **Validación de contraseña actual** | `Hash::check()` en el controlador, nunca se devuelve el hash |
| **DNI inmutable** | No se incluye en el formulario de edición ni en los campos fillable del update |
| **Email unique** | Validación con ignore del propio ID |
| **Acceso restringido** | Middleware `auth:clientes` en todas las rutas del perfil |
| **SQL Injection** | Eloquent ORM + query parameter binding |

---

## 13. Archivos del Feature

### Nuevos (4 archivos)

| # | Ruta | Tipo |
|---|---|---|
| 1 | `database/migrations/2026_06_13_000001_add_ultimo_login_to_cliente_table.php` | Migración |
| 2 | `app/Http/Controllers/Public/PerfilController.php` | Controlador |
| 3 | `app/Http/Requests/Public/PerfilInfoRequest.php` | Form Request |
| 4 | `app/Http/Requests/Public/PerfilPasswordRequest.php` | Form Request |
| 5 | `resources/views/public/perfil/show.blade.php` | Vista |

### Modificados (4 archivos)

| # | Ruta | Cambio |
|---|---|---|
| 1 | `app/Models/Cliente.php` | Agregar `ultimo_login` a `$fillable` y `$casts` |
| 2 | `app/Http/Controllers/Public/SesionController.php` | Registrar `ultimo_login` en login exitoso |
| 3 | `routes/web.php` | Agregar 3 rutas en grupo `auth:clientes` |
| 4 | `resources/views/layouts/public.blade.php` | Enlace "Mi Perfil" en nav (desktop + mobile) |

---

## 14. Checklist de Implementación

### Fase 1 — Base de datos y modelo
- [ ] Crear migración `add_ultimo_login_to_cliente_table`
- [ ] Ejecutar `php artisan migrate`
- [ ] Modificar `Cliente.php`: agregar `ultimo_login` a `$fillable` y `$casts`

### Fase 2 — Controladores
- [ ] Crear `PerfilController` con métodos `show()`, `updateInfo()`, `updatePassword()`
- [ ] Modificar `SesionController@login`: registrar `ultimo_login` tras login exitoso

### Fase 3 — Form Requests
- [ ] Crear `PerfilInfoRequest` (validación nombre, apellidos, email unique, teléfono)
- [ ] Crear `PerfilPasswordRequest` (validación current_password, password, password_confirmation)

### Fase 4 — Vistas
- [ ] Crear `public/perfil/show.blade.php`
- [ ] Modificar `layouts/public.blade.php`: agregar "Mi Perfil" en nav desktop y mobile

### Fase 5 — Rutas
- [ ] Agregar rutas GET/PUT `/mi-perfil` y PUT `/mi-perfil/password` en grupo `auth:clientes`
- [ ] Verificar con `php artisan route:list`

### Fase 6 — Verificación
- [ ] Probar visualización de perfil con cliente autenticado
- [ ] Probar edición de datos personales (éxito y errores)
- [ ] Probar cambio de contraseña (éxito, actual incorrecta, nueva muy corta, confirmación no coincide)
- [ ] Probar acceso sin autenticación (debe redirigir a login)
- [ ] Probar que DNI no se puede modificar
- [ ] Probar que `ultimo_login` se actualiza al iniciar sesión
