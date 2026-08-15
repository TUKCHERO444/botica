# Módulo WhatsApp — Botón de Consulta en Detalle de Producto

## 1. Propósito

Agregar un botón "Consultar disponibilidad por WhatsApp" en la página pública de detalle de producto (`/productos/{producto}`) que permita al cliente contactar directamente a la botica con un mensaje predefinido sobre el producto que está visualizando.

---

## 2. Arquitectura

### 2.1. Flujo General

```
show.blade.php
  |
  |--> <x-whatsapp-button :producto="$producto" />
         |
         |--> ¿config('services.whatsapp.phone') existe?
                |-- NO  → no renderiza nada
                |-- SÍ  → renderiza <a href="https://wa.me/{numero}?text={mensaje}" target="_blank">
                            Ícono WhatsApp + "Consultar disponibilidad"
```

### 2.2. Formato del mensaje

El mensaje se construye con texto plano, URL-encoded:

```
Hola, quisiera consultar la disponibilidad de {NOMBRE_PRODUCTO}
```

Ejemplo real: `Hola, quisiera consultar la disponibilidad de Ibuprofeno 400mg`

### 2.3. Enlace generado

```
https://wa.me/519XXXXXXXX?text=Hola%2C%20quisiera%20consultar%20la%20disponibilidad%20de%20Ibuprofeno%20400mg
```

---

## 3. Configuración

### 3.1. Variable de entorno

Se agrega una nueva variable en `.env`:

```ini
WHATSAPP_PHONE_NUMBER=519XXXXXXXX
```

Formato: código de país + número, **sin** `+` ni espacios ni guiones.

### 3.2. Config service

En `config/services.php`:

```php
'whatsapp' => [
    'phone' => env('WHATSAPP_PHONE_NUMBER'),
],
```

Esto centraliza la configuración siguiendo la convención existente del proyecto (ver `services.php`).

### 3.3. Dato por defecto en `.env.example`

Se agrega también en `.env.example` para documentar la variable en nuevos entornos:

```ini
WHATSAPP_PHONE_NUMBER=
```

---

## 4. Componente Blade: `x-whatsapp-button`

Se crea un componente Blade reutilizable en `resources/views/components/whatsapp-button.blade.php`.

### 4.1. Props

| Prop | Tipo | Requerido | Descripción |
|------|------|-----------|-------------|
| `producto` | `App\Models\Producto` | Sí | Instancia del producto para extraer nombre |

### 4.2. Template

```blade
@props(['producto'])

@php
    $phone = config('services.whatsapp.phone');
    if (!$phone) return;
    $message = 'Hola, quisiera consultar la disponibilidad de ' . $producto->nombre;
    $url = 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
@endphp

<a href="{{ $url }}"
   target="_blank"
   rel="noopener noreferrer"
   class="inline-flex items-center justify-center gap-2 w-full mt-4 px-6 py-3 bg-emerald-500 hover:bg-emerald-600 text-white font-semibold rounded-lg transition-colors duration-200 shadow-sm">
    <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
    </svg>
    Consultar disponibilidad por WhatsApp
</a>
```

### 4.3. Comportamiento cuando no hay número

Si `WHATSAPP_PHONE_NUMBER` está vacío o no definido, el componente retorna vacío (no renderiza nada). Esto se logra con un `@php if (!$phone) return;` al inicio.

---

## 5. Integración en la vista `show.blade.php`

Se inserta el componente dentro de la columna derecha, **después del bloque de stock** (línea 114) y **antes de la descripción** (línea 116).

### Cambio exacto

En `resources/views/public/productos/show.blade.php`, después de:

```blade
            @endif
        </div>
```

Agregar:

```blade
        {{-- WhatsApp --}}
        <x-whatsapp-button :producto="$producto" />
```

El bloque completo de la columna derecha queda:

```
[1] Categoría
[2] Nombre del producto
[3] Marca
[4] Precio
[5] Stock (con indicador verde/ámbar)
[6] WHATSAPP – botón verde "Consultar disponibilidad por WhatsApp"
[7] Descripción
```

