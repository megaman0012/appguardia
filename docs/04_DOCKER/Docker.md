# Docker

## Servicios en ejecucion

Proyecto compose: **`backend`** (el nombre viene del directorio
`totalsecureapp/totalsecureapp/backend`).

| Servicio | Contenedor | Imagen | Puerto | Healthcheck |
|---|---|---|---|---|
| `db` | `ts_db` | `postgres:16-alpine` | **`127.0.0.1:5434`** ✅ solo local | ✅ `pg_isready` |
| `backend` | `ts_backend` | build local (Laravel/PHP 8.3) | ninguno | ❌ |
| `nginx` | `ts_nginx` | `nginx:alpine` | **`3031:80`** ⚠️ alcanzable desde internet | ❌ |

Mas el contenedor **suelto** `v1_analisis` (MariaDB 10.4), **fuera de todo
compose**.

## Variables

| Variable | Nota |
|---|---|
| `DB_PASSWORD` | **fail-fast**: `${DB_PASSWORD:?Definir DB_PASSWORD en .env}`. `SECRET DETECTADO — NO DOCUMENTAR VALOR` |
| `APP_ENV` | `production` ✅ verificado |
| `APP_DEBUG` | `false` ✅ verificado |
| `APP_URL` | `http://181.198.245.50:3031` ⚠️ IP publica, sin TLS |
| `TOKEN_EXPIRE_IN` | 3600 |
| `TOKEN_REFRESH_EXPIRE_IN` | 3600 |

> ⚠️ **Estas variables del compose PISAN al `.env`.** El propio archivo lo
> advierte: *"cambiar solo el archivo no alcanza. Es aca donde hay que
> ponerlos"*. Es una trampa que cuesta horas si no se sabe.

## Bind mounts

    backend:  ./:/var/www
    nginx:    ./:/var/www

El codigo fuente se monta en los contenedores. Es habitual en PHP, pero
significa que **produccion ejecuta lo que hay en el disco, no una imagen
inmutable**. Ver SEC-02.

## Comandos

    cd /home/server-dt/Documentos/totalsecureapp/totalsecureapp/backend

    docker compose ps
    docker compose logs -f backend
    docker compose restart backend
    docker compose up -d --build

    # Artisan
    docker compose exec backend php artisan migrate --force
    docker compose exec backend php artisan cache:clear
    docker compose exec backend php artisan config:clear

> Tras cambiar variables del compose hay que **recrear** el contenedor
> (`up -d`), no solo reiniciarlo: un `restart` conserva el entorno anterior.

## Base de datos

    docker compose exec db psql -U totalsecure -d coredt360

Y la legada, que **no esta en el compose**:

    docker exec -it v1_analisis mysql -u root -p

## Despliegue con HTTPS, preparado y sin usar

`docker-compose.prod.yml` define `nginx:1.27-alpine` con puertos **80 y 443** y
un servicio **certbot**. `DESPLIEGUE-DOMINIO.md` documenta el procedimiento.

**Activarlo resuelve el hallazgo critico SEC-01.**

## Backup

⚠️ **No se encontro respaldo automatico.** Ver `12_CONTINUIDAD`. Es el sistema
con mas datos del servidor.
