# Integración API SUNAT — Consulta de RUC para Proveedores

> **Versión:** 1.0.0  
> **Fecha:** 2026-06-21  
> **Módulos afectados:** Proveedores, Ingresos  
> **Proveedor:** [dacoleta.api](https://api.decolecta.com)  
> **Endpoints involucrados:** `GET https://api.decolecta.com/v1/sunat/ruc?numero={ruc}`  
> **Estado:** Pendiente de implementación

---

## Índice

1. [Descripción General](#1-descripción-general)
2. [Objetivos del Negocio](#2-objetivos-del-negocio)
3. [Arquitectura de la Solución](#3-arquitectura-de-la-solución)
4. [Proveedor Externo: dacoleta.api](#4-proveedor-externo-dacoletaapi)
5. [Entidades Involucradas](#5-entidades-involucradas)
6. [Mapeo de Datos API → Tabla Proveedor](#6-mapeo-de-datos-api--tabla-proveedor)
7. [Reglas de Negocio](#7-reglas-de-negocio)
8. [Casos de Uso Detallados](#8-casos-de-uso-detallados)
9. [Diseño Técnico](#9-diseño-técnico)
10. [Endpoint: Consulta RUC (Admin)](#10-endpoint-consulta-ruc-admin)
11. [Endpoint: Consulta RUC (Público)](#11-endpoint-consulta-ruc-público)
12. [Flujo: Creación de Proveedor con Auto-Completado](#12-flujo-creación-de-proveedor-con-auto-completado)
13. [Flujo: Creación de Ingreso con Auto-Completado de Proveedor](#13-flujo-creación-de-ingreso-con-auto-completado-de-proveedor)
14. [Componente Frontend: sunat-search.js](#14-componente-frontend-sunat-searchjs)
15. [Archivos a Crear](#15-archivos-a-crear)
16. [Archivos a Modificar](#16-archivos-a-modificar)
17. [Variables de Entorno](#17-variables-de-entorno)
18. [Configuración services.php](#18-configuración-servicesphp)
19. [Manejo de Errores](#19-manejo-de-errores)
20. [Consideraciones de Seguridad](#20-consideraciones-de-seguridad)
21. [Consideraciones de Performance y Rate Limiting](#21-consideraciones-de-performance-y-rate-limiting)
22. [Pruebas Unitarias y de Integración](#22-pruebas-unitarias-y-de-integración)
23. [Glosario](#23-glosario)

---

## 1. Descripción General

El sistema **Botica Sistema** requiere integrar la consulta de datos de empresas a través del **RUC** utilizando el proveedor externo **dacoleta.api** (el mismo proveedor de la API RENIEC ya integrada). Esta funcionalidad permite:

- **Carga automática** de datos del proveedor (razón social, condición, estado, dirección, ubigeo, distrito, provincia, departamento) a partir de su número de RUC.
- **Agilizar el registro** de proveedores al evitar el tipeo manual de todos los campos.
- **Garantizar la integridad de datos** al usar información oficial de SUNAT.
- **Reducir errores ortográficos** en los datos maestros de proveedores.

La integración abarca dos puntos del sistema:

| Módulo | Tipo de usuario | Funcionalidad |
|--------|----------------|---------------|
| **Proveedores** (admin) | Administrador | Botón "Buscar RUC" en formulario de creación que auto-completa datos |
| **Ingresos** (admin) | Administrador | Auto-completado de datos de proveedor al seleccionar RUC en ingreso |

---

## 2. Objetivos del Negocio

| ID | Objetivo | Métrica de éxito |
|----|----------|------------------|
| OBJ-01 | Reducir tiempo de registro de proveedores nuevos | Reducción del 40% en tiempo promedio de creación de proveedor |
| OBJ-02 | Eliminar errores de tipeo en datos de proveedores | 0% de reclamos por razón social mal escrita |
| OBJ-03 | Unificar datos maestros de proveedores evitando duplicados | Sin registros duplicados de proveedor con mismo RUC |
| OBJ-04 | Contar con dirección completa y ubigeo para cada proveedor | 100% de proveedores nuevos con dirección, distrito, provincia y departamento |

---

## 3. Arquitectura de la Solución

```
┌─────────────────────────────────────────────────────────────────┐
│                        FRONTEND (Blade + JS)                     │
│                                                                   │
│  ┌──────────────────────────────┐                                │
│  │  sunat-search.js  (NUEVO)    │                                │
│  │  - Botón "Buscar RUC"        │                                │
│  │  - Validación 11 dígitos     │                                │
│  │  - AJAX a /admin/sunat/      │                                │
│  │    consultar                 │                                │
│  │  - Auto-completa campos      │                                │
│  └──────────────┬───────────────┘                                │
│                 │ Axios                                          │
└─────────────────┼────────────────────────────────────────────────┘
                  │
          HTTP POST │ JSON
                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                     BACKEND (Laravel 12)                         │
│                                                                   │
│  ┌────────────────────────────┐  ┌──────────────────────────┐   │
│  │ SunatController (Admin)    │  │ Api\SunatController     │   │
│  │ /admin/sunat/consultar     │  │ /api/sunat/consultar    │   │
│  │ (auth + permission:        │  │ (throttle:60,1 público) │   │
│  │  proveedores)              │  │                          │   │
│  └─────────┬──────────────────┘  └───────────┬──────────────┘   │
│            │                                  │                  │
│            └──────────────┬───────────────────┘                  │
│                           │                                      │
│                    ┌──────▼───────┐                               │
│                    │ SunatService  │  (NUEVO)                    │
│                    │ - consultar() │                             │
│                    │ - mapear()    │                             │
│                    └──────┬───────┘                               │
│                           │ Http::withToken()                     │
│                           │ timeout(5) · retry(2,100)             │
└───────────────────────────┼──────────────────────────────────────┘
                            │
                    HTTP GET │ ?numero=XXXXXXXXXXX
                            │ Authorization: Bearer <token>
                            ▼
              ┌─────────────────────────────┐
              │  dacoleta.api               │
              │  https://api.decolecta.com  │
              │  /v1/sunat/ruc              │
              └─────────────────────────────┘
```

### Flujo de datos

1. **Frontend**: Usuario ingresa RUC (11 dígitos) y presiona "Buscar RUC"
2. **Frontend**: Valida que sean exactamente 11 dígitos numéricos
3. **Frontend**: `axios.post('/admin/sunat/consultar', { ruc })`
4. **SunatController**: Valida el RUC (required, size:11, regex:/^\d{11}$/)
5. **SunatController**: Busca `Proveedor::where('ruc', $ruc)->first()`
   - **Existe** → retorna `{ success: true, exists: true, proveedor: {...} }`
   - **No existe** → delega a `SunatService::consultar($ruc)`
6. **SunatService**: `Http::withToken(token)->timeout(5)->get(url, ['numero' => $ruc])`
7. **SunatService**: Mapea respuesta JSON al formato interno
8. **SunatController**: Retorna `{ success: true, exists: false, data: {...} }`
9. **Frontend**: Auto-completa los campos del formulario con los datos obtenidos

---

## 4. Proveedor Externo: dacoleta.api

### 4.1 Endpoint básico (el que usaremos)

| Propiedad | Valor |
|-----------|-------|
| **Método** | `GET` |
| **URL base** | `https://api.decolecta.com/v1/sunat/ruc` |
| **Parámetro query** | `numero` (string, 11 dígitos) |
| **Autenticación** | Bearer Token (JWT) — mismo token que la API RENIEC |

### 4.2 Headers

| Header | Valor |
|--------|-------|
| `Content-Type` | `application/json` |
| `Authorization` | `Bearer <token>` |

### 4.3 Request

```
GET https://api.decolecta.com/v1/sunat/ruc?numero=20601030013
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
Content-Type: application/json
```

### 4.4 Response 200 (Success)

```json
{
    "razon_social": "REXTIE S.A.C.",
    "numero_documento": "20601030013",
    "estado": "ACTIVO",
    "condicion": "HABIDO",
    "direccion": "AV. JOSE GALVEZ BARRENECHEA NRO 566 INT. 101 URB. CORPAC ",
    "ubigeo": "150131",
    "via_tipo": "AV.",
    "via_nombre": "JOSE GALVEZ BARRENECHEA",
    "zona_codigo": "URB.",
    "zona_tipo": "CORPAC",
    "numero": "566",
    "interior": "101",
    "lote": "-",
    "dpto": "-",
    "manzana": "-",
    "kilometro": "-",
    "distrito": "SAN ISIDRO",
    "provincia": "LIMA",
    "departamento": "LIMA",
    "es_agente_retencion": false,
    "es_buen_contribuyente": false,
    "locales_anexos": null
}
```

| Campo | Tipo | Descripción | Almacenar |
|-------|------|-------------|-----------|
| `razon_social` | string | Razón social completa | Sí → `razon_social` |
| `numero_documento` | string | RUC (11 dígitos) | Sí → `ruc` |
| `estado` | string | "ACTIVO" u otro | Sí → `estado` (mapeado a int) |
| `condicion` | string | "HABIDO", "NO HABIDO", etc. | Sí → `condicion` |
| `direccion` | string | Dirección fiscal completa | Sí → `direccion` |
| `ubigeo` | string | Código ubigeo (6 dígitos) | Sí → `ubigeo` |
| `distrito` | string | Distrito | Sí → `distrito` |
| `provincia` | string | Provincia | Sí → `provincia` |
| `departamento` | string | Departamento | Sí → `departamento` |
| `via_tipo` | string | Tipo de vía | No |
| `via_nombre` | string | Nombre de vía | No |
| `numero` | string | Número de dirección | No |
| `interior` | string | Interior | No |
| `es_agente_retencion` | bool | Es agente de retención | No (futuro) |
| `es_buen_contribuyente` | bool | Es buen contribuyente | No (futuro) |
| `locales_anexos` | list | Locales anexos | No |

### 4.5 Response 422 (Error)

```json
{
    "message": "ruc no valido"
}
```

### 4.6 SLA esperado

| Métrica | Valor |
|---------|-------|
| Tiempo de respuesta esperado | < 2s |
| Timeout configurado en cliente | 5s |
| Reintentos automáticos | 2 intentos con 100ms de espera |
| Disponibilidad esperada | 99.5% |

---

## 5. Entidades Involucradas

### 5.1 Tabla `proveedor` (existente — se modifica)

| Columna | Tipo | Restricciones | Uso en SUNAT |
|---------|------|--------------|--------------|
| `id_proveedor` | int (PK, auto_increment) | NOT NULL | Solo referencia FK |
| `ruc` | char(11) | | **Se mapea desde `numero_documento`** |
| `razon_social` | varchar(150) | NOT NULL | **Se mapea desde `razon_social`** |
| `condicion` | varchar(15) | NOT NULL | **Se mapea desde `condicion`** |
| `estado` | smallInteger | NOT NULL | **Se mapea desde `estado` (string→int)** |
| `direccion` | varchar(255) | NULLABLE (NUEVO) | **Se mapea desde `direccion`** |
| `ubigeo` | char(6) | NULLABLE (NUEVO) | **Se mapea desde `ubigeo`** |
| `distrito` | varchar(100) | NULLABLE (NUEVO) | **Se mapea desde `distrito`** |
| `provincia` | varchar(100) | NULLABLE (NUEVO) | **Se mapea desde `provincia`** |
| `departamento` | varchar(100) | NULLABLE (NUEVO) | **Se mapea desde `departamento`** |

### 5.2 Tabla `ingreso` (existente)

| Columna | Tipo | Uso en SUNAT |
|---------|------|--------------|
| `id_ingreso` | int (PK) | No directo |
| `proveedor_id_proveedor` | int (FK → `proveedor.id_proveedor`) | **Referencia al proveedor** |
| `user_id` | bigint (FK → `users.id`) | No directo |

---

## 6. Mapeo de Datos API → Tabla Proveedor

### 6.1 Mapeo directo

| Respuesta API (JSON) | Campo `proveedor` | Tipo destino | Longitud | Transformación |
|---------------------|-------------------|-------------|----------|---------------|
| `numero_documento` | `ruc` | char(11) | 11 | Sin cambio |
| `razon_social` | `razon_social` | varchar(150) | 150 | Sin cambio |
| `condicion` | `condicion` | varchar(15) | 15 | Sin cambio |
| `estado` | `estado` | smallInteger | — | `ACTIVO` → 1, otro → 0 |
| `direccion` | `direccion` | varchar(255) | 255 | Sin cambio |
| `ubigeo` | `ubigeo` | char(6) | 6 | Sin cambio |
| `distrito` | `distrito` | varchar(100) | 100 | Sin cambio |
| `provincia` | `provincia` | varchar(100) | 100 | Sin cambio |
| `departamento` | `departamento` | varchar(100) | 100 | Sin cambio |

### 6.2 Reglas de mapeo

1. **Truncamiento**: Si `razon_social` excede 150 caracteres, se trunca con `mb_substr($valor, 0, 150)`. Igual para los demás campos según su longitud máxima. Se registra warning en log.
2. **Normalización**: Los valores de la API vienen en mayúsculas sostenidas. Se almacenan tal cual.
3. **Estado**: La API devuelve `estado` como string ("ACTIVO", "BAJA DEFINITIVA", etc.). Se mapea a `smallInteger`: `1` para ACTIVO, `0` para cualquier otro valor.
4. **Unicidad**: El RUC debe ser único en la tabla `proveedor`. Si ya existe un proveedor con ese RUC, se retorna el existente (no se duplica).
5. **Ubigeo**: Se almacena como string de 6 caracteres (ej. "150131").

### 6.3 Ejemplo de mapeo

```php
// Respuesta API
[
    'razon_social' => 'REXTIE S.A.C.',
    'numero_documento' => '20601030013',
    'estado' => 'ACTIVO',
    'condicion' => 'HABIDO',
    'direccion' => 'AV. JOSE GALVEZ BARRENECHEA NRO 566 INT. 101 URB. CORPAC',
    'ubigeo' => '150131',
    'distrito' => 'SAN ISIDRO',
    'provincia' => 'LIMA',
    'departamento' => 'LIMA',
]

// Array mapeado para autocompletado
[
    'ruc' => '20601030013',
    'razon_social' => 'REXTIE S.A.C.',
    'condicion' => 'HABIDO',
    'estado' => 1,
    'direccion' => 'AV. JOSE GALVEZ BARRENECHEA NRO 566 INT. 101 URB. CORPAC',
    'ubigeo' => '150131',
    'distrito' => 'SAN ISIDRO',
    'provincia' => 'LIMA',
    'departamento' => 'LIMA',
]
```

---

## 7. Reglas de Negocio

| ID | Regla | Clasificación | Incumplimiento |
|----|-------|---------------|----------------|
| **RN-01** | El campo RUC debe tener exactamente 11 dígitos numéricos para activar la consulta | Validación frontend | El botón "Buscar RUC" permanece deshabilitado |
| **RN-02** | Antes de llamar a la API externa, se debe buscar el RUC en la tabla `proveedor` local | Validación backend | Llamada API innecesaria, costo/timeout |
| **RN-03** | Si el proveedor existe en BD local, NO se llama a la API externa | Optimización backend | Gasto innecesario de cuota API |
| **RN-04** | Si la API externa falla (timeout, error 5xx, 401), se debe mostrar un error claro al usuario, permitiendo el ingreso manual | UX / Resiliencia | Bloqueo del flujo de registro |
| **RN-05** | El estado "ACTIVO" de SUNAT se mapea a `estado = 1`; cualquier otro estado a `estado = 0` | Regla de negocio | Proveedores con estado incorrecto |
| **RN-06** | El token de API nunca debe estar expuesto en el frontend ni en código fuente (solo en `.env`) | Seguridad | Exposición de credenciales |
| **RN-07** | La consulta RUC pública debe tener rate limiting máximo de 60 requests/min por IP | Seguridad / Anti-abuso | Abuso del endpoint gratuito |

---

## 8. Casos de Uso Detallados

### 8.1 UC-01: Admin crea proveedor con auto-completado desde RUC

**Actor primario**: Administrador (con permiso `proveedores`)

**Precondiciones**: Modal "Nuevo Proveedor" abierto

**Flujo principal**:
1. Admin ingresa RUC en campo correspondiente
2. Presiona botón "Buscar RUC" junto al campo
3. Sistema valida 11 dígitos, envía `POST /admin/sunat/consultar`
4. API retorna datos → frontend auto-completa:
   - Campo `razon_social` ← `razon_social`
   - Campo `condicion` ← `condicion`
   - Campo `direccion` ← `direccion`
   - Campo `ubigeo` ← `ubigeo`
   - Campo `distrito` ← `distrito`
   - Campo `provincia` ← `provincia`
   - Campo `departamento` ← `departamento`
   - Campo `estado` ← `1` (si ACTIVO) o `0` (otro)
5. Admin verifica datos y presiona "Guardar"
6. Proveedor creado con datos completos

**Flujo alternativo**:
- **4a**: RUC ya existe en BD local → se muestran datos actuales y se informa "El proveedor ya está registrado". Se permite editar y actualizar.
- **4b**: API retorna error → toast "No se pudo consultar el RUC. Puede ingresar los datos manualmente."

### 8.2 UC-02: Admin registra ingreso con proveedor existente

**Actor primario**: Administrador (con permiso `ingresos`)

**Precondiciones**: Modal "Nuevo Ingreso" abierto

**Flujo principal**:
1. Admin selecciona un proveedor del selector (búsqueda por RUC o razón social)
2. Si el proveedor ya existe, se selecciona directamente
3. Admin completa el ingreso con productos y cantidades
4. Guarda el ingreso

**Flujo alternativo** (con autocompletado):
- **1a**: Admin ingresa RUC en un campo de búsqueda rápida y el sistema muestra los datos del proveedor para confirmar la selección

---

## 9. Diseño Técnico

### 9.1 Servicio: `SunatService`

**Namespace**: `App\Services`
**Archivo**: `app/Services/SunatService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Exceptions\Sunat\SunatNotFoundException;
use App\Exceptions\Sunat\SunatServiceException;

class SunatService
{
    private const int CACHE_TTL = 5;

    private const int HTTP_TIMEOUT = 5;

    public function consultar(string $ruc): array
    {
        $this->validarFormato($ruc);

        return Cache::remember("sunat:{$ruc}", self::CACHE_TTL * 60, function () use ($ruc) {
            return $this->consultarApi($ruc);
        });
    }

    private function validarFormato(string $ruc): void
    {
        if (!preg_match('/^\d{11}$/', $ruc)) {
            throw new SunatServiceException('El RUC debe tener 11 dígitos numéricos.');
        }
    }

    private function consultarApi(string $ruc): array
    {
        $response = Http::withToken(config('services.sunat.token'))
            ->timeout(self::HTTP_TIMEOUT)
            ->retry(2, 100)
            ->get(config('services.sunat.url'), ['numero' => $ruc]);

        if ($response->status() === 422) {
            throw new SunatNotFoundException(
                "El RUC {$ruc} no fue encontrado en los registros SUNAT."
            );
        }

        if ($response->status() === 401) {
            throw new SunatServiceException(
                'Error de autenticación con el servicio SUNAT. Contacte al administrador.'
            );
        }

        if ($response->failed()) {
            throw new SunatServiceException(
                'Error al consultar el servicio SUNAT. Intente nuevamente.'
            );
        }

        return $this->mapearRespuesta($response->json());
    }

    private function mapearRespuesta(array $data): array
    {
        return [
            'ruc' => $data['numero_documento'],
            'razon_social' => $this->truncar($data['razon_social'] ?? '', 150),
            'condicion' => $this->truncar($data['condicion'] ?? '', 15),
            'estado' => ($data['estado'] ?? '') === 'ACTIVO' ? 1 : 0,
            'direccion' => $this->truncar($data['direccion'] ?? '', 255),
            'ubigeo' => $data['ubigeo'] ?? '',
            'distrito' => $this->truncar($data['distrito'] ?? '', 100),
            'provincia' => $this->truncar($data['provincia'] ?? '', 100),
            'departamento' => $this->truncar($data['departamento'] ?? '', 100),
        ];
    }

    private function truncar(string $valor, int $maximo): string
    {
        if (mb_strlen($valor) > $maximo) {
            logger()->warning("SUNAT: valor truncado de {$maximo} caracteres", [
                'original' => $valor,
                'truncado' => mb_substr($valor, 0, $maximo),
            ]);
            return mb_substr($valor, 0, $maximo);
        }
        return $valor;
    }
}
```

### 9.2 Excepciones

**Archivo**: `app/Exceptions/Sunat/SunatNotFoundException.php`
```php
<?php

namespace App\Exceptions\Sunat;

use Exception;

class SunatNotFoundException extends Exception
{
    protected $message = 'RUC no encontrado en SUNAT.';
}
```

**Archivo**: `app/Exceptions/Sunat/SunatServiceException.php`
```php
<?php

namespace App\Exceptions\Sunat;

use Exception;

class SunatServiceException extends Exception
{
    protected $message = 'Error al consultar el servicio SUNAT.';
}
```

### 9.3 Controlador Admin: `SunatController`

**Namespace**: `App\Http\Controllers\Admin`
**Archivo**: `app/Http/Controllers/Admin/SunatController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Sunat\SunatNotFoundException;
use App\Exceptions\Sunat\SunatServiceException;
use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use App\Services\SunatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SunatController extends Controller
{
    public function __construct(
        private readonly SunatService $sunatService
    ) {}

    public function checkRuc(Request $request): JsonResponse
    {
        $request->validate([
            'ruc' => 'required|string|size:11|regex:/^\d{11}$/',
        ]);

        $ruc = $request->input('ruc');

        // Paso 1: Buscar en BD local
        $proveedor = Proveedor::where('ruc', $ruc)->first([
            'id_proveedor', 'ruc', 'razon_social', 'condicion',
            'estado', 'direccion', 'ubigeo', 'distrito',
            'provincia', 'departamento',
        ]);

        if ($proveedor) {
            return response()->json([
                'success' => true,
                'exists' => true,
                'proveedor' => $proveedor->toArray(),
            ]);
        }

        // Paso 2: Consultar API externa
        try {
            $data = $this->sunatService->consultar($ruc);

            return response()->json([
                'success' => true,
                'exists' => false,
                'data' => $data,
            ]);
        } catch (SunatNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (SunatServiceException $e) {
            logger()->error('SUNAT: Error de servicio', [
                'ruc' => substr($ruc, 0, 3) . '*****' . substr($ruc, -3),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar SUNAT. Verifique el RUC o intente más tarde.',
            ], 503);
        }
    }
}
```

### 9.4 Controlador Público: `Api\SunatController`

**Namespace**: `App\Http\Controllers\Api`
**Archivo**: `app/Http/Controllers/Api/SunatController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Sunat\SunatNotFoundException;
use App\Exceptions\Sunat\SunatServiceException;
use App\Http\Controllers\Controller;
use App\Services\SunatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SunatController extends Controller
{
    public function __construct(
        private readonly SunatService $sunatService
    ) {}

    public function checkRuc(Request $request): JsonResponse
    {
        $request->validate([
            'ruc' => 'required|string|size:11|regex:/^\d{11}$/',
        ]);

        $ruc = $request->input('ruc');

        try {
            $data = $this->sunatService->consultar($ruc);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (SunatNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (SunatServiceException $e) {
            logger()->error('SUNAT (público): Error de servicio', [
                'ruc' => substr($ruc, 0, 3) . '*****' . substr($ruc, -3),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar SUNAT. Verifique el RUC o intente más tarde.',
            ], 503);
        }
    }
}
```

### 9.5 Migración de base de datos

**Archivo**: `database/migrations/YYYY_MM_DD_HHMMSS_add_direccion_ubigeo_to_proveedor_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedor', function (Blueprint $table) {
            $table->string('direccion', 255)->nullable()->after('estado');
            $table->char('ubigeo', 6)->nullable()->after('direccion');
            $table->string('distrito', 100)->nullable()->after('ubigeo');
            $table->string('provincia', 100)->nullable()->after('distrito');
            $table->string('departamento', 100)->nullable()->after('provincia');
        });
    }

    public function down(): void
    {
        Schema::table('proveedor', function (Blueprint $table) {
            $table->dropColumn(['direccion', 'ubigeo', 'distrito', 'provincia', 'departamento']);
        });
    }
};
```

### 9.6 Modificación al modelo `Proveedor`

Agregar a `$fillable`:
```php
protected $fillable = [
    'ruc',
    'razon_social',
    'condicion',
    'estado',
    'direccion',
    'ubigeo',
    'distrito',
    'provincia',
    'departamento',
];
```

### 9.7 Frontend: `sunat-search.js`

**Archivo**: `resources/js/admin/sunat-search.js`

```javascript
import axios from 'axios';

export function initSunatSearch(rucInputId, btnId, fields) {
  const rucInput = document.getElementById(rucInputId);
  const btnBuscar = document.getElementById(btnId);
  if (!rucInput || !btnBuscar) return;

  rucInput.addEventListener('input', function () {
    btnBuscar.disabled = !/^\d{11}$/.test(this.value);
  });

  btnBuscar.addEventListener('click', async function () {
    const ruc = rucInput.value.trim();
    if (!/^\d{11}$/.test(ruc)) return;

    btnBuscar.disabled = true;
    btnBuscar.innerHTML = '<svg class="w-4 h-4 animate-spin inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>';

    try {
      const res = await axios.post('/admin/sunat/consultar', { ruc });

      if (res.data.success) {
        const data = res.data.data || res.data.proveedor;
        fields.forEach(field => {
          const el = document.querySelector(`[name="${field}"]`);
          if (el && data[field] !== undefined) el.value = data[field];
        });

        if (res.data.exists) {
          alert('El proveedor ya está registrado. Se cargaron sus datos actuales.');
        }
      } else {
        alert(res.data.message || 'No se pudieron obtener los datos del RUC.');
      }
    } catch (err) {
      const msg = err.response?.data?.message || 'Error al consultar SUNAT.';
      alert(msg);
    } finally {
      btnBuscar.disabled = false;
      btnBuscar.innerHTML = '<svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg> Buscar RUC';
    }
  });
}
```

---

## 10. Endpoint: Consulta RUC (Admin)

### 10.1 Definición

| Propiedad | Valor |
|-----------|-------|
| **Ruta** | `POST /admin/sunat/consultar` |
| **Nombre** | `admin.sunat.consultar` |
| **Controlador** | `App\Http\Controllers\Admin\SunatController@checkRuc` |
| **Middleware** | `auth`, `permission:proveedores` |
| **Rate limit** | Ilimitado (solo para admins autenticados) |

### 10.2 Request

```json
{
    "ruc": "20601030013"
}
```

### 10.3 Response 200 — Proveedor existe localmente

```json
{
    "success": true,
    "exists": true,
    "proveedor": {
        "id_proveedor": 1,
        "ruc": "20601030013",
        "razon_social": "REXTIE S.A.C.",
        "condicion": "HABIDO",
        "estado": 1,
        "direccion": "AV. JOSE GALVEZ BARRENECHEA NRO 566 INT. 101 URB. CORPAC",
        "ubigeo": "150131",
        "distrito": "SAN ISIDRO",
        "provincia": "LIMA",
        "departamento": "LIMA"
    }
}
```

### 10.4 Response 200 — Proveedor nuevo (desde API)

```json
{
    "success": true,
    "exists": false,
    "data": {
        "ruc": "20601030013",
        "razon_social": "REXTIE S.A.C.",
        "condicion": "HABIDO",
        "estado": 1,
        "direccion": "AV. JOSE GALVEZ BARRENECHEA NRO 566 INT. 101 URB. CORPAC",
        "ubigeo": "150131",
        "distrito": "SAN ISIDRO",
        "provincia": "LIMA",
        "departamento": "LIMA"
    }
}
```

### 10.5 Response 422 — RUC no encontrado

```json
{
    "success": false,
    "message": "El RUC 20601030013 no fue encontrado en los registros SUNAT."
}
```

### 10.6 Response 503 — Error de servicio

```json
{
    "success": false,
    "message": "Error al consultar SUNAT. Verifique el RUC o intente más tarde."
}
```

---

## 11. Endpoint: Consulta RUC (Público)

### 11.1 Definición

| Propiedad | Valor |
|-----------|-------|
| **Ruta** | `POST /api/sunat/consultar` |
| **Controlador** | `App\Http\Controllers\Api\SunatController@checkRuc` |
| **Middleware** | `throttle:60,1` (60 requests por minuto por IP) |
| **Rate limit** | 60 requests/min por IP |

### 11.2 Request

```json
{
    "ruc": "20601030013"
}
```

### 11.3 Response 200

```json
{
    "success": true,
    "data": {
        "ruc": "20601030013",
        "razon_social": "REXTIE S.A.C.",
        "condicion": "HABIDO",
        "estado": 1,
        "direccion": "AV. JOSE GALVEZ BARRENECHEA NRO 566 INT. 101 URB. CORPAC",
        "ubigeo": "150131",
        "distrito": "SAN ISIDRO",
        "provincia": "LIMA",
        "departamento": "LIMA"
    }
}
```

### 11.4 Response 429 — Rate limit excedido

```json
{
    "success": false,
    "message": "Demasiadas consultas. Intente nuevamente en un minuto."
}
```

Headers:
```
Retry-After: 60
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
```

---

## 12. Flujo: Creación de Proveedor con Auto-Completado

### 12.1 Request del frontend (desde formulario admin)

```http
POST /admin/sunat/consultar HTTP/1.1
Content-Type: application/json

{
    "ruc": "20601030013"
}
```

### 12.2 Response y auto-completado en frontend

```javascript
// Respuesta del servidor (exists: false)
{
    "success": true,
    "exists": false,
    "data": {
        "ruc": "20601030013",
        "razon_social": "REXTIE S.A.C.",
        "condicion": "HABIDO",
        "estado": 1,
        "direccion": "AV. JOSE GALVEZ BARRENECHEA NRO 566 INT. 101 URB. CORPAC",
        "ubigeo": "150131",
        "distrito": "SAN ISIDRO",
        "provincia": "LIMA",
        "departamento": "LIMA"
    }
}

// JS: auto-completar campos del formulario
document.querySelector('input[name="razon_social"]').value = data.data.razon_social;
document.querySelector('input[name="condicion"]').value = data.data.condicion;
document.querySelector('select[name="estado"]').value = data.data.estado;
document.querySelector('input[name="direccion"]').value = data.data.direccion;
document.querySelector('input[name="ubigeo"]').value = data.data.ubigeo;
document.querySelector('input[name="distrito"]').value = data.data.distrito;
document.querySelector('input[name="provincia"]').value = data.data.provincia;
document.querySelector('input[name="departamento"]').value = data.data.departamento;
```

### 12.3 Vista Blade modificada

```blade
<div>
    <label class="block text-sm font-medium text-stone-700 mb-1">RUC</label>
    <div class="flex gap-2">
        <input type="text" name="ruc" id="ruc-input" required maxlength="11" minlength="11"
            class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        <button type="button" id="btn-buscar-ruc"
            class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
            disabled>
            <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            Buscar RUC
        </button>
    </div>
</div>
```

### 12.4 Árbol de decisión

```
                    ┌─────────────────────────────┐
                    │  POST /admin/sunat/consultar │
                    └─────────────┬───────────────┘
                                  │
                    ┌─────────────▼───────────────┐
                    │  Validar RUC (11 dígitos)   │
                    └───┬─────────────────────────┘
                   OK   │
                        │
            ┌───────────▼───────────┐
            │  ¿Proveedor existe    │
            │  en BD local?         │
            └───┬───────┬───────────┘
           Sí   │       │   No
                │       │
                │       └──┐
                │          ├── Llamar API SUNAT
                │          │   ├── 200 → mapear respuesta
                │          │   ├── 422 → SunatNotFoundException
                │          │   └── 5xx/401 → SunatServiceException
                │          │
                │     ┌────▼────┐
                │     │ Retornar│
                │     │ success │
                │     │ exists: │
                │     │ false   │
                │     │ data:{} │
                │     └─────────┘
                │
      ┌─────────▼──────────────────┐
      │ Retornar success           │
      │ exists: true               │
      │ proveedor: {...}           │
      └────────────────────────────┘
```

---

## 13. Flujo: Creación de Ingreso con Auto-Completado de Proveedor

El módulo de ingresos ya cuenta con un selector de proveedores. Con esta integración:

1. El usuario puede buscar un proveedor por RUC en el campo de búsqueda
2. Si el proveedor ya existe en BD, se selecciona directamente
3. Si no existe, se habilita un botón "Buscar RUC" que consulta la API y muestra los datos para confirmar la creación rápida del proveedor

Este flujo es análogo al de ventas con auto-creación de cliente (ver documentación de RENIEC).

---

## 14. Componente Frontend: sunat-search.js

### 14.1 Especificación

**Archivo**: `resources/js/admin/sunat-search.js`

**Propósito**: Componente que agrega un botón "Buscar RUC" junto al campo RUC en el formulario de creación de proveedores. Consulta el endpoint de SUNAT y auto-completa los campos del formulario.

### 14.2 Responsabilidades

1. Validar que el input tenga exactamente 11 dígitos numéricos
2. Habilitar/deshabilitar el botón según la validación
3. Realizar la llamada AJAX a `POST /admin/sunat/consultar`
4. Si `exists: true` → cargar datos del proveedor existente y notificar al usuario
5. Si `exists: false` → auto-completar todos los campos del formulario
6. Manejar errores mostrando alert/toast

### 14.3 Diseño de la UI

```
┌──────────────────────────────────────────────────────┐
│  RUC *                                                  │
│  ┌──────────────────────────┐  ┌──────────────────┐    │
│  │ 20601030013              │  │ 🔍 Buscar RUC   │    │
│  └──────────────────────────┘  └──────────────────┘    │
│                                                          │
│  Razón Social                                            │
│  ┌──────────────────────────────────────────────────┐   │
│  │ REXTIE S.A.C.                                    │   │
│  └──────────────────────────────────────────────────┘   │
│                                                          │
│  Condición     Estado                                    │
│  ┌──────────┐  ┌──────────┐                              │
│  │ HABIDO   │  │ ACTIVO   │                              │
│  └──────────┘  └──────────┘                              │
│                                                          │
│  Dirección                                               │
│  ┌──────────────────────────────────────────────────┐   │
│  │ AV. JOSE GALVEZ BARRENECHEA NRO 566 INT. 101... │   │
│  └──────────────────────────────────────────────────┘   │
│                                                          │
│  Ubigeo    Distrito         Provincia   Departamento     │
│  ┌──────┐  ┌────────────┐  ┌────────┐  ┌──────────┐   │
│  │150131│  │ SAN ISIDRO │  │ LIMA   │  │ LIMA     │   │
│  └──────┘  └────────────┘  └────────┘  └──────────┘   │
└──────────────────────────────────────────────────────┘
```

---

## 15. Archivos a Crear

| # | Ruta | Propósito | Líneas estimadas |
|---|------|-----------|-----------------|
| 1 | `app/Services/SunatService.php` | Servicio de consulta a API externa | ~120 |
| 2 | `app/Exceptions/Sunat/SunatNotFoundException.php` | Excepción RUC no encontrado | ~15 |
| 3 | `app/Exceptions/Sunat/SunatServiceException.php` | Excepción error de servicio | ~15 |
| 4 | `app/Http/Controllers/Admin/SunatController.php` | Endpoint admin de consulta RUC | ~90 |
| 5 | `app/Http/Controllers/Api/SunatController.php` | Endpoint público de consulta RUC | ~80 |
| 6 | `database/migrations/YYYY_MM_DD_HHMMSS_add_direccion_ubigeo_to_proveedor_table.php` | Migración nuevas columnas | ~35 |
| 7 | `resources/js/admin/sunat-search.js` | Componente JS de búsqueda RUC | ~60 |
| 8 | `tests/Unit/SunatServiceTest.php` | Tests unitarios del servicio | ~120 |
| 9 | `tests/Feature/SunatConsultaTest.php` | Tests del endpoint admin | ~100 |
| 10 | `tests/Feature/SunatPublicoTest.php` | Tests del endpoint público con rate limit | ~80 |

**Total nuevos archivos: 10**

---

## 16. Archivos a Modificar

| # | Ruta | Cambio |
|---|------|--------|
| 1 | `.env` | Agregar `SUNAT_API_TOKEN` y `SUNAT_API_URL` |
| 2 | `.env.example` | Agregar `SUNAT_API_TOKEN` y `SUNAT_API_URL` (documentación) |
| 3 | `config/services.php` | Agregar entrada `sunat` con token y url |
| 4 | `routes/admin.php` | Agregar `POST /sunat/consultar` |
| 5 | `routes/api.php` | Agregar `POST /sunat/consultar` con throttle |
| 6 | `routes/web.php` | *(ninguno)* |
| 7 | `app/Models/Proveedor.php` | Agregar nuevos campos a `$fillable` |
| 8 | `resources/views/admin/proveedores/create.blade.php` | Agregar botón "Buscar RUC" + nuevos campos |
| 9 | `resources/views/admin/proveedores/edit.blade.php` | Agregar nuevos campos (dirección, ubigeo, etc.) |

**Total archivos a modificar: 9**

---

## 17. Variables de Entorno

### 17.1 `.env`

```ini
SUNAT_API_TOKEN=sk_16636.s4Vhmw5pPXXpSDY3e52TDStmr43qVYsW
SUNAT_API_URL=https://api.decolecta.com/v1/sunat/ruc
```

> **Nota**: `SUNAT_API_TOKEN` puede omitirse si se desea reutilizar el token de RENIEC. En `config/services.php` se configurará con fallback a `RENIEC_API_TOKEN`.

### 17.2 `.env.example`

```ini
SUNAT_API_TOKEN=
SUNAT_API_URL=https://api.decolecta.com/v1/sunat/ruc
```

---

## 18. Configuración services.php

### 18.1 Código a agregar

```php
'sunat' => [
    'token' => env('SUNAT_API_TOKEN', env('RENIEC_API_TOKEN')),
    'url' => env('SUNAT_API_URL', 'https://api.decolecta.com/v1/sunat/ruc'),
],
```

### 18.2 Ubicación en el archivo

Agregar después de la entrada de `reniec`:

```php
'reniec' => [
    'token' => env('RENIEC_API_TOKEN'),
    'url' => env('RENIEC_API_URL', 'https://api.decolecta.com/v1/reniec/dni'),
],

'sunat' => [
    'token' => env('SUNAT_API_TOKEN', env('RENIEC_API_TOKEN')),
    'url' => env('SUNAT_API_URL', 'https://api.decolecta.com/v1/sunat/ruc'),
],
```

---

## 19. Manejo de Errores

### 19.1 Matriz de errores

| Escenario | Origen | HTTP Status | Mensaje usuario | Acción sistema |
|-----------|--------|-------------|-----------------|----------------|
| RUC inválido (no 11 dígitos) | Validación frontend | — (no request) | Botón deshabilitado | N/A |
| RUC no existe en SUNAT | API (422) | 422 | "El RUC X no fue encontrado en los registros SUNAT." | Log warning |
| Token API inválido/expirado | API (401) | 503 | "Error de autenticación con el servicio SUNAT. Contacte al administrador." | Log error, notificar admin |
| API no disponible | API (5xx) | 503 | "Error al consultar SUNAT. Verifique el RUC o intente más tarde." | Log error |
| Timeout de conexión | HTTP Client (5s) | 503 | "El servicio SUNAT no respondió a tiempo. Intente nuevamente." | Log error |
| Rate limit excedido (público) | Middleware throttle | 429 | "Demasiadas consultas. Intente nuevamente en un minuto." | N/A (Laravel automático) |

### 19.2 Estrategia de logs

| Nivel | Cuándo | Información |
|-------|--------|-------------|
| `info` | Consulta exitosa | RUC (enmascarado: primeras 3 + ***** + últimas 3), origen (BD/API) |
| `warning` | RUC no encontrado | RUC completo, respuesta API |
| `error` | API caída, timeout, 401 | RUC enmascarado, mensaje de error, stack trace |
| `notice` | Truncamiento de datos | Campo truncado, valor original, valor truncado |

---

## 20. Consideraciones de Seguridad

| # | Aspecto | Medida |
|---|---------|--------|
| **S-01** | Token API expuesto | Solo en `.env`, nunca en frontend ni en repositorio. `.env` en `.gitignore` (ya incluido). |
| **S-02** | Endpoint público abusado | Rate limiting con `throttle:60,1` en middleware. |
| **S-03** | Inyección de RUC | Validación con regex `/^\d{11}$/` tanto en frontend como en backend. |
| **S-04** | CSRF | Todas las rutas POST usan middleware `web` que incluye verificación CSRF (Laravel automático). |
| **S-05** | Autenticación endpoint admin | Middleware `auth` + `permission:proveedores` (Spatie). |
| **S-06** | Logging de datos sensibles | RUC enmascarado (primeros 3 + ***** + últimos 3) en logs. |

---

## 21. Consideraciones de Performance y Rate Limiting

### 21.1 Caché de consultas

El servicio `SunatService` implementa caché con `Cache::remember()` con TTL de 5 minutos. Esto significa:

- Si se consulta el mismo RUC dentro de 5 minutos, se retorna el resultado cacheado (sin llamar a la API).
- La clave de caché es `sunat:{ruc}`.
- Solo se cachean resultados exitosos. Errores no se cachean.

```php
Cache::remember("sunat:{$ruc}", self::CACHE_TTL * 60, function () use ($ruc) {
    return $this->consultarApi($ruc);
});
```

### 21.2 Rate limiting público

```php
// routes/api.php
Route::post('/sunat/consultar', [SunatController::class, 'checkRuc'])
    ->middleware('throttle:60,1');
```

- 60 requests por minuto por dirección IP.
- Si se excede, Laravel retorna automáticamente 429 Too Many Requests.

### 21.3 Timeout y reintentos

```php
Http::withToken(config('services.sunat.token'))
    ->timeout(5)          // 5 segundos de timeout
    ->retry(2, 100)       // 2 reintentos con 100ms de espera entre cada uno
    ->get(...);
```

- Tiempo total máximo: ~5.2s (5s timeout + 2 × 100ms entre reintentos).
- Los reintentos solo ocurren en errores de conexión, no en respuestas HTTP 422/401/500.

### 21.4 Impacto en BD

- La consulta `Proveedor::where('ruc', $ruc)->first()` está cubierta por el índice en la columna `ruc`.

---

## 22. Pruebas Unitarias y de Integración

### 22.1 Test Unitario: SunatServiceTest

**Archivo**: `tests/Unit/SunatServiceTest.php`

| Test | Descripción | Assert |
|------|-------------|--------|
| `test_consulta_exitosa_retorna_datos_mapeados()` | Mock HTTP 200 con JSON válido | Verifica que retorna array con todos los campos mapeados |
| `test_ruc_no_encontrado_lanza_sunat_not_found()` | Mock HTTP 422 | Espera `SunatNotFoundException` |
| `test_error_servidor_lanza_sunat_service_exception()` | Mock HTTP 500 | Espera `SunatServiceException` |
| `test_timeout_lanza_sunat_service_exception()` | Mock timeout HTTP | Espera `SunatServiceException` |
| `test_truncamiento_de_campos_largos()` | Mock con razón social de >150 caracteres | Verifica que se trunca a 150 |
| `test_cache_funciona_correctamente()` | Mock HTTP llamado una vez, consulta 2 veces el mismo RUC | Segunda consulta no llama HTTP |
| `test_ruc_invalido_lanza_exception()` | RUC "123" | Espera `SunatServiceException` |
| `test_estado_activo_se_mapea_a_1()` | RUC con estado "ACTIVO" | `estado` es `1` |
| `test_estado_no_activo_se_mapea_a_0()` | RUC con estado "BAJA DEFINITIVA" | `estado` es `0` |

### 22.2 Test Feature: SunatConsultaTest

**Archivo**: `tests/Feature/SunatConsultaTest.php`

| Test | Descripción | Setup |
|------|-------------|-------|
| `test_admin_puede_consultar_ruc_no_existente()` | Autenticado, RUC nuevo, verifica response `exists: false` | Crear usuario con permiso `proveedores`, mockear SunatService |
| `test_admin_consulta_ruc_existente_retorna_proveedor()` | Crear proveedor, consultar, verifica `exists: true` + datos | Crear Proveedor con RUC específico |
| `test_ruc_invalido_retorna_error_422()` | RUC "123" | Espera 422 con error de validación |
| `test_sin_autenticacion_retorna_302()` | Sin sesión | Espera redirect a login |

### 22.3 Test Feature: SunatPublicoTest

**Archivo**: `tests/Feature/SunatPublicoTest.php`

| Test | Descripción | Assert |
|------|-------------|--------|
| `test_publico_puede_consultar_ruc()` | Sin autenticación, RUC válido | 200, `success: true` |
| `test_publico_rate_limit_excedido()` | 61 requests en 1 minuto | 429 Too Many Requests |

### 22.4 Comando para ejecutar tests

```bash
composer test
# o directamente:
php artisan test --filter=Sunat
```

---

## 23. Glosario

| Término | Definición |
|---------|-----------|
| **RUC** | Registro Único de Contribuyentes. Número único de 11 dígitos asignado por SUNAT a cada persona jurídica o natural en Perú. |
| **SUNAT** | Superintendencia Nacional de Aduanas y de Administración Tributaria. Entidad peruana que mantiene el registro de contribuyentes. |
| **dacoleta.api** | Proveedor externo de API que provee acceso a datos de SUNAT mediante token de autenticación. |
| **Ubigeo** | Código de ubicación geográfica de 6 dígitos utilizado por el INEI para identificar distritos, provincias y departamentos. |
| **Razón social** | Nombre oficial de una persona jurídica registrado en SUNAT. |
| **Condición** | Estado tributario del contribuyente: "HABIDO", "NO HABIDO", etc. |
| **Agente de retención** | Contribuyente autorizado por SUNAT para retener impuestos a sus proveedores. |
| **Auto-completado** | Mecanismo por el cual el frontend llena automáticamente los campos del formulario con datos obtenidos de la API. |
| **Rate limiting** | Técnica de control de tráfico que limita el número de requests que un cliente puede realizar en un período de tiempo. |
| **Throttle** | Middleware de Laravel que implementa rate limiting. |
| **Bearer Token** | Token JWT utilizado para autenticación en la API de dacoleta. Se envía en el header `Authorization: Bearer <token>`. |
| **Cache** | Almacenamiento temporal de datos para evitar llamadas repetidas a la API externa. Implementado con `Cache::remember()` de Laravel. |

---

> **Documento creado por:** Equipo de Desarrollo  
> **Última revisión:** 2026-06-21  
> **Próxima acción:** Implementación de archivos según el plan detallado en las secciones 15 y 16.
