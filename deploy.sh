#!/usr/bin/env bash
# ============================================================================
#  deploy.sh — Despliegue MANUAL a produccion (bonifarmachiclayo.com)
#
#  Uso:
#    ./deploy.sh --dry-run     # solo muestra que haria, no toca nada
#    ./deploy.sh               # despliegue real (pide confirmaciones)
#    ./deploy.sh --si          # sin preguntas (para automatizar despues)
#    ./deploy.sh --forzar      # ignora el aviso de caja abierta
#    ./deploy.sh --sin-build   # no recompila assets
#
#  Nunca ejecuta: migrate:fresh, migrate:refresh, migrate:rollback, db:wipe.
#  Solo aplica migraciones PENDIENTES (las que no estan en la tabla migrations).
# ============================================================================

set -uo pipefail
trap '' HUP   # si se cae el SSH, el despliegue termina en vez de morir a medias

REPO=/var/www/botica
DOMINIO=bonifarmachiclayo.com
LOG=/home/deploy/deploy.log
PHP=/usr/bin/php
COMPOSER=/usr/local/bin/composer
MYSQL_CNF=/home/deploy/.my.cnf
RAMAS_PROTEGIDAS_PULL="master"

DRY=0; SI=0; FORZAR=0; SIN_BUILD=0
for arg in "$@"; do
    case "$arg" in
        --dry-run)   DRY=1 ;;
        --si)        SI=1 ;;
        --forzar)    FORZAR=1 ;;
        --sin-build) SIN_BUILD=1 ;;
        -h|--help)   sed -n '2,16p' "$0" | sed 's/^#//'; exit 0 ;;
        *)           echo "Opcion desconocida: $arg"; exit 2 ;;
    esac
done

EN_MANTENIMIENTO=0
PREV=""
BK=""
SECRETO=""

log()  { printf '%s  %s\n' "$(date '+%F %T')" "$*" | tee -a "$LOG"; }
paso() { log ""; log ">> $*"; }
morir(){ log "ABORTADO: $*"; exit 1; }

# Si el script muere por cualquier motivo, nunca dejamos el sitio en mantenimiento
al_salir() {
    if [ "$EN_MANTENIMIENTO" -eq 1 ]; then
        $PHP artisan up >>"$LOG" 2>&1
        log "Mantenimiento desactivado (salida del script)"
    fi
}
trap al_salir EXIT

confirma() {
    [ "$SI" -eq 1 ] && return 0
    [ -t 0 ] || return 1
    local r
    read -r -p "$1 [escribe '$2']: " r </dev/tty
    [ "$r" = "$2" ]
}

# ---------------------------------------------------------------- preflight ---
paso "PREFLIGHT (solo lectura)"
cd "$REPO" || morir "no existe $REPO"

RAMA=$(git rev-parse --abbrev-ref HEAD)
[ "$RAMA" = "master" ] || morir "la rama activa es '$RAMA', debe ser 'master'"
log "rama: $RAMA"

SUCIO=$(git status --porcelain --untracked-files=no)
[ -z "$SUCIO" ] || morir "hay cambios sin commitear en el servidor:
$SUCIO"
log "arbol de trabajo limpio"

[ -f public/hot ] && morir "existe public/hot (rompe los assets en produccion)"

git fetch --quiet origin master || morir "git fetch fallo"
PREV=$(git rev-parse HEAD)
REMOTO=$(git rev-parse origin/master)

if [ "$PREV" = "$REMOTO" ]; then
    log "Produccion ya esta en $(git log -1 --pretty='%h %s') — nada que desplegar"
    exit 0
fi

log "commits a desplegar: $(git rev-list --count "$PREV..$REMOTO")"
git log --oneline "$PREV..$REMOTO" | sed 's/^/    /' | tee -a "$LOG" >/dev/null

CAMBIOS=$(git diff --name-only "$PREV" "$REMOTO")
log "archivos modificados: $(echo "$CAMBIOS" | wc -l)"

