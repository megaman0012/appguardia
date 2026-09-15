#!/usr/bin/env bash
# =============================================================================
# respaldo.sh — Respaldo diario de Total Secure App
#
# Empaqueta lo que NO se puede reconstruir si este servidor se pierde:
#
#   1. La base PostgreSQL `coredt360` (880 personas, 12.664 biometrías).
#   2. La base MariaDB heredada de la V1 (contenedor `v1_analisis`), que no
#      pertenece a ningún compose y no la respalda nadie más.
#   3. Los secretos y la configuración: .env, docker-compose.yml y los dos
#      nginx (el del contenedor y el del host).
#   4. El **keystore de firma del APK**. Sin él no se puede publicar una
#      actualización de la app nunca más: habría que desinstalarla de cada
#      tablet, y eso borra los datos locales de cada una.
#
# Las fotos van aparte, por rsync incremental: son 1,3 GB que casi no cambian
# —una foto subida no se edita— así que meterlas en el .tar.gz de cada día
# serían 1,3 GB diarios de lo mismo.
#
# -----------------------------------------------------------------------------
# ⚠️ ESTO NO ES UN RESPALDO COMPLETO TODAVÍA
# -----------------------------------------------------------------------------
# Todo queda EN ESTE MISMO SERVIDOR. Sirve contra un borrado accidental, una
# migración que sale mal o una tabla que se corrompe. NO sirve si se pierde el
# servidor, que es justo el escenario en el que el keystore no se puede
# recuperar.
#
# Para que sea un respaldo de verdad hay que definir DESTINO_EXTERNO (abajo)
# apuntando a otra máquina. Mientras esté vacío, el script avisa en cada corrida.
#
# -----------------------------------------------------------------------------
# QUIÉN LO EJECUTA
# -----------------------------------------------------------------------------
# El usuario dueño del repositorio y miembro del grupo `docker` (server-dt). No
# root: no hace falta. El paquete lleva el .env con la contraseña de la base y
# el keystore de firma, así que el script (700), el directorio (700) y cada
# .tar.gz (600) quedan solo para su dueño.
#
# -----------------------------------------------------------------------------
# DÓNDE SE GUARDA, Y POR QUÉ NO EN EL REPOSITORIO
# -----------------------------------------------------------------------------
# En ~/respaldos/totalsecureapp, FUERA del árbol del proyecto. En este repo el
# árbol de trabajo es también producción, y ya se coló un dump de la base en un
# commit y un .zip de 63 MB que llegó a GitHub y dejó el repo sin poder empujar.
# Un respaldo dentro del repo es ese accidente esperando repetirse.
#
# -----------------------------------------------------------------------------
# COMPORTAMIENTO ANTE ERRORES
# -----------------------------------------------------------------------------
# Ningún paso aborta el script: si uno falla se registra y se intentan los
# demás, para no perder el respaldo entero por una falla parcial. El resumen
# final lista qué falló.
#
# Códigos de salida:  0 = todo bien   1 = uno o más pasos fallaron
# =============================================================================

set -uo pipefail   # -e a propósito NO: cada paso se evalúa por separado.

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
BACKEND="$(cd -- "$SCRIPT_DIR/.." && pwd)"
REPO_RAIZ="$(cd -- "$BACKEND/../.." && pwd)"

DIR_RESPALDOS="${DIR_RESPALDOS:-$HOME/respaldos/totalsecureapp}"
DIR_FOTOS="$DIR_RESPALDOS/fotos"
DIR_LOG="$DIR_RESPALDOS/log"
ARCHIVO_LOG="$DIR_LOG/respaldo.log"

CONTENEDOR_DB="${CONTENEDOR_DB:-ts_db}"
CONTENEDOR_V1="${CONTENEDOR_V1:-v1_analisis}"

# Días que se conservan los paquetes diarios.
RETENCION_DIAS="${RETENCION_DIAS:-14}"

# Copia fuera del servidor. Formato de rsync: "usuario@host:/ruta".
# Vacío = solo local, y el script lo avisa en cada corrida.
DESTINO_EXTERNO="${DESTINO_EXTERNO:-}"

