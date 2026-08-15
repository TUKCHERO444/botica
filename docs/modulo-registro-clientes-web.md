# Módulo de Registro de Clientes desde la Web

## 1. Visión General

Se integra un sistema de registro y autenticación en el lado público de la Botica, permitiendo que los clientes creen una cuenta con sus datos personales. Estos clientes quedan registrados en la tabla `cliente` existente con un marcador especial (`registro_web = true`) que los identifica como clientes registrados desde la web. En el panel administrativo, al realizar ventas o atenciones, estos clientes muestran un badge "Registrado" para que el usuario admin pueda identificarlos fácilmente.

---

## 2. Entidades y Módulos Involucrados

### 2.1 Entidades Existentes (Modificadas)

| Entidad | Tabla | Cambio |
|---|---|---|
| `Cliente` | `cliente` | Se agregan columnas para autenticación: `email`, `telefono`, `password`, `registro_web`, `remember_token`, `email_verified_at`. Se habilitan timestamps. |
| `User` | `users` | Sin cambios. Los usuarios admin continúan usando esta tabla independientemente. |

### 2.2 Módulos del Sistema que Intervienen

| Módulo | Rol en esta feature |
|---|---|
| **Public (Catálogo)** | Provee el layout y la navegación donde se insertan los enlaces de registro/login |
| **Auth (Admin)** | Se crea un guard independiente `clientes` para no mezclar sesiones con admin |
| **Admin Clientes** | Muestra los nuevos campos (email, teléfono) y el badge "Registrado" |
| **Admin Ventas** | En el buscador de clientes (create/edit) se muestra el badge para clientes web |
| **Admin Atenciones** | Ídem que Ventas |

### 2.3 Entidades Nuevas

Ninguna tabla nueva. Todo se resuelve agregando columnas a la tabla `cliente` existente y creando controladores/vistas nuevos.

---

## 3. Arquitectura de Autenticación

### 3.1 Guard y Provider

Se configura un **segundo guard** de autenticación en Laravel para separar clientes de administradores:

```
Guard 'web'       → Provider 'users'    → Modelo User      (admin)
Guard 'clientes'  → Provider 'clientes' → Modelo Cliente    (clientes web)
```

Ambos usan driver `session`. Esto permite que un admin y un cliente puedan tener sesiones simultáneas sin conflicto (aunque en la práctica no se use).

### 3.2 Middleware

- `guest:clientes` — para páginas de registro/login público (redirige al home si ya está autenticado como cliente)
- `auth:clientes` — para rutas protegidas de clientes (futuro portal del cliente; por ahora solo logout)
- El admin continúa usando `auth` (que resuelve al guard por defecto `web`)

### 3.3 Modelo Cliente como Authenticatable

El modelo `Cliente` implementa `Illuminate\Contracts\Auth\Authenticatable` usando el trait `Illuminate\Auth\Authenticatable`. Esto le otorga los métodos:

| Método | Implementación |
|---|---|
| `getAuthIdentifierName()` | Retorna `id_cliente` |
| `getAuthIdentifier()` | Retorna `$this->id_cliente` |
| `getAuthPassword()` | Retorna `$this->password` |
| `getRememberToken()` | Retorna `$this->remember_token` |
| `setRememberToken($value)` | Setea `$this->remember_token` |
| `getRememberTokenName()` | Retorna `remember_token` |

### 3.4 Hash de Contraseña

Se usa el cast nativo de Laravel 12:
```php
protected function casts(): array
{
    return [
        'password' => 'hashed',
        'registro_web' => 'boolean',
    ];
}
```

`'hashed'` usa `Hash::make()` automáticamente al setear y nunca expone el texto plano.

---

## 4. Base de Datos — Migración

### 4.1 Archivo

`database/migrations/2026_06_12_000001_add_auth_fields_to_cliente_table.php`

### 4.2 Columnas Agregadas a `cliente`

