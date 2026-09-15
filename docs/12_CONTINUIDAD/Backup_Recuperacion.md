# Backup y recuperacion

## 🔴 Sin respaldo automatico, y es el sistema con mas que perder

Verificado el 2026-09-15: **no se encontro cron ni timer de systemd** para este
proyecto. El unico respaldo programado del servidor es el del coordinador
(`/etc/cron.d/backup-coordinador`).

**Lo que hoy no esta respaldado:**

| Elemento | Volumen | Recuperable |
|---|---|---|
| Base `coredt360` | 57 tablas, 880 usuarios, **12.664 biometrias** | **no** |
| Base legada V1 (`v1_analisis`) | MariaDB, fuera de todo compose | parcialmente: existe `coredt360_bk.sql` (32 MB) |
| `.env` (secretos) | `DB_PASSWORD` | **no** |
| **Keystore de firma** | `apk/totalsecureapp-release.jks` | **no — e insustituible** |
| Codigo | git local | si |

## Lo mas urgente: el keystore

`apk/totalsecureapp-release.jks` merece tratamiento aparte. **No es un dato: es
la identidad de la aplicacion.**

Si se pierde:

- **no se pueden publicar mas actualizaciones** de Total Secure App;
- habria que republicar la app con otro certificado y **reinstalarla
  manualmente en todas las tablets**, perdiendo los datos locales.

Esta en un solo servidor, sin copia. Debe tener **al menos una copia fuera**,
en un almacen de secretos o medio cifrado.

## Procedimiento de respaldo (propuesto, NO implementado)

```bash
cd /home/server-dt/Documentos/totalsecureapp/totalsecureapp/backend
set -a; . ./.env; set +a
FECHA=$(date +%Y%m%d)
DEST=/home/server-dt/backups/totalsecureapp
mkdir -p "$DEST"

# 1. Base principal
docker compose exec -T db pg_dump -U "${DB_USERNAME:-totalsecure}" \
  "${DB_DATABASE:-coredt360}" | gzip > "$DEST/coredt360-$FECHA.sql.gz"

# 2. Base legada V1 (contenedor suelto, hay que nombrarlo aparte)
docker exec v1_analisis sh -c 'mysqldump -u root -p"$MARIADB_ROOT_PASSWORD" --all-databases' \
  | gzip > "$DEST/v1-$FECHA.sql.gz"

# 3. Secretos y keystore (guardar con permisos restringidos)
cp ../../.env "$DEST/env-$FECHA.bak" 2>/dev/null
cp ../../apk/totalsecureapp-release.jks "$DEST/keystore-$FECHA.jks" 2>/dev/null
chmod 600 "$DEST"/env-* "$DEST"/keystore-* 2>/dev/null

find "$DEST" -name '*.gz' -mtime +30 -delete
```

**Frecuencia sugerida:** diaria.
**Retencion sugerida:** 30 diarios + 12 mensuales.

> El paso 2 es facil de olvidar: `v1_analisis` **no esta en el compose**, asi
> que un respaldo escrito "sobre el proyecto" lo dejaria fuera.

Seguir el patron de `/etc/cron.d/backup-coordinador`, que ya funciona en este
servidor y ademas vigila el espacio libre.

## Recuperacion

```bash
gunzip -c coredt360-AAAAMMDD.sql.gz | \
  docker compose exec -T db psql -U totalsecure -d coredt360
docker compose exec backend php artisan config:clear
```

## Validacion

Sin respaldos, no hay nada que validar. Una vez activado, restaurar contra una
base desechable y comparar `count(*)` de `users`, `acceso` y
`user_has_biometria`.

## Recuperacion ante desastre

| Paso | Hoy |
|---|---|
| Recuperar el codigo | ✅ git local (⚠️ sin remoto) |
| Recuperar la base | ❌ **no seria posible** |
| Recuperar los secretos | ❌ **no seria posible** |
| Recuperar el keystore | ❌ **no seria posible, e insustituible** |
| Recuperar la base V1 | 🟡 parcial, con `coredt360_bk.sql` |

## RPO / RTO

| Medida | Valor real |
|---|---|
| RPO | **total** |
| RTO | el servicio volveria en minutos; los datos, nunca |

**Es la peor relacion riesgo/valor del servidor:** el sistema con mas datos
personales, el unico expuesto a internet, y sin ninguna copia.