MIGS=$(git diff --name-only "$PREV" "$REMOTO" -- database/migrations | sort)
RIESGOS=""
if [ -n "$MIGS" ]; then
    log "migraciones nuevas:"
    for f in $MIGS; do
        cuerpo=$(git show "$REMOTO:$f" | awk '/function up\(\)/,/function down\(\)/')
        ops=$(echo "$cuerpo" | grep -oE 'dropColumn|dropTable|dropIfExists|renameColumn|truncate|->change\(' | sort -u | tr '\n' ' ')
        if [ -n "$ops" ]; then
            log "    $f   <<< RIESGOSA: $ops"
            RIESGOS="$RIESGOS$f: $ops
"
        else
            log "    $f"
        fi
    done
else
    log "migraciones nuevas: ninguna"
fi

CAJAS=$(mysql --defaults-file="$MYSQL_CNF" -N -B botica -e "select count(*) from caja where estado='abierta'" 2>/dev/null || echo "?")
log "cajas abiertas: $CAJAS"
if [ "$CAJAS" != "0" ]; then
    [ "$FORZAR" -eq 1 ] && log "AVISO: se continua con caja abierta por --forzar" \
                        || morir "hay $CAJAS caja(s) abierta(s). Cierralas o usa --forzar"
fi

LIBRE_MB=$(( $(df --output=avail -k "$REPO" | tail -1) / 1024 ))
[ "$LIBRE_MB" -ge 2048 ] || morir "solo ${LIBRE_MB} MB libres (minimo 2048)"
log "disco libre: ${LIBRE_MB} MB"

if [ "$DRY" -eq 1 ]; then
    log ""
    log "DRY-RUN: todo listo, no se ha cambiado nada."
    [ -n "$RIESGOS" ] && log "OJO: hay migraciones riesgosas (ver arriba)"
    exit 0
fi

# ------------------------------------------------------------- confirmacion ---
if [ -n "$RIESGOS" ]; then
    log ""
    log "==> ATENCION: hay migraciones con operaciones destructivas:"
    printf '%s' "$RIESGOS" | sed 's/^/      /' | tee -a "$LOG"
    confirma "Vas a BORRAR datos de produccion. Escribe 'si-borrar' para continuar" "si-borrar" \
        || morir "cancelado por el operador"
fi

confirma "Desplegar $(git rev-parse --short "$REMOTO") a PRODUCCION" "desplegar" \
    || morir "cancelado por el operador"

# ------------------------------------------------------------------ backup ---
paso "BACKUP VERIFICADO"
BK="/var/backups/botica/pre-deploy-$(date +%F_%H%M%S).sql.gz"
mysqldump --defaults-file="$MYSQL_CNF" --single-transaction --quick --routines --triggers \
    botica 2>>"$LOG" | gzip > "$BK" || morir "mysqldump fallo"
gzip -t "$BK" || morir "el backup no pasa gzip -t"
TABLAS_BK=$(zcat "$BK" | grep -c '^CREATE TABLE')
TABLAS_DB=$(mysql --defaults-file="$MYSQL_CNF" -N -B -e "select count(*) from information_schema.tables where table_schema='botica'")
[ "$TABLAS_BK" -eq "$TABLAS_DB" ] || morir "backup incompleto: $TABLAS_BK tablas vs $TABLAS_DB en la base"
log "backup OK: $(basename "$BK") — $TABLAS_BK tablas, $(du -h "$BK" | cut -f1)"

# ------------------------------------------------- dependencias (sin tocar) ---
if echo "$CAMBIOS" | grep -qE '^composer\.(json|lock)$'; then
    paso "COMPOSER INSTALL (el sitio sigue arriba)"
    $COMPOSER install --no-dev --optimize-autoloader --no-interaction >>"$LOG" 2>&1 \
        || morir "composer fallo (nada se ha desplegado)"
    log "composer OK"
fi