| Columna | Tipo | Nullable | Default | Índice |
|---|---|---|---|---|
| `email` | `string(255)` | Sí (nullable) | — | Unique |
| `telefono` | `string(9)` | Sí | — | — |
| `password` | `string(255)` | Sí | — | — |
| `registro_web` | `boolean` | No | `false` | — |
| `email_verified_at` | `timestamp` | Sí | — | — |
| `remember_token` | `string(100)` | Sí | — | — |
| `created_at` | `timestamp` | Sí | — | — |
| `updated_at` | `timestamp` | Sí | — | — |

### 4.3 Notas sobre la Migración

- `password` es **nullable** porque los clientes creados desde el panel admin no tienen contraseña ni capacidad de login.
- `email` es nullable pero **unique** cuando tiene valor. Los clientes creados desde admin pueden no tener email. Los que se registran desde web siempre tendrán email único.
- `telefono` se almacena como string de 9 caracteres (celular Perú: 9 dígitos comenzando con 9).
- Se habilitan los timestamps (`created_at`, `updated_at`) que antes no existían en esta tabla. El migration los agrega como nullable para no romper registros existentes.

### 4.4 Rollback

El migration `down()` revierte: drop de columnas agregadas y deshabilita timestamps.

---

## 5. Rutas

### 5.1 En `routes/web.php`

Se agregan bajo el grupo `guest:clientes` (excepto logout):

```php
Route::middleware('guest:clientes')->group(function () {
    Route::get('/registro', [RegistroController::class, 'showForm'])->name('public.registro');
    Route::post('/registro', [RegistroController::class, 'register']);
    Route::get('/iniciar-sesion', [SesionController::class, 'showForm'])->name('public.login');
    Route::post('/iniciar-sesion', [SesionController::class, 'login']);
});

Route::middleware('auth:clientes')->group(function () {
    Route::post('/cerrar-sesion', [SesionController::class, 'logout'])->name('public.logout');
});
```

### 5.2 Resumen de Endpoints

| Método | Ruta | Nombre | Controlador | Propósito |
|---|---|---|---|---|
| GET | `/registro` | `public.registro` | `RegistroController@showForm` | Mostrar formulario |
| POST | `/registro` | — | `RegistroController@register` | Procesar registro |
| GET | `/iniciar-sesion` | `public.login` | `SesionController@showForm` | Mostrar formulario login |
| POST | `/iniciar-sesion` | — | `SesionController@login` | Procesar login |
| POST | `/cerrar-sesion` | `public.logout` | `SesionController@logout` | Cerrar sesión |

---

## 6. Controladores

### 6.1 `RegistroController` (Nuevo)

**Namespace:** `App\Http\Controllers\Public`

#### `showForm()`
- Renderiza vista `public.auth.registro`
- No requiere datos adicionales

#### `register(RegistroRequest $request)`
- Recibe datos validados
- Crea registro en `Cliente`:
  - Todos los campos del formulario
  - `password` hasheado (automático vía cast)
  - `registro_web = true`
- Inicia sesión con `Auth::guard('clientes')->login($cliente)`
- Redirige a `route('home')` con mensaje de bienvenida flash
- En caso de error, retorna back con errores

### 6.2 `SesionController` (Nuevo)

**Namespace:** `App\Http\Controllers\Public`

#### `showForm()`
- Renderiza vista `public.auth.login`

#### `login(Request $request)`
- Valida: `email` (required, email), `password` (required)
- Intenta `Auth::guard('clientes')->attempt($credentials)`
- Éxito: regenera sesión, redirige a `route('home')`
- Fracaso: back con error `'Las credenciales no coinciden.'`

#### `logout(Request $request)`
- `Auth::guard('clientes')->logout()`
- `$request->session()->invalidate()`
- `$request->session()->regenerateToken()`
- Redirige a `route('home')`

---

## 7. Form Request

### 7.1 `RegistroRequest` (Nuevo)