---

## 6. Archivos modificados

| Archivo | Acción |
|---------|--------|
| `.env` | Agregar `WHATSAPP_PHONE_NUMBER` |
| `.env.example` | Agregar `WHATSAPP_PHONE_NUMBER` |
| `config/services.php` | Agregar entrada `whatsapp.phone` |
| `resources/views/components/whatsapp-button.blade.php` | **Nuevo** — componente Blade |
| `resources/views/public/productos/show.blade.php` | Insertar `<x-whatsapp-button>` |

---

## 7. Consideraciones técnicas

### 7.1. Formato del número

- Usar solo dígitos: `519XXXXXXXX` (código país 51 = Perú + número).
- El componente no agrega validación de formato. Si el número está mal formado, WhatsApp Web puede rechazarlo o mostrar "Número inválido".
- **Responsabilidad del administrador** ingresar un número válido en el `.env`.

### 7.2. URL-encoding

Se usa `rawurlencode()` de PHP para codificar el mensaje correctamente. Esto maneja caracteres especiales como `ñ`, tildes, `&`, `=`, etc.

### 7.3. Apertura en nueva pestaña

El enlace usa `target="_blank"` con `rel="noopener noreferrer"` para:
- Abrir WhatsApp Web (o la app si está configurada en el dispositivo).
- Seguridad: `noopener` evita que la pestaña hija acceda a `window.opener`.
- Rendimiento: `noreferrer` evita filtrar la URL de origen.

### 7.4. Condicional de visibilidad

El botón **siempre se muestra** si hay número configurado, independientemente del stock. Esto es intencional: incluso si el producto está sin stock, el cliente puede consultar cuándo llegará.

### 7.5. Sin JavaScript

Toda la funcionalidad es 100% HTML+PHP. No requiere JS adicional. Cero dependencias nuevas.

### 7.6. Sin cambios en el controlador

El controlador `ProductoCatalogoController::show()` no necesita modificación. El número de WhatsApp se obtiene desde `config/services.php` en la vista, no desde el controlador.

### 7.7. Sin cambios en rutas

No se agregan nuevas rutas. Todo ocurre en el cliente al hacer clic en el enlace.

---

## 8. Pruebas y verificación

### 8.1. Pruebas manuales

| # | Escenario | Pasos | Resultado esperado |
|---|-----------|-------|--------------------|
| 1 | Número configurado + producto visible | Navegar a `/productos/{id}` | Botón verde visible debajo del stock |
| 2 | Click en botón | Hacer clic en "Consultar disponibilidad por WhatsApp" | Se abre `https://wa.me/519XXXXXXXX?text=Hola%2C%20quisiera%20consultar%20la%20disponibilidad%20de%20...` en nueva pestaña |
| 3 | Mensaje correcto | Verificar URL generada | El texto contiene el nombre del producto exacto |
| 4 | Número vacío | `WHATSAPP_PHONE_NUMBER=` (vacío) | Botón **no se muestra** |
| 5 | Variable ausente | Eliminar `WHATSAPP_PHONE_NUMBER` del `.env` | Botón **no se muestra** (config devuelve `null`) |
| 6 | Producto sin stock | Stock 0, número configurado | Botón visible (pueden consultar reabastecimiento) |
| 7 | Caracteres especiales en nombre | Producto "Paracetamol 500mg + Cafeína 65mg" | `&` se codifica como `%26` en la URL |

### 8.2. Verificación de archivos

```bash
# Confirmar que la variable está cargada
php artisan tinker
> config('services.whatsapp.phone')
# => "519XXXXXXXX"

# Confirmar ruta de componente existe
Test-Path -LiteralPath "resources/views/components/whatsapp-button.blade.php"

# Confirmar que el componente se incluye en la vista
Get-Content "resources/views/public/productos/show.blade.php" | Select-String "whatsapp"
```

### 8.3. Prueba de integración (PHPUnit)

