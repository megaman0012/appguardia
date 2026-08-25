# Checklist de Despliegue — Total Secure App v2 (Fases 1 a 9)

> **Antes de empezar:** este despliegue incluye **una migración que borra datos de
> forma irreversible** (§4.1). El backup no es una formalidad: es el único camino
> de vuelta para esa columna.

Entorno de referencia: PostgreSQL 16, PHP 8.3, Laravel 8.75, Docker Compose
(`ts_db` en `127.0.0.1:5434`, `ts_backend`, `nginx` en `:3031`).

---

## 1. Antes de tocar producción

- [ ] Anotar el commit que se despliega: `git rev-parse --short HEAD`
- [ ] Confirmar que la suite pasa en el commit a desplegar:
      `DB_PORT=5434 ./vendor/bin/phpunit --testsuite Unit` → **94 tests**
- [ ] Revisar `php artisan migrate:status` en producción y anotar el último batch
      aplicado. Es el punto al que se vuelve si algo sale mal.
- [ ] Verificar espacio en disco para el backup: `df -h`
- [ ] Avisar la ventana de mantenimiento. Los guardias en campo con la APK
      **seguirán registrando offline** (Fase 7), así que la cola se sincroniza
      sola al volver el servicio; no se pierde nada, pero llegará en lote.

## 2. Backup pre-migración (obligatorio)

```bash
cd totalsecureapp/backend
FECHA=$(date +%Y%m%d-%H%M)

# Volcado completo, formato custom (permite restaurar tablas sueltas)
docker compose exec -T db pg_dump -U totalsecure -Fc coredt360 \
  > ~/backups/coredt360-pre-v2-$FECHA.dump

# Verificar que el volcado no está vacío ni truncado
ls -lh ~/backups/coredt360-pre-v2-$FECHA.dump
docker compose exec -T db pg_restore --list \
  < ~/backups/coredt360-pre-v2-$FECHA.dump | head
```

- [ ] El `.dump` pesa lo esperado (no unos pocos KB)
- [ ] `pg_restore --list` lista las tablas del negocio
- [ ] **Copiar el backup fuera del servidor** antes de seguir
- [ ] Respaldar también `public/images/` (fotos de accesos, rondas, novedades y
      biometría): las migraciones no las tocan, pero un rollback de datos sin las
      fotos deja registros apuntando a archivos inexistentes

Volcado extra, específicamente de lo que la Fase 5 destruye (§4.1):

```bash
docker compose exec -T db psql -U totalsecure -d coredt360 -c \
  "\copy (SELECT ac_code, ac_nombre_contrato FROM acceso WHERE ac_nombre_contrato IS NOT NULL) TO STDOUT CSV HEADER" \
  > ~/backups/ac_nombre_contrato-$FECHA.csv
```

- [ ] Ese CSV existe (aunque salga vacío, sirve de constancia)

## 3. Orden de ejecución

`php artisan migrate` respeta el orden por nombre de archivo, así que **no hay
que ejecutar nada a mano**. Este es el orden en que corren, agrupadas por fase:

| Fase | Migración | Reversible |
|---|---|---|
| 1 | `2026_08_20_000001_create_inv_producto_catalogo_table` | Sí |
| 1 | `2026_08_20_000002_create_inv_lista_table` | Sí |
| 1 | `2026_08_20_000003_create_inv_lista_item_table` | Sí |
| 1 | `2026_08_20_000004_create_inv_movimiento_cabecera_table` | Sí |
| 1 | `2026_08_20_000005_create_inv_movimiento_detalle_table` | Sí |
| 2 | `2026_08_20_100001_add_radio_tolerancia_to_institucion_table` | Sí |
| 3 | `2026_08_20_200001_create_turno_table` | Sí |
| 3 | `2026_08_20_200002_add_bio_tu_code_to_biometria_table` | Sí |
| 4 | `2026_08_20_300001_add_escalamiento_to_alertas_table` | Sí |
| 4 | `2026_08_20_300002_create_alertas_detalle_table` | Sí |
| 4 | `2026_08_20_300003_create_alertas_historial_table` | Sí |
| 1 | `2026_08_21_000001_add_stock_actual_to_inv_producto_catalogo_table` | Sí |
| 5 | `2026_08_21_100001_create_acceso_generalizado_tables` | Sí |
| 5 | `2026_08_21_100002_acceso_generalizado_migrate_data` | **Parcial — ver §4.1** |
| 6 | `2026_08_21_200001_seed_mobile_permissions` | Sí |
| 7 | `2026_08_21_300001_add_offline_sync_columns` | Sí |
| 8 | `2026_08_21_400001_seed_portal_permissions` | Sí |
| 9 | `2026_08_21_500001_add_composite_indexes` | Sí |

