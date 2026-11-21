# Chuleta de comandos — VPS Botica

> Referencia operativa. Todo lo de aquí se ejecuta **en el servidor** salvo lo marcado como *(en tu PC)*.
> Última actualización: 21/11/2026.

---

## 0. Datos clave

| Qué | Dónde |
|---|---|
| VPS | `deploy@109.123.241.250` |
| Dominio | `https://bonifarmachiclayo.com` (apex; `www` → 301) |
| Staging | `https://staging.bonifarmachiclayo.com` — Basic Auth usuario `staging`, contraseña en `/root/staging-basic-pass.txt` |
| Producción | `/var/www/botica` (usuario `deploy`) |
| Staging | `/var/www/botica-staging` (usuario `staging`) |
| BD producción | `botica` / `botica_user` (credenciales en `.env`) |
| BD staging | `botica_staging` / `staging` (`/root/staging-db.env`) |
| Certificados | `/etc/letsencrypt/live/*` (prod y staging) |
| Backups en VPS | `/var/backups/botica` (producción) y `/var/backups/staging` |
| Backups en tu PC | `D:\BackupsBotica` |
| Logs | `/home/deploy/deploy.log`, `/var/backups/botica/backup.log`, `/var/www/botica-staging/storage/logs/autodeploy.log` |

**Regla de oro:** en producción (`/var/www/botica`) todo se hace como `deploy`. En staging, **todo** con `sudo -u staging` (`deploy` no es dueño de `/var/www/botica-staging` y git lo rechaza con *dubious ownership*).

---

## 1. Conectar

```bash
ssh deploy@109.123.241.250
sudo -i                       # shell de root (pide la contraseña de deploy)
```

*(en tu PC)* cargar la clave en el agente tras un reinicio:

```powershell
ssh-add $env:USERPROFILE\.ssh\id_ed25519
```

---

## 2. Estado en 10 segundos

```bash
cd /var/www/botica
systemctl is-active nginx php8.2-fpm mysql cron
echo -n "web: "; curl -s -o /dev/null -w '%{http_code}\n' --resolve bonifarmachiclayo.com:443:127.0.0.1 https://bonifarmachiclayo.com/up
echo -n "version: "; git log -1 --pretty='%h %s'
df -h / | tail -1
free -h | head -2
echo -n "errores hoy: "; grep -c ERROR storage/logs/laravel-$(date +%F).log 2>/dev/null || echo 0
```

---

## 3. Desplegar a producción

```bash
cd /var/www/botica
./deploy.sh --dry-run      # ver el plan, no cambia nada
./deploy.sh                # desplegar (pide confirmaciones)
```

Variantes: `--si` (sin preguntas), `--forzar` (ignora caja abierta), `--sin-build`.

---

## 4. Staging

```bash
# ver qué hay desplegado
sudo -u staging git -C /var/www/botica-staging log -1 --oneline

# cambiar de rama (el auto-deploy la sigue)
sudo -u staging git -C /var/www/botica-staging fetch origin
sudo -u staging git -C /var/www/botica-staging checkout feature/otra-rama

# desplegar ya, sin esperar los 2 minutos
sudo systemctl start staging-autodeploy.service

# log
sudo tail -30 /var/www/botica-staging/storage/logs/autodeploy.log

# pausar / reanudar el automático
sudo systemctl stop staging-autodeploy.timer
sudo systemctl start staging-autodeploy.timer
systemctl list-timers staging-autodeploy.timer

# cambiar la contraseña de acceso a staging
sudo bash -c 'H=$(openssl passwd -apr1 "NUEVA_CLAVE"); echo "staging:$H" > /etc/nginx/.htpasswd-staging; echo NUEVA_CLAVE > /root/staging-basic-pass.txt'
```

---

## 5. Backups

```bash
# forzar backup de producción
/home/deploy/backup-botica.sh && tail -1 /var/backups/botica/backup.log

# listar y verificar el último
ls -lh /var/backups/botica/*.sql.gz | tail -5
gzip -t "$(ls -t /var/backups/botica/*.sql.gz | head -1)" && echo "integro"
```