# ------------------------------------------------------------ mantenimiento ---
paso "MODO MANTENIMIENTO"
SECRETO=$(head -c 32 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | cut -c1-16)
$PHP artisan down --retry=60 --secret="$SECRETO" >>"$LOG" 2>&1 || morir "no se pudo activar mantenimiento"
EN_MANTENIMIENTO=1
log "mantenimiento activo. Acceso de verificacion: https://$DOMINIO/?secret=$SECRETO"

rollback() {
    log "FALLO en: $1"
    log "Iniciando rollback de CODIGO al commit ${PREV:0:7}"
    git reset --hard "$PREV" >>"$LOG" 2>&1
    if echo "$CAMBIOS" | grep -qE '^composer\.(json|lock)$'; then
        $COMPOSER install --no-dev --optimize-autoloader --no-interaction >>"$LOG" 2>&1
    fi
    $PHP artisan optimize:clear >>"$LOG" 2>&1
    $PHP artisan optimize >>"$LOG" 2>&1
    $PHP artisan up >>"$LOG" 2>&1
    EN_MANTENIMIENTO=0
    log "Rollback aplicado. El sitio esta arriba con el codigo anterior."
    log "Si la migracion fallo a medias, el esquema puede estar inconsistente."
    log "Para restaurar datos:  php artisan down && gunzip -c $BK | mysql botica && php artisan up"
    exit 1
}

# ------------------------------------------------------------------ deploy ---
paso "GIT PULL"
git pull --ff-only >>"$LOG" 2>&1 || rollback "git pull --ff-only"
log "codigo en $(git rev-parse --short HEAD)"

paso "MIGRACIONES"
$PHP artisan migrate --force >>"$LOG" 2>&1 || rollback "migrate"
log "migraciones aplicadas"

if [ "$SIN_BUILD" -eq 0 ] && echo "$CAMBIOS" | grep -qE '^(package(-lock)?\.json|vite\.config\.js|resources/|tailwind)'; then
    paso "ASSETS (npm ci && npm run build)"
    (npm ci && npm run build) >>"$LOG" 2>&1 || rollback "npm build"
    [ -f public/build/manifest.json ] || rollback "no se genero public/build/manifest.json"
    log "assets compilados: $(ls public/build/assets | wc -l) archivos"
fi

paso "CACHES Y PERMISOS"
$PHP artisan optimize:clear >>"$LOG" 2>&1 || true
$PHP artisan optimize >>"$LOG" 2>&1 || rollback "optimize"
chown -R deploy:www-data storage bootstrap/cache >>"$LOG" 2>&1 || true
log "caches reconstruidas"

# ------------------------------------------------- verificacion pre-apertura ---
paso "VERIFICACION (con el sitio aun en mantenimiento)"
JAR=$(mktemp)
curl -s -c "$JAR" -o /dev/null --resolve "$DOMINIO:443:127.0.0.1" "https://$DOMINIO/?secret=$SECRETO"
CODIGO=$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' --resolve "$DOMINIO:443:127.0.0.1" "https://$DOMINIO/")
rm -f "$JAR"
[ "$CODIGO" = "200" ] || rollback "la home no responde 200 con el secret (obtuvo $CODIGO)"
log "home interna: 200"

ERRORES=$(grep -c 'ERROR' storage/logs/laravel-"$(date +%F)".log 2>/dev/null || echo 0)
log "errores en el log de hoy: $ERRORES"

paso "ABRIENDO AL PUBLICO"
$PHP artisan up >>"$LOG" 2>&1 || rollback "artisan up"
EN_MANTENIMIENTO=0

sleep 1
for ruta in / /login /up; do
    C=$(curl -s -o /dev/null -w '%{http_code}' --resolve "$DOMINIO:443:127.0.0.1" "https://$DOMINIO$ruta")
    log "  $ruta -> $C"
    [ "$C" = "200" ] || log "  AVISO: $ruta devolvio $C"
done

log ""
log "DESPLIEGUE OK: $(git log -1 --pretty='%h %s')"
log "backup previo: $BK"
exit 0
