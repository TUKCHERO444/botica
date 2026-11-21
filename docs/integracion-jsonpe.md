# Integración json.pe — Consulta DNI y RUC

> **Versión:** 1.2.0  
> **Fecha:** 2026-11-20  
> **Módulos afectados:** Clientes, Ventas, Atenciones, Registro público, Proveedores  
> **Proveedor:** [json.pe](https://api.json.pe)  
> **Endpoints:** `POST https://api.json.pe/api/dni` · `POST https://api.json.pe/api/ruc`  
> **Estado:** Implementado (DNI y RUC). Reemplaza a decolecta en ambos casos.

---

## 1. Resumen

Las consultas de DNI y RUC usan **json.pe**, pero **solo después de verificar la
base de datos local**. Cada formulario tiene dos pasos:

1. **Verificar** el DNI/RUC en la BD (endpoint local, sin tokens).
2. Si no existe, **recién** habilitar la consulta a json.pe.

| Módulo | Punto de entrada | Verificación local | Consulta API |
|---|---|---|---|
| Clientes (admin) | “Verificar cliente” → “Buscar DNI” | `/admin/clientes/verificar-dni` | `ReniecService` → `/api/dni` |
| Ventas (admin) | “Verificar cliente” → “RENIEC” | `/admin/clientes/verificar-dni` | `ReniecService` → `/api/dni` |
| Atenciones (admin) | “Verificar cliente” → “RENIEC” | `/admin/clientes/verificar-dni` | `ReniecService` → `/api/dni` |
| Registro público | “Verificar DNI” → “Buscar DNI” | `/api/reniec/verificar` | `ReniecService` → `/api/dni` |
| Proveedores (admin) | “Verificar proveedor” → “Buscar RUC” | `/admin/proveedores/verificar-ruc` | `SunatService` → `/api/ruc` |

Rutas internas:

- `POST /admin/clientes/verificar-dni` → `ClienteController@verificarDni` (solo BD)
- `POST /admin/proveedores/verificar-ruc` → `ProveedorController@verificarRuc` (solo BD)
- `POST /api/reniec/verificar` → `Api\ReniecController@verificar` (solo `exists`, sin datos)
- `POST /api/sunat/verificar` → `Api\SunatController@verificar` (solo `exists`, sin datos)
- `POST /admin/reniec/consultar` → `Admin\ReniecController@checkDni`
- `POST /api/reniec/consultar` → `Api\ReniecController@checkDni`
- `POST /admin/sunat/consultar` → `Admin\SunatController@checkRuc`
- `POST /api/sunat/consultar` → `Api\SunatController@checkRuc`

Los endpoints `verificar-*` **nunca** llaman a json.pe. Los endpoints `consultar`
también vuelven a comprobar la BD antes de la API (defensa en profundidad).

---

## 2. Regla de oro: seeders y migraciones no consumen APIs

**Ningún seeder, factory ni migración debe llamar a json.pe ni a ningún servicio
externo.** Los datos de prueba y las migraciones se construyen solo con datos
locales.

Esta regla se protege en tres niveles:

1. **Código**: no existe ninguna referencia a la API en `database/seeders`,
   `database/migrations` ni `database/factories`.
2. **Runtime**: `App\Support\ApiExternaGuard` bloquea el consumo dentro de
   `migrate`, `migrate:fresh`, `migrate:refresh`, `migrate:rollback`, `db:seed`,
   `db:wipe` y comandos equivalentes. `JsonPeClient` llama al guard antes de
   cualquier petición HTTP.
3. **Tests**: `tests/Feature/SeedersSinConsumoApiTest.php` falla si aparece
   `Http::`, `ReniecService`, `SunatService`, `JsonPeClient`, `curl_init`,
   `GuzzleHttp` o una URL HTTP en `database/migrations`, `database/seeders` o
   `database/factories`.

Consecuencia práctica: `php artisan migrate:fresh --seed` nunca gasta tokens.
La API solo se consume desde peticiones HTTP reales de la aplicación.

### 2.1 Regla de ahorro de tokens

Antes de consultar json.pe:

1. El formulario llama a un endpoint `verificar-*`.
2. Ese endpoint busca en `cliente` / `proveedor`.
3. Si existe, devuelve los datos locales y **no se habilita** la consulta API.
4. Si no existe, se habilita el botón de consulta a json.pe.

Los endpoints `consultar` repiten la búsqueda local antes de la API, de modo que
una llamada directa tampoco gasta tokens si el registro ya existe.

---

## 3. Arquitectura

```
Navegador (Blade + JS)
        │  "Verificar cliente" / "Verificar proveedor"
        ▼
ClienteController@verificarDni · ProveedorController@verificarRuc
Api\ReniecController@verificar · Api\SunatController@verificar
        │  SOLO consulta local (cliente / proveedor) · sin tokens
        ▼
   ¿existe?
   ├─ Sí → datos locales / exists=true (registro público) · fin
   └─ No → se habilita "Buscar DNI/RUC"
                     │
                     ▼
        Admin\ReniecController@checkDni · Admin\SunatController@checkRuc
        Api\ReniecController@checkDni · Api\SunatController@checkRuc
                     │  vuelve a verificar BD (defensa en profundidad)
                     ▼
        ReniecService / SunatService
                     │  validación · caché positiva/negativa · mapeo
                     ▼
        JsonPeClient
                     │  ApiExternaGuard + Bearer + JSON + timeout + reintentos
                     ▼
        POST https://api.json.pe/api/dni   { "dni": "27427864" }
        POST https://api.json.pe/api/ruc   { "ruc": "20552103816" }
```

### Responsabilidades

| Componente | Archivo | Responsabilidad |
|---|---|---|
| `JsonPeClient` | `app/Services/JsonPe/JsonPeClient.php` | Transporte HTTP compartido, token, timeout, reintentos y guard. Única puerta a json.pe. |
| `ReniecService` | `app/Services/ReniecService.php` | Valida 8 dígitos, cachea positivo/negativo, traduce errores y mapea DNI. |
| `SunatService` | `app/Services/SunatService.php` | Valida 11 dígitos, cachea positivo/negativo, traduce errores y mapea RUC. |
| `ApiExternaGuard` | `app/Support/ApiExternaGuard.php` | Bloquea llamadas desde migraciones/seeders. |
| Verificación admin | `ClienteController@verificarDni`, `ProveedorController@verificarRuc` | Solo BD local, devuelven datos del registro existente. |
| Verificación pública | `Api\ReniecController@verificar`, `Api\SunatController@verificar` | Solo BD local, devuelven únicamente `exists` (sin PII). |
| Controladores `consultar` | Admin y Api | Vuelven a verificar BD antes de llamar a la API. |

### 3.1 Registro público

Si el DNI ya existe (por ejemplo, un cliente creado en una venta), el endpoint
público responde **solo** `exists: true`, sin nombres ni apellidos, y no consume
tokens. El formulario muestra:

> “Este DNI ya está registrado. Acércate a la botica para habilitar tu cuenta.”

No se permite continuar con el registro. Esto evita exponer datos personales y
evita el error de `unique` al final del formulario.

### 3.2 Permisos

Los endpoints de verificación/selección de clientes aceptan cualquiera de estos
permisos: `clientes`, `ventas` o `atenciones`. Así un **Vendedor** puede buscar,
verificar y seleccionar clientes sin tener acceso al módulo Clientes completo.

---

## 4. Contrato de la API

### 4.1 DNI — Request

```http
POST /api/dni HTTP/1.1
Host: api.json.pe
Authorization: Bearer <token>
Content-Type: application/json

{
  "dni": "27427864"
}
```

- `dni`: string obligatorio, exactamente 8 dígitos (`^[0-9]{8}$`).

### 4.2 DNI — Response 200

```json
{
  "success": true,
  "message": "exito",
  "data": {
    "numero": "27427864",
    "nombre_completo": "CASTILLO TERRONES, JOSE PEDRO",
    "nombres": "JOSE PEDRO",
    "apellido_paterno": "CASTILLO",
    "apellido_materno": "TERRONES",
    "codigo_verificacion": 7,
    "direccion": "",
    "direccion_completa": "",
    "ubigeo_reniec": "",
    "ubigeo_sunat": ""
  }
}
```

`codigo_verificacion` puede venir como número o string; internamente se normaliza
a string.

### 4.3 RUC — Request

```http
POST /api/ruc HTTP/1.1
Host: api.json.pe
Authorization: Bearer <token>
Content-Type: application/json

{
  "ruc": "20552103816"
}
```

- `ruc`: string obligatorio de 11 dígitos (`^\d{11}$`).

### 4.4 RUC — Response 200

```json
{
  "success": true,
  "message": "exito",
  "data": {
    "ruc": "20552103816",
    "nombre_o_razon_social": "AGROLIGHT PERU S.A.C.",
    "estado": "SUSPENSION TEMPORAL",
    "condicion": "HABIDO",
    "departamento": "LIMA",
    "provincia": "LIMA",
    "distrito": "SANTA ANITA",
    "direccion": "PJ. JORGE BASADRE NRO. 158 URB. POP LA UNIVERSAL 2DA ET.",
    "direccion_completa": "PJ. JORGE BASADRE NRO. 158 URB. POP LA UNIVERSAL 2DA ET., LIMA - LIMA - SANTA ANITA",
    "ubigeo_sunat": "150137",
    "ubigeo": ["15", "1501", "150137"],
    "es_agente_de_retencion": "NO",
    "es_agente_de_percepcion": "NO",
    "es_agente_de_percepcion_combustible": "NO",
    "es_buen_contribuyente": "SI"
  }
}
```

### 4.5 Errores

| Situación | Status | Resultado interno |
|---|---|---|
| DNI/RUC inexistente | `404` (o `200` con `success: false`) | `ReniecNotFoundException` / `SunatNotFoundException` → HTTP 422 |
| Token inválido/expirado | `401` / `403` | `ReniecServiceException` / `SunatServiceException` → HTTP 503 |
| Error del proveedor | `5xx` | Excepción de servicio → HTTP 503 |
| Timeout / conexión | — | 2 reintentos (100 ms) y luego excepción de servicio → HTTP 503 |
| Token sin configurar | — | `JsonPeException` → excepción de servicio → HTTP 503 |
| Formato inválido | — | Validado antes de llamar (8 u 11 dígitos) |

Los controladores nunca exponen el token ni el detalle técnico al usuario.

---

## 5. Mapeo de datos

### 5.1 DNI → `cliente`

| json.pe | `ReniecService` | Destino |
|---|---|---|
| `data.numero` | `dni` | `cliente.dni` (8) |
| `data.nombres` | `nombre` (máx. 50) | `cliente.nombre` |
| `data.apellido_paterno` | `apellido_paterno` (máx. 25) | `cliente.apellido_paterno` |
| `data.apellido_materno` | `apellido_materno` (máx. 25) | `cliente.apellido_materno` |
| `data.nombre_completo` | `nombre_completo` | Solo visualización |
| `data.direccion_completa` / `data.direccion` | `direccion` (máx. 255) | Disponible para futuros usos |
| `data.ubigeo_reniec` | `ubigeo` | Disponible para futuros usos |
| `data.codigo_verificacion` | `codigo_verificacion` | Disponible para futuros usos |

Reglas:

- Los valores se truncan a la longitud de la tabla y se registra un `warning`.
- Si `nombre_completo` viene vacío, se arma como `APELLIDOS, NOMBRES`.
- Los controladores actuales solo devuelven `dni`, `nombre`, `apellido_paterno`,
  `apellido_materno` y `nombre_completo`.

### 5.2 RUC → `proveedor`

| json.pe | `SunatService` | Destino |
|---|---|---|
| `data.ruc` | `ruc` | `proveedor.ruc` (11) |
| `data.nombre_o_razon_social` | `razon_social` (máx. 150) | `proveedor.razon_social` |
| `data.condicion` | `condicion` (máx. 15) | `proveedor.condicion` |
| `data.estado` | `estado` (texto → 0/1) | `proveedor.estado` (`smallInteger`) |
| `data.direccion` | `direccion` (máx. 255) | `proveedor.direccion` |
| `data.ubigeo_sunat` | `ubigeo` (máx. 6) | `proveedor.ubigeo` |
| `data.distrito` | `distrito` (máx. 100) | `proveedor.distrito` |
| `data.provincia` | `provincia` (máx. 100) | `proveedor.provincia` |
| `data.departamento` | `departamento` (máx. 100) | `proveedor.departamento` |

Reglas:

- `estado` en la API es texto (`ACTIVO`, `SUSPENSION TEMPORAL`, `BAJA`, ...).
  La tabla usa 0/1: **solo `ACTIVO` se guarda como 1**; el resto como 0.
- Si `direccion` viene vacía se usa `direccion_completa`.
- Si `ubigeo_sunat` viene vacío se usa el último elemento del array `ubigeo`.
- Los indicadores (`es_agente_de_retencion`, `es_buen_contribuyente`, etc.) no
  se almacenan: no existe columna para ellos. Se pueden agregar en el futuro con
  una migración explícita.
- Los valores se truncan a la longitud de la columna y se registra un `warning`.

---

## 6. Caché

Ambos servicios cachean resultados:

| Tipo | Clave | Duración | Efecto |
|---|---|---|---|
| Éxito | `reniec:{dni}` / `sunat:{ruc}` | `JSONPE_CACHE_MINUTOS` (60) | Evita repetir la API para el mismo documento. |
| No encontrado | `reniec:no-encontrado:{dni}` / `sunat:no-encontrado:{ruc}` | `JSONPE_CACHE_NEGATIVO_MINUTOS` (10) | Evita gastar tokens al reintentar un DNI/RUC inexistente. |

Los errores de servicio (`401`, `5xx`, timeout) **no** se cachean. Un resultado
exitoso limpia la marca negativa previa.

---

## 7. Configuración

`config/services.php`:

```php
'jsonpe' => [
    'token' => env('JSONPE_API_TOKEN'),
    'url' => env('JSONPE_API_URL', 'https://api.json.pe/api'),
    'timeout' => (int) env('JSONPE_API_TIMEOUT', 8),
    'cache_minutos' => (int) env('JSONPE_CACHE_MINUTOS', 60),
    'cache_negativo_minutos' => (int) env('JSONPE_CACHE_NEGATIVO_MINUTOS', 10),
],
```

`.env`:

```ini
JSONPE_API_TOKEN=tu_token_de_json_pe
JSONPE_API_URL=https://api.json.pe/api
JSONPE_CACHE_NEGATIVO_MINUTOS=10
```

> DNI y RUC comparten el mismo token de json.pe. Ya no existe `SUNAT_API_TOKEN`
> ni `RENIEC_API_TOKEN`: el proveedor anterior (decolecta) fue reemplazado.

---

## 8. Archivos involucrados

Creados:

- `app/Services/JsonPe/JsonPeClient.php`
- `app/Support/ApiExternaGuard.php`
- `app/Exceptions/JsonPe/JsonPeException.php`
- `app/Exceptions/JsonPe/ConsumoApiBloqueadoException.php`
- `tests/Unit/ReniecServiceTest.php`
- `tests/Unit/SunatServiceTest.php`
- `tests/Unit/ApiExternaGuardTest.php`
- `tests/Feature/ReniecConsultaTest.php`
- `tests/Feature/ReniecPublicoTest.php`
- `tests/Feature/SunatConsultaTest.php`
- `tests/Feature/SunatPublicoTest.php`
- `tests/Feature/SeedersSinConsumoApiTest.php`

Modificados:

- `app/Services/ReniecService.php`
- `app/Services/SunatService.php`
- `app/Http/Controllers/Admin/ClienteController.php`
- `app/Http/Controllers/Admin/ProveedorController.php`
- `app/Http/Controllers/Api/ReniecController.php`
- `app/Http/Controllers/Api/SunatController.php`
- `routes/admin.php` / `routes/api.php`
- `resources/js/admin/client-search.js`
- `resources/js/admin/reniec-search.js`
- `resources/js/admin/sunat-search.js`
- `resources/js/admin/clientes/create.js`
- `resources/js/admin/proveedores/create.js`
- `resources/js/public/registro.js`
- `resources/views/admin/clientes/create.blade.php`
- `resources/views/admin/proveedores/create.blade.php`
- `resources/views/public/auth/registro.blade.php`
- `config/services.php`
- `.env` / `.env.example`

---

## 9. Pruebas

```bash
php artisan test tests/Unit/ReniecServiceTest.php
php artisan test tests/Unit/SunatServiceTest.php
php artisan test tests/Unit/ApiExternaGuardTest.php
php artisan test tests/Feature/ReniecConsultaTest.php
php artisan test tests/Feature/ReniecPublicoTest.php
php artisan test tests/Feature/SunatConsultaTest.php
php artisan test tests/Feature/SunatPublicoTest.php
php artisan test tests/Feature/SeedersSinConsumoApiTest.php
```

Cubren: request POST con Bearer, mapeo de campos, estado 0/1, `404`/`success:false`,
`401`, `5xx`, timeout con reintentos, token ausente, caché positiva y negativa,
truncamiento, respaldo de dirección/ubigeo, verificación local sin llamada externa,
vendedor con acceso a verificación/selección, registro público sin datos ni API,
y bloqueo de la API en seeders/migraciones.

---

## 10. Notas

- El formulario de proveedor muestra `estado` como selector Activo/Inactivo.
  Un RUC en `SUSPENSION TEMPORAL` se precarga como Inactivo (0), que es lo que
  permite la tabla actual.
- Si se necesitan los indicadores de agente de retención/percepción o buen
  contribuyente, primero hay que agregar columnas a `proveedor` y luego
  mapearlas en `SunatService`.
