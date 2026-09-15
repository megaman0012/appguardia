# Operacion

## Arquitectura productiva

| Elemento | Valor |
|---|---|
| Puerto publicado | **`0.0.0.0:3031`** (nginx) |
| **Alcance** | ⚠️ **INTERNET** — `http://181.198.245.50:3031` responde (302) |
| Backend | sin puerto publicado |
| PostgreSQL | `127.0.0.1:5434` ✅ solo local |
| Legado | `v1_analisis` (MariaDB), **fuera del compose** |
| Reinicio | `unless-stopped` |

**Es el unico sistema del servidor accesible desde internet.** Todo lo demas
—coordinador, psicometrico, lavado, traccar— es de red local.

Eso cambia su perfil de riesgo: no depende de estar en la red de la empresa,
cualquiera en el mundo puede alcanzarlo.

## Healthchecks

| Servicio | Healthcheck |
|---|---|
| `db` | ✅ `pg_isready` cada 5s, 12 reintentos |
| `backend` | ❌ |
| `nginx` | ❌ |

El backend espera con `condition: service_healthy`.

## Dependencias externas

| Servicio | Estado |
|---|---|
| Evolution API (WhatsApp) | documentado, **no verificado** |

## Monitoreo

Sin herramienta. Verificacion manual:

    docker compose ps
    curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3031/

## Logs

    docker compose logs -f backend
    docker compose exec backend tail -f storage/logs/laravel.log

Con `APP_DEBUG=false` el detalle de los errores **solo esta en el log de
Laravel**, no en la respuesta HTTP. Es lo correcto, y hay que saberlo para
diagnosticar.

## Actualizacion

    git pull
    docker compose up -d --build
    docker compose exec backend php artisan migrate --force
    docker compose exec backend php artisan config:clear

**Respaldar antes.** Hoy no hay respaldo automatico: ver `12_CONTINUIDAD`.

## Actualizacion de la app movil

Es un proceso aparte, manual, tablet por tablet. Ver `10_MOBILE`. El APK
vigente es la **1.0.2**.

## Rollback

    git checkout <commit-anterior>
    docker compose up -d --build

Volver atras el codigo **no revierte migraciones**. Con 57 tablas y 880
usuarios, restaurar desde respaldo seria la via segura — **si existiera**.

## Ventana de indisponibilidad

Sistema en produccion con **880 usuarios** y guardias operando en campo con
tablets. Cualquier parada debe coordinarse. Es el sistema con mayor impacto
operativo del servidor junto con el coordinador.

## Pendiente prioritario: HTTPS

`docker-compose.prod.yml` (nginx 1.27 + certbot, puertos 80 y 443) y
`DESPLIEGUE-DOMINIO.md` ya estan escritos. **Activarlos cierra el hallazgo
critico SEC-01.**