```bash
# 1. Detener el tráfico de escritura (nginx abajo, db arriba)
docker compose stop nginx backend

# 2. Migrar
docker compose run --rm backend php artisan migrate --force

# 3. Datos de la Fase 1 (idempotente: se puede repetir sin duplicar)
docker compose run --rm backend php artisan db:seed --class=SeedInvInventoryData --force
docker compose run --rm backend php artisan db:seed --class=VerifyInventoryMigration --force

# 4. Limpiar cachés (el de config es el que más suele quedar viejo)
docker compose run --rm backend php artisan config:clear
docker compose run --rm backend php artisan route:clear
docker compose run --rm backend php artisan cache:clear

# 5. Levantar
docker compose up -d
```

- [ ] `migrate` terminó sin errores
- [ ] `migrate:status` no deja ninguna en `No`
- [ ] El seeder de inventario reportó los totales esperados
- [ ] `cache:clear` corrido **después** de migrar (el caché del dashboard de la
      Fase 9 se invalida por evento, pero los contadores viejos de antes del
      despliegue no tienen quién los invalide)

### Notas sobre las migraciones que crean roles

`seed_mobile_permissions` (Fase 6) y `seed_portal_permissions` (Fase 8) **crean
los roles `Supervisor`, `Vigilante` y `Cliente` si no existen**, porque
`DatabaseSeeder` corre después de las migraciones. En una base que ya tiene esos
roles, los reutilizan por nombre y no duplican. No hace falta correr
`db:seed` completo en producción: eso crearía el usuario demo.

## 4. Plan de rollback

### 4.1 La única migración que pierde datos

`2026_08_21_100002_acceso_generalizado_migrate_data` mueve los datos vehiculares
de `acceso` a `acceso_vehiculo` y luego borra las columnas viejas. Su `down()`
recrea las columnas y **copia de vuelta** patente, empresa, sellos, neumático,
carro, llave y kms.

**Pero `ac_nombre_contrato` no tiene destino en `acceso_vehiculo`.** El `up()` la
borra y el `down()` la recrea vacía: ese dato **no se recupera con
`migrate:rollback`**, solo con el backup o el CSV de §2.

- [ ] Si hay que revertir la Fase 5 y `ac_nombre_contrato` importa, restaurarla
      desde el CSV **antes** de dar por buena la reversa

### 4.2 Reversa por fase

Cada fase se revierte con su batch. Consultar el batch antes:

```bash
docker compose run --rm backend php artisan migrate:status
```

| Revertir | Comando | Efecto |
|---|---|---|
| Fase 9 (índices) | `migrate:rollback --step=1` | Solo borra índices. Sin riesgo de datos. |
| Fase 8 (portal) | `migrate:rollback --step=1` | Borra los permisos `portal.*` y el rol `Cliente`. Los usuarios con ese rol quedan sin acceso al portal. |
| Fase 7 (offline) | `migrate:rollback --step=1` | Borra `client_uuid` y `sincronizado_en`. **La APK perdería la idempotencia**: revertir esto exige también revertir la APK, o los reintentos empezarán a duplicar registros. |
| Fase 6 (RBAC) | `migrate:rollback --step=1` | Borra los permisos móviles. Las rutas con `permission.api` responderán 403 a todos hasta que se revierta también el código. |
| Fase 5 (accesos) | `migrate:rollback --step=2` | Dos migraciones. Ver §4.1 antes. |
| Fases 1-4 | `migrate:rollback --step=N` | Borran tablas completas de inventario, turnos y alertas con sus datos. |

**Regla de orden:** revertir siempre de la fase más alta a la más baja. La reversa
de la Fase 5 depende de que `acceso_vehiculo` siga existiendo, y esa tabla la crea
`100001`, que se revierte después. Laravel lo hace bien por sí solo si se usa
`migrate:rollback`; no invocar migraciones sueltas con `--path` fuera de orden.

### 4.3 Rollback total (el camino seguro)

Ante cualquier duda, restaurar el volcado en vez de encadenar reversas:

```bash
docker compose stop nginx backend
docker compose exec -T db dropdb -U totalsecure coredt360
docker compose exec -T db createdb -U totalsecure coredt360
docker compose exec -T db pg_restore -U totalsecure -d coredt360 \
  < ~/backups/coredt360-pre-v2-FECHA.dump
git checkout <commit-anterior>
docker compose up -d --build
```

- [ ] Restaurar también `public/images/` si se revirtió con datos nuevos ya subidos

## 5. Verificación post-despliegue

### API de la app móvil

```bash
# Login (devuelve access_token, abilities granulares y perfiles)
curl -s -X POST http://SERVIDOR:3031/api/login \
  -H 'Accept: application/json' -d 'usu_cedula=CEDULA&usu_password=CLAVE'

T=<access_token>

# RBAC: con permiso responde 200; sin permiso, 403 con required_permission
curl -s -o /dev/null -w '%{http_code}\n' -X POST http://SERVIDOR:3031/api/seleccionar_perfil \
  -H "Authorization: Bearer $T" -H 'Accept: application/json'

# Idempotencia offline: el MISMO client_uuid dos veces debe dar 200 y duplicado:true
UUID=$(cat /proc/sys/kernel/random/uuid)
for i in 1 2; do
  curl -s -X POST http://SERVIDOR:3031/api/novedad_create \
    -H "Authorization: Bearer $T" -H 'Accept: application/json' -H 'Content-Type: application/json' \
    -d "{\"ins_code\":1,\"nv_observacion\":\"prueba despliegue\",\"nv_lat\":\"0\",\"nv_lng\":\"0\",\"client_uuid\":\"$UUID\"}"
  echo
done
```