*(en tu PC)* traer el último al disco `D:`:

```powershell
powershell -ExecutionPolicy Bypass -File D:\BackupsBotica\sync-botica.ps1
Get-Content D:\BackupsBotica\sync.log -Tail 5
```

### Restaurar producción (con el sitio cerrado)

```bash
cd /var/www/botica
php artisan down --secret=restauro
gunzip -c /var/backups/botica/ARCHIVO.sql.gz | mysql --defaults-file=/home/deploy/.my.cnf botica
php artisan optimize:clear && php artisan optimize
php artisan up
```

### Restaurar staging

```bash
sudo -u staging bash -c 'gunzip -c /var/backups/botica/ARCHIVO.sql.gz | mysql --defaults-file=/home/staging/.my.cnf botica_staging'
```

---

## 6. Certificados (TLS)

```bash
sudo certbot certificates                        # ver dominios y vencimiento
sudo certbot renew --dry-run                     # probar la renovación
sudo certbot renew                               # renovar ahora
sudo certbot renew --force-renewal               # forzar (si quedó vencido)
systemctl list-timers certbot.timer              # renovación automática

# fecha real del certificado que sirve nginx
echo | openssl s_client -servername bonifarmachiclayo.com -connect 127.0.0.1:443 2>/dev/null | openssl x509 -noout -dates
```

Emitir para un subdominio nuevo:

```bash
sudo certbot --nginx -d nuevo.bonifarmachiclayo.com --redirect
```

---

## 7. nginx

```bash
sudo nginx -t                    # validar configuración (siempre antes de recargar)
sudo systemctl reload nginx      # recarga sin cortar conexiones
sudo systemctl status nginx
ls -l /etc/nginx/sites-enabled/
sudo tail -50 /var/log/nginx/error.log
sudo tail -20 /var/log/nginx/access.log
```

---

## 8. PHP-FPM

```bash
sudo php-fpm8.2 -t
sudo systemctl reload php8.2-fpm     # preferible
sudo systemctl restart php8.2-fpm    # corta peticiones en curso
ls -l /run/php/*.sock
sudo tail -30 /var/log/php8.2-fpm.log
```

---

## 9. Laravel (producción)

```bash
cd /var/www/botica
php artisan about
php artisan optimize:clear && php artisan optimize
php artisan migrate:status | tail -5
php artisan migrate --pretend --force          # qué SQL correría (no ejecuta)
php artisan down --secret=miacceso             # mantenimiento (bypass en /miacceso)
php artisan up
tail -50 storage/logs/laravel-$(date +%F).log
grep -h ERROR storage/logs/laravel-*.log | tail -10
```

> Nunca en producción: `migrate:fresh`, `migrate:refresh`, `migrate:rollback`, `db:wipe`, `db:seed` sin `--class`.

---

## 10. MySQL

```bash
mysql --defaults-file=/home/deploy/.my.cnf botica -e "select count(*) from producto"
mysql --defaults-file=/home/deploy/.my.cnf botica -e "select count(*) from caja where estado='abierta'"
mysql --defaults-file=/home/deploy/.my.cnf -e "select table_schema, round(sum(data_length+index_length)/1024/1024,1) as MB from information_schema.tables group by 1"
mysql --defaults-file=/home/deploy/.my.cnf -e "show processlist"

# integridad de inventario (stock vs lotes)
cd /var/www/botica && php artisan inventario:conciliar
```

---

## 11. Incidentes: qué mirar primero

