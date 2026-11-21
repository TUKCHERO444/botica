# Integración API RENIEC — dacoleta.api

> ⚠️ **Documento histórico parcial (2026-11-20).** El proveedor vigente para la
> consulta DNI es **json.pe** (`POST https://api.json.pe/api/dni`) mediante
> `JsonPeClient`. La especificación actual está en
> [`integracion-jsonpe.md`](integracion-jsonpe.md). Las secciones de este
> documento que describen decolecta (endpoint, request/response, configuración y
> variables de entorno) ya **no** aplican. El flujo funcional, controladores,
> reglas de negocio y frontend siguen siendo válidos.

> **Versión:** 1.0.0  
> **Fecha:** 2026-06-21  
> **Módulos afectados:** Clientes, Ventas, Atenciones, Registro Público  
> **Proveedor:** [dacoleta.api](https://api.decolecta.com) (reemplazado por json.pe para DNI)  
> **Endpoints involucrados:** `GET https://api.decolecta.com/v1/reniec/dni?numero={dni}`  
> **Estado:** Reemplazado para DNI; ver `integracion-jsonpe.md`

---

## Índice

1. [Descripción General](#1-descripción-general)
2. [Objetivos del Negocio](#2-objetivos-del-negocio)
3. [Arquitectura de la Solución](#3-arquitectura-de-la-solución)
4. [Proveedor Externo: dacoleta.api](#4-proveedor-externo-dacoletaapi)
5. [Entidades Involucradas](#5-entidades-involucradas)
6. [Mapeo de Datos API → Tabla Cliente](#6-mapeo-de-datos-api--tabla-cliente)
7. [Reglas de Negocio](#7-reglas-de-negocio)
8. [Casos de Uso Detallados](#8-casos-de-uso-detallados)
9. [Diseño Técnico](#9-diseño-técnico)
10. [Endpoint: Consulta DNI (Admin)](#10-endpoint-consulta-dni-admin)
11. [Endpoint: Consulta DNI (Público)](#11-endpoint-consulta-dni-público)
12. [Flujo: Creación de Venta con Auto-Creación de Cliente](#12-flujo-creación-de-venta-con-auto-creación-de-cliente)
13. [Flujo: Creación de Atención con Auto-Creación de Cliente](#13-flujo-creación-de-atención-con-auto-creación-de-cliente)
14. [Flujo: Creación de Cliente (Admin) con Auto-Completado](#14-flujo-creación-de-cliente-admin-con-auto-completado)
15. [Flujo: Registro Público con Auto-Completado](#15-flujo-registro-público-con-auto-completado)
16. [Componente Frontend: reniec-search.js](#16-componente-frontend-reniec-searchjs)
17. [Modificaciones al Componente client-search.js](#17-modificaciones-al-componente-client-searchjs)
18. [Archivos a Crear](#18-archivos-a-crear)
19. [Archivos a Modificar](#19-archivos-a-modificar)
20. [Variables de Entorno](#20-variables-de-entorno)
21. [Configuración services.php](#21-configuración-servicesphp)
22. [Manejo de Errores](#22-manejo-de-errores)
23. [Consideraciones de Seguridad](#23-consideraciones-de-seguridad)
24. [Consideraciones de Performance y Rate Limiting](#24-consideraciones-de-performance-y-rate-limiting)
25. [Pruebas Unitarias y de Integración](#25-pruebas-unitarias-y-de-integración)
26. [Glosario](#26-glosario)

---

## 1. Descripción General

El sistema **Botica Sistema** requiere integrar la consulta de datos personales a través del DNI utilizando el proveedor externo **dacoleta.api**. Esta funcionalidad es crítica para el flujo de negocio porque:

- Permite la **carga automática** de datos del cliente (nombres, apellidos) a partir de su número de DNI.
- Agiliza el registro de ventas y atenciones al evitar el tipeo manual de datos del cliente.
- Garantiza la **integridad de datos** al atomicidad de creación de cliente + venta/atención en una sola transacción de base de datos.
- Reduce errores ortográficos y de digitación en los datos maestros de clientes.

La integración abarca cuatro puntos del sistema:

| Módulo | Tipo de usuario | Funcionalidad |
|--------|----------------|---------------|
| **Ventas** (admin) | Administrador | Lupa 🔍 en barra de búsqueda de cliente; auto-creación del cliente al guardar venta |
| **Atenciones** (admin) | Administrador | Lupa 🔍 en barra de búsqueda de cliente; auto-creación del cliente al guardar atención |
| **Clientes** (admin) | Administrador | Botón "Buscar DNI" en formulario de creación que auto-completa datos |
| **Registro** (público) | Cliente/Público | Botón "Buscar DNI" en formulario de registro que auto-completa datos |

---

## 2. Objetivos del Negocio

| ID | Objetivo | Métrica de éxito |
|----|----------|------------------|
| OBJ-01 | Reducir tiempo de registro de ventas con clientes nuevos | Reducción del 40% en tiempo promedio de creación de venta |
| OBJ-02 | Eliminar errores de tipeo en datos de clientes | 0% de reclamos por nombres/apellidos mal escritos |
| OBJ-03 | Unificar datos maestros de clientes evitando duplicados | Sin registros duplicados de cliente con mismo DNI |
| OBJ-04 | Garantizar atomicidad en creación de cliente + transacción | 100% de ventas con cliente nuevo tienen cliente creado en misma transacción |
| OBJ-05 | Experiencia de registro rápida para usuarios públicos | Reducción de abandono en formulario de registro |

---

## 3. Arquitectura de la Solución

```
┌─────────────────────────────────────────────────────────────────┐
│                        FRONTEND (Blade + JS)                     │
│                                                                   │
│  ┌──────────────────────┐  ┌──────────────────────────────────┐  │
│  │  client-search.js     │  │  reniec-search.js  (NUEVO)      │  │
│  │  - Búsqueda local     │  │  - Botón 🔍 lupa                │  │
│  │  - Selector cliente   │  │  - Validación 8 dígitos          │  │
│  │  - Modal genérico     │  │  - AJAX a /admin/reniec/consultar│  │
│  └──────────┬───────────┘  │  - Callback a client-search       │  │
│             │              └──────────────┬───────────────────┘  │
│             └──────────────┬──────────────┘                      │
│                            │ Axios                               │
└────────────────────────────┼─────────────────────────────────────┘
                             │
                    HTTP POST │ JSON
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                     BACKEND (Laravel 12)                         │
│                                                                   │
│  ┌────────────────────┐   ┌────────────────────────────────┐     │
│  │ ReniecController    │   │ Api\ReniecController (NUEVO) │     │
│  │ /admin/reniec/      │   │ /api/reniec/consultar        │     │
│  │  consultar          │   │ Con middleware throttle:60   │     │
│  │  (con auth admin)   │   │ (público)                    │     │
│  └─────────┬───────────┘   └────────────┬─────────────────┘     │
│            │                             │                        │
│            └──────────────┬──────────────┘                        │
│                           │                                       │
│                    ┌──────▼───────┐                                │
│                    │ ReniecService │  (NUEVO)                     │
│                    │ - consultar() │                               │
│                    │ - mapear()    │                               │
│                    └──────┬───────┘                                │
│                           │ Http::withToken()                      │
│                           │ timeout(5) · retry(2,100)              │
└───────────────────────────┼───────────────────────────────────────┘
                            │
                    HTTP GET │ ?numero=XXXXXXXX
                            │ Authorization: Bearer <token>
                            ▼
              ┌─────────────────────────────┐
              │  dacoleta.api               │
              │  https://api.decolecta.com  │
              │  /v1/reniec/dni             │
              └─────────────────────────────┘
```

### Flujo de datos

1. **Frontend**: Usuario ingresa DNI (8 dígitos) y presiona 🔍 (lupa)
2. **Frontend**: Valida que sean exactamente 8 dígitos numéricos
3. **Frontend**: `axios.post('/admin/reniec/consultar', { dni })`
4. **ReniecController**: Valida el DNI (required, size:8, regex:/^\d{8}$/)
5. **ReniecController**: Busca `Cliente::where('dni', $dni)->first()`
   - **Existe** → retorna `{ success: true, exists: true, cliente: {...} }`
   - **No existe** → delega a `ReniecService::consultar($dni)`
6. **ReniecService**: `Http::withToken(token)->timeout(5)->get(url, ['numero' => $dni])`
7. **ReniecService**: Mapea respuesta JSON al formato interno
8. **ReniecController**: Retorna `{ success: true, exists: false, data: {...} }`
9. **Frontend**: Muestra los datos obtenidos y prepara `cliente_data` oculto
10. **Usuario**: Completa venta/atención y presiona "Guardar"
11. **VentaController/AtencionController**: Dentro de `DB::transaction`:
    - Si viene `cliente_data` → `Cliente::create(...)` (o `firstOrCreate`)
    - Crea la venta/atención referenciando al cliente
    - Consume stock, registra caja movimiento

---

## 4. Proveedor Externo: dacoleta.api

### 4.1 Endpoint

| Propiedad | Valor |
|-----------|-------|
| **Método** | `GET` |
| **URL base** | `https://api.decolecta.com/v1/reniec/dni` |
| **Parámetro query** | `numero` (string, 8 dígitos) |
| **Autenticación** | Bearer Token (JWT) |

### 4.2 Headers

| Header | Valor |
|--------|-------|
| `Content-Type` | `application/json` |
| `Authorization` | `Bearer <token>` |

### 4.3 Request

```
GET https://api.decolecta.com/v1/reniec/dni?numero=46027897
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
Content-Type: application/json
```

### 4.4 Response 200 (Success)

```json
{
    "first_name": "ROXANA KARINA",
    "first_last_name": "DELGADO",
    "second_last_name": "HUAMANI",
    "full_name": "DELGADO HUAMANI ROXANA KARINA",
    "document_number": "46027897"
}
```

| Campo | Tipo | Descripción | Longitud máx. |
|-------|------|-------------|---------------|
| `first_name` | string | Nombres completos | Variable |
| `first_last_name` | string | Apellido paterno | Variable |
| `second_last_name` | string | Apellido materno | Variable |
| `full_name` | string | Nombre completo formateado (Apellidos, Nombres) | Variable |
| `document_number` | string | Número de DNI (8 dígitos) | 8 |

### 4.5 Response 400 (Error)

```json
{
    "error": "Invalid request"
}
```

Posibles causas:
- DNI con formato incorrecto (no 8 dígitos)
- DNI no existe en el registro RENIEC
- Token inválido o expirado

### 4.6 Response 401 (No autorizado)

```json
{
    "error": "Unauthorized"
}
```

El token ha expirado o es inválido. Se debe renovar con el proveedor.

### 4.7 SLA esperado

| Métrica | Valor |
|---------|-------|
| Tiempo de respuesta esperado | < 2s |
| Timeout configurado en cliente | 5s |
| Reintentos automáticos | 2 intentos con 100ms de espera |
| Disponibilidad esperada | 99.5% |

---

## 5. Entidades Involucradas

### 5.1 Tabla `cliente` (existente)

| Columna | Tipo | Restricciones | Uso en RENIEC |
|---------|------|--------------|---------------|
| `id_cliente` | int (PK, auto_increment) | NOT NULL | Sólo referencia FK |
| `dni` | char(8) | NULLABLE, UNIQUE | **Se mapea desde `document_number`** |
| `nombre` | varchar(50) | NOT NULL | **Se mapea desde `first_name`** |
| `apellido_paterno` | varchar(25) | NULLABLE | **Se mapea desde `first_last_name`** |
| `apellido_materno` | varchar(25) | NULLABLE | **Se mapea desde `second_last_name`** |
| `email` | varchar(255) | NULLABLE, UNIQUE | No aplica (dato manual) |
| `telefono` | varchar(9) | NULLABLE | No aplica (dato manual) |
| `password` | varchar(255) | NULLABLE | No aplica |
| `registro_web` | boolean, default false | NOT NULL | Se asigna `false` en auto-creación |
| `ultimo_login` | timestamp | NULLABLE | No aplica |
| `email_verified_at` | timestamp | NULLABLE | No aplica |
| `remember_token` | varchar(100) | NULLABLE | No aplica |
| `created_at` | timestamp | NULLABLE | Generado automáticamente |
| `updated_at` | timestamp | NULLABLE | Generado automáticamente |

### 5.2 Tabla `venta` (existente)

| Columna | Tipo | Uso en RENIEC |
|---------|------|--------------|
| `id_venta` | int (PK) | No directo |
| `correlativo` | varchar(15) | No directo |
| `total` | decimal(10,2) | No directo |
| `cliente_id_cliente` | int (FK → `cliente.id_cliente`) | **Referencia al cliente creado/consultado** |
| `user_id` | bigint (FK → `users.id`) | No directo |

### 5.3 Tabla `atencion` (existente)

| Columna | Tipo | Uso en RENIEC |
|---------|------|--------------|
| `id_atencion` | int (PK) | No directo |
| `observacio` | varchar(50) | No directo |
| `total` | decimal(10,2) | No directo |
| `cliente_id_cliente` | int (FK → `cliente.id_cliente`) | **Referencia al cliente creado/consultado** |
| `user_id` | bigint (FK → `users.id`) | No directo |

### 5.4 Tablas secundarias afectadas (vía FK)

| Tabla | Relación |
|-------|----------|
| `detalle_venta` | Productos asociados a la venta |
| `detalle_venta_lote` | Consumo de lotes por la venta |
| `atencion_producto` | Productos asociados a la atención |
| `atencion_servicio` | Servicios asociados a la atención |
| `atencion_producto_lote` | Consumo de lotes por la atención |
| `caja_movimiento` | Movimiento de ingreso generado |
| `auditoria` | Registro de auditoría de la operación |

---

## 6. Mapeo de Datos API → Tabla Cliente

### 6.1 Mapeo directo

| Respuesta API (JSON) | Campo `cliente` | Tipo destino | Longitud | Transformación |
|---------------------|-----------------|-------------|----------|---------------|
| `document_number` | `dni` | char(8) | 8 | Sin cambio |
| `first_name` | `nombre` | varchar(50) | 50 | Sin cambio |
| `first_last_name` | `apellido_paterno` | varchar(25) | 25 | Sin cambio |
| `second_last_name` | `apellido_materno` | varchar(25) | 25 | Sin cambio |
| `full_name` | *(no se almacena)* | — | — | Se usa solo para visualización |

### 6.2 Reglas de mapeo

1. **Truncamiento**: Si `first_name` excede 50 caracteres, se trunca con `mb_substr($valor, 0, 50)`. Igual para apellidos (25 caracteres). Se registra warning en log.
2. **Normalización**: Los valores de la API vienen en mayúsculas RENIEC. Se almacenan tal cual (mayúsculas sostenidas).
3. **Unicidad**: El DNI debe ser único en la tabla `cliente`. Si ya existe un cliente con ese DNI, se usa el existente (no se duplica).
4. **Campos nulos**: La API siempre entrega los 4 campos. Si alguno viniera vacío, se almacena como string vacío.

### 6.3 Ejemplo de mapeo

```php
// Respuesta API
[
    'first_name' => 'ROXANA KARINA',
    'first_last_name' => 'DELGADO',
    'second_last_name' => 'HUAMANI',
    'full_name' => 'DELGADO HUAMANI ROXANA KARINA',
    'document_number' => '46027897',
]

// Array mapeado para Cliente::create()
[
    'dni' => '46027897',
    'nombre' => 'ROXANA KARINA',
    'apellido_paterno' => 'DELGADO',
    'apellido_materno' => 'HUAMANI',
    'registro_web' => false,
]
```

---

## 7. Reglas de Negocio

| ID | Regla | Clasificación | Incumplimiento |
|----|-------|---------------|----------------|
| **RN-01** | El campo DNI debe tener exactamente 8 dígitos numéricos para activar la consulta | Validación frontend | El botón 🔍 permanece deshabilitado |
| **RN-02** | Antes de llamar a la API externa, se debe buscar el DNI en la tabla `cliente` local | Validación backend | Llamada API innecesaria, costo/timeout |
| **RN-03** | Si el cliente existe en BD local, NO se llama a la API externa | Optimización backend | Gasto innecesario de cuota API |
| **RN-04** | La creación de `Cliente` + `Venta`/`Atencion` debe ocurrir en una sola transacción DB | Integridad referencial | Datos huérfanos o inconsistentes |
| **RN-05** | Los campos `cliente_data` en el request de venta/atención deben contener: `dni`, `nombre`, `apellido_paterno`, `apellido_materno` | Validación request | Error de validación 422 |
| **RN-06** | Si la API externa falla (timeout, error 5xx, 401), se debe mostrar un error claro al usuario, permitiendo el ingreso manual | UX / Resíliencia | Bloqueo del flujo de venta |
| **RN-07** | Clientes creados vía auto-creación en ventas/atenciones deben tener `registro_web = false` | Segregación de datos | Clientes marcados incorrectamente como registrados en web |
| **RN-08** | El token de API nunca debe estar expuesto en el frontend ni en código fuente (solo en `.env`) | Seguridad | Exposición de credenciales |
| **RN-09** | La consulta DNI pública debe tener rate limiting máximo de 60 requests/min por IP | Seguridad / Anti-abuso | Abuso del endpoint gratuito |
| **RN-10** | Si `cliente_id_cliente` y `cliente_data` se envían simultáneamente en la misma request, prevalece `cliente_id_cliente` y se ignora `cliente_data` | Determinismo | Comportamiento ambiguo |

---

## 8. Casos de Uso Detallados

### 8.1 UC-01: Admin consulta DNI y registra venta con cliente nuevo

**Actor primario**: Administrador (con permiso `ventas`)

**Precondiciones**:
- Caja abierta
- Productos con stock disponible

**Flujo principal**:
1. Admin abre modal "Nueva Venta" (`/admin/ventas/create`)
2. Ingresa "46027897" en la barra de búsqueda de cliente
3. Presiona botón 🔍 (lupa)
4. Sistema valida 8 dígitos, envía `POST /admin/reniec/consultar`
5. Backend no encuentra cliente en BD, llama a API dacoleta
6. API retorna datos de ROXANA KARINA DELGADO HUAMANI
7. Frontend muestra badge con "ROXANA KARINA DELGADO HUAMANI — 46027897"
8. Frontend crea inputs ocultos `cliente_data[dni]`, `cliente_data[nombre]`, etc.
9. Admin agrega productos y presiona "Guardar"
10. Backend recibe `cliente_data`, dentro de `DB::transaction`:
    - `Cliente::create(['dni'=>'46027897', 'nombre'=>'ROXANA KARINA', 'apellido_paterno'=>'DELGADO', 'apellido_materno'=>'HUAMANI', 'registro_web'=>false])`
    - `Venta::create(['cliente_id_cliente'=>$cliente->id_cliente, ...])`
    - Adjunta productos, consume stock, registra caja movimiento
11. Se retorna éxito con venta y cliente creados

**Postcondiciones**:
- Cliente creado en BD con datos de RENIEC
- Venta registrada vinculada al nuevo cliente
- Stock actualizado
- Caja actualizada

**Flujos alternativos**:
- **5a**: DNI ya existe local → se selecciona cliente existente directamente, no se llama API
- **6a**: API retorna error (400/500/timeout) → toast "No se pudo consultar el DNI. Puede ingresar los datos manualmente."
- **10a**: DNI duplicado (race condition) → `firstOrCreate` previene duplicado

---

### 8.2 UC-02: Admin consulta DNI y registra atención con cliente nuevo

**Actor primario**: Administrador (con permiso `atenciones`)

**Flujo principal**: Similar a UC-01 pero en modal "Nueva Atención"
- Mismo flujo de consulta DNI
- En `store()`: `Cliente::create()` + `Atencion::create()` + attach productos/servicios + stock + caja

**Postcondiciones**: Ídem UC-01 pero con atención en lugar de venta.

---

### 8.3 UC-03: Admin crea cliente con auto-completado desde DNI

**Actor primario**: Administrador (con permiso `clientes`)

**Precondiciones**: Modal "Nuevo Cliente" abierto

**Flujo principal**:
1. Admin ingresa DNI en campo correspondiente
2. Presiona botón "Buscar DNI" junto al campo
3. Sistema valida 8 dígitos, envía `POST /admin/reniec/consultar`
4. API retorna datos → frontend auto-completa:
   - Campo `nombre` ← `first_name`
   - Campo `apellido_paterno` ← `first_last_name`
   - Campo `apellido_materno` ← `second_last_name`
5. Admin completa email y teléfono (opcionales)
6. Presiona "Guardar"
7. Cliente creado con datos completos

**Flujo alternativo**:
- **4a**: DNI ya existe en BD local → se muestran datos actuales y se informa "El cliente ya está registrado". Se permite editar y actualizar.

---

### 8.4 UC-04: Usuario público se registra con auto-completado desde DNI

**Actor primario**: Usuario público (no autenticado)

**Precondiciones**: Formulario de registro en `/registro`

**Flujo principal**:
1. Usuario ingresa DNI en campo correspondiente
2. Presiona botón "Buscar DNI" junto al campo
3. Sistema envía `POST /api/reniec/consultar` (endpoint público con throttle)
4. API retorna datos → frontend auto-completa:
   - Campo `nombre` ← `first_name`
   - Campo `apellido_paterno` ← `first_last_name`
   - Campo `apellido_materno` ← `second_last_name`
5. Usuario completa email, teléfono, contraseña
6. Presiona "Crear cuenta"
7. Cliente creado con `registro_web = true`

**Flujo alternativo**:
- **3a**: Rate limit excedido (60 requests/min) → respuesta 429 "Demasiadas consultas. Intente en un minuto."

---

### 8.5 UC-05: Admin consulta DNI ya existente en venta

**Actor primario**: Administrador

**Precondiciones**: Cliente "ROXANA KARINA DELGADO HUAMANI" con DNI "46027897" ya existe en BD local

**Flujo principal**:
1. Admin ingresa "46027897" en barra de búsqueda
2. Presiona 🔍
3. `POST /admin/reniec/consultar`
4. Backend: `Cliente::where('dni', '46027897')->first()` → existe
5. Backend retorna inmediatamente: `{ exists: true, cliente: { id_cliente: 5, ... } }` (sin llamar API)
6. Frontend selecciona el cliente en el componente client-search
7. Admin guarda venta → flujo normal sin `cliente_data`

**Regla**: No se llama a la API externa si el cliente ya existe localmente.

---

## 9. Diseño Técnico

### 9.1 Servicio: `ReniecService`

**Namespace**: `App\Services`
**Archivo**: `app/Services/ReniecService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Exceptions\Reniec\ReniecNotFoundException;
use App\Exceptions\Reniec\ReniecServiceException;

class ReniecService
{
    /**
     * Tiempo de caché para consultas exitosas (minutos).
     */
    private const int CACHE_TTL = 5;

    /**
     * Timeout de la llamada HTTP (segundos).
     */
    private const int HTTP_TIMEOUT = 5;

    /**
     * Consulta los datos de una persona por su número de DNI.
     *
     * @param string $dni Número de DNI (8 dígitos)
     * @return array{nombre: string, apellido_paterno: string, apellido_materno: string, dni: string, nombre_completo: string}
     * @throws ReniecNotFoundException Cuando el DNI no existe en RENIEC
     * @throws ReniecServiceException Cuando hay un error de conexión o servicio
     */
    public function consultar(string $dni): array
    {
        $this->validarFormato($dni);

        return Cache::remember("reniec:{$dni}", self::CACHE_TTL * 60, function () use ($dni) {
            return $this->consultarApi($dni);
        });
    }

    /**
     * Valida que el DNI tenga el formato correcto (8 dígitos).
     */
    private function validarFormato(string $dni): void
    {
        if (!preg_match('/^\d{8}$/', $dni)) {
            throw new ReniecServiceException('El DNI debe tener 8 dígitos numéricos.');
        }
    }

    /**
     * Realiza la llamada HTTP a la API de dacoleta.
     */
    private function consultarApi(string $dni): array
    {
        $response = Http::withToken(config('services.reniec.token'))
            ->timeout(self::HTTP_TIMEOUT)
            ->retry(2, 100)
            ->get(config('services.reniec.url'), ['numero' => $dni]);

        if ($response->status() === 400) {
            throw new ReniecNotFoundException(
                "El DNI {$dni} no fue encontrado en los registros RENIEC."
            );
        }

        if ($response->status() === 401) {
            throw new ReniecServiceException(
                'Error de autenticación con el servicio RENIEC. Contacte al administrador.'
            );
        }

        if ($response->failed()) {
            throw new ReniecServiceException(
                'Error al consultar el servicio RENIEC. Intente nuevamente.'
            );
        }

        return $this->mapearRespuesta($response->json());
    }

    /**
     * Mapea la respuesta de la API al formato interno del sistema.
     */
    private function mapearRespuesta(array $data): array
    {
        return [
            'dni' => $data['document_number'],
            'nombre' => $this->truncar($data['first_name'] ?? '', 50),
            'apellido_paterno' => $this->truncar($data['first_last_name'] ?? '', 25),
            'apellido_materno' => $this->truncar($data['second_last_name'] ?? '', 25),
            'nombre_completo' => $data['full_name'] ?? '',
        ];
    }

    /**
     * Trunca un string a una longitud máxima.
     */
    private function truncar(string $valor, int $maximo): string
    {
        if (mb_strlen($valor) > $maximo) {
            logger()->warning("RENIEC: valor truncado de {$maximo} caracteres", [
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

**Archivo**: `app/Exceptions/Reniec/ReniecNotFoundException.php`
```php
<?php

namespace App\Exceptions\Reniec;

use Exception;

class ReniecNotFoundException extends Exception
{
    protected $message = 'DNI no encontrado en RENIEC.';
}
```

**Archivo**: `app/Exceptions/Reniec/ReniecServiceException.php`
```php
<?php

namespace App\Exceptions\Reniec;

use Exception;

class ReniecServiceException extends Exception
{
    protected $message = 'Error al consultar el servicio RENIEC.';
}
```

### 9.3 Controlador Admin: `ReniecController`

**Namespace**: `App\Http\Controllers\Admin`
**Archivo**: `app/Http/Controllers/Admin/ReniecController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Reniec\ReniecNotFoundException;
use App\Exceptions\Reniec\ReniecServiceException;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Services\ReniecService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReniecController extends Controller
{
    public function __construct(
        private readonly ReniecService $reniecService
    ) {}

    /**
     * Consulta los datos de un DNI.
     * Primero busca en BD local, si no existe consulta API externa.
     */
    public function checkDni(Request $request): JsonResponse
    {
        $request->validate([
            'dni' => 'required|string|size:8|regex:/^\d{8}$/',
        ]);

        $dni = $request->input('dni');

        // Paso 1: Buscar en BD local
        $cliente = Cliente::where('dni', $dni)->first([
            'id_cliente', 'nombre', 'apellido_paterno',
            'apellido_materno', 'dni', 'registro_web',
        ]);

        if ($cliente) {
            return response()->json([
                'success' => true,
                'exists' => true,
                'cliente' => [
                    'id_cliente' => $cliente->id_cliente,
                    'nombre' => $cliente->nombre,
                    'apellido_paterno' => $cliente->apellido_paterno,
                    'apellido_materno' => $cliente->apellido_materno,
                    'dni' => $cliente->dni,
                    'registro_web' => $cliente->registro_web,
                ],
            ]);
        }

        // Paso 2: Consultar API externa
        try {
            $data = $this->reniecService->consultar($dni);

            return response()->json([
                'success' => true,
                'exists' => false,
                'data' => [
                    'dni' => $data['dni'],
                    'nombre' => $data['nombre'],
                    'apellido_paterno' => $data['apellido_paterno'],
                    'apellido_materno' => $data['apellido_materno'],
                    'nombre_completo' => $data['nombre_completo'],
                ],
            ]);
        } catch (ReniecNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (ReniecServiceException $e) {
            logger()->error('RENIEC: Error de servicio', [
                'dni' => $dni,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar RENIEC. Verifique el DNI o intente más tarde.',
            ], 503);
        }
    }
}
```

### 9.4 Controlador Público: `Api\ReniecController`

**Namespace**: `App\Http\Controllers\Api`
**Archivo**: `app/Http/Controllers/Api/ReniecController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Reniec\ReniecNotFoundException;
use App\Exceptions\Reniec\ReniecServiceException;
use App\Http\Controllers\Controller;
use App\Services\ReniecService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReniecController extends Controller
{
    public function __construct(
        private readonly ReniecService $reniecService
    ) {}

    /**
     * Endpoint público con rate limiting para consulta de DNI.
     * Tiene las siguientes protecciones:
     * - Rate limit: 60 requests/min por IP (middleware throttle)
     * - Solo retorna datos parciales (nombre y apellidos, no datos sensibles)
     * - No expone el token de API
     */
    public function checkDni(Request $request): JsonResponse
    {
        $request->validate([
            'dni' => 'required|string|size:8|regex:/^\d{8}$/',
        ]);

        $dni = $request->input('dni');

        // El endpoint público solo consulta API, no busca en BD local
        // (porque el usuario público no debería ver datos de clientes existentes)
        try {
            $data = $this->reniecService->consultar($dni);

            return response()->json([
                'success' => true,
                'data' => [
                    'nombre' => $data['nombre'],
                    'apellido_paterno' => $data['apellido_paterno'],
                    'apellido_materno' => $data['apellido_materno'],
                    'nombre_completo' => $data['nombre_completo'],
                ],
            ]);
        } catch (ReniecNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (ReniecServiceException $e) {
            logger()->error('RENIEC (público): Error de servicio', [
                'dni' => substr($dni, 0, 2) . '****' . substr($dni, -2),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar RENIEC. Verifique el DNI o intente más tarde.',
            ], 503);
        }
    }
}
```

### 9.5 Request de Venta con `cliente_data`

**Archivo**: `app/Http/Requests/Admin/StoreVentaRequest.php`

```php
public function rules(): array
{
    return [
        // ... reglas existentes ...

        'cliente_id_cliente' => [
            'nullable',
            'integer',
            'exists:cliente,id_cliente',
            'required_without:cliente_data',
        ],
        'cliente_data' => [
            'nullable',
            'array',
            'required_without:cliente_id_cliente',
        ],
        'cliente_data.dni' => [
            'required_with:cliente_data',
            'string',
            'size:8',
            'unique:cliente,dni',
        ],
        'cliente_data.nombre' => [
            'required_with:cliente_data',
            'string',
            'max:50',
        ],
        'cliente_data.apellido_paterno' => [
            'required_with:cliente_data',
            'string',
            'max:25',
        ],
        'cliente_data.apellido_materno' => [
            'required_with:cliente_data',
            'string',
            'max:25',
        ],
    ];
}
```

### 9.6 Modificación a VentaController@store

```php
public function store(StoreVentaRequest $request)
{
    $cajaAbierta = Caja::abierta()->first();
    if (!$cajaAbierta) {
        // ... manejo de error existente ...
    }

    $data = $request->validated();

    $venta = DB::transaction(function () use ($data, $cajaAbierta) {
        // Auto-crear cliente si viene cliente_data
        if (empty($data['cliente_id_cliente']) && !empty($data['cliente_data'])) {
            $cliente = Cliente::create([
                'dni' => $data['cliente_data']['dni'],
                'nombre' => $data['cliente_data']['nombre'],
                'apellido_paterno' => $data['cliente_data']['apellido_paterno'],
                'apellido_materno' => $data['cliente_data']['apellido_materno'],
                'registro_web' => false,
            ]);

            $this->auditarCrear($cliente, 'clientes',
                "Cliente auto-creado desde consulta RENIEC (DNI: {$cliente->dni})"
            );

            $data['cliente_id_cliente'] = $cliente->id_cliente;
        }

        // ... resto del flujo existente (crear venta, productos, stock, caja) ...
    });
}
```

### 9.7 Modificación a AtencionController@store

Análogo a VentaController@store. El cambio es idéntico: dentro de `DB::transaction`, antes de crear `Atencion::create(...)`, se verifica `cliente_data` y se crea el `Cliente`.

---

## 10. Endpoint: Consulta DNI (Admin)

### 10.1 Definición

| Propiedad | Valor |
|-----------|-------|
| **Ruta** | `POST /admin/reniec/consultar` |
| **Nombre** | `admin.reniec.consultar` |
| **Controlador** | `App\Http\Controllers\Admin\ReniecController@checkDni` |
| **Middleware** | `auth`, `permission:clientes` |
| **Rate limit** | Ilimitado (solo para admins autenticados) |

### 10.2 Request

```json
{
    "dni": "46027897"
}
```

### 10.3 Response 200 — Cliente existe localmente

```json
{
    "success": true,
    "exists": true,
    "cliente": {
        "id_cliente": 5,
        "nombre": "ROXANA KARINA",
        "apellido_paterno": "DELGADO",
        "apellido_materno": "HUAMANI",
        "dni": "46027897",
        "registro_web": false
    }
}
```

### 10.4 Response 200 — Cliente nuevo (desde API)

```json
{
    "success": true,
    "exists": false,
    "data": {
        "dni": "46027897",
        "nombre": "ROXANA KARINA",
        "apellido_paterno": "DELGADO",
        "apellido_materno": "HUAMANI",
        "nombre_completo": "DELGADO HUAMANI ROXANA KARINA"
    }
}
```

### 10.5 Response 422 — DNI no encontrado

```json
{
    "success": false,
    "message": "El DNI 46027897 no fue encontrado en los registros RENIEC."
}
```

### 10.6 Response 503 — Error de servicio

```json
{
    "success": false,
    "message": "Error al consultar RENIEC. Verifique el DNI o intente más tarde."
}
```

---

## 11. Endpoint: Consulta DNI (Público)

### 11.1 Definición

| Propiedad | Valor |
|-----------|-------|
| **Ruta** | `POST /api/reniec/consultar` |
| **Controlador** | `App\Http\Controllers\Api\ReniecController@checkDni` |
| **Middleware** | `throttle:60,1` (60 requests por minuto por IP) |
| **Rate limit** | 60 requests/min por IP |

### 11.2 Request

```json
{
    "dni": "46027897"
}
```

### 11.3 Response 200

```json
{
    "success": true,
    "data": {
        "nombre": "ROXANA KARINA",
        "apellido_paterno": "DELGADO",
        "apellido_materno": "HUAMANI",
        "nombre_completo": "DELGADO HUAMANI ROXANA KARINA"
    }
}
```

> **Nota**: El endpoint público no retorna `id_cliente` ni información de clientes existentes en BD. Solo retorna datos de la API. Esto protege la privacidad de clientes registrados.

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

## 12. Flujo: Creación de Venta con Auto-Creación de Cliente

### 12.1 Request del frontend

```http
POST /admin/ventas HTTP/1.1
Content-Type: application/json

{
    "cliente_data": {
        "dni": "46027897",
        "nombre": "ROXANA KARINA",
        "apellido_paterno": "DELGADO",
        "apellido_materno": "HUAMANI"
    },
    "productos": [
        {
            "producto_id_producto": 1,
            "cantidad": 2,
            "precio": 15.50
        }
    ]
}
```

### 12.2 Lógica de `VentaController@store` (modificado)

```php
$venta = DB::transaction(function () use ($data, $cajaAbierta) {
    // 1. Auto-crear cliente si es necesario
    if (empty($data['cliente_id_cliente']) && !empty($data['cliente_data'])) {
        $cliente = Cliente::firstOrCreate(
            ['dni' => $data['cliente_data']['dni']],
            [
                'nombre' => $data['cliente_data']['nombre'],
                'apellido_paterno' => $data['cliente_data']['apellido_paterno'],
                'apellido_materno' => $data['cliente_data']['apellido_materno'],
                'registro_web' => false,
            ]
        );
        $data['cliente_id_cliente'] = $cliente->id_cliente;
    }

    // 2. Crear venta (código existente)
    $total = collect($data['productos'])->sum(fn($p) => $p['cantidad'] * $p['precio']);
    $venta = Venta::create([
        'correlativo' => 'TEMP',
        'total' => $total,
        'cliente_id_cliente' => $data['cliente_id_cliente'],
        'user_id' => auth()->id(),
    ]);
    $venta->update(['correlativo' => $this->generarCorrelativo($venta->id_venta)]);

    // 3. Productos, stock, caja (código existente)
    foreach ($data['productos'] as $p) { /* ... */ }

    return $venta;
});
```

### 12.3 Árbol de decisión

```
                    ┌─────────────────────────────┐
                    │  POST /admin/ventas          │
                    └─────────────┬───────────────┘
                                  │
                    ┌─────────────▼───────────────┐
                    │  ¿Caja abierta?              │
                    └───┬───────┬──────────────────┘
                   Sí   │       │   No
                        │       └──→ Error 422: "Debe abrir caja"
                        │
            ┌───────────▼───────────┐
            │  ¿cliente_id_cliente  │
            │  está presente?       │
            └───┬───────┬───────────┘
           Sí   │       │   No
                │       │
                │       └──┐
                │          ├── ¿cliente_data presente?
                │          │   ├── Sí → Cliente::create()
                │          │   │     → asignar $cliente_id_cliente
                │          │   └── No → Error 422: "cliente requerido"
                │          │
                │     ┌────▼────┐
                │     │ Usar    │
                │     │ $data[  │
                │     │ 'cliente│
                │     │ _id_    │
                │     │ cliente'│
                │     └─────────┘
                │
     ┌──────────▼──────────────────────────┐
     │  DB::transaction()                   │
     │   ├─ Cliente::create() (si aplica)   │
     │   ├─ Venta::create()                 │
     │   ├─ attach productos                │
     │   ├─ StockHelper::consumir()         │
     │   └─ CajaMovimiento::create()        │
     └─────────────────────────────────────┘
```

---

## 13. Flujo: Creación de Atención con Auto-Creación de Cliente

Análogo al flujo de venta (sección 12). Cambia:
- Controlador: `AtencionController@store`
- Modelo: `Atencion::create()`
- Relaciones: attach productos + servicios

La lógica de auto-creación de cliente es exactamente la misma:

```php
if (empty($data['cliente_id_cliente']) && !empty($data['cliente_data'])) {
    $cliente = Cliente::firstOrCreate(
        ['dni' => $data['cliente_data']['dni']],
        [
            'nombre' => $data['cliente_data']['nombre'],
            'apellido_paterno' => $data['cliente_data']['apellido_paterno'],
            'apellido_materno' => $data['cliente_data']['apellido_materno'],
            'registro_web' => false,
        ]
    );
    $data['cliente_id_cliente'] = $cliente->id_cliente;
}
```

---

## 14. Flujo: Creación de Cliente (Admin) con Auto-Completado

### 14.1 Request del frontend (desde formulario admin)

```http
POST /admin/reniec/consultar HTTP/1.1
Content-Type: application/json

{
    "dni": "46027897"
}
```

### 14.2 Response y auto-completado en frontend

```javascript
// Respuesta del servidor (exists: false)
{
    "success": true,
    "exists": false,
    "data": {
        "dni": "46027897",
        "nombre": "ROXANA KARINA",
        "apellido_paterno": "DELGADO",
        "apellido_materno": "HUAMANI",
        "nombre_completo": "DELGADO HUAMANI ROXANA KARINA"
    }
}

// JS: auto-completar campos del formulario
document.querySelector('input[name="nombre"]').value = data.data.nombre;
document.querySelector('input[name="apellido_paterno"]').value = data.data.apellido_paterno;
document.querySelector('input[name="apellido_materno"]').value = data.data.apellido_materno;
```

### 14.3 Vista Blade modificada

Se agrega botón "Buscar DNI" junto al campo DNI:

```blade
<div>
    <label class="block text-sm font-medium text-stone-700 mb-1">
        DNI <span class="text-stone-400 font-normal">(opcional)</span>
    </label>
    <div class="flex gap-2">
        <input type="text" name="dni" maxlength="8" minlength="8"
            class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
            id="cliente-dni-input">
        <button type="button" id="btn-buscar-dni"
            class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
            disabled>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            Buscar DNI
        </button>
    </div>
</div>
```

---

## 15. Flujo: Registro Público con Auto-Completado

### 15.1 Endpoint público

```
POST /api/reniec/consultar
Headers: Content-Type: application/json
Body: { "dni": "46027897" }
```

### 15.2 Vista Blade modificada

Se agrega botón "Buscar DNI" junto al campo DNI en el formulario de registro público:

```blade
<div>
    <label class="block text-sm font-medium text-stone-700 mb-1">
        DNI <span class="text-red-500">*</span>
    </label>
    <div class="flex gap-2">
        <input type="text" name="dni" value="{{ old('dni') }}" maxlength="8" required
            class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none @error('dni') border-red-400 @enderror"
            placeholder="12345678" id="registro-dni-input">
        <button type="button" id="btn-buscar-dni"
            class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
            disabled>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            Buscar
        </button>
    </div>
    @error('dni')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>
```

### 15.3 JS específico para registro público

```javascript
// resources/js/public/reniec-registro.js

import axios from 'axios';

export function initReniecRegistro() {
    const dniInput = document.getElementById('registro-dni-input');
    const btnBuscar = document.getElementById('btn-buscar-dni');
    if (!dniInput || !btnBuscar) return;

    // Habilitar/deshabilitar botón según longitud del DNI
    dniInput.addEventListener('input', function () {
        btnBuscar.disabled = !/^\d{8}$/.test(this.value);
    });

    // Consultar DNI
    btnBuscar.addEventListener('click', async function () {
        const dni = dniInput.value.trim();
        if (!/^\d{8}$/.test(dni)) return;

        btnBuscar.disabled = true;
        btnBuscar.innerHTML = '...';

        try {
            const res = await axios.post('/api/reniec/consultar', { dni });

            if (res.data.success) {
                const data = res.data.data;
                document.querySelector('input[name="nombre"]').value = data.nombre || '';
                document.querySelector('input[name="apellido_paterno"]').value = data.apellido_paterno || '';
                document.querySelector('input[name="apellido_materno"]').value = data.apellido_materno || '';
            }
        } catch (err) {
            const msg = err.response?.data?.message || 'Error al consultar DNI';
            alert(msg);
        } finally {
            btnBuscar.disabled = false;
            btnBuscar.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg> Buscar';
        }
    });
}
```

---

## 16. Componente Frontend: reniec-search.js

### 16.1 Especificación

**Archivo**: `resources/js/admin/reniec-search.js`

**Propósito**: Componente reutilizable que agrega un botón de lupa 🔍 junto al buscador de cliente en los formularios de venta y atención. Se integra con `client-search.js` para seleccionar clientes existentes o preparar `cliente_data` para clientes nuevos.

### 16.2 Responsabilidades

1. Buscar elementos contenedores con `data-reniec-search` dentro del DOM
2. Renderizar un botón de lupa al lado del input de búsqueda de clientes
3. Validar que el input tenga exactamente 8 dígitos numéricos
4. Realizar la llamada AJAX a `POST /admin/reniec/consultar`
5. Si `exists: true` → llamar a `clientSearch.selectClient(id, text)` para seleccionar el cliente existente
6. Si `exists: false` → mostrar los datos obtenidos y crear inputs ocultos `cliente_data[...]`
7. Manejar errores mostrando toast

### 16.3 Diseño de la UI

```
┌──────────────────────────────────────────────────────┐
│  Cliente *                         [Cliente sin identificar] │
│  ┌──────────────────────────┐  ┌───┐                    │
│  │ Buscar cliente por DNI...│  │ 🔍 │                    │
│  └──────────────────────────┘  └───┘                    │
│                                                          │
│  (Cuando se encuentra un cliente nuevo desde API)        │
│  ┌───────────────────────────────────────┐               │
│  │ ROXANA KARINA DELGADO HUAMANI — 46027897   [✕]      │
│  └───────────────────────────────────────┘               │
│                                                          │
│  (Cuando se selecciona cliente existente desde API)      │
│  ┌───────────────────────────────────────┐               │
│  │ ROXANA KARINA DELGADO HUAMANI — 46027897   [✕]      │
│  └───────────────────────────────────────┘               │
└──────────────────────────────────────────────────────┘
```

### 16.4 Código

```javascript
// resources/js/admin/reniec-search.js

import axios from 'axios';

/**
 * Inicializa los componentes de búsqueda RENIEC.
 * Busca contenedores [data-reniec-search] y agrega
 * el botón de lupa y la lógica de consulta.
 */
export function initReniecSearch() {
    document.querySelectorAll('[data-reniec-search]').forEach(container => {
        buildReniecSearch(container);
    });
}

function buildReniecSearch(container) {
    // Encontrar el client-search asociado (hermano anterior o padre)
    const clientSearchContainer = container.closest('.client-search')
        || container.parentElement.querySelector('.client-search');

    if (!clientSearchContainer) return;

    const input = clientSearchContainer.querySelector('input[type="text"]');
    if (!input) return;

    // Crear botón de lupa
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'reniec-search-btn px-2.5 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-stone-300 text-white rounded-lg text-sm transition disabled:cursor-not-allowed';
    btn.disabled = true;
    btn.title = 'Buscar DNI en RENIEC';
    btn.innerHTML = `
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>`;

    // Insertar botón después del input
    input.parentNode.insertBefore(btn, input.nextSibling);

    // Ajustar estilos del input para que quede en fila
    input.classList.remove('w-full');
    input.classList.add('flex-1', 'min-w-0');
    input.parentElement.classList.add('flex', 'gap-2');

    // Estado
    let currentClienteData = null;

    // Validar DNI en tiempo real
    input.addEventListener('input', function () {
        const value = this.value.trim();
        btn.disabled = !/^\d{8}$/.test(value);
    });

    // Consultar DNI al hacer clic
    btn.addEventListener('click', async function () {
        const dni = input.value.trim();
        if (!/^\d{8}$/.test(dni)) return;

        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = `
            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>`;

        try {
            const res = await axios.post('/admin/reniec/consultar', { dni });

            if (res.data.success && res.data.exists) {
                // Cliente ya existe en BD → seleccionar en client-search
                const cliente = res.data.cliente;
                const text = `${cliente.nombre} ${cliente.apellido_paterno || ''} ${cliente.apellido_materno || ''} — ${cliente.dni}`;
                // Disparar evento para que client-search lo seleccione
                clientSearchContainer.dispatchEvent(new CustomEvent('reniec:select', {
                    detail: { id: cliente.id_cliente, text, registroWeb: cliente.registro_web },
                }));
                currentClienteData = null;
                limpiarClienteData();
            } else if (res.data.success && !res.data.exists) {
                // Cliente nuevo desde API
                const data = res.data.data;
                currentClienteData = data;
                const text = `${data.nombre} ${data.apellido_paterno} ${data.apellido_materno} — ${data.dni}`;
                mostrarDatosClienteNuevo(text, data);
            } else {
                showError(res.data.message || 'No se pudieron obtener los datos del DNI.');
            }
        } catch (err) {
            const msg = err.response?.data?.message || 'Error de conexión al consultar RENIEC.';
            showError(msg);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });

    function mostrarDatosClienteNuevo(text, data) {
        // Mostrar badge con datos del cliente
        const selected = clientSearchContainer.querySelector('div.hidden');
        if (selected) {
            selected.classList.remove('hidden');
            selected.innerHTML = `
                <div class="flex items-center justify-between bg-amber-50 border border-amber-200 rounded-lg px-4 py-2">
                    <span class="text-sm text-amber-800 font-medium">
                        <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-1 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        ${escapeHtml(text)}
                    </span>
                    <button type="button" class="reniec-clear text-amber-500 hover:text-amber-700 p-1" title="Cancelar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>`;
            selected.querySelector('.reniec-clear').addEventListener('click', () => {
                selected.classList.add('hidden');
                input.classList.remove('hidden');
                input.value = '';
                input.focus();
                currentClienteData = null;
                limpiarClienteData();
            });
        }

        // Crear inputs ocultos para cliente_data
        crearClienteData(data);

        // Ocultar input de búsqueda
        input.classList.add('hidden');

        // Habilitar botón de cliente genérico si existe
        const btnGenerico = container.closest('form')?.querySelector('[data-generico]');
        if (btnGenerico) btnGenerico.style.display = 'none';
    }

    function crearClienteData(data) {
        limpiarClienteData();
        const form = container.closest('form');
        if (!form) return;

        const fields = ['dni', 'nombre', 'apellido_paterno', 'apellido_materno'];
        fields.forEach(field => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = `cliente_data[${field}]`;
            hidden.value = data[field] || '';
            hidden.dataset.reniecClienteData = '';
            form.appendChild(hidden);
        });
    }

    function limpiarClienteData() {
        document.querySelectorAll('[data-reniec-cliente-data]').forEach(el => el.remove());
        const btnGenerico = container.closest('form')?.querySelector('[data-generico]');
        if (btnGenerico) btnGenerico.style.display = '';
    }

    function showError(message) {
        // Usar toast si está disponible
        if (window.showToast) {
            window.showToast(message, 'error');
        } else {
            alert(message);
        }
    }

    function escapeHtml(text) {
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }
}
```

### 16.5 Evento de integración con client-search

El componente `reniec-search.js` dispara un evento `reniec:select` en el contenedor `.client-search` cuando se encuentra un cliente existente. El componente `client-search.js` debe escuchar este evento:

```javascript
// Dentro de buildClientSearch() en client-search.js
container.addEventListener('reniec:select', (e) => {
    const { id, text, registroWeb } = e.detail;
    selectedClient = { id, text, registroWeb };
    hidden.value = id;
    showSelected(text);
    results.classList.add('hidden');
});
```

---

## 17. Modificaciones al Componente client-search.js

### 17.1 Cambios necesarios

1. **Escuchar evento `reniec:select`** para integración con reniec-search:
   ```javascript
   container.addEventListener('reniec:select', (e) => {
       selectedClient = { id: e.detail.id, text: e.detail.text, registroWeb: e.detail.registroWeb };
       hidden.value = e.detail.id;
       showSelected(e.detail.text);
       results.classList.add('hidden');
   });
   ```

2. **No bloquear entrada de solo números**: El input de búsqueda actualmente acepta cualquier texto. Debe permitir números para que el componente reniec-search pueda validar 8 dígitos.

3. **Ajustar layout**: El contenedor `.client-search` debe usar `flex` para que el input y el botón de lupa queden en línea.

### 17.2 Layout final del contenedor client-search

```html
<div class="client-search relative" data-field="cliente_id_cliente">
    <input type="hidden" name="cliente_id_cliente" value="">
    <div class="flex gap-2">
        <input type="text" class="flex-1 min-w-0 rounded-lg border ..." placeholder="Buscar cliente por nombre o DNI...">
        <button class="reniec-search-btn px-2.5 py-2 bg-blue-600 ..." disabled>🔍</button>
    </div>
    <div class="hidden">...</div>
    <div class="hidden absolute left-0 right-0 ...">...</div>
</div>
```

---

## 18. Archivos a Crear

| # | Ruta | Propósito | Líneas estimadas |
|---|------|-----------|-----------------|
| 1 | `app/Services/ReniecService.php` | Servicio de consulta a API externa | ~120 |
| 2 | `app/Exceptions/Reniec/ReniecNotFoundException.php` | Excepción DNI no encontrado | ~15 |
| 3 | `app/Exceptions/Reniec/ReniecServiceException.php` | Excepción error de servicio | ~15 |
| 4 | `app/Http/Controllers/Admin/ReniecController.php` | Endpoint admin de consulta DNI | ~90 |
| 5 | `app/Http/Controllers/Api/ReniecController.php` | Endpoint público de consulta DNI | ~85 |
| 6 | `resources/js/admin/reniec-search.js` | Componente JS de lupa para ventas/atenciones | ~210 |
| 7 | `resources/js/public/reniec-registro.js` | Componente JS para registro público | ~80 |
| 8 | `tests/Unit/ReniecServiceTest.php` | Tests unitarios del servicio | ~120 |
| 9 | `tests/Feature/ReniecConsultaTest.php` | Tests del endpoint admin | ~100 |
| 10 | `tests/Feature/VentaConReniecTest.php` | Tests de venta + auto-creación cliente | ~150 |
| 11 | `tests/Feature/AtencionConReniecTest.php` | Tests de atención + auto-creación cliente | ~150 |
| 12 | `tests/Feature/ReniecPublicoTest.php` | Tests del endpoint público con rate limit | ~80 |

**Total nuevos archivos: 12**

---

## 19. Archivos a Modificar

| # | Ruta | Cambio |
|---|------|--------|
| 1 | `.env` | Agregar `RENIEC_API_TOKEN` y `RENIEC_API_URL` |
| 2 | `.env.example` | Agregar `RENIEC_API_TOKEN` y `RENIEC_API_URL` (documentación) |
| 3 | `config/services.php` | Agregar entrada `reniec` con token y url |
| 4 | `routes/admin.php` | Agregar `POST /reniec/consultar` |
| 5 | `routes/api.php` | Agregar `POST /reniec/consultar` con throttle |
| 6 | `app/Http/Controllers/Admin/VentaController.php` | Modificar `store()` para crear cliente desde `cliente_data` |
| 7 | `app/Http/Controllers/Admin/AtencionController.php` | Modificar `store()` análogo a VentaController |
| 8 | `app/Http/Requests/Admin/StoreVentaRequest.php` | Agregar reglas de validación para `cliente_data` |
| 9 | `app/Http/Requests/Admin/StoreAtencionRequest.php` | Agregar reglas de validación para `cliente_data` |
| 10 | `resources/js/admin/client-search.js` | Agregar listener para evento `reniec:select`, mejorar layout |
| 11 | `resources/js/admin/ventas/create.js` | Importar e inicializar `initReniecSearch` |
| 12 | `resources/js/admin/atenciones/create.js` | Importar e inicializar `initReniecSearch` |
| 13 | `resources/views/admin/clientes/create.blade.php` | Agregar botón "Buscar DNI" + JS |
| 14 | `resources/views/public/auth/registro.blade.php` | Agregar botón "Buscar DNI" + JS |

**Total archivos a modificar: 14**

---

## 20. Variables de Entorno

### 20.1 `.env`

```ini
RENIEC_API_TOKEN=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
RENIEC_API_URL=https://api.decolecta.com/v1/reniec/dni
```

### 20.2 `.env.example`

```ini
RENIEC_API_TOKEN=
RENIEC_API_URL=https://api.decolecta.com/v1/reniec/dni
```

---

## 21. Configuración services.php

### 21.1 Código a agregar

```php
'reniec' => [
    'token' => env('RENIEC_API_TOKEN'),
    'url' => env('RENIEC_API_URL', 'https://api.decolecta.com/v1/reniec/dni'),
],
```

### 21.2 Ubicación en el archivo

Agregar después de la entrada de `whatsapp`:

```php
'whatsapp' => [
    'phone' => env('WHATSAPP_PHONE_NUMBER'),
],

'reniec' => [
    'token' => env('RENIEC_API_TOKEN'),
    'url' => env('RENIEC_API_URL', 'https://api.decolecta.com/v1/reniec/dni'),
],
```

---

## 22. Manejo de Errores

### 22.1 Matriz de errores

| Escenario | Origen | HTTP Status | Mensaje usuario | Acción sistema |
|-----------|--------|-------------|-----------------|----------------|
| DNI inválido (no 8 dígitos) | Validación frontend | — (no request) | Botón deshabilitado | N/A |
| DNI no existe en RENIEC | API (400) | 422 | "El DNI X no fue encontrado en los registros RENIEC." | Log warning |
| Token API inválido/expirado | API (401) | 503 | "Error de autenticación con el servicio RENIEC. Contacte al administrador." | Log error, notificar admin |
| API no disponible | API (500) | 503 | "Error al consultar RENIEC. Verifique el DNI o intente más tarde." | Log error |
| Timeout de conexión | HTTP Client (5s) | 503 | "El servicio RENIEC no respondió a tiempo. Intente nuevamente." | Log error |
| Rate limit excedido (público) | Middleware throttle | 429 | "Demasiadas consultas. Intente nuevamente en un minuto." | N/A (Laravel automático) |
| DNI duplicado (race condition) | BD (unique) | 422 | "El cliente con este DNI ya fue registrado." | Usar `firstOrCreate` para prevenir |

### 22.2 Estrategia de logs

| Nivel | Cuándo | Información |
|-------|--------|-------------|
| `info` | Consulta exitosa | DNI (enmascarado: primeras 2 + **** + últimas 2), origen (BD/API) |
| `warning` | DNI no encontrado | DNI completo, respuesta API |
| `error` | API caída, timeout, 401 | DNI enmascarado, mensaje de error, stack trace |
| `notice` | Truncamiento de datos | Campo truncado, valor original, valor truncado |

---

## 23. Consideraciones de Seguridad

| # | Aspecto | Medida |
|---|---------|--------|
| **S-01** | Token API expuesto | Solo en `.env`, nunca en frontend ni en repositorio. `.env` en `.gitignore` (ya incluido). |
| **S-02** | Endpoint público abusado | Rate limiting con `throttle:60,1` en middleware. |
| **S-03** | Exposición de datos de clientes existentes | Endpoint público **no retorna** datos de clientes en BD, solo datos de API. |
| **S-04** | Inyección de DNI | Validación con regex `/^\d{8}$/` tanto en frontend como en backend. |
| **S-05** | CSRF | Todas las rutas POST usan middleware `web` que incluye verificación CSRF (Laravel automático). |
| **S-06** | Autenticación endpoint admin | Middleware `auth` + `permission:clientes` (Spatie). |
| **S-07** | Logging de datos sensibles | DNI enmascarado (primeros 2 + **** + últimos 2) en logs. |
| **S-08** | Creación masiva de clientes | Solo admins autenticados pueden crear clientes via venta/atención. |

---

## 24. Consideraciones de Performance y Rate Limiting

### 24.1 Caché de consultas

El servicio `ReniecService` implementa caché con `Cache::remember()` con TTL de 5 minutos. Esto significa:

- Si se consulta el mismo DNI dentro de 5 minutos, se retorna el resultado cachead (sin llamar a la API).
- La clave de caché es `reniec:{dni}`.
- Solo se cachean resultados exitosos. Errores no se cachean.

```php
Cache::remember("reniec:{$dni}", self::CACHE_TTL * 60, function () use ($dni) {
    return $this->consultarApi($dni);
});
```

### 24.2 Rate limiting público

```php
// routes/api.php
Route::post('/reniec/consultar', [ReniecController::class, 'checkDni'])
    ->middleware('throttle:60,1');
```

- 60 requests por minuto por dirección IP.
- Si se excede, Laravel retorna automáticamente 429 Too Many Requests.
- El frontend puede leer el header `Retry-After` para informar al usuario.

### 24.3 Timeout y reintentos

```php
Http::withToken(config('services.reniec.token'))
    ->timeout(5)          // 5 segundos de timeout
    ->retry(2, 100)       // 2 reintentos con 100ms de espera entre cada uno
    ->get(...);
```

- Tiempo total máximo: ~5.2s (5s timeout + 2 × 100ms entre reintentos).
- Los reintentos solo ocurren en errores de conexión (timeout, rede), no en respuestas HTTP 400/401/500.

### 24.4 Impacto en BD

- La creación de cliente + venta/atención en una sola transacción no implica bloqueos prolongados (son inserts simples).
- La consulta `Cliente::where('dni', $dni)->first()` está cubierta por el índice `unique` en la columna `dni`.

---

## 25. Pruebas Unitarias y de Integración

### 25.1 Test Unitario: ReniecServiceTest

**Archivo**: `tests/Unit/ReniecServiceTest.php`

| Test | Descripción | Assert |
|------|-------------|--------|
| `test_consulta_exitosa_retorna_datos_mapeados()` | Mock HTTP 200 con JSON válido | Verifica que retorna array con keys dni, nombre, apellido_paterno, apellido_materno |
| `test_dni_no_encontrado_lanza_reniec_not_found()` | Mock HTTP 400 | Espera `ReniecNotFoundException` |
| `test_error_servidor_lanza_reniec_service_exception()` | Mock HTTP 500 | Espera `ReniecServiceException` |
| `test_timeout_lanza_reniec_service_exception()` | Mock timeout HTTP | Espera `ReniecServiceException` |
| `test_truncamiento_de_campos_largos()` | Mock con nombre de >50 caracteres | Verifica que se trunca a 50 |
| `test_cache_funciona_correctamente()` | Mock HTTP llamado una vez, consulta 2 veces el mismo DNI | Segunda consulta no llama HTTP |
| `test_dni_invalido_lanza_exception()` | DNI "123" | Espera `ReniecServiceException` |

### 25.2 Test Feature: ReniecConsultaTest

**Archivo**: `tests/Feature/ReniecConsultaTest.php`

| Test | Descripción | Setup |
|------|-------------|-------|
| `test_admin_puede_consultar_dni_no_existente()` | Autenticado, DNI nuevo, verifica response `exists: false` | Crear usuario con permiso `clientes`, mockear ReniecService |
| `test_admin_consulta_dni_existente_retorna_cliente()` | Crear cliente, consultar, verifica `exists: true` + datos | Crear Cliente con DNI específico |
| `test_dni_invalido_retorna_error_422()` | DNI "123" | Espera 422 con error de validación |
| `test_sin_autenticacion_retorna_302()` | Sin sesión | Espera redirect a login |

### 25.3 Test Feature: VentaConReniecTest

**Archivo**: `tests/Feature/VentaConReniecTest.php`

| Test | Descripción | Assert |
|------|-------------|--------|
| `test_crear_venta_con_cliente_data_crea_cliente_y_venta()` | Enviar productos + `cliente_data`, caja abierta | Cliente creado, Venta creada, stock actualizado, caja incrementada |
| `test_crear_venta_con_cliente_id_existente_no_crea_duplicado()` | Enviar `cliente_id_cliente` + `cliente_data` | Usa cliente existente, no crea duplicado |
| `test_crear_venta_sin_cliente_id_ni_data_falla()` | Solo productos | Error 422 |
| `test_crear_venta_con_cliente_data_dni_duplicado()` | DNI que ya existe en BD, enviado en `cliente_data` | `firstOrCreate` previene duplicado, venta se asocia al existente |
| `test_crear_venta_sin_caja_abierta_falla()` | Sin caja abierta | Error 422 "Debe abrir una caja" |

### 25.4 Test Feature: AtencionConReniecTest

**Archivo**: `tests/Feature/AtencionConReniecTest.php`

Tests análogos a VentaConReniecTest pero para el módulo de atenciones.

### 25.5 Test Feature: ReniecPublicoTest

**Archivo**: `tests/Feature/ReniecPublicoTest.php`

| Test | Descripción | Assert |
|------|-------------|--------|
| `test_publico_puede_consultar_dni()` | Sin autenticación, DNI válido | 200, `success: true` |
| `test_publico_no_ve_datos_de_clientes_existentes()` | DNI que existe en BD | 200, `exists` no está presente, no retorna `id_cliente` |
| `test_publico_rate_limit_excedido()` | 61 requests en 1 minuto | 429 Too Many Requests |

### 25.6 Comando para ejecutar tests

```bash
composer test
# o directamente:
php artisan test --filter=Reniec
php artisan test --filter=VentaConReniec
php artisan test --filter=AtencionConReniec
```

---

## 26. Glosario

| Término | Definición |
|---------|-----------|
| **DNI** | Documento Nacional de Identidad. Número único de 8 dígitos asignado por RENIEC a cada ciudadano peruano. |
| **RENIEC** | Registro Nacional de Identificación y Estado Civil. Entidad peruana que mantiene el registro de identidad de los ciudadanos. |
| **dacoleta.api** | Proveedor externo de API que provee acceso a datos de RENIEC mediante token de autenticación. |
| **cliente_data** | Objeto JSON con los datos del cliente (dni, nombre, apellidos) que se envía al backend cuando se consulta un DNI nuevo y se va a crear el cliente junto con la venta/atención. |
| **Auto-creación** | Mecanismo por el cual el sistema crea automáticamente un registro `Cliente` en la base de datos cuando se registra una venta o atención con un DNI que no existe localmente. |
| **Atomicidad** | Propiedad de las transacciones de base de datos que garantiza que todas las operaciones se completen exitosamente o ninguna tenga efecto. |
| **Rate limiting** | Técnica de control de tráfico que limita el número de requests que un cliente puede realizar en un período de tiempo. |
| **Throttle** | Middleware de Laravel que implementa rate limiting. |
| **`firstOrCreate`** | Método de Eloquent que busca un registro por condiciones y lo crea si no existe. Previene duplicados por race condition. |
| **Bearer Token** | Token JWT utilizado para autenticación en la API de dacoleta. Se envía en el header `Authorization: Bearer <token>`. |
| **Cache** | Almacenamiento temporal de datos para evitar llamadas repetidas a la API externa. Implementado con `Cache::remember()` de Laravel. |

---

> **Documento creado por:** Equipo de Desarrollo  
> **Última revisión:** 2026-06-21  
> **Próxima acción:** Implementación de archivos según el plan detallado en las secciones 18 y 19.