FECHA="$(date +%Y-%m-%d)"
HORA="$(date +%H%M)"

declare -a FALLOS=()

mkdir -p "$DIR_LOG" "$DIR_FOTOS" 2>/dev/null
chmod 700 "$DIR_RESPALDOS" "$DIR_LOG" 2>/dev/null

log() {
  printf '%s\n' "$(date '+%Y-%m-%d %H:%M:%S')  $*" | tee -a "$ARCHIVO_LOG"
}

fallo() {
  FALLOS+=("$1: $2")
  log "  [ERROR] $1 — $2"
}

# Lee una clave del .env sin ejecutarlo.
#
# Deliberadamente NO se hace `source`: eso EJECUTA el archivo, y un valor con
# $(...) o backticks correría como comando. Este lector solo extrae texto.
leer_env() {
  local clave="$1" archivo="$2"
  [[ -f "$archivo" ]] || return 1
  sed -n -E "s/^[[:space:]]*${clave}[[:space:]]*=[[:space:]]*(.*)$/\1/p" "$archivo" \
    | tail -n1 \
    | sed -E "s/^\"(.*)\"$/\1/; s/^'(.*)'$/\1/"
}

corriendo() {
  docker inspect -f '{{.State.Running}}' "$1" 2>/dev/null | grep -q true
}

copiar() {
  local origen="$1" destino="$2" paso="$3"
  if [[ ! -e "$origen" ]]; then
    fallo "$paso" "no existe $origen"
    return 1
  fi
  if cp -p "$origen" "$destino" 2>/dev/null; then
    log "  [OK] ${origen#"$REPO_RAIZ"/}"
    return 0
  fi
  fallo "$paso" "no se pudo copiar $origen"
  return 1
}

log "═══════════════════════════════════════════════════════════════"
log "Respaldo de Total Secure App (usuario: $(id -un))"

# Nunca se sobrescribe un respaldo previo: si ya hay uno de hoy (una corrida
# manual además de la del cron) el nuevo lleva sufijo de hora.
NOMBRE="totalsecureapp_${FECHA}"
if [[ -e "$DIR_RESPALDOS/${NOMBRE}.tar.gz" ]]; then
  NOMBRE="totalsecureapp_${FECHA}_${HORA}"
  n=2
  while [[ -e "$DIR_RESPALDOS/${NOMBRE}.tar.gz" ]]; do
    NOMBRE="totalsecureapp_${FECHA}_${HORA}-${n}"
    n=$(( n + 1 ))
  done
  log "Ya existía un respaldo de hoy; este será ${NOMBRE}.tar.gz"
fi
ARCHIVO_FINAL="$DIR_RESPALDOS/${NOMBRE}.tar.gz"

STAGE="$(mktemp -d)" || { log "[FATAL] No se pudo crear el directorio temporal."; exit 1; }
chmod 700 "$STAGE"
PAQUETE="$STAGE/$NOMBRE"
mkdir -p "$PAQUETE"
trap 'rm -rf "$STAGE"' EXIT

ENV_BACKEND="$BACKEND/.env"
DB_NAME="$(leer_env DB_DATABASE "$ENV_BACKEND")"; DB_NAME="${DB_NAME:-coredt360}"
DB_USER="$(leer_env DB_USERNAME "$ENV_BACKEND")"; DB_USER="${DB_USER:-totalsecure}"

# -----------------------------------------------------------------------------
# Paso 1: base PostgreSQL
# -----------------------------------------------------------------------------
# NO se cierran las conexiones activas antes del dump, al revés de lo que hace
# el respaldo de la ticketera. `pg_dump` abre una transacción con snapshot
# consistente: ve la base entera a un instante fijo aunque se siga escribiendo.
# Cortar conexiones acá significaría interrumpir a un guardia a mitad de un
# marcaje para obtener exactamente el mismo resultado.
#
# Formato `custom` (-Fc): ya viene comprimido y permite restaurar una sola
# tabla con pg_restore, que es lo que hace falta cuando se rompe una tabla y no
# la base entera.
log "Paso 1: dump de PostgreSQL '$DB_NAME'..."
if ! corriendo "$CONTENEDOR_DB"; then
  fallo "Paso 1 (PostgreSQL)" "el contenedor '$CONTENEDOR_DB' no está corriendo"