```php
<?php

namespace Tests\Feature;

use App\Models\Producto;
use Tests\TestCase;

class WhatsAppButtonTest extends TestCase
{
    public function test_boton_whatsapp_se_muestra_cuando_hay_numero_configurado()
    {
        config(['services.whatsapp.phone' => '51900000000']);

        $producto = Producto::factory()->create(['estado' => 1, 'stock' => 5]);

        $response = $this->get(route('public.productos.show', $producto));

        $response->assertOk();
        $response->assertSee('Consultar disponibilidad por WhatsApp');
        $response->assertSee('wa.me/51900000000');
        $response->assertSee(rawurlencode($producto->nombre));
    }

    public function test_boton_whatsapp_no_se_muestra_sin_numero_configurado()
    {
        config(['services.whatsapp.phone' => null]);

        $producto = Producto::factory()->create(['estado' => 1, 'stock' => 5]);

        $response = $this->get(route('public.productos.show', $producto));

        $response->assertOk();
        $response->assertDontSee('Consultar disponibilidad por WhatsApp');
    }

    public function test_boton_whatsapp_se_muestra_incluso_sin_stock()
    {
        config(['services.whatsapp.phone' => '51900000000']);

        $producto = Producto::factory()->create(['estado' => 1, 'stock' => 0]);

        $response = $this->get(route('public.productos.show', $producto));

        $response->assertOk();
        $response->assertSee('Consultar disponibilidad por WhatsApp');
    }

    public function test_mensaje_whatsapp_incluye_nombre_del_producto()
    {
        config(['services.whatsapp.phone' => '51900000000']);

        $producto = Producto::factory()->create([
            'estado' => 1,
            'nombre' => 'Paracetamol 500mg',
        ]);

        $response = $this->get(route('public.productos.show', $producto));

        $response->assertSee(rawurlencode('Paracetamol 500mg'));
    }
}
```

**Nota**: Los tests asumen que existe `Producto::factory()`. Si no existe el factory, se puede crear o reemplazar por `Producto::create([...])` con datos manuales.

---

## 9. Orden de implementación

| Paso | Archivos | Acción | Verificación |
|------|----------|--------|--------------|
| 1 | `.env`, `.env.example` | Agregar `WHATSAPP_PHONE_NUMBER` | `php artisan tinker > config('services.whatsapp.phone')` |
| 2 | `config/services.php` | Agregar entrada `whatsapp` | Mismo comando anterior |
| 3 | `resources/views/components/whatsapp-button.blade.php` | **Crear** componente | `Test-Path` del archivo |
| 4 | `resources/views/public/productos/show.blade.php` | Insertar `<x-whatsapp-button>` | Navegar a `/productos/{id}` |
| 5 | Pruebas manuales | Ejecutar escenarios de la sección 8.1 | Verificar visual + funcional |
| 6 | (Opcional) Tests | Ejecutar `php artisan test --filter WhatsApp` | Todos verdes |

---

## 10. Posibles extensiones futuras

| Funcionalidad | Impacto |
|---------------|---------|
| **Mensaje personalizable** | Agregar `WHATSAPP_MESSAGE_TEMPLATE` en `.env` para que el admin defina el texto |
| **Número por producto** | Columna `whatsapp_phone` en tabla `producto`, con fallback al número global |
| **Número por categoría** | Columna `whatsapp_phone` en `categoria` para delegar consultas a especialistas |
| **Botón en grilla (ícono)** | Agregar un ícono de WhatsApp en cada card de producto que abra el mismo chat |
| **Múltiples números (sucursales)** | Selector de sucursal en la vista, cada una con su número de WhatsApp |
| **Click tracking** | Evento analítico (Google Analytics, Meta Pixel) al hacer clic en el botón |
| **Previsualización del mensaje** | Mostrar al usuario el texto exacto que se enviará antes de abrir WhatsApp |

---

## 11. Diagrama de decisión

```
¿WHATSAPP_PHONE_NUMBER está configurado?
├── SÍ
│   └── ¿Producto existe y es visible (estado=1)?
│       ├── SÍ → Renderizar botón WhatsApp
│       └── NO → 404 (manejado por el controlador)
└── NO
    └── No renderizar nada
```
