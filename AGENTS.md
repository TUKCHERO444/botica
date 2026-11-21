# AGENTS.md

Laravel 12 pharmacy ("botica") management system. PHP 8.2, Blade, Tailwind CSS v4, Vite, vanilla JS + Axios. UI copy, docs, and most identifiers are in Spanish.

## Commands

- Fresh install: `composer run setup` (installs deps, copies `.env`, key:generate, migrate --force, npm install, npm run build)
- Dev (server + queue worker + logs + Vite): `composer run dev` — requires `QUEUE_CONNECTION=database` and does **not** run migrations.
- Tests: `composer run test` (clears config then `php artisan test`). Single test: `php artisan test --filter=test_name`. Single suite: `php artisan test tests/Feature/CajaTest.php`.
- Assets: `npm run build`. Lint/format: `./vendor/bin/pint` (no `pint.json`, defaults apply).
- DB: `php artisan migrate:fresh --seed` (seeders: permissions, roles, base tables).

## Architecture

- Two areas: public storefront (`routes/web.php`, `App\Http\Controllers\Public`) and admin panel (`routes/admin.php`, `App\Http\Controllers\Admin`).
- Admin is an **SPA over Blade partials**: `layouts.admin` is a shell loaded once; menu links fetch partials into `#panel-content`; CRUD forms open in AJAX modals. There is no frontend framework/router.
- Two view types per module: `admin/<modulo>/index.blade.php` (partial, **never** `@extends`) and `admin/<modulo>/full.blade.php` (extends layout, includes the partial).
- Two auth guards: `web` for admin `users`, `clientes` guard for the public storefront. Admin authorization uses `spatie/laravel-permission`; every admin route carries a `permission:<modulo>` middleware and there is one permission per module (seeded in `database/seeders/PermissionSeeder.php`).

## Gotchas

- **JS entry files must be registered in `vite.config.js` `input`.** `@vite('resources/js/...')` fails the build otherwise; add the file there when creating a new module script.
- Loading JS in Blade: full pages use `@push('scripts') @vite(...) @endpush`; AJAX-loaded partials (no `@extends`) use `@vite(...)` directly, since `@push` never renders for them. Modal JS re-executes injected scripts itself.
- `App\Http\Controllers\Admin\Traits\CacheaPanel` is intentionally a **no-op** (`getIndex` runs the callback, `olvidaIndex` does nothing). Do not reintroduce response caching; see `docs/cache-panel-administrativo.md`.
- **Compras**: `ingreso` is the header of the "Compras" module. It starts in `estado = borrador` and moves no stock; `App\Services\CompraService::recibir()` creates lots/layers, stock, kardex and optional cash expense. `anular()` reverses it. Received purchases are never edited or deleted. See `docs/modulo-compras-proveedores.md`.
- **Devoluciones a proveedor**: `DevolucionProveedorService` consumes a **specific layer** (`InventarioService::consumirDeCapa`, not FEFO), writes kardex `DEVOLUCION` and optional cash refund. `anular()` restores the exact layer via `InventarioService::revertirConsumos`.
- **Ajustes de inventario**: `AjusteInventarioService` is immutable and works **per lot in base units**; positive adjustments create a new layer, negative ones consume the lot's layers, and no cash movement is generated. See `docs/modulo-ajustes-inventario.md`.
- **Inventario inicial (D1)**: products are created with `stock = 0` and no `SIN-LOTE`. Existing stock is loaded only through `/admin/inventario-inicial` (manual or CSV), which creates lots/layers, stock and kardex `INGRESO` with reference `InventarioInicial`. Drafts can be edited (header and lines); confirmation is transactional, recalculates totals, and becomes immutable. See `docs/plan-inventario-inicial-d1.md`.
- **D2 / stock consistency**: `InventarioService::validarStockContraLotes()` blocks purchases, sales and attentions when `producto.stock` differs from the sum of lot layers. `SIN-LOTE` is never created automatically; list legacy lots with `php artisan inventario:sin-lote` and regularize them manually with a physical count, a negative adjustment and initial inventory. `producto.inventario` remains a hidden legacy column.
- **Decimal precision**: prices/costs (`precio_compra`, `precio_venta`, `costo_unitario`, `monto`, `descuento`) accept at most **2 decimals**; quantities, factors, `paso` and `stock_minimo` accept at most **3 decimals**. Enforce with `decimal:0,2` / `decimal:0,3` in FormRequests and `step="0.01"` / `step="0.001"` in inputs. CSV import rounds excess decimals with warnings; internal calculations may use 4 decimals via `CantidadHelper`.
- **Conciliaciones**: `ConciliacionInventarioService` + `inventario:conciliar` compare `producto.stock` against lot layers and each lot against its layers, then persist evidence in `conciliacion_inventario`/`detalle_conciliacion_*`. They never correct stock or kardex; corrections are adjustments or initial inventory. See `docs/modulo-conciliaciones.md`.
- **Ventas**: a sale is created `confirmada` and is **never edited or deleted**; `edit/update/destroy` return 422. Use `AnulacionVentaService` (motivo obligatorio) to revert layers/stock, write kardex `REVERSION` and a compensating cash expense, then mark it `anulada`. Reports and dashboard filter `estado = confirmada`. See `docs/modulo-ventas.md`.
- **Atenciones**: same immutability convention as sales. Created `confirmada` with correlativo `A001-XXXXXXXX`; `edit/update/destroy` return 422. `AnulacionAtencionService` reverts layers/stock, writes kardex `REVERSION` and a compensating cash movement, then marks it `anulada`. They have an `A001` ticket with products, services and payments. Reports and dashboard filter confirmadas. See `docs/modulo-atenciones.md`.
- **Caja**: `CajaService` owns apertura, movimientos, arqueo y cierre. Only methods with `metodo_pago.afecta_efectivo = 1` count toward physical cash; sales/attentions create **one movement per payment method**. Cierre exige `efectivo_contado`, calcula `efectivo_esperado`/`diferencia` and requires `observacion_cierre` when they differ. Movements are append-only (compensating rows, never update/delete); a closed caja is immutable. One open caja is enforced by `caja.abierta_unica` (unique). See `docs/modulo-caja.md`.
- **Métodos de pago**: dynamic `metodo_pago` entity (active/inactive) and `pago` rows keyed by `referencia_tipo` (`Venta`/`Atencion`). Sales and attentions require `pagos` whose amounts sum exactly the total; mixed payments are allowed. Caja records one income per method (`CajaService::registrarPagos`), not one for the full total. See `docs/modulo-metodos-pago.md`.
- **Devoluciones de cliente (C1)**: no separate module. Full return = annul the sale; unsellable product = negative per-lot adjustment; partial return = positive per-lot adjustment plus optional manual cash expense (or annul + new sale). Never add a positive adjustment after annulling, because the reversal already restored stock. See `docs/modulo-devoluciones-cliente.md`.
- **Kardex append-only**: never delete or regenerate `kardex_movimientos`. `InventarioService::revertirConsumos()` and `revertirIngreso()` write `REVERSION` movements; `KardexHelper` no longer exposes a delete method. See `docs/kardex-append-only.md`.
- **`producto.inventario` is legacy**: it is kept in the DB and synced to `stock` for compatibility, but it is hidden from the product form and inventory report. Stock alerts use `stock <= stock_minimo`.
- Stock is tracked in **base units** and by **lots with FEFO**:
  - `producto.stock` is a cache; the real source is `lote_ingreso.cantidad_disponible_base` (sum of available lot layers).
  - Ingresos create/update `producto_lote` + `lote_ingreso`; ventas/atenciones consume via `App\Services\InventarioService::consumir()` and write `lote_consumo`.
  - Every movement writes `kardex_movimientos` via `App\Helpers\KardexHelper` (with lot/expiry).
  - Expired or `baja` lots are never sold. A `SIN-LOTE` lot is created for stock without lots (initial stock/backfill) and must be regularized with physical inventory.
  - Un ingreso con lotes ya consumidos no se edita ni elimina; usar ajuste/devolución.
  - See `docs/plan-lotes-fefo-v2.md`; `docs/plan-eliminacion-logica-lotes.md` is historical.