**Namespace:** `App\Http\Requests\Public`

**Reglas de validación:**

| Campo | Regla | Mensaje personalizado |
|---|---|---|
| `dni` | `required`, `string`, `size:8`, `unique:cliente,dni` | El DNI ya está registrado |
| `nombre` | `required`, `string`, `max:50` | |
| `apellido_paterno` | `required`, `string`, `max:25` | |
| `apellido_materno` | `required`, `string`, `max:25` | |
| `telefono` | `required`, `string`, `size:9`, `regex:/^9[0-9]{8}$/` | Ingrese un celular peruano válido (9 dígitos) |
| `email` | `required`, `email`, `max:255`, `unique:cliente,email` | El email ya está registrado |
| `password` | `required`, `string`, `min:8`, `confirmed` | La contraseña debe tener al menos 8 caracteres |
| `password_confirmation` | `required` | |

### 7.2 `StoreClienteRequest` y `UpdateClienteRequest` (Modificados)

Se agregan reglas para los nuevos campos (opcionales desde admin):

- `email`: `nullable|email|max:255|unique:cliente,email` (en Update se ignora el propio ID)
- `telefono`: `nullable|string|size:9|regex:/^9[0-9]{8}$/`

No se agrega password — el admin no puede crear/modificar contraseñas de clientes.

---

## 8. Vistas

### 8.1 Vistas Públicas Nuevas

#### `resources/views/public/auth/registro.blade.php`

- Extiende `layouts.public`
- Título: "Crear cuenta"
- Formulario con campos en grid (2 columnas en desktop):
  - DNI (text, max 8)
  - Nombres (text, required)
  - Apellido Paterno (text, required)
  - Apellido Materno (text, required)
  - Teléfono (text, max 9, placeholder: 987654321)
  - Email (email, required)
  - Contraseña (password, required)
  - Confirmar contraseña (password, required)
- Botón: "Crear cuenta"
- Enlace: "¿Ya tienes cuenta? Inicia sesión" → `/iniciar-sesion`
- Estilo: Tailwind, colores emerald, consistente con el catálogo
- Muestra errores de validación individuales bajo cada campo
- Muestra mensaje flash de éxito si aplica

#### `resources/views/public/auth/login.blade.php`

- Extiende `layouts.public`
- Título: "Iniciar sesión"
- Formulario:
  - Email (email, required)
  - Contraseña (password, required)
- Botón: "Iniciar sesión"
- Enlace: "¿No tienes cuenta? Regístrate" → `/registro`
- Muestra error de credenciales inválidas

### 8.2 Vistas Públicas Modificadas

#### `layouts/public.blade.php`

En el nav, entre el enlace "Nuestros productos" y el botón hamburguesa:

**Cuando NO autenticado (guard clientes guest):**
```html
<a href="{{ route('public.login') }}"
   class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition rounded-lg hover:bg-emerald-50">
    Iniciar sesión
</a>
<a href="{{ route('public.registro') }}"
   class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition font-medium">
    Registrarse
</a>
```

**Cuando SÍ autenticado (guard clientes auth):**
```html
<span class="text-sm text-stone-600">Hola, {{ Auth::guard('clientes')->user()->nombre }}</span>
<form method="POST" action="{{ route('public.logout') }}" class="inline">
    @csrf
    <button type="submit" class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition">
        Cerrar sesión
    </button>
</form>
```

Ídem en el menú móvil.

### 8.3 Vistas Admin Modificadas

#### `admin/clientes/index.blade.php`

Se agregan columnas en la tabla:
- `Email` — muestra el email o "—"
- `Teléfono` — muestra el teléfono o "—"
- `Registro` — badge verde "Registrado" si `$c->registro_web`, badge gris "Admin" si no

Estructura del badge:
```html
@if ($c->registro_web)
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">
        <svg class="w-3 h-3" ...>check icon</svg>
        Registrado
    </span>
@else
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-stone-100 text-stone-500">
        Admin
    </span>
@endif
```