else
  if docker exec "$CONTENEDOR_DB" pg_dump -U "$DB_USER" -d "$DB_NAME" -Fc \
      > "$PAQUETE/${DB_NAME}.dump" 2>"$STAGE/pg.err"; then
    log "  [OK] ${DB_NAME}.dump ($(du -h "$PAQUETE/${DB_NAME}.dump" | cut -f1))"
  else
    fallo "Paso 1 (PostgreSQL)" "pg_dump falló: $(tail -n2 "$STAGE/pg.err" | tr '\n' ' ')"
    rm -f "$PAQUETE/${DB_NAME}.dump"
  fi
fi

# -----------------------------------------------------------------------------
# Paso 2: base MariaDB de la V1
# -----------------------------------------------------------------------------
# `v1_analisis` no pertenece a ningún docker-compose: se creó a mano y no se
# recrea solo. Si se pierde, se pierde el histórico de la versión anterior.
log "Paso 2: dump de MariaDB (V1)..."
if ! corriendo "$CONTENEDOR_V1"; then
  log "  [AVISO] '$CONTENEDOR_V1' no está corriendo, se omite."
else
  V1_PASS="$(docker inspect "$CONTENEDOR_V1" \
    --format '{{range .Config.Env}}{{println .}}{{end}}' 2>/dev/null \
    | sed -n -E 's/^MARIADB_ROOT_PASSWORD=(.*)$/\1/p' | head -n1)"
  V1_DB="$(docker inspect "$CONTENEDOR_V1" \
    --format '{{range .Config.Env}}{{println .}}{{end}}' 2>/dev/null \
    | sed -n -E 's/^MARIADB_DATABASE=(.*)$/\1/p' | head -n1)"

  if [[ -z "$V1_PASS" || -z "$V1_DB" ]]; then
    fallo "Paso 2 (MariaDB V1)" "no se pudieron leer las credenciales del contenedor"
  elif docker exec -e MYSQL_PWD="$V1_PASS" "$CONTENEDOR_V1" \
        mysqldump -u root --single-transaction --databases "$V1_DB" \
        2>"$STAGE/v1.err" | gzip > "$PAQUETE/v1_${V1_DB}.sql.gz"; then
    log "  [OK] v1_${V1_DB}.sql.gz ($(du -h "$PAQUETE/v1_${V1_DB}.sql.gz" | cut -f1))"
  else
    fallo "Paso 2 (MariaDB V1)" "mysqldump falló: $(tail -n2 "$STAGE/v1.err" | tr '\n' ' ')"
    rm -f "$PAQUETE/v1_${V1_DB}.sql.gz"
  fi
fi

# -----------------------------------------------------------------------------
# Paso 3: secretos y configuración
# -----------------------------------------------------------------------------
# El .env lleva APP_KEY, que cifra los QR de las rondas y las claves guardadas
# en la tabla `configuracion`. Restaurar la base sin esa misma APP_KEY deja esos
# valores ilegibles.
log "Paso 3: secretos y configuración..."
mkdir -p "$PAQUETE/config"
copiar "$ENV_BACKEND"                                  "$PAQUETE/config/backend.env"            "Paso 3 (config)"
copiar "$BACKEND/docker-compose.yml"                   "$PAQUETE/config/docker-compose.yml"     "Paso 3 (config)"
copiar "$BACKEND/docker/nginx/default.conf"            "$PAQUETE/config/nginx-contenedor.conf"  "Paso 3 (config)"
copiar "$BACKEND/deploy/nginx-host-totalsecureapp.conf" "$PAQUETE/config/nginx-host.conf"       "Paso 3 (config)"

# -----------------------------------------------------------------------------
# Paso 4: keystore de firma
# -----------------------------------------------------------------------------
# Lo único verdaderamente irremplazable del paquete.
log "Paso 4: keystore de firma del APK..."
mkdir -p "$PAQUETE/keystore"
copiar "$REPO_RAIZ/apk/totalsecureapp-release.jks" "$PAQUETE/keystore/" "Paso 4 (keystore)"
copiar "$REPO_RAIZ/apk/LEEME-keystore.txt"         "$PAQUETE/keystore/" "Paso 4 (keystore)"

