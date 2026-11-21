# Despliegue en VPS — Botica

> **Última actualización:** 21/11/2026 (ver §18: estado real en producción)
> **Base verificada:** `composer.json`, `composer.lock`, `package.json`, `vite.config.js`, `.env`, migraciones, `php artisan route:list` (231 rutas), `php artisan about` y `php artisan schedule:list`.
>
> Este documento responde a dos preguntas: **qué es el sistema** y **qué necesita exactamente un VPS para servirlo en producción**.

---

## 1. Resumen ejecutivo

El sistema es **un solo monolito Laravel 12** que sirve dos aplicaciones sobre el mismo código y la misma base de datos:

| Área | URL | Autenticación |
|---|---|---|
| Tienda pública | `/`, `/productos`, `/combos`, `/carrito`, `/mi-perfil` | Sin login (carrito y perfil con guard `clientes`) |
| Panel administrativo | `/admin/*` | Guard `web` + permisos Spatie |
| API interna DNI/RUC | `/api/reniec/*`, `/api/sunat/*` | Pública con `throttle:60,1` |

**Infraestructura mínima para producción:**

```
Internet
   │ 80/443
   ▼
 Nginx ──► PHP-FPM 8.2 (Laravel 12)
   │              │
   │              ├── MySQL 8 (local, 127.0.0.1)
   │              ├── Cloudinary (imágenes, salida HTTPS)
   │              └── api.json.pe (DNI/RUC, salida HTTPS)
   │
   └── /public (único docroot expuesto)
```

**Decisiones clave:**

- **No se necesita Redis, Docker, Supervisor ni cron de scheduler.** No hay jobs en cola (`ShouldQueue` = 0), no hay tareas programadas (`schedule:list` vacío) y caché/sesión/cola usan la base de datos.
- **Node.js solo se necesita para compilar assets** (`npm ci && npm run build`); no queda corriendo en producción.
- **Las imágenes no viven en el VPS**: se suben a Cloudinary. El disco local solo guarda logs, caché, sesiones y fuentes de DomPDF.
- **La impresión de tickets es del navegador** (`window.print()`), no del servidor: no se requiere CUPS ni drivers en el VPS.
- **No hay correo saliente** (`MAIL_MAILER=log`); no se necesita SMTP para operar.
- El punto más delicado del despliegue es la **configuración del `.env`** (el `.env.example` está desactualizado: apunta a SQLite y no documenta Cloudinary).

---

## 2. Mapa funcional del sistema

### 2.1. Rutas registradas

| Grupo | Cantidad | Archivo |
|---|---:|---|
| Panel administrativo (`admin/*`) | 203 | `routes/admin.php` |
| API DNI/RUC (`api/*`) | 4 | `routes/api.php` |
| Tienda, carrito, auth y perfil | 24 | `routes/web.php` |
| **Total** | **231** | — |

### 2.2. Módulos del panel

| Grupo | Módulos |
|---|---|
| Información | Dashboard |
| Inventario | Productos, Combos, Categorías, Marcas, Unidades de medida, Presentaciones, Ingresos (compras), Lotes, Kardex, Ajustes, Devoluciones a proveedor, Inventario inicial, Conciliaciones |
| Clientes | Clientes |
| Operaciones | Ventas, Atenciones |
| Administración | Servicios, Proveedores, Usuarios, Roles, Métodos de pago, Auditoría, Reportes |
| Finanzas | Caja |
| Portada | Sliders, Textos del header, Banners, Categorías en portada, Promociones |

Cada módulo tiene `index.blade.php` (partial para AJAX) y `full.blade.php` (carga directa). El panel es una **SPA sobre Blade**: `resources/js/admin/router.js` reemplaza `#panel-content` y `modal.js` gestiona los formularios.

### 2.3. Flujos críticos que deben probarse tras desplegar

1. **Inventario inicial** (`/admin/inventario-inicial`): carga manual o CSV; crea lotes, capas, stock y kardex.
2. **Compra** (`/admin/ingresos`): nace `borrador`, `recibir` mueve stock y opcionalmente caja; `anular` revierte.
3. **Venta** (`/admin/ventas`): se crea `confirmada`, consume lotes por FEFO, registra kardex y uno o varios pagos.
4. **Atención** (`/admin/atenciones`): igual que venta, con productos y servicios.
5. **Caja** (`/admin/caja`): apertura única, movimientos por método de pago, arqueo y cierre.
6. **Anulaciones**: venta/atención/compra/devolución generan movimientos compensatorios; nada se borra.
7. **Reportes** (`/admin/reportes`): 30 reportes PDF (DomPDF) y CSV.
8. **Tienda**: catálogo, carrito persistente, PDF del carrito, WhatsApp y registro de clientes.

### 2.4. Estructura del código

| Elemento | Cantidad |
|---|---:|
| Controladores | 46 |
| Modelos Eloquent | 41 |
| Servicios de dominio | 15 |
| Vistas Blade | 181 |
| Archivos JS | 62 |
| Entradas Vite | 46 |
| Migraciones | 66 |
| Tablas creadas | 52 |
| Tests | 122 |

Servicios relevantes: `InventarioService`, `CompraService`, `DevolucionProveedorService`, `AjusteInventarioService`, `ConciliacionInventarioService`, `AnulacionVentaService`, `AnulacionAtencionService`, `CajaService`, `ReporteService`, `CloudinaryService`, `ReniecService`, `SunatService`.