Ajustar `colspan` en la fila empty state.

#### `admin/clientes/create.blade.php`

Agregar después del campo Apellido Materno:
- Email (input type email, opcional)
- Teléfono (input text, max 9, opcional, placeholder: 987654321)

#### `admin/clientes/edit.blade.php`

Agregar:
- Email (input type email, opcional, readonly si `registro_web = true` con nota "Registrado desde web, no editable")
- Teléfono (input text, max 9, opcional)
- Si `cliente.registro_web` es true, mostrar badge informativo: "Cliente registrado desde la web"

#### `admin/ventas/create.blade.php` y `admin/atenciones/create.blade.php`

Marcador especial en client-search.js se maneja automáticamente (ver sección 9).

#### `admin/ventas/show.blade.php` y `admin/atenciones/show.blade.php` (si existen)

Si se muestra el nombre del cliente, agregar badge si `$venta->cliente->registro_web`.

---

## 9. JavaScript — Marcador Especial en Cliente Search

### 9.1 `client-search.js` (Modificado)

**Cambio 1 — `buildClientSearch()`:**
Al crear los items del dropdown de resultados (`res.data.forEach(c => ...)`), se verifica `c.registro_web`. Si es `true`, se agrega un badge verde al texto:

```js
const text = `${c.nombre} ${c.apellido_paterno}... — ${c.dni}`;
const itemHtml = c.registro_web
  ? `${text} <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 ml-2">Registrado</span>`
  : text;
```

**Cambio 2 — `showSelected()`:**
Cuando se muestra un cliente ya seleccionado, si tiene `registro_web = true` (guardado en `selectedClient`), se agrega el badge junto al nombre.

**Cambio 3 — Función `generico()` (data-generico):**
El cliente genérico "SIN IDENTIFICAR" también debe incluir `registro_web` en la respuesta (será `false`).

### 9.2 `ClienteController@search` (Modificado)

Incluir `registro_web` en el select:
```php
->get(['id_cliente', 'nombre', 'apellido_paterno', 'apellido_materno', 'dni', 'registro_web']);
```

### 9.3 `ClienteController@generico` (Modificado)

Incluir `registro_web = false` en la respuesta JSON.

---

## 10. Layout Público — Navegación

### 10.1 Enlaces en Desktop

Dentro del `<div class="hidden md:flex items-center gap-1">`, después del enlace "Nuestros productos":

**Invitado:**
```
[Iniciar sesión] [Registrarse]
```

**Autenticado:**
```
[Hola, {nombre}] [Cerrar sesión]
```

### 10.2 Enlaces en Móvil

Dentro del menú móvil (`#mobile-menu`), después de "Nuestros productos":
Ídem condicional.

---

## 11. Admin — Sidebar (Sin cambios)

No se agrega un nuevo módulo en el sidebar. Los clientes registrados desde web son parte del módulo Clientes existente. El badge en la tabla y en el buscador es suficiente para la identificación.

---

## 12. Permisos (Sin cambios)

Los clientes web **NO** usan Spatie Permission. No tienen roles ni permisos. El guard `clientes` es completamente independiente del sistema de roles de admin.

---

## 13. Consideraciones de Seguridad

| Aspecto | Medida |
|---|---|
| **Hash de contraseña** | Laravel `'hashed'` cast (bcrypt) |
| **Protección CSRF** | @csrf en todos los formularios |
| **Sesión separada** | Guard `clientes` independiente, sin acceso a admin |
| **Validación teléfono** | Regex estricto: solo 9 dígitos comenzando con 9 |
| **Unique email** | No pueden existir dos clientes con el mismo email |
| **Password nullable** | Solo los registrados desde web tienen password; admin no expone ni edita passwords |
| **Rate limiting** | Considerar aplicar throttle en `/registro` y `/iniciar-sesion` en el futuro |
| **SQL Injection** | Eloquent ORM + query parameter binding |