- [ ] `POST api/login` devuelve `access_token`, `abilities` y `perfiles`
- [ ] Un endpoint sin permiso devuelve **403** con `required_permission`
- [ ] Sin token, **401**
- [ ] El segundo envío del mismo `client_uuid` devuelve `"duplicado":true` y el
      **mismo id**, y en la base hay **una sola fila**
- [ ] Borrar la novedad de prueba

### API del portal cliente

- [ ] `GET api/portal/instituciones` con un token de rol `Cliente` → 200 y solo
      sus instituciones
- [ ] `GET api/portal/novedades?ins_code=<ajena>` → **403**, no una lista vacía
- [ ] Un token de la app móvil (Vigilante) contra `api/portal/*` → **403**
- [ ] `POST` a cualquier ruta del portal → **405** (es solo lectura)

### Panel web

- [ ] `/acceso/login` carga y el login por cédula funciona
- [ ] Se entra a `/admin` con perfil Supervisor
- [ ] El dashboard muestra los widgets **Alertas activas** y **Cumplimiento de
      turnos** con números coherentes
- [ ] Atender una alerta y recargar: el contador del widget **baja de inmediato**
      (invalidación por evento; si no baja, revisar que los observers estén
      registrados en `EventServiceProvider`)
- [ ] Abrir las tablas de Accesos, Rondas, Alertas, Novedades e Inventario y
      confirmar que las columnas de institución/organización/sede se llenan
- [ ] Usar el **filtro de rango de fechas de Accesos** (apuntaba a una columna
      inexistente antes de la Fase 9; debe filtrar sin error)

### Rendimiento

```bash
docker compose exec -T db psql -U totalsecure -d coredt360 -c "ANALYZE;"
docker compose exec -T db psql -U totalsecure -d coredt360 -c "
  select indexrelname, idx_scan from pg_stat_user_indexes
  where indexrelname in ('uhi_usuario_estado','acceso_ins_fecha','novedad_ins_fecha',
                         'ronda_cab_ins_fecha','biometria_ins_fecha','ronda_det_ronda_estado')
  order by idx_scan desc;"
```

- [ ] `ANALYZE` corrido: sin estadísticas frescas el planner puede ignorar los
      índices nuevos
- [ ] Tras un rato de uso, `idx_scan` sube en `uhi_usuario_estado` (es la consulta
      más frecuente del sistema: cada request del portal y cada validación de
      institución de la app)

### Tareas programadas

- [ ] El cron del scheduler está activo (`* * * * * php artisan schedule:run`)
- [ ] `php artisan turnos:cerrar-dia` corre sin error (se agenda a las 23:55)
- [ ] `php artisan turnos:revisar-cobertura` corre sin error (se agenda cada 5 min)

  > **Sin el cron, la cobertura de turnos no funciona.** Las faltas se detectan
  > ahí: si el scheduler no corre, nadie se entera de que un puesto quedó vacío
  > hasta el cierre del día, que es cuando ya no se puede cubrir. Las vacantes
  > cargadas a mano por el supervisor sí siguen funcionando, pero pierden el
  > escalado a la ciudad y el cierre de las vencidas.

## 6. Riesgos conocidos y cómo se mitigan

| Riesgo | Mitigación |
|---|---|
| `ac_nombre_contrato` se pierde al migrar | CSV dedicado en §2 más el backup completo |
| Revertir la Fase 7 sin revertir la APK → registros duplicados | Desplegar/revertir backend y APK como una unidad |
| Revertir la Fase 6 sin revertir el código → todo 403 | Ídem: el `permission.api` de las rutas necesita los permisos en base |
| `QUEUE_CONNECTION=sync`: el escalamiento de alertas (`NotificarAlertaPendiente`) corre en el request | Funciona, pero encarece la petición. Si se pasa a un driver con worker, **no** registrar el caché del dashboard con `Cache::tags()`: el driver es `file` y no las soporta (por eso se usa el contador de versión) |
| `CACHE_DRIVER=file` en varios servidores | Con más de una instancia, el caché no se comparte y la invalidación por evento solo aplica a la instancia que escribió. Pasar a Redis antes de escalar horizontalmente |
| Faltan las notificaciones push en dispositivos reales | Falta `google-services.json` (pendiente previo, no lo introduce la v2) |
| Contraseña del usuario demo desconocida | No es `123456`. Para verificar la API sin ella, generar un token con `artisan tinker` (documentado en `AGENTS.md`) |

## 7. Cierre

- [ ] `git tag v2.0.0 && git push --tags` (solo con autorización explícita)
- [ ] Anotar en `RESUMEN-AVANCE.md` la fecha del despliegue y el commit
- [ ] Conservar el backup pre-migración al menos 30 días