---

## 3. Stack y versiones

### 3.1. Backend

| Componente | Versión verificada | Nota |
|---|---|---|
| PHP | 8.2.12 | `composer.json` exige `^8.2` (8.3 también es válido) |
| Laravel | 12.60.2 | Framework |
| MySQL | 8.x | `.env` local apunta a `botica`; tests usan SQLite en memoria |
| Composer | 2.x | Producción: `--no-dev --optimize-autoloader` |
| DomPDF | `barryvdh/laravel-dompdf` | PDF de carrito y 30 reportes |
| Spatie Permission | 6.25 | Roles/permisos |
| Cloudinary SDK | `cloudinary-labs/cloudinary-laravel` 3.0 | Imágenes |
| bcmath | Extensión PHP | `CantidadHelper` y servicios de inventario |

### 3.2. Frontend (solo build)

| Componente | Versión | Nota |
|---|---|---|
| Vite | 7.x | Requiere **Node `^20.19` o `>=22.12`** |
| Tailwind CSS | 4.x | Plugin `@tailwindcss/vite` |
| Axios | 1.11 | AJAX del panel y tienda |
| Chart.js | 4.5 | Dashboard |
| SortableJS | 1.15 | Reordenamiento |
| Leaflet | 1.9 | Mapa del footer (tiles de OpenStreetMap, lado cliente) |

> Los assets se sirven desde `public/build`; no hay CDN de JS/CSS. El mapa consume tiles de `tile.openstreetmap.org` **desde el navegador del visitante**, no desde el VPS.

### 3.3. Extensiones PHP requeridas

| Extensión | Motivo |
|---|---|
| `bcmath` | Cálculos decimales de cantidades/inventario |
| `ctype`, `json`, `tokenizer`, `filter`, `hash`, `pcre`, `session`, `openssl` | Requisitos base de Laravel |
| `curl` | Cloudinary y json.pe |
| `dom`, `libxml`, `xml`, `xmlwriter` | Blade/DomPDF/Cloudinary |
| `fileinfo` | Validación de subidas |
| `mbstring` | Texto, mayúsculas, límites |
| `pdo`, `pdo_mysql` | MySQL |
| `zip` | Composer y SDK de Cloudinary |
| `gd` | **Requerida por DomPDF** para procesar imágenes |
| `exif` (opcional) | Metadatos de imágenes |

Instalación típica en Debian/Ubuntu:

```bash
php8.2-cli php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml \
php8.2-curl php8.2-zip php8.2-bcmath php8.2-gd
```

### 3.4. Dependencias del sistema operativo

- `nginx`
- `mysql-server` 8
- `git`, `unzip`
- `composer` 2
- `nodejs` 22 + `npm` (solo build; puede instalarse temporalmente)
- `certbot` + plugin Nginx (SSL)

---

## 4. Mapa de datos