# -----------------------------------------------------------------------------
# Paso 5: empaquetar
# -----------------------------------------------------------------------------
log "Paso 5: empaquetando..."
if tar -czf "$ARCHIVO_FINAL" -C "$STAGE" "$NOMBRE" 2>"$STAGE/tar.err"; then
  chmod 600 "$ARCHIVO_FINAL"
  log "  [OK] ${NOMBRE}.tar.gz ($(du -h "$ARCHIVO_FINAL" | cut -f1))"
else
  fallo "Paso 5 (empaquetar)" "tar falló: $(tail -n2 "$STAGE/tar.err" | tr '\n' ' ')"
fi

# -----------------------------------------------------------------------------
# Paso 6: fotos, por espejo incremental
# -----------------------------------------------------------------------------
# 1,3 GB que casi no cambian. rsync copia solo lo nuevo, así que la primera
# corrida tarda y las siguientes son segundos.
#
# SIN --delete a propósito: si alguien borra fotos del servidor por error, el
# espejo las conserva. Es la diferencia entre un respaldo y un espejo.
log "Paso 6: fotos (incremental)..."
if [[ -d "$BACKEND/public/images" ]]; then
  if rsync -a --info=stats2 "$BACKEND/public/images/" "$DIR_FOTOS/" >"$STAGE/rsync.out" 2>&1; then
    # rsync preserva los permisos del origen, y en `public/` son legibles por
    # todos porque los sirve nginx. Acá son las fotos de biometría y accesos de
    # 880 personas fuera de ese contexto: el espejo se cierra al dueño.
    chmod -R go-rwx "$DIR_FOTOS" 2>/dev/null
    log "  [OK] fotos sincronizadas ($(du -sh "$DIR_FOTOS" 2>/dev/null | cut -f1) en total)"
  else
    fallo "Paso 6 (fotos)" "rsync falló: $(tail -n2 "$STAGE/rsync.out" | tr '\n' ' ')"
  fi
else
  fallo "Paso 6 (fotos)" "no existe $BACKEND/public/images"
fi

# -----------------------------------------------------------------------------
# Paso 7: retención
# -----------------------------------------------------------------------------
log "Paso 7: borrando paquetes de más de ${RETENCION_DIAS} días..."
borrados="$(find "$DIR_RESPALDOS" -maxdepth 1 -name 'totalsecureapp_*.tar.gz' \
  -mtime "+${RETENCION_DIAS}" -print -delete 2>/dev/null | wc -l)"
log "  [OK] ${borrados} paquete(s) borrado(s)"

# -----------------------------------------------------------------------------
# Paso 8: copia fuera del servidor
# -----------------------------------------------------------------------------
if [[ -n "$DESTINO_EXTERNO" ]]; then
  log "Paso 8: copiando a $DESTINO_EXTERNO..."
  if rsync -a "$ARCHIVO_FINAL" "$DESTINO_EXTERNO" 2>"$STAGE/ext.err"; then
    log "  [OK] copia externa hecha"
  else
    fallo "Paso 8 (copia externa)" "rsync falló: $(tail -n2 "$STAGE/ext.err" | tr '\n' ' ')"
  fi
else
  log "Paso 8: OMITIDO — DESTINO_EXTERNO está vacío."
  log "  [AVISO] El respaldo vive en el mismo servidor que el sistema. Si se"
  log "          pierde el servidor se pierde también el respaldo, y con él el"
  log "          keystore de firma, que no se puede regenerar."
fi

# -----------------------------------------------------------------------------
# Resumen
# -----------------------------------------------------------------------------
log "───────────────────────────────────────────────────────────────"
if (( ${#FALLOS[@]} == 0 )); then
  log "Respaldo terminado sin errores: $ARCHIVO_FINAL"
  exit 0
fi

log "Respaldo terminado CON ${#FALLOS[@]} fallo(s):"
for f in "${FALLOS[@]}"; do
  log "  - $f"
done
exit 1