- Images go through Cloudinary (`app/Services/CloudinaryService.php`); uploads store a `public_id` alongside the URL.
- RENIEC DNI and SUNAT RUC lookups use **json.pe** (`POST https://api.json.pe/api/dni` and `/api/ruc`) via `app/Services/JsonPe/JsonPeClient.php`, `ReniecService.php` and `SunatService.php`; token comes from `JSONPE_API_TOKEN`.
- **External APIs are never called from migrations or seeders.** `App\Support\ApiExternaGuard` blocks `migrate*`/`db:seed`/`db:wipe`, and `tests/Feature/SeedersSinConsumoApiTest.php` enforces it. `migrate:fresh --seed` must work offline and without spending tokens. See `docs/integracion-jsonpe.md`.
- **Verify locally before json.pe.** Admin forms use `POST /admin/clientes/verificar-dni` / `POST /admin/proveedores/verificar-ruc`; public registration uses `/api/reniec/verificar` / `/api/sunat/verificar`. These endpoints are DB-only and must never call the API. The `consultar` endpoints repeat the local check as defense in depth. Service cache includes 10-minute negative caching. See `docs/integracion-jsonpe.md`.

## Testing

- `phpunit.xml` forces in-memory SQLite, array cache/session, sync queue — no services needed.
- Feature tests use `RefreshDatabase` and create Spatie permissions manually in `setUp()` (seeders are NOT run); call `PermissionRegistrar::forgetCachedPermissions()` when your test creates permissions.
- External HTTP calls are mocked with `Http::fake()` (see `tests/Feature/ReniecConsultaTest.php`).

## Reference docs

`docs/` holds the de facto specifications (Spanish), one file per module (`modulo-*.md`, `panel-administrativo.md`, `arquitectura-y-stack.md`, cloudinary/kardex/vencimiento plans). Read the relevant doc before changing a module; update it when behavior changes.