| Síntoma | Primer comando | Causa habitual |
|---|---|---|
| **502 Bad Gateway** | `systemctl status php8.2-fpm; ls -l /run/php/` | PHP-FPM caído o socket inexistente |
| **500** | `tail -50 storage/logs/laravel-$(date +%F).log` | Error de aplicación |
| **503** | `ls storage/framework/down` | Quedó en mantenimiento → `php artisan up` |
| **No responde / timeout** | `systemctl status nginx; ss -ltn; sudo ufw status` | nginx caído, puerto cerrado o firewall |
| **Sin estilos / JS roto** | `ls -l public/build/manifest.json` | Build ausente o `public/hot` presente |
| **Aviso de certificado** | `sudo certbot certificates` | Certificado vencido → `certbot renew --force-renewal` |
| **Disco lleno** | `df -h /; du -sh /var/log/* \| sort -h \| tail` | Logs |
| **Muy lento** | `free -h; uptime; show processlist` | RAM/CPU o consultas largas |

---

## 12. Rollback

```bash
cd /var/www/botica
git log --oneline -5
git status --porcelain          # debe estar vacío
git reset --hard <commit_anterior>
composer install --no-dev --optimize-autoloader
php artisan optimize:clear && php artisan optimize
```

Si además hay que revertir datos: restaurar el backup (sección 5) **y** volver el código al commit de esa fecha.

---

## 13. Seguridad

```bash
sudo ufw status verbose
ss -ltn
sudo last -n 10
sudo grep -c "Failed password" /var/log/auth.log 2>/dev/null
cat ~/.ssh/authorized_keys
sudo ls -l /etc/sudoers.d/
```

Quitar sudo temporal:

```bash
sudo rm -f /etc/sudoers.d/90-deploy-nopass && sudo -n true 2>/dev/null && echo "AUN ACTIVO" || echo "quitado"
```

---

## 14. Errores que se repiten

| Error | Causa | Solución |
|---|---|---|
| `dubious ownership ... /var/www/botica-staging` | git como `deploy` en repo de `staging` | usar `sudo -u staging git -C ...` |
| `untracked working tree files would be overwritten` | archivo creado a mano que también viene del repo | `rm` del archivo y `git pull` |
| `Nothing to migrate` | no hay migraciones nuevas | normal |
| `migrate:pretend is not defined` | el flag es `--pretend` | `php artisan migrate --pretend` |
| 503 tras un deploy | quedó en mantenimiento | `php artisan up` |
| `Access denied ... PROCESS privilege` | mysqldump sobre MySQL 8 | añadir `--no-tablespaces` |
| Assets sin cargar | falta `public/build` o hay `public/hot` | `npm ci && npm run build` |

---

## 15. Lo que nunca se hace

1. `php artisan migrate:fresh` / `refresh` / `db:wipe` en producción.
2. `migrate:rollback` en producción.
3. Editar o renombrar una migración ya aplicada.
4. Editar archivos del repo por SSH (rompe `git pull --ff-only`).
5. `git push --force` a `master`.
6. Desplegar con la caja abierta.
7. Desplegar sin backup verificado.
8. Restaurar un backup con el sitio abierto.

---

## 16. fail2ban (baneo de IPs)

```bash
sudo fail2ban-client status                 # jails activos
sudo fail2ban-client status sshd            # estado del jail SSH
sudo fail2ban-client get sshd ignoreip      # IPs nunca baneadas
sudo fail2ban-client set sshd unbanip 1.2.3.4
sudo fail2ban-client set sshd banip   1.2.3.4   # prueba manual
sudo tail -30 /var/log/fail2ban.log
cat /etc/fail2ban/jail.local
```

Reglas actuales: `maxretry = 4`, `findtime = 10m`, `bantime = 1h`, `ignoreip` incluye la IP del dueño.

> En Ubuntu 24.04 fail2ban banea con **nftables** (`table inet f2b-table`), no con ufw ni iptables. Para ver los baneos:
> `sudo nft list ruleset | grep -A5 f2b`

> Si cambia la IP pública del dueño (IP dinámica), hay que actualizar `ignoreip` en `/etc/fail2ban/jail.local` y reiniciar el servicio; si no, podría autobanearse. La salida de emergencia es siempre la consola VNC de Contabo.

Los intentos de login web **no** pasan por fail2ban: los frena el rate limit de Laravel (5/min por email+IP, 20/min por IP).
