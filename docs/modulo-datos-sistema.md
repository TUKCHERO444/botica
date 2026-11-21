# Módulo Datos del sistema

> **Última actualización:** 21/11/2026
> **Estado:** implementado. Panel de configuración con el nombre del negocio.
> **Referencias:** `panel-administrativo.md`, `modulo-ventas.md`, `modulo-atenciones.md`.

---

## 1. Idea general

**Datos del sistema** es un módulo pequeño de configuración global. Por ahora solo permite editar el **nombre de la empresa o negocio**, que se reutiliza en:

- El encabezado del **sidebar** del panel administrativo (antes `Botica Admin`).
- La cabecera y el título de los **tickets** de venta y atención (antes `Botica`).
- La cabecera de los **reportes PDF**.

El valor se guarda en la base de datos, por lo que se mantiene entre sesiones y despliegues.

---

## 2. Persistencia

Tabla `configuracion_sistema` (registro único):

| Columna | Tipo | Uso |
|---|---|---|
| `id_configuracion` | integer (PK) | Identificador |
| `nombre_negocio` | varchar(100) | Nombre visible de la empresa o negocio |
| `created_at` / `updated_at` | timestamps | Auditoría temporal |

Reglas:

- Se usa un **único registro**. Si no existe, el sistema muestra el valor de respaldo de `config/ticket.php` (`TICKET_NEGOCIO`, por defecto `Botica`).
- Al guardar por primera vez se crea el registro.
- No se elimina el registro desde el panel.

---

## 3. Acceso y permisos

- Ruta: `/admin/datos-sistema` (permiso `datos-sistema`).
- El permiso se agrega a `PermissionSeeder` y al rol **Administrador** en `RoleSeeder`.
- La migración de permisos también lo asigna al rol `Administrador` existente para instalaciones que solo ejecutan `php artisan migrate`.
- El enlace aparece en el sidebar dentro del bloque **Administración**.
- Roles personalizados pueden recibir el permiso desde el módulo de Roles.

---

## 4. Interfaz

La vista es un panel (no un modal) con:

- Campo **Nombre de la empresa o negocio** (obligatorio, máximo 100 caracteres).
- Botón **Guardar cambios**.
- Vista previa del nombre en el sidebar y en la cabecera del ticket.
- Guardado por AJAX: actualiza el nombre en el panel y en el sidebar sin recargar la página.
- Si JavaScript no está disponible, el formulario funciona con un POST normal y redirección.

Archivos:

```text
app/
  Http/Controllers/Admin/DatosSistemaController.php
  Http/Requests/Admin/UpdateDatosSistemaRequest.php
  Models/ConfiguracionSistema.php
resources/
  views/admin/datos-sistema/{index,full}.blade.php
  js/admin/datos-sistema/index.js
```

---

## 5. Uso del nombre en vistas

`App\Providers\AppServiceProvider` registra un view composer para:

- `layouts.admin`
- `admin.tickets.base`
- `admin.reportes.pdf.partials.negocio`

El composer comparte `$nombreNegocio` usando `ConfiguracionSistema::nombreNegocio()`. Si la tabla aún no existe (por ejemplo, antes de migrar), el modelo devuelve el valor de configuración `config('ticket.negocio', 'Botica')` sin romper el panel.

---

## 6. Migraciones

Se agregaron migraciones nuevas (no se modificaron las existentes):

- `2026_11_21_000001_create_configuracion_sistema_table.php`
- `2026_11_21_000002_add_datos_sistema_permission.php`

La segunda es aditiva y su `down()` es intencionalmente vacío para no dejar roles sin un permiso que pudieran estar usando.

---

## 7. Pruebas

`tests/Feature/DatosSistemaTest.php` cubre:

- Render del panel.
- Actualización y persistencia del nombre.
- Nombre en el sidebar.
- Nombre en el ticket.
- Restricción por permiso.
