# Respaldo y restauración — Total Secure App

## Qué se respalda

`scripts/respaldo.sh` corre **todos los días a las 03:00** por el crontab de
`server-dt` y produce dos cosas en `~/respaldos/totalsecureapp/`:

| Qué | Dónde | Tamaño |
|---|---|---|
| Base PostgreSQL `coredt360` (formato custom) | dentro del `.tar.gz` diario | ~3,3 MB |
| Base MariaDB de la V1 (`v1_analisis`) | dentro del `.tar.gz` diario | ~4,3 MB |
| `.env`, `docker-compose.yml`, los dos nginx | dentro del `.tar.gz` diario | KB |
| **Keystore de firma del APK** | dentro del `.tar.gz` diario | 4,5 KB |
| Fotos de accesos, biometría y novedades | `fotos/`, espejo incremental | 1,3 GB |

El paquete diario pesa unos **7 MB**. Se conservan **14 días**; los más viejos se
borran solos. Las fotos van por `rsync` incremental, sin `--delete`: si alguien
borra fotos del servidor por error, el espejo las conserva.

Permisos: el directorio y el espejo en `700`, cada paquete en `600`. Contienen la
contraseña de la base, `APP_KEY` y el keystore de firma. **No aflojar esos
permisos ni mover los respaldos a una ruta compartida.**

## ⚠️ Lo que este respaldo todavía no cubre

Todo queda **en este mismo servidor**. Sirve contra un borrado accidental, una
migración que sale mal o una tabla corrupta. **No sirve si se pierde el
servidor**, que es justo el escenario en el que el keystore no se puede
recuperar — y sin el keystore no se puede volver a publicar una actualización de
la app: habría que desinstalarla de cada tablet, y eso borra los datos locales
de cada una.

Para cerrarlo hay que definir `DESTINO_EXTERNO` en el script, apuntando a otra
máquina por `rsync`. Mientras esté vacío, cada corrida lo avisa en el log.

## Cómo restaurar

### La base PostgreSQL

El formato `custom` permite restaurar **una sola tabla**, que es lo que suele
hacer falta, en vez de la base entera:

```bash
tar -xzf ~/respaldos/totalsecureapp/totalsecureapp_AAAA-MM-DD.tar.gz
docker cp totalsecureapp_AAAA-MM-DD/coredt360.dump ts_db:/tmp/r.dump

# Una tabla sola:
docker exec ts_db pg_restore -U totalsecure -d coredt360 --data-only -t alertas /tmp/r.dump

# La base entera (⚠️ destructivo: reemplaza lo que haya):
docker exec ts_db pg_restore -U totalsecure -d coredt360 --clean --if-exists /tmp/r.dump
```

Ver qué hay dentro sin restaurar nada: `pg_restore --list /tmp/r.dump`.

### La base de la V1

```bash
gunzip -c totalsecureapp_AAAA-MM-DD/v1_coredt360.sql.gz \
  | docker exec -i v1_analisis mysql -u root -p<clave>
```

### Los secretos

⚠️ **`APP_KEY` no es opcional.** Cifra los QR de las rondas y las claves
guardadas en la tabla `configuracion`. Restaurar la base con una `APP_KEY`
distinta deja esos valores ilegibles para siempre: hay que restaurar el `.env`
del mismo día que la base.

### El keystore

Va a `apk/totalsecureapp-release.jks`. Comprobar que es el bueno:

```bash
sha256sum apk/totalsecureapp-release.jks
```

La huella SHA-256 del **certificado** (distinta de la del archivo) está en
`apk/LEEME-keystore.txt`, que viaja en el mismo paquete.

### Las fotos

```bash
rsync -a ~/respaldos/totalsecureapp/fotos/ \
  /home/server-dt/Documentos/totalsecureapp/totalsecureapp/backend/public/images/
```

Después hay que devolverles los permisos que espera nginx, porque el espejo las
guarda cerradas al dueño.

## Verificar que el respaldo sirve

Un respaldo que no se probó no es un respaldo. La comprobación barata, sin
restaurar nada:

```bash
# ¿El dump es legible y trae las tablas?
docker exec ts_db pg_restore --list /tmp/r.dump | grep -c 'TABLE DATA'

# ¿El gzip de la V1 está entero?
gzip -t totalsecureapp_AAAA-MM-DD/v1_coredt360.sql.gz

# ¿El keystore es idéntico al del servidor?
sha256sum totalsecureapp_AAAA-MM-DD/keystore/totalsecureapp-release.jks
```

Verificado así el 2026-09-15: 58 tablas con datos, gzip íntegro y keystore
idéntico al original.

## Correr uno a mano

```bash
sudo -u server-dt -H .../backend/scripts/respaldo.sh
```

No sobrescribe el del día: le agrega un sufijo con la hora. El log histórico
queda en `~/respaldos/totalsecureapp/log/respaldo.log` y la salida del cron en
`log/cron.log`.