---

## 14. Casos de Uso Cubiertos

### 14.1 Registro Exitoso
1. Cliente ingresa a `/registro`
2. Llena todos los campos requeridos
3. Envía formulario
4. Se valida y crea registro en `cliente`
5. Se inicia sesión automáticamente
6. Redirige al homepage con mensaje "Cuenta creada correctamente"

### 14.2 Registro con Errores de Validación
1. Cliente llena formulario incorrectamente (DNI inválido, email duplicado, passwords no coinciden, etc.)
2. Se muestran errores individuales bajo cada campo
3. Formulario mantiene datos ingresados (old input)

### 14.3 Inicio de Sesión Exitoso
1. Cliente ingresa a `/iniciar-sesion`
2. Ingresa email y contraseña correctos
3. Se inicia sesión en guard `clientes`
4. Redirige al homepage

### 14.4 Inicio de Sesión Fallido
1. Cliente ingresa credenciales incorrectas
2. Muestra error "Las credenciales no coinciden"
3. Mantiene el email en el input

### 14.5 Cierre de Sesión
1. Cliente autenticado hace click en "Cerrar sesión"
2. Se invalida sesión del guard `clientes`
3. Redirige al homepage, muestra navegación de invitado

### 14.6 Cliente Web en Admin — Módulo Clientes
1. Admin ingresa al módulo Clientes
2. Ve columna "Registro" con badge verde "Registrado" para clientes web
3. Puede ver email y teléfono
4. No puede editar email si es registro_web (readonly)
5. No puede ver/editar contraseña

### 14.7 Cliente Web en Ventas/Atenciones
1. Admin crea venta o atención
2. En el buscador de clientes, escribe nombre
3. En los resultados, los clientes web muestran badge "Registrado" verde
4. Al seleccionar cliente web, el badge persiste junto al nombre seleccionado
5. Esto permite al admin identificar clientes que tienen cuenta web

---

## 15. Casos Borde y Edge Cases

| Caso | Comportamiento |
|---|---|
| **Cliente sin email creado desde admin** | `email = null`, no puede iniciar sesión. Sin badge "Registrado". |
| **Email duplicado** | Validación unique impide duplicados, tanto en registro público como admin. |
| **DNI duplicado** | Validación unique impide duplicados. |
| **Admin edita email de cliente web** | El email se muestra como readonly si `registro_web = true`. Debe gestionarse en el cliente directamente o como funcionalidad futura. |
| **Cliente web eliminado desde admin** | Se elimina el registro de `cliente` normalmente. No hay cuentas huerfanas porque no hay tabla separada de auth. |
| **Registro con DNI de cliente existente (creado desde admin)** | Unique validation: rechazado. El cliente debe usar otro DNI o el admin debe vincular. |
| **Cliente web intenta acceder a `/admin`** | El middleware `auth` (guard `web`) redirige al login admin. No hay acceso. |
| **Admin logueado intenta acceder a `/registro`** | No hay conflicto porque admin usa guard `web` y `/registro` usa middleware `guest:clientes`. |
| **Cliente logueado intenta acceder a `/registro`** | Redirige al home porque usa middleware `guest:clientes`. |
| **Teléfono inválido (letras, menos de 9 dígitos, etc.)** | Regex `/^9[0-9]{8}$/` rechaza. |

---

## 16. Flujo Completo de Datos

### 16.1 Registro
```
[Formulario] → POST /registro → RegistroController@register
  → RegistroRequest (validación)
  → Cliente::create([...datos..., 'registro_web' => true])
  → Auth::guard('clientes')->login($cliente)
  → redirect()->route('home')
```

### 16.2 Login
```
[Formulario] → POST /iniciar-sesion → SesionController@login
  → Validación (email, password)
  → Auth::guard('clientes')->attempt($credentials)
  → Éxito: redirect()->route('home')
  → Error: back()->withErrors(['email' => '...'])
```