- **52 tablas** creadas por **66 migraciones**.
- Tablas de infraestructura: `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.
- Nombres y PK en español (`producto_id_producto`, `venta`, `caja_movimiento`, etc.).
- Codificación: `utf8mb4` / `utf8mb4_unicode_ci`, `strict = true`.
- Zona horaria de conexión: `DB_TIMEZONE=-05:00` (Perú). La app usa `APP_TIMEZONE=America/Lima`.
- El stock real está en `producto_lote` + `lote_ingreso`; `producto.stock` es una caché que debe coincidir (hay validación que bloquea ventas/compras si no cuadra).
- `kardex_movimientos` es **append-only**: nunca se borra ni regenera.
- No hay datos binarios grandes en MySQL; las imágenes están en Cloudinary.

**Volumen esperado:** una botica con decenas de miles de movimientos al año entra sin problema en un MySQL de 1–4 GB de datos. El crecimiento relevante es `kardex_movimientos`, `lote_consumo` y `auditoria` (solo texto).

---

## 5. Servicios externos y variables de entorno

| Servicio | Uso | Variables | Imprescindible |
|---|---|---|---|
| **Cloudinary** | Subida/borrado de imágenes de productos, banners y combos | `CLOUDINARY_URL` **o** `CLOUDINARY_CLOUD_NAME` + `CLOUDINARY_KEY` + `CLOUDINARY_SECRET` | Sí, para cargar imágenes |
| **json.pe** | Consulta DNI (RENIEC) y RUC (SUNAT) | `JSONPE_API_TOKEN`, `JSONPE_API_URL` | Sí, para autocompletar documentos |
| **WhatsApp** | Botón `wa.me` de producto/carrito | `WHATSAPP_PHONE_NUMBER` | Opcional |
| **OpenStreetMap** | Mapa del footer | Sin variable (cliente) | Opcional |
| **SMTP** | No usado (`MAIL_MAILER=log`) | — | No |

> **Ojo con Cloudinary:** `config/cloudinary.php` solo define carpetas. Las credenciales las aporta el paquete mediante `CLOUDINARY_URL` o `CLOUDINARY_CLOUD_NAME`/`CLOUDINARY_KEY`/`CLOUDINARY_SECRET`. El `.env.example` actual **no** las incluye.
>
> **Ojo con json.pe:** el token de `.env` es de desarrollo. Use un token de producción y no lo suba al repositorio. La caché positiva es de 60 min y la negativa de 10 min.

---

## 6. Arquitectura de despliegue

```
/var/www/botica                 ← código (propiedad deploy:www-data)
├── app/ config/ database/ ...
├── public/                     ← ÚNICO docroot de Nginx
│   ├── index.php
│   ├── build/                  ← assets compilados por Vite
│   └── .htaccess               ← irrelevante con Nginx
├── storage/                    ← escritura por www-data
│   ├── app/
│   ├── fonts/                  ← DomPDF (crear y dar permisos)
│   ├── framework/{cache,sessions,views}
│   └── logs/
├── bootstrap/cache/            ← escritura por www-data
├── vendor/
└── .env                        ← 600, fuera del docroot
```

**Puertos y red:**

| Puerto | Servicio | Exposición |
|---|---|---|
| 22 | SSH | Solo IPs de administración (ideal) |
| 80 | Nginx | Público (redirige a 443) |
| 443 | Nginx + TLS | Público |
| 3306 | MySQL | Solo `127.0.0.1` |

**Salidas HTTPS necesarias:** `api.cloudinary.com`, `res.cloudinary.com`, `api.json.pe`. Si el VPS tiene firewall de salida restringido, hay que permitirlas.

---

## 7. Dimensionamiento del VPS

| Recurso | Mínimo | Recomendado | Motivo |
|---|---|---|---|
| vCPU | 2 | 2–4 | PHP-FPM + MySQL + compilación de assets |
| RAM | 2 GB | 4 GB | MySQL + DomPDF (reportes) + OPcache |
| Disco | 40 GB SSD | 80 GB SSD | SO, vendor, logs, backups locales |
| Ancho de banda | 1 TB | 2 TB | Imágenes las sirve Cloudinary; tráfico bajo |
| IPv4 | 1 | 1 | — |

**Concurrencia:** pocos administradores y visitantes de tienda. Con 4 GB, `pm.max_children = 15–20` es suficiente. El límite real lo pone la generación simultánea de reportes PDF.

**Opciones válidas:** Ubuntu 24.04 LTS (recomendado), Debian 12, Ubuntu 22.04. No se necesita panel de control (CyberPanel, cPanel, etc.).

---

## 8. Aprovisionamiento paso a paso (Ubuntu 24.04)

### 8.1. Paquetes base

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server git unzip curl ca-certificates \
  php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml \
  php8.2-curl php8.2-zip php8.2-bcmath php8.2-gd
```

Si la distro no trae PHP 8.2 (Ubuntu 24.04 trae 8.3), use el PPA de Ondřej:

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
# luego repita el apt install anterior
```

> Laravel 12 acepta PHP 8.3; se fija 8.2 por paridad con el entorno local. No baje de 8.2.

**Composer:**

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

**Node (solo para compilar):**

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
node -v   # debe ser >= 22.12
```

### 8.2. Base de datos

```bash
sudo mysql_secure_installation
sudo mysql
```

```sql
CREATE DATABASE botica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'botica'@'localhost' IDENTIFIED BY 'CAMBIA_ESTA_CLAVE';
GRANT ALL PRIVILEGES ON botica.* TO 'botica'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Verifique que MySQL escuche solo en local:

```bash
sudo grep -n "^bind-address" /etc/mysql/mysql.conf.d/mysqld.cnf
# bind-address = 127.0.0.1
```

Ajuste conservador para 4 GB de RAM (`/etc/mysql/mysql.conf.d/mysqld.cnf`):

```ini
innodb_buffer_pool_size = 1G
max_connections = 60
```

> En MySQL 8.0.30+ el redo log se ajusta con `innodb_redo_log_capacity` (por ejemplo `512M`). No use `innodb_log_file_size` en MySQL 8.4: fue eliminado.
>
> `DB_TIMEZONE=-05:00` funciona con offset y **no** requiere cargar las tablas de zonas horarias de MySQL.

### 8.3. Código y dependencias

```bash
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG www-data deploy
sudo mkdir -p /var/www/botica
sudo chown -R deploy:www-data /var/www/botica

