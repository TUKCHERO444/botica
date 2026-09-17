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
- Stock is tracked directly on the product: an ingreso increments `producto.stock`/`producto.inventario`, ventas/atenciones decrement `producto.stock`. Every stock movement writes a row in `kardex_movimientos` via `App\Helpers\KardexHelper` (see `docs/plan-eliminacion-logica-lotes.md`; lot-based docs in `docs/` are historical reference only).
- Images go through Cloudinary (`app/Services/CloudinaryService.php`); uploads store a `public_id` alongside the URL.
- RENIEC/SUNAT DNI/RUC lookups use the external `decolecta.com` API via `app/Services/ReniecService.php` and `SunatService.php`; tokens come from `RENIEC_API_TOKEN` / `SUNAT_API_TOKEN`.

## Testing

- `phpunit.xml` forces in-memory SQLite, array cache/session, sync queue — no services needed.
- Feature tests use `RefreshDatabase` and create Spatie permissions manually in `setUp()` (seeders are NOT run); call `PermissionRegistrar::forgetCachedPermissions()` when your test creates permissions.
- External HTTP calls are mocked with `Http::fake()` (see `tests/Feature/ReniecConsultaTest.php`).

## Reference docs

`docs/` holds the de facto specifications (Spanish), one file per module (`modulo-*.md`, `panel-administrativo.md`, `arquitectura-y-stack.md`, cloudinary/kardex/vencimiento plans). Read the relevant doc before changing a module; update it when behavior changes.