### 16.3 Visualización en Admin (Ventas)
```
ClienteController@search → GET /admin/clientes/search?q=...
  → Cliente::where(...)->get([..., 'registro_web'])
  → JSON con registro_web
  → client-search.js renderiza badge verde si registro_web = true
```

---

## 17. Archivos del Feature

### Nuevos (7 archivos)

| # | Ruta | Tipo |
|---|---|---|
| 1 | `database/migrations/2026_06_12_000001_add_auth_fields_to_cliente_table.php` | Migración |
| 2 | `app/Http/Controllers/Public/RegistroController.php` | Controlador |
| 3 | `app/Http/Controllers/Public/SesionController.php` | Controlador |
| 4 | `app/Http/Requests/Public/RegistroRequest.php` | Form Request |
| 5 | `resources/views/public/auth/registro.blade.php` | Vista |
| 6 | `resources/views/public/auth/login.blade.php` | Vista |
| 7 | `resources/js/public/auth.js` | JavaScript (opcional) |

### Modificados (12 archivos)

| # | Ruta | Cambio |
|---|---|---|
| 1 | `app/Models/Cliente.php` | Implementar Authenticatable, nuevos fillable/hidden/casts |
| 2 | `config/auth.php` | Agregar guard 'clientes' y provider 'clientes' |
| 3 | `routes/web.php` | Agregar rutas de registro, login, logout público |
| 4 | `app/Http/Controllers/Admin/ClienteController.php` | Incluir `registro_web` en search y generico |
| 5 | `app/Http/Requests/Admin/StoreClienteRequest.php` | Agregar reglas email/telefono |
| 6 | `app/Http/Requests/Admin/UpdateClienteRequest.php` | Agregar reglas email/telefono |
| 7 | `resources/views/layouts/public.blade.php` | Enlaces de login/registro en nav |
| 8 | `resources/views/admin/clientes/index.blade.php` | Columnas email, teléfono, badge registro |
| 9 | `resources/views/admin/clientes/create.blade.php` | Campos email, teléfono |
| 10 | `resources/views/admin/clientes/edit.blade.php` | Campos email, teléfono, badge informativo |
| 11 | `resources/js/admin/client-search.js` | Badge "Registrado" en dropdown y selección |
| 12 | `resources/views/auth/login.blade.php` | Enlace a registro público "¿Eres cliente? Regístrate aquí" |

---

## 18. Checklist de Implementación

### Fase 1 — Base de datos y modelo
- [ ] Crear migración con nuevas columnas
- [ ] Ejecutar `php artisan migrate`
- [ ] Modificar modelo `Cliente` (Authenticatable, casts, fillable, hidden)

### Fase 2 — Configuración de autenticación
- [ ] Agregar guard `clientes` en `config/auth.php`
- [ ] Agregar provider `clientes` en `config/auth.php`

### Fase 3 — Controladores y rutas públicas
- [ ] Crear `RegistroController`
- [ ] Crear `SesionController`
- [ ] Crear `RegistroRequest`
- [ ] Agregar rutas en `web.php`

### Fase 4 — Vistas públicas
- [ ] Crear `public/auth/registro.blade.php`
- [ ] Crear `public/auth/login.blade.php`
- [ ] Modificar `layouts/public.blade.php` (enlaces nav)

### Fase 5 — Admin: Módulo Clientes
- [ ] Modificar `StoreClienteRequest` y `UpdateClienteRequest`
- [ ] Modificar `admin/clientes/index.blade.php`
- [ ] Modificar `admin/clientes/create.blade.php`
- [ ] Modificar `admin/clientes/edit.blade.php`

### Fase 6 — Admin: Marcador en Ventas/Atenciones
- [ ] Modificar `ClienteController@search` y `@generico`
- [ ] Modificar `client-search.js`

### Fase 7 — Admin: Enlace en login
- [ ] Modificar `auth/login.blade.php`
