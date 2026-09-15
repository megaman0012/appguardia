# Manual de soporte — Sistemas

Version 1.0 · 2026-09-15

Ruta: `/home/server-dt/Documentos/totalsecureapp/totalsecureapp/backend`

## Diagnostico rapido

    cd /home/server-dt/Documentos/totalsecureapp/totalsecureapp/backend
    docker compose ps
    curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3031/
    docker compose logs --tail 60 backend
    docker compose exec backend tail -50 storage/logs/laravel.log

## Tabla de problemas

| Problema | Causa | Verificacion | Solucion |
|---|---|---|---|
| El portal no abre | nginx o backend caidos | `docker compose ps` | `docker compose up -d` |
| **Ningun contenedor arranca** | falta `DB_PASSWORD` | logs con el mensaje del fail-fast | definir `DB_PASSWORD` en `.env` |
| Backend reinicia en bucle | no conecta a la base | `docker compose ps` (db healthy) | esperar el healthcheck; revisar credenciales |
| **Un cambio en `.env` no surte efecto** | **el compose pisa al `.env`** | comparar `printenv` del contenedor con el `.env` | cambiar el valor **en `docker-compose.yml`** y `docker compose up -d` |
| Error 500 sin detalle | `APP_DEBUG=false` (correcto) | `docker compose exec backend tail storage/logs/laravel.log` | el detalle esta en el log, **no activar debug en produccion** |
| **El PDF sale sin logo** | `APP_URL` mal configurada | `docker compose exec backend printenv APP_URL` | debe ser la URL publica real, no `localhost` |
| La app movil no conecta | red, URL o servidor caido | probar la URL desde la tablet | verificar `http://181.198.245.50:3031` |
| Guardias sin boton de emergencia | **tablet con version anterior a 1.0.2** | ver version en la app | instalar `TotalSecureApp-v1.0.2-prod-20260909.apk` |
| Un APK no instala sobre el anterior | certificado distinto | `apksigner verify --print-certs` | usar solo APK con la huella publicada en `LEEME-apk.txt` |
| Se pide restaurar datos | **no hay respaldo automatico** | ver `12_CONTINUIDAD` | escalamiento inmediato |

## Comandos de Laravel

    docker compose exec backend php artisan migrate --force
    docker compose exec backend php artisan cache:clear
    docker compose exec backend php artisan config:clear
    docker compose exec backend php artisan route:list | head -50
    docker compose exec backend php artisan module:list

> `route:list` y `module:list` son la forma mas rapida de ver la API real, mas
> confiable que `openapi.yaml`, que puede estar desactualizado.

## Base de datos

    docker compose exec db psql -U totalsecure -d coredt360

```sql
SELECT count(*) FROM users;
SELECT count(*) FROM user_has_biometria;
SELECT count(*) FROM acceso;
SELECT count(*) FROM alertas ORDER BY 1 DESC;
\dt
```

> Usar `count(*)`. Las estimaciones de `pg_stat_user_tables` en este servidor
> resultaron muy imprecisas.

## La base legada V1

Contenedor **suelto**, no esta en ningun compose:

    docker ps | grep v1_analisis
    docker exec -it v1_analisis mysql -u root -p

⚠️ **No se recrea con `docker compose up`.** Si se borra, hay que recrearlo a
mano. Y **no entra en ningun respaldo**.

## Escalamiento

| Situacion | A quien | Urgencia |
|---|---|---|
| Sospecha de acceso no autorizado | responsable de seguridad | **inmediata**: hay biometria de por medio |
| Perdida de datos | responsable del sistema | **inmediata**: no hay respaldo |
| Perdida del keystore de firma | Sistemas | **inmediata**: sin el no hay mas actualizaciones de la app |
| Solicitud de activar HTTPS | Sistemas | alta — ya esta preparado en `docker-compose.prod.yml` |