su - deploy
cd /var/www/botica
git clone <URL_DEL_REPOSITORIO> .
composer install --no-dev --optimize-autoloader --no-interaction
```

> Si despliega por `rsync`/SFTP en vez de Git, **excluya** `node_modules`, `vendor`, `.env`, `public/hot` y `storage/logs/*`.

### 8.4. `.env` de producción

```bash
cp .env.example .env
chmod 600 .env
nano .env
```

Plantilla completa (sobrescriba la del ejemplo):

```dotenv
APP_NAME="Botica"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://botica.sudominio.com

APP_TIMEZONE=America/Lima
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=botica
DB_USERNAME=botica
DB_PASSWORD=CAMBIA_ESTA_CLAVE
DB_TIMEZONE=-05:00

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=log

# Cloudinary (obligatorio para imágenes)
CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME

# json.pe (obligatorio para DNI/RUC)
JSONPE_API_TOKEN=TOKEN_DE_PRODUCCION
JSONPE_API_URL=https://api.json.pe/api
JSONPE_CACHE_NEGATIVO_MINUTOS=10

WHATSAPP_PHONE_NUMBER=51XXXXXXXXX

# Ticket
TICKET_NEGOCIO="Botica"
TICKET_RUC=
TICKET_DIRECCION=
TICKET_TELEFONO=
TICKET_ANCHO_MM=80
TICKET_MONEDA="S/"
TICKET_PIE="¡Gracias por su compra!"

VITE_APP_NAME="${APP_NAME}"
```

Luego:

```bash
php artisan key:generate --force
```

> **No** ejecute `php artisan config:cache` antes de terminar el `.env`. Si cambia variables después, vuelva a ejecutar `php artisan optimize`.

### 8.5. Migraciones y datos iniciales

```bash
php artisan migrate --force
```

**No** ejecute `migrate:fresh --seed` en producción: `TablasBaseSeeder` inserta productos, ventas y atenciones de demostración.

Siembre solo lo imprescindible:

```bash
php artisan db:seed --class=PresentacionSeeder --force
php artisan db:seed --class=UnidadMedidaSeeder --force
php artisan db:seed --class=PermissionSeeder --force
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=MetodoPagoSeeder --force
```

`RoleSeeder` crea dos usuarios con contraseñas conocidas:

| Usuario | Contraseña inicial | Acción |
|---|---|---|
| `admin@sistema.com` | `admin123` | **Cambiar correo y contraseña antes de abrir** |
| vendedor (si se usa) | `vendedor123` | Cambiar o desactivar |

```bash
php artisan tinker
```

```php
$u = App\Models\User::where('email', 'admin@sistema.com')->first();
$u->email = 'admin@sunegocio.pe';
$u->password = Illuminate\Support\Facades\Hash::make('UnaClaveLargaYUnica');
$u->save();
```

> No siembre `TablasBaseSeeder` en producción. Si ya lo hizo por error, use una base nueva: ese seeder asume IDs 1 y 2 y crea movimientos demo.

### 8.6. Assets de producción

```bash
npm ci
npm run build
```

Verifique que exista `public/build/manifest.json` y que **no** exista `public/hot` (el archivo `hot` solo se usa en desarrollo y rompe el sitio en producción).

### 8.7. Permisos del sistema de archivos

```bash
sudo chown -R deploy:www-data /var/www/botica
sudo find /var/www/botica/storage /var/www/botica/bootstrap/cache -type d -exec chmod 775 {} \;
sudo find /var/www/botica/storage /var/www/botica/bootstrap/cache -type f -exec chmod 664 {} \;
sudo -u www-data mkdir -p /var/www/botica/storage/fonts
```

`storage/fonts` es obligatorio: DomPDF cachea ahí las fuentes de los reportes. Si no existe o no es escribible, los PDF fallan.

> No hace falta `php artisan storage:link`: la aplicación no usa el disco `public` (las imágenes van a Cloudinary). Ejecutarlo no rompe nada, pero es innecesario.

### 8.8. Nginx

`/etc/nginx/sites-available/botica`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name botica.sudominio.com;
    root /var/www/botica/public;

    index index.php;
    charset utf-8;
    client_max_body_size 16m;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_param HTTPS $https if_not_empty;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(?:css|js|jpg|jpeg|png|webp|svg|ico|woff2?)$ {
        expires 30d;
        access_log off;
        add_header Cache-Control "public, immutable";
    }

    # Solo si PHP-FPM usa otro socket:
    # fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
```

```bash
sudo ln -s /etc/nginx/sites-available/botica /etc/nginx/sites-enabled/botica
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

> **Cloudflare:** si el DNS está proxied y el origen es HTTP, Laravel puede creer que la petición es insegura (redirecciones/ cookies). Use Cloudflare en modo **Full (strict)** con certificado de origen, o configure proxies de confianza en `bootstrap/app.php`. Con Let's Encrypt directo (sin proxy) no hay problema.

### 8.9. PHP-FPM y `php.ini`

`/etc/php/8.2/fpm/pool.d/www.conf`:

```ini
pm = dynamic
pm.max_children = 15
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500
```

`/etc/php/8.2/fpm/php.ini` y `/etc/php/8.2/cli/php.ini`:

```ini
memory_limit = 512M
upload_max_filesize = 8M
post_max_size = 16M
max_execution_time = 120
max_input_time = 120
date.timezone = America/Lima
expose_php = Off

opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
```

```bash
sudo systemctl restart php8.2-fpm
```

**Por qué esos límites:**

- 4 imágenes de producto × 2 MB = 8 MB por request → `post_max_size=16M` cubre el formulario completo.
- CSV de inventario inicial: máximo 5 MB.
- Los reportes PDF pueden tardar y consumir memoria; 512 MB y 120 s evitan errores en reportes grandes.
- `validate_timestamps=0` acelera producción, pero obliga a recargar PHP-FPM en cada despliegue. Para empezar, déjelo en `1` y optimice después.

### 8.10. HTTPS

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d botica.sudominio.com
sudo certbot renew --dry-run
```

Renovación automática: el paquete instala el timer de systemd. Verifique:

```bash
systemctl list-timers | grep certbot
```

### 8.11. Optimización de Laravel

```bash
php artisan optimize:clear
php artisan optimize
```

`optimize` ejecuta `config:cache`, `events:cache`, `routes:cache` y `views:cache`. Se verificó que `route:cache` funciona con las rutas actuales (incluida la closure de promociones).

Si más adelante pone `opcache.validate_timestamps=0`, cada despliegue debe terminar con:

```bash
php artisan optimize
sudo systemctl reload php8.2-fpm
```

---

## 9. Procesos en segundo plano

| Proceso | ¿Necesario? | Evidencia |
|---|---|---|
| **Queue worker** | **No** | No hay clases `ShouldQueue` ni `dispatch()` de jobs. `QUEUE_CONNECTION=database` existe, pero la tabla `jobs` queda vacía. |
| **Scheduler / cron** | **No** | `php artisan schedule:list` responde “No scheduled tasks have been defined”. |
| **Supervisor** | **No** | Consecuencia de lo anterior. |
| **Redis** | **No** | Caché y sesión usan la base de datos. |
| **WebSockets / broadcasting** | **No** | `BROADCAST_CONNECTION=log`. |

Los comandos `inventario:sin-lote` y `inventario:conciliar` son **manuales** y no están programados.

> Si en el futuro agrega jobs o tareas programadas, ahí sí necesitará Supervisor + `queue:work` y una línea de cron para `schedule:run` cada minuto.

---

## 10. Backups

### 10.1. Base de datos (lo crítico)

```bash
sudo mkdir -p /var/backups/botica
sudo chown deploy:deploy /var/backups/botica
```

`/home/deploy/.my.cnf`:

```ini
[client]
user=botica
password=CAMBIA_ESTA_CLAVE
host=127.0.0.1
```

```bash
chmod 600 /home/deploy/.my.cnf
```

`/home/deploy/backup-botica.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
FECHA=$(date +%Y-%m-%d_%H%M)
DESTINO=/var/backups/botica
mysqldump --defaults-file=/home/deploy/.my.cnf \
  --single-transaction --quick --routines --triggers \
  botica | gzip > "$DESTINO/botica-$FECHA.sql.gz"
find "$DESTINO" -name 'botica-*.sql.gz' -mtime +14 -delete
```

```bash
chmod +x /home/deploy/backup-botica.sh
```

Cron del usuario `deploy`:

```cron
0 2 * * * /home/deploy/backup-botica.sh >> /var/log/backup-botica.log 2>&1
```

**Copie los backups fuera del VPS** (S3, Backblaze, otro servidor). Un backup en el mismo disco no protege contra fallo de disco.

### 10.2. Qué más respaldar

| Elemento | Acción |
|---|---|
| `.env` | Copia cifrada aparte (contiene token de json.pe y claves) |
| Código | Repositorio Git + tag/release |
| Imágenes | Cloudinary (no están en el VPS); asegure acceso a la cuenta |
| `storage/logs` | Rotación, no backup permanente |
| `storage/fonts` | Se regenera solo |

### 10.3. Restauración

```bash
gunzip -c botica-2026-11-20_0200.sql.gz | mysql -u botica -p botica
```

Pruébela al menos una vez en un entorno de staging.

---

## 11. Logs y monitoreo

| Qué | Dónde |
|---|---|
| Aplicación | `storage/logs/laravel-YYYY-MM-DD.log` (canal `daily`) |
| Nginx acceso/error | `/var/log/nginx/access.log`, `error.log` |
| PHP-FPM | `/var/log/php8.2-fpm.log` |
| MySQL | `/var/log/mysql/error.log` |
| Health check | `GET /up` (Laravel health endpoint) |

**Rotación:** el canal `daily` de Laravel conserva 14 días. Para Nginx/PHP use `logrotate` (viene configurado por defecto en Ubuntu).

**Monitoreo mínimo:**

- Uptime externo apuntando a `https://botica.sudominio.com/up`.
- Alerta de disco > 80 %.
- Revisión semanal de `storage/logs` y `failed_jobs`.
- `php artisan about` para confirmar entorno/drivers tras cada despliegue.

---

## 12. Seguridad

- [ ] `APP_ENV=production` y `APP_DEBUG=false` (con debug activo se filtran tokens y rutas).
- [ ] `.env` con permisos `600`, fuera del docroot (ya está fuera).
- [ ] Usuario MySQL dedicado, sin `root` remoto.
- [ ] `bind-address = 127.0.0.1`.
- [ ] Firewall: `sudo ufw allow OpenSSH`, `sudo ufw allow 'Nginx Full'`, `sudo ufw enable`.
- [ ] HTTPS con renovación automática; `SESSION_SECURE_COOKIE=true`.
- [ ] Cambiar las contraseñas sembradas por `RoleSeeder`.
- [ ] Desactivar/eliminar el usuario vendedor demo si no se usa.
- [ ] Token de json.pe de producción; rotar el de desarrollo.
- [ ] `composer install --no-dev` (no subir PHPUnit/Pint a producción).
- [ ] No versionar `.env`, `vendor`, `node_modules`, `public/build` ni `public/hot`.
- [ ] Opcional: `fail2ban` para SSH y Nginx.
- [ ] Opcional: acceso SSH solo por clave, sin password.

---

## 13. Checklist de salida a producción

**Infraestructura**

- [ ] DNS apunta al VPS y propaga.
- [ ] Nginx responde y `nginx -t` pasa.
- [ ] HTTPS válido; HTTP redirige a HTTPS.
- [ ] MySQL solo en local; base `utf8mb4`.
- [ ] `.env` completo (MySQL, Cloudinary, json.pe, ticket).

**Aplicación**

- [ ] `php artisan migrate --force` sin errores.
- [ ] Seeders base ejecutados (sin `TablasBaseSeeder`).
- [ ] `admin@sistema.com` con correo/contraseña nuevos.
- [ ] `public/build/manifest.json` existe y **no** hay `public/hot`.
- [ ] `storage/` y `bootstrap/cache/` escribibles por `www-data`.
- [ ] `storage/fonts` existe y es escribible.
- [ ] `php artisan optimize` ejecutado.

**Pruebas funcionales**

- [ ] Home y catálogo cargan con estilos e imágenes.
- [ ] Login admin funciona; sidebar y un módulo AJAX cargan.
- [ ] Subir imagen de producto/banner → aparece en Cloudinary.
- [ ] Consultar DNI/RUC → responde json.pe.
- [ ] Inventario inicial de un producto → stock y kardex correctos.
- [ ] Compra en borrador → recibir → stock actualizado.
- [ ] Venta con pago mixto → ticket imprime (`window.print`) → caja registra movimientos.
- [ ] Atención con producto + servicio → ticket A001.
- [ ] Anular venta → stock y kardex revertidos.
- [ ] Abrir caja → cerrar caja con arqueo.
- [ ] Reporte PDF y CSV se descargan.
- [ ] Carrito público, PDF y botón WhatsApp.
- [ ] `/up` responde 200.

**Operación**

- [ ] Backup manual ejecutado y restaurado en staging.
- [ ] Cron de backup activo.
- [ ] Uptime monitor apuntando a `/up`.
- [ ] Logs revisados sin errores 500.

---

## 14. Actualizaciones y rollback

### 14.1. Despliegue de una nueva versión

```bash
cd /var/www/botica
php artisan down --retry=60

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction

php artisan migrate --force
npm ci
npm run build

php artisan optimize:clear
php artisan optimize

sudo chown -R deploy:www-data storage bootstrap/cache
sudo systemctl reload php8.2-fpm

php artisan up
```

> Mantenga `php artisan down` < 1–2 minutos. Si `npm ci` tarda, compile antes de bajar la aplicación o use un directorio de release + symlink.

### 14.2. Rollback

1. `git checkout <tag_anterior>` (o reactive el release anterior).
2. `composer install --no-dev --optimize-autoloader`.
3. `php artisan migrate:rollback --step=N` **solo si** la migración nueva es reversible y no hay datos que perder.
4. `npm ci && npm run build`.
5. `php artisan optimize:clear && php artisan optimize`.
6. `sudo systemctl reload php8.2-fpm`.

> En este proyecto varias migraciones históricas fueron reescritas. **No** ejecute `migrate:fresh` en producción para “arreglar” el esquema: perdería todos los datos.

### 14.3. `deploy.sh` (despliegue con verificaciones)

Script versionado en la raíz del repo, se ejecuta en el servidor como `deploy`:

```bash
./deploy.sh --dry-run     # muestra el plan, no cambia nada
./deploy.sh               # despliegue real (pide confirmaciones)
./deploy.sh --si          # sin preguntas
./deploy.sh --forzar      # ignora el aviso de caja abierta
./deploy.sh --sin-build   # no recompila assets
```

Qué comprueba **antes** de tocar nada (si algo falla, aborta):

- rama `master` y árbol de trabajo limpio (sin ediciones manuales en el servidor);
- commits nuevos respecto a `origin/master`; si no hay, sale sin hacer nada;
- lista de migraciones nuevas y **análisis del `up()`** buscando `dropColumn`, `dropTable`, `dropIfExists`, `renameColumn`, `truncate` o `->change(`;
- **caja abierta** (`caja.estado = 'abierta'`): no despliega a mitad de turno;
- 2 GB libres de disco; ausencia de `public/hot`;
- **backup verificado** (`gzip -t` + número de tablas igual al de la base).

Después: `composer install` (si cambió) → `down --retry=60 --secret=...` → `git pull --ff-only` → `migrate --force` → `npm ci && build` (si cambió el frontend) → `optimize` → verificación con el secret → `up` → verificación pública de `/`, `/login` y `/up`.

> El bypass de mantenimiento en Laravel 12 es la **ruta** `https://<dominio>/<secret>` (devuelve 302 + cookie `laravel_maintenance`), **no** `?secret=`. El script ya lo usa así.

Si algo falla: revierte el código al commit anterior, reconstruye cachés y vuelve a abrir el sitio. El log queda en `/home/deploy/deploy.log` y el backup previo en `/var/backups/botica/pre-deploy-*.sql.gz`.

Nunca ejecuta `migrate:fresh`, `migrate:refresh`, `migrate:rollback` ni `db:wipe`; solo aplica migraciones **pendientes** (las que no están en la tabla `migrations`).

> **Primera vez / bootstrap:** si el servidor tiene un `deploy.sh` sin versionar (creado antes de subirlo al repo), `git pull` fallará con *“untracked working tree files would be overwritten”*. Solución: `rm -f /var/www/botica/deploy.sh && git pull --ff-only`; a partir de ahí el archivo viene del repo y ya es tracked.

---

## 15. Consideraciones específicas de esta aplicación

1. **El `.env.example` no sirve tal cual.** Apunta a SQLite, usa `APP_NAME=Laravel` y no documenta Cloudinary. Use la plantilla de la sección 8.4.
2. **Usuario admin sembrado.** `RoleSeeder` crea `admin@sistema.com` / `admin123`. Cambiarlo es obligatorio antes de publicar.
3. **Seeder de demo.** `TablasBaseSeeder` inserta categorías, productos, ventas y atenciones de prueba; nunca lo ejecute en producción.
4. **Imágenes en Cloudinary.** Sin credenciales, el alta/edición de productos, banners y combos falla al subir. Pruebe una subida real.
5. **json.pe.** Sin token, las consultas DNI/RUC fallan; la verificación local en BD sigue funcionando. El token de `.env` es de desarrollo.
6. **DomPDF.** Requiere `ext-gd`, `storage/fonts` escribible y memoria suficiente. Los reportes son 30 y algunos recorren mucho historial.
7. **Zona horaria.** `APP_TIMEZONE=America/Lima` + `DB_TIMEZONE=-05:00`. Mantenga NTP activo (`timedatectl`) para que los cierres de caja y correlativos no salten.
8. **Ticket térmico.** Se imprime desde el navegador con `window.print()`; el servidor no habla con la impresora. El ancho se configura en `TICKET_ANCHO_MM`.
9. **Sin correo.** No hay envío de emails ni recuperación de contraseña por correo; los usuarios se gestionan desde el panel.
10. **Sin colas ni scheduler.** No instale Supervisor “por si acaso”; no hay nada que ejecutar.
11. **`public/hot`.** Si despliega desde su máquina con `rsync`, excluya ese archivo; si queda, Vite intenta usar el servidor de desarrollo y el sitio pierde estilos/JS.
12. **Build reproducible.** Vite 7 exige Node 20.19+ o 22.12+. Un Node viejo falla en `npm run build`.
13. **Migraciones reescritas.** Para bases nuevas no hay problema; para actualizar una base existente, revise cada migración antes de ejecutarla.
14. **Reportes en hora local.** Los filtros usan `now()` con zona Perú; no cambie `APP_TIMEZONE` sin migrar datos históricos.
15. **Caché del panel.** `CacheaPanel` es un no-op intencional. No agregue caché de vistas del panel en el servidor.

---

## 16. Estimación de esfuerzo

| Tarea | Tiempo aproximado |
|---|---|
| Aprovisionar VPS (paquetes, MySQL, PHP, Nginx) | 45–60 min |
| Configurar `.env`, migrar y sembrar | 20–30 min |
| Compilar assets y ajustar permisos | 15 min |
| SSL + DNS | 15 min (+ propagación) |
| Pruebas funcionales del checklist | 60 min |
| Backups + monitoreo | 30 min |
| **Total** | **3–4 horas** |

---

## 17. Referencias

| Documento | Contenido |
|---|---|
| [`arquitectura-y-stack.md`](arquitectura-y-stack.md) | Arquitectura, módulos, modelo de datos |
| [`funcionalidades-del-sistema.md`](funcionalidades-del-sistema.md) | Funcionalidades públicas y del panel |
| [`integracion-jsonpe.md`](integracion-jsonpe.md) | DNI/RUC, caché, guard de APIs externas |
| [`flujo-cloudinary-productos.md`](flujo-cloudinary-productos.md) | Subida y borrado de imágenes |
| [`modulo-caja.md`](modulo-caja.md) | Apertura, movimientos, arqueo y cierre |
| [`modulo-reportes.md`](modulo-reportes.md) | Reportes PDF/CSV |
| [`plan-lotes-fefo-v2.md`](plan-lotes-fefo-v2.md) | Lotes, FEFO y stock |

---

## 18. Estado real del despliegue en producción (21/11/2026)

Servidor `109.123.241.250` (Ubuntu 24.04.5, 4 vCPU, 8 GB RAM, 96 GB SSD). Aplicación en `/var/www/botica`, propietario `deploy:www-data`, pool PHP-FPM `www` (socket `php8.2-fpm.sock`).

| Elemento | Valor real |
|---|---|
| Dominio canónico | `https://bonifarmachiclayo.com` (apex, sin `www`) |
| `www` | `301` → apex |
| Certificado | Let's Encrypt en `/etc/letsencrypt/live/bonifarmachiclayo.com/`, vence 19/02/2027 |
| Renovación | `certbot.timer` activo; `certbot renew --dry-run` verificado OK |
| nginx | un bloque para el dominio + catch-all `return 444` en 80 y 443 |
| `APP_URL` | `https://bonifarmachiclayo.com` |
| `SESSION_SECURE_COOKIE` | `true` (obligatorio con TLS; rompe el login si se activa sin HTTPS) |
| `LOG_STACK` | `daily` |
| Zona horaria del sistema | `America/Lima` (la app ya usaba `America/Lima` + `DB_TIMEZONE=-05:00`) |
| SSH root | **deshabilitado** (`/etc/ssh/sshd_config.d/00-hardening.conf`) |
| SSH por contraseña | deshabilitado; solo clave |
| Sudo de `deploy` | requiere contraseña; no hay `NOPASSWD` permanente |
| Backups nginx/.env | `/root/nginx-botica-*.bak` y `/root/env-botica-*.bak` |
| Backup MySQL | cron de `deploy` a las 02:00 America/Lima → `/var/backups/botica/botica-*.sql.gz`, retención 14 días, log en `backup.log` |
| Backup **fuera** del VPS | tarea de Windows `Botica - Backup fuera del VPS` (diaria 10:00) ejecuta `D:\BackupsBotica\sync-botica.ps1` → copia el último dump a `D:\BackupsBotica\`, verifica `sha256` + tamaño y retiene 14 archivos; log en `D:\BackupsBotica\sync.log` |
| Clave de backup | `D:\BackupsBotica\backup_key` (sin passphrase) restringida en el servidor con `restrict,command="/home/deploy/entregar-backup.sh"`: solo puede pedir `latest` y `get <nombre>`; no abre shell ni lee otros archivos. No depende de `ssh-agent` |
| Firewall | `ufw` activo: `deny incoming`, permite 22/80/443; MySQL solo en `127.0.0.1` |
| DNS comodín | `A * → 109.123.241.250`; los subdominios futuros resuelven sin tocar Namecheap |

### 18.1. Reglas para añadir nuevas aplicaciones

1. No reutilizar el usuario `deploy` ni el pool `www` para otra aplicación: compartir usuario es compartir secretos (`.env`, base de datos).
2. Cada aplicación: `/var/www/<app>`, usuario Linux propio, pool PHP-FPM propio (socket propio) y base MySQL con usuario propio.
3. Cada aplicación: su propio `server` block en `/etc/nginx/sites-available/<app>` con `server_name` único.
4. El catch-all `return 444` se mantiene como último recurso; una aplicación nunca debe quedar como `default_server`.
5. DNS: ya existen `A` para el apex y `CNAME www`. Los proyectos futuros van en subdominios (`<app>.bonifarmachiclayo.com`); conviene un `A *` comodín para no volver a tocar Namecheap.
6. Antes de tocar DNS: `nginx -t`, `systemctl reload nginx` y probar con `curl -H "Host: <dominio>" http://127.0.0.1/`.
7. Certbot exige que el DNS ya apunte al VPS (validación HTTP-01). Orden correcto: DNS → nginx → certificado → `.env`.

### 18.2. Entorno de staging (21/11/2026)

| Elemento | Valor |
|---|---|
| URL | `https://staging.bonifarmachiclayo.com` (HTTP → 301) |
| Acceso | HTTP Basic Auth, usuario `staging` (contraseña en `/root/staging-basic-pass.txt`, root) |
| Código | `/var/www/botica-staging`, propietario `staging:www-data` |
| Pool PHP-FPM | `/etc/php/8.2/fpm/pool.d/staging.conf`, socket `/run/php/php8.2-fpm-staging.sock`, `pm.max_children=3` |
| Base de datos | `botica_staging` + usuario MySQL `staging` (credenciales en `/root/staging-db.env`) |
| `.env` | `APP_ENV=production`, `APP_DEBUG=true`, `APP_URL` de staging, `QUEUE_CONNECTION=sync` |
| Certificado | Let's Encrypt propio, independiente del de producción |
| Indexación | `X-Robots-Tag: noindex, nofollow` |
| Cron / colas | ninguno |

Uso:

1. Restaurar el último dump en `botica_staging` (probado: 58 tablas en 3 s).
2. `git fetch && git checkout <rama>` en `/var/www/botica-staging`.
3. `php artisan migrate --pretend` → `php artisan migrate --force`.
4. Pasar el checklist de §2.3 y, si se tocó inventario, `php artisan inventario:conciliar`.

Advertencias: Cloudinary es la misma cuenta (las imágenes subidas en staging conviven con las de producción) y json.pe consume tokens reales.

**Auto-deploy desde GitHub.** `staging-autodeploy.timer` ejecuta cada 2 minutos `staging-autodeploy.service` (Usuario `staging`), que corre `/usr/local/bin/staging-autodeploy.sh`:

1. Solo actúa sobre la rama activa en `/var/www/botica-staging`; si es `master`/`main`, no hace nada.
2. `git fetch` de esa rama; si no hay commits nuevos, termina sin tocar nada.
3. Backup de `botica_staging` en `/var/backups/staging/` (conserva 5).
4. `git reset --hard origin/<rama>`, `composer install` si cambió `composer.json/lock`, `migrate --force`, `npm ci && build` si cambió el frontend, `optimize`.
5. Si un paso falla, revierte el código al commit anterior y lo registra.

Log: `/var/www/botica-staging/storage/logs/autodeploy.log` · Forzar: `sudo systemctl start staging-autodeploy.service` · Cambiar de rama: `sudo -u staging git -C /var/www/botica-staging checkout <rama>`.

Los cambios locales dentro de staging se descartan en cada despliegue (`reset --hard`): staging siempre refleja GitHub.

### 18.3. Pendiente

- **Contraseña de `admin@sistema.com`: cambiada el 21/11/2026** (verificada con login real; `admin123` ya no funciona).
- Desactivar `vendedor@sistema.com` si no se usa (sigue con `vendedor123`).
- Ensayo de restauración: **hecho** en `botica_staging` (58 tablas, `inventario:conciliar` sin diferencias).
- Nuevos subdominios: crear su `server` block, su pool PHP-FPM, su usuario y su base; luego `certbot --nginx -d <subdominio>`.
- `storage/fonts/` ya está en `.gitignore` local; falta commit + pull para limpiar el `git status` del servidor.

---

*Guía de despliegue mantenida junto al código. Si cambia el stack, los servicios externos o los requisitos de PHP, actualice este documento.*
