# RESUMEN DE AVANCE — Total Secure App

> **Última actualización:** 2026-08-21 (Fases 1 a 9 completas ✅ — 94 tests pasando)

---

## Estado General del Proyecto

| Fase | Descripción | Estado | Documento |
|------|-------------|--------|-----------|
| 0 | Auditoría código + esquema | ✅ Completada | `ANALISIS-ESQUEMA-ACTUAL.md` |
| 1 | Inventario unificado | ✅ Implementación Completada | `FASE1-INVENTARIO-UNIFICADO.md` |
| 2 | Validación presencia central | ✅ Implementación Completada | `FASE2-PRESENCE-VALIDATION.md` |
| 3 | Modelo de turnos | ✅ Implementación Completada | `FASE3-TURNOS.md` |
| 4 | Alertas con escalamiento | ✅ Implementación Completada | `FASE4-ALERTAS.md` |
| 5 | Acceso generalizado | ✅ Completa (backend + frontend) | `FASE5-ACCESOS.md` |
| 6 | RBAC granular | ✅ Implementación Completada | `FASE6-RBAC.md` |
| 7 | Offline sync (backend) | ✅ Implementación Completada | `API-OFFLINE-SYNC.md` |
| 8 | API portal cliente | ✅ Implementación Completada | `openapi.yaml` |
| 9 | QA + despliegue | ✅ Implementación Completada | `CHECKLIST-DESPLIEGUE-V2.md` |

---

## Archivos Importantes

| Archivo | Descripción |
|---------|-------------|
| `planificacion_pasos.md` | Roadmap completo del proyecto |
| `ANALISIS-ESQUEMA-ACTUAL.md` | Diagrama ER + modelos + endpoints |
| `FASE1-INVENTARIO-UNIFICADO.md` | Documentación técnica Fase 1 |
| `FASE2-PRESENCE-VALIDATION.md` | Documentación técnica Fase 2 |
| `FASE3-TURNOS.md` | Documentación técnica Fase 3 |
| `FASE4-ALERTAS.md` | Documentación técnica Fase 4 |
| `FASE5-ACCESOS.md` | Documentación técnica Fase 5 |
| `FASE6-RBAC.md` | Documentación técnica Fase 6 |
| `API-OFFLINE-SYNC.md` | Contrato de sincronización offline (Fase 7) |
| `openapi.yaml` | Especificación OpenAPI de la API del portal cliente (Fase 8) |
| `CHECKLIST-DESPLIEGUE-V2.md` | Backup, orden de migraciones y rollback por fase (Fase 9) |
| `RESUMEN-AVANCE.md` | Este archivo |

---

## Archivos Creados - Fase 1

### Migraciones
- `database/migrations/2026_08_20_000001_create_inv_producto_catalogo_table.php`
- `database/migrations/2026_08_20_000002_create_inv_lista_table.php`
- `database/migrations/2026_08_20_000003_create_inv_lista_item_table.php`
- `database/migrations/2026_08_20_000004_create_inv_movimiento_cabecera_table.php`
- `database/migrations/2026_08_20_000005_create_inv_movimiento_detalle_table.php`

### Seeders
- `database/seeders/SeedInvInventoryData.php` (migración de datos)
- `database/seeders/VerifyInventoryMigration.php` (verificación)

### Modelos Eloquent
- `Modules/Administracion/Models/ProductoCatalogo.php`
- `Modules/Administracion/Models/Lista.php`
- `Modules/Administracion/Models/ListaItem.php`
- `Modules/Administracion/Models/MovimientoCabecera.php`
- `Modules/Administracion/Models/MovimientoDetalle.php`

### Controllers
- `Modules/MobileApp/Http/Controllers/InventarioController.php` (actualizado)

### Rutas
- `Modules/MobileApp/Routes/api.php` (agregada ruta `/inventario/registrar-baja`)

---

## Archivos Creados - Fase 2

### Migraciones
- `database/migrations/2026_08_20_100001_add_radio_tolerancia_to_institucion_table.php`

### Modelos Eloquent
- `Modules/Administracion/Models/OrganizacionInstitucion.php` (actualizado con `ins_radio_tolerancia_metros`)

### Services
- `app/Services/PresenceValidationService.php` (servicio central de validación)

### Controllers Refactorizados
- `Modules/MobileApp/Http/Controllers/BiometriaController.php`
- `Modules/MobileApp/Http/Controllers/RondaController.php`
- `Modules/MobileApp/Http/Controllers/AccesoController.php`

### Tests
- `tests/Unit/PresenceValidationServiceTest.php`

---

## Archivos Creados - Fase 3

### Migraciones
- `database/migrations/2026_08_20_200001_create_turno_table.php`
- `database/migrations/2026_08_20_200002_add_bio_tu_code_to_biometria_table.php`

### Modelos Eloquent
- `Modules/Administracion/Models/Turno.php`
- `Modules/Administracion/Models/user_has_biometria.php` (actualizado con `bio_tu_code`)

### Services
- `app/Services/TurnoService.php` (servicio central de turnos)

### Controllers
- `Modules/MobileApp/Http/Controllers/TurnoController.php` (3 endpoints)

### Console Commands
- `app/Console/Commands/CerrarTurnosDelDia.php` (command artisan)
- `app/Console/Kernel.php` (scheduler configurado a las 23:55)

### Tests
- `tests/Unit/TurnoServiceTest.php` (8 tests unitarios)

---

## Archivos Creados - Fase 4

### Migraciones
- `database/migrations/2026_08_20_300001_add_escalamiento_to_alertas_table.php`
- `database/migrations/2026_08_20_300002_create_alertas_detalle_table.php`
- `database/migrations/2026_08_20_300003_create_alertas_historial_table.php`

### Modelos Eloquent
- `Modules/Administracion/Models/Alertas.php` (actualizado con relaciones, scopes, accessors)
- `Modules/Administracion/Models/AlertaDetalle.php` (nuevo)
- `Modules/Administracion/Models/AlertaHistorial.php` (nuevo)

### Services
- `app/Services/AlertaService.php` (crear, asignar, atender, escalar, cancelar, estadísticas)

### Events
- `app/Events/AlertaCreada.php` (broadcast para notificaciones en tiempo real)

### Jobs
- `app/Jobs/NotificarAlertaPendiente.php` (escalamiento automático por tiempo)

### Controllers
- `Modules/MobileApp/Http/Controllers/AlertaController.php` (6 endpoints)

### Rutas
- `Modules/MobileApp/Routes/api.php` (agregadas rutas de alertas)

### Tests
- `tests/Unit/AlertaServiceTest.php` (8 tests unitarios)

---

## Sesión 2026-08-21: Migraciones, Seeder y Tests

### Correcciones aplicadas

| Problema | Causa raíz | Solución |
|----------|-----------|----------|
| Migración fallaba: "Cannot declare class AddRadioToleranciaToInstitucion" | Nombre de clase no seguía convención StudlyCase del archivo | Renombradas clases en `2026_08_20_100001` y `2026_08_20_200002` |
| Migración alertas fallaba: DBAL no instalado | `renameColumn()` requiere doctrine/dbal (no presente) | Reemplazado con SQL nativo `ALTER TABLE ... RENAME COLUMN` |
| Seeder fallaba: columna inexistente | `ipc_stock_actual` no estaba en la migración del catálogo | Nueva migración `2026_08_21_000001_add_stock_actual_to_inv_producto_catalogo_table` |
| Seeder no idempotente | Re-ejecución duplicaba filas / violaba PK | Verificaciones de existencia antes de cada insert |
| Tests: clase `Tests\TestCase` inexistente | Faltaban archivos base de testing | Creados `tests/TestCase.php` y `tests/CreatesApplication.php` |
| Tests: FK violation en users | BD de pruebas sin usuario id=1 | setUp crea usuario de prueba; BD dedicada `coredt360_testing` en phpunit.xml |
| Tests: factory de Alertas inexistente | Modelo usaba HasFactory sin factory | Creada `database/factories/Modules/Administracion/Models/AlertasFactory.php` |

### Bugs reales detectados por tests (corregidos en TurnoService)

1. **`calcularTardanza()` / `calcularMinutosExtras()`** — comparaban fechas completas en vez de solo hora del día (`Carbon::parse('08:00')` toma la fecha actual), produciendo valores absurdos al cruzar medianoche. Además el signo de `diffInMinutes()` estaba invertido. Refactorizado a minutos-del-día.
2. **`cerrarTurnosSinMarcacion()`** — solo procesaba turnos de HOY; los turnos vencidos de días anteriores quedaban 'programado' para siempre. Ahora incluye `tu_fecha <= hoy`.

### Resultado final

- ✅ 12 migraciones ejecutadas (11 pendientes + 1 nueva de stock)
- ✅ Seeder ejecutado e idempotente (6 productos, 3 listas, 6 items, movimientos migrados)
- ✅ **20/20 tests unitarios pasando** (PresenceValidation: 4, TurnoService: 8, AlertaService: 8)
- ✅ BD de pruebas aislada: `coredt360_testing` (RefreshDatabase seguro, no toca la BD de desarrollo)


## Próximos Pasos

### Fase 1 (Inventario Unificado) ✅
1. ✅ Crear migraciones (5 archivos)
2. ✅ Ejecutar `php artisan migrate`
3. ✅ Crear seeder de migración
4. ✅ Ejecutar `php artisan db:seed --class=SeedInvInventoryData`
5. ✅ Crear modelos actualizados
6. ✅ Actualizar controller
7. ✅ Actualizar rutas
8. ⏳ Probar endpoints

### Fase 2 (Validación de Presencia) ✅
1. ✅ Crear migración `add_radio_tolerancia_to_institucion`
2. ✅ Actualizar modelo `OrganizacionInstitucion`
3. ✅ Crear `app/Services/PresenceValidationService.php`
4. ✅ Refactorizar `BiometriaController`
5. ✅ Refactorizar `RondaController`
6. ✅ Refactorizar `AccesoController`
7. ✅ Crear tests unitarios
8. ✅ Ejecutar tests y verificar

### Fase 3 (Modelo de Turnos) ✅
1. ✅ Crear migración `create_turno_table`
2. ✅ Crear migración `add_bio_tu_code_to_biometria_table`
3. ✅ Ejecutar `php artisan migrate`
4. ✅ Crear modelo `Turno.php`
5. ✅ Actualizar modelo `user_has_biometria.php`
6. ✅ Crear `app/Services/TurnoService.php`
7. ✅ Crear `TurnoController.php` + rutas
8. ✅ Crear command `CerrarTurnosDelDia`
9. ✅ Configurar scheduler en Kernel.php
10. ✅ Crear tests unitarios
11. ✅ Ejecutar tests y verificar

### Fase 4 (Alertas con Escalamiento) ✅
1. ✅ Crear migración `add_escalamiento_to_alertas`
2. ✅ Crear migración `create_alertas_detalle_table`
3. ✅ Crear migración `create_alertas_historial_table`
4. ✅ Ejecutar `php artisan migrate`
5. ✅ Actualizar modelo `Alertas.php`
6. ✅ Crear modelos `AlertaDetalle.php` y `AlertaHistorial.php`
7. ✅ Crear `app/Services/AlertaService.php`
8. ✅ Crear evento `AlertaCreada.php`
9. ✅ Crear job `NotificarAlertaPendiente.php`
10. ✅ Actualizar `AlertaController.php`
11. ✅ Actualizar rutas API
12. ✅ Crear tests unitarios

### Fase 5 (Acceso Generalizado) ✅ Completa (backend + frontend)
1. ✅ Crear migración `create_acceso_generalizado_tables` (vehiculo, visitante, historial, preregistro)
2. ✅ Crear migración `acceso_generalizado_migrate_data` (conversión ac_tipo int→string, backfill estado, drop columnas vehiculares)
3. ✅ Ejecutar `php artisan migrate`
4. ✅ Crear modelos: `AccesoVehiculo`, `AccesoVisitante`, `AccesoHistorial`, `AccesoPreregistro`
5. ✅ Actualizar modelo `Acceso.php` (constantes, relaciones, scopes, tiempo_permanencia)
6. ✅ Fix `AccesoPersona.php` (relación rota)
7. ✅ Eliminar `AccesoTransporte.php` (código muerto)
8. ✅ Crear `app/Services/AccesoService.php` (validación por tipo + preregistros)
9. ✅ Refactorizar `AccesoController.php` (delega en servicio; conserva validación GPS y foto)
10. ✅ Rutas nuevas: `acceso/preregistro`, `acceso/preregistros`, `acceso/cancelar-preregistro`
11. ✅ Crear tests unitarios (21 tests)
12. ✅ Actualizar `AccesoFormScreen.tsx` (campos dinámicos por tipo: vehículo opcional en proveedor, motivo para visitante/proveedor)
13. ✅ Actualizar `AccesoListScreen.tsx` (pestañas Accesos/Pre-registros, filtro por tipo, badge estado EN CURSO/COMPLETADA, permanencia, salida solo si en_curso)
14. ✅ Crear `PreregistroFormScreen.tsx` + ruta de navegación
15. ✅ `constants.ts`: endpoints de preregistro

### Fase 6 (RBAC Granular)
1. Crear migración `seed_mobile_permissions.php`
2. Ejecutar `php artisan migrate`
3. Crear `app/Http/Middleware/CheckPermission.php`
4. Registrar middleware en `Kernel.php`
5. Crear `app/Traits/BelongsToInstitution.php`
6. Crear `Modules/MobileApp/Http/Controllers/PerfilController.php`
7. Actualizar `Modules/MobileApp/Routes/api.php` con permisos
8. Aplicar BelongsToInstitution a modelos
9. Reescr `ProfileSelectionScreen.tsx`
10. Limpiar modelos duplicados
11. Actualizar LoginController abilities
12. Crear tests de permisos
13. Ejecutar tests y verificar

---

## Archivos Creados - Fase 6 (RBAC)

### Migraciones
- `database/migrations/2026_08_21_200001_seed_mobile_permissions.php` (9 secciones, 31 permisos; crea los roles si no existen)

### Middleware / Traits
- `app/Http/Middleware/CheckPermission.php` (alias `permission.api`)
- `app/Traits/BelongsToInstitution.php`

### Controllers
- `Modules/MobileApp/Http/Controllers/PerfilController.php` (`seleccionar_perfil`, `procesar_perfil`)
- `Modules/MobileApp/Http/Controllers/LoginController.php` (abilities granulares + `perfiles`)

### Frontend
- `src/screens/ProfileSelectionScreen.tsx` (reescrito y conectado al navegador)
- `src/context/AuthContext.tsx` (`perfil`, `permisos`, helper `can()`)
- `src/screens/HomeScreen.tsx` (módulos según permiso)

### Tests
- `tests/Unit/RbacTest.php` (9 tests)

---

## Archivos Creados - Fase 7 (Offline Sync)

### Migraciones
- `database/migrations/2026_08_21_300001_add_offline_sync_columns.php` (`client_uuid` unique + `sincronizado_en` en `user_has_biometria`, `ronda_detalle`, `acceso`, `novedad`)

### Services
- `app/Services/OfflineSyncService.php` (idempotencia, carrera de reintentos, fecha del evento)

### Controllers con idempotencia
- `BiometriaController`, `RondaController` (2 endpoints), `AccesoController`, `NovedadController`
- `app/Services/AccesoService.php` (columnas offline en el create)

### Tests
- `tests/Unit/OfflineSyncTest.php` (16 tests, incluye contrato HTTP)

### Bugs reales encontrados en Fase 7
1. **`catch (Exception $e)` sin barra inicial** en `NovedadController` y `RondaController`: en ese namespace resolvía a una clase inexistente, así que el catch nunca capturaba nada y cualquier excepción escapaba como error 500. Corregido a `\Exception`.
2. **La fecha del evento era la del servidor** (`date('Y-m-d H:i:s')`): un registro hecho sin señal quedaba fechado en el momento de la sincronización, no en el del hecho. Ahora la envía el dispositivo en `ocurrido_en`.

---

## Archivos Creados - Fase 8 (API Portal Cliente)

### Módulo nuevo
- `Modules/PortalApi/` — módulo propio, prefijo `api/portal`, registrado en `modules_statuses.json`
  - `Providers/PortalApiServiceProvider.php`, `Providers/RouteServiceProvider.php`
  - `Routes/api.php` (7 rutas, todas GET)
  - `Http/Controllers/PortalController.php` (base: resuelve el alcance por institución)
  - `Http/Controllers/InstitucionController.php`, `ReporteController.php`, `ResumenController.php`

### Services
- `app/Services/PortalScopeService.php` (instituciones del token, validación de `ins_code`, rango de fechas, tope de paginación)
- `app/Services/PortalContext.php` (contexto validado; su método `consulta()` es la **única** forma de abrir una consulta del portal)

### Migraciones
- `database/migrations/2026_08_21_400001_seed_portal_permissions.php` (sección `ps_codigo` 19, 7 permisos `portal.*`, rol `Cliente`)

### Modificados
- `app/Traits/BelongsToInstitution.php` (nuevo scope `forInstitutions()` para varias instituciones)
- `Modules/Administracion/Models/user_has_biometria.php` (adopta el trait con `bio_ins_code`)

### Documentación
- `openapi.yaml` (OpenAPI 3.0.3: 7 endpoints, 10 esquemas)

### Tests
- `tests/Unit/PortalApiTest.php` (15 tests; las rutas se descubren del router, así que un endpoint nuevo queda cubierto sin tocar el test)

### Refactor: el filtro por institución no se puede olvidar

El primer diseño centralizaba la **decisión** (qué instituciones ve el token) pero
dejaba distribuida la **aplicación**: 10 llamadas a `forInstitutions()` más un
`whereIn` aparte en `InstitucionController`, que se saltaba el camino común. Un
endpoint nuevo que olvidara filtrar compilaba, pasaba los tests (que enumeraban 5
rutas a mano) y devolvía los datos de todos los clientes.

Ahora el controller no consulta modelos: pide el contexto y de ahí sale el builder
ya acotado por institución y por fecha (`$ctx->consulta(Modelo::class, 'columna')`).
Quedan **cero** consultas directas a modelos en el módulo. Descartado el global
scope de Eloquent: `addGlobalScope()` es estático y se quedaría pegado en los
procesos largos de la cola (`NotificarAlertaPendiente`), afectando a la app móvil
y a Filament.

Como red, el test descubre las rutas GET del router en vez de enumerarlas, y falla
si un endpoint devuelve otra institución **o** si su respuesta no expone `ins_code`
ni `instituciones` (es decir, si no se puede auditar). Verificado agregando a
propósito un endpoint sin filtro: el test lo detectó y lo nombró.

De paso, `puntos_recorridos` pasó de un `count()` por fila a `withCount()`: con 200
filas por página eran 200 consultas extra.

**No se tocó el panel Filament**, como pide el roadmap: esta API es un consumidor
adicional de los mismos modelos y servicios.

---

## Archivos Creados - Fase 9 (QA, performance y despliegue)

### N+1 en el panel
Solo 1 de 21 resources de Filament hacía eager loading. Medido con volumen real:
**5N+1 consultas** (51 con 10 filas, 251 con 50), o sea **126 consultas por página**
con las 25 filas por defecto de Filament, contra **6 constantes** con `with()`.

- 16 resources: constante `RELACIONES_TABLA` + `->with()` en `getEloquentQuery()`
  (4 de ellos no tenían el método y se creó)
- `ReporteController` del portal: `puntos_recorridos` pasó de un `count()` por fila
  a `withCount()`
- `tests/Unit/EagerLoadingTest.php` (4 tests): compara el costo entre 10 y 30 filas
  en vez de contra un número fijo, y **descubre los resources del directorio** para
  que uno nuevo con columnas de relación no pueda omitir su eager loading

### Índices compuestos
- `database/migrations/2026_08_21_500001_add_composite_indexes.php` — 15 índices en
  6 tablas, con el orden igualdad→rango para que sirvan al filtro y al ordenamiento
  a la vez. Derivados de contar los `where`/`orderBy` reales del código, no de
  suposiciones. `alertas` y `turno` ya traían los suyos de las Fases 3-4.
- El más valioso: `(ui_usu_id, ui_state)` en `user_has_institucion`, que es la
  consulta más frecuente del sistema (16 usos) y no tenía ningún índice.
- Verificado con `EXPLAIN`: `Index Only Scan` en el conteo de puntos por ronda e
  `Index Scan Backward` en los listados por institución y fecha.

### Caché del dashboard con invalidación por evento
- `app/Services/DashboardStatsService.php` — contador de versión por institución
  incluido en la clave, porque `CACHE_DRIVER=file` **no soporta `Cache::tags()`**.
- `app/Observers/AlertaObserver.php` y `TurnoObserver.php`, registrados en
  `EventServiceProvider`. Van en observers y no en los services para cubrir también
  las escrituras del panel Filament y de los seeders.
- `app/Filament/Widgets/AlertasActivasWidget.php` y `CumplimientoTurnosWidget.php`
  (los widgets no existían; Filament los autodescubre).
- `tests/Unit/DashboardCacheTest.php` (9 tests). Verificado que 5 de ellos fallan
  si se desactivan los observers, o sea que prueban la invalidación y no solo el
  caché.

### Bugs reales encontrados en la revisión
1. **`AccesoResource`: el filtro de rango de fechas apuntaba a `ac_crated_at`**
   (typo, falta la 'e'). Esa columna no existe, así que usar el filtro lanzaba un
   error SQL en el panel. Corregido en los 2 lugares, más un barrido que confirma
   que no queda ninguna otra referencia a columna inexistente.
2. **`ronda_cabecera::detalles()` estaba comentada** desde antes; se activó para
   poder usar `withCount`.

### Despliegue
- `CHECKLIST-DESPLIEGUE-V2.md` — backup obligatorio, orden de las 18 migraciones
  por fase, rollback por fase y verificación post-despliegue. Todos los comandos
  de backup y de `EXPLAIN`/`pg_stat` fueron ejecutados para comprobar que funcionan.
- Hallazgo crítico documentado: `ac_nombre_contrato` se borra en la migración de la
  Fase 5 y **no tiene destino** en `acceso_vehiculo`, así que el `down()` recrea la
  columna vacía. Ese dato solo se recupera del backup, por eso el checklist incluye
  un CSV dedicado.

---

## Cierre de pendientes (posterior a la Fase 9)

### `procesar_perfil` y `login` exponían permisos del panel web

El catálogo de permisos es compartido con el panel web legacy, y sus secciones
(`ps_codigo` 1 Administración, 2 Formularios) salían por la API móvil: un
Vigilante recibía 22 permisos en vez de 21 y un Supervisor 34 en vez de 31,
incluyendo `administracion/persona.index`. La app los ignoraba porque filtra por
nombres concretos, pero el conteo que `PerfilScreen` muestra al usuario estaba
mal y la API entregaba nombres de un panel al que ese cliente no entra.

- `app/Services/PermisosApiService.php` — único lugar donde se resuelven los
  permisos que ve un cliente de la API. `login` y `procesar_perfil` tenían cada
  uno su propia consulta escrita a mano; ahora comparten esta.
- Excluye las secciones web en vez de permitir solo las móviles (10-18), para que
  un rol de una sección nueva —como el Portal Cliente, la 19— no quede sin
  permisos por olvido.
- 3 tests nuevos en `RbacTest` (12 en total). Verificado que 2 de ellos fallan si
  se quita el filtro.
- En vivo: Vigilante 22 → **21**, Supervisor 34 → **31**, que son los números
  documentados en la Fase 6.
- De paso, quedó sin uso el import de `DB` en `LoginController` y se eliminó.

---

## Comando Útil para Reanudar

```bash
# Ver estado de migraciones
php artisan migrate:status

# Ejecutar migraciones pendientes
php artisan migrate

# Ejecutar seeder específico
php artisan db:seed --class=SeedInvInventoryData

# Verificar migración
php artisan db:seed --class=VerifyInventoryMigration

# Ejecutar tests de presencia
php artisan test --unit=PresenceValidationServiceTest

# Ejecutar tests de turnos
php artisan test --unit=TurnoServiceTest

# Ejecutar tests de alertas
php artisan test --unit=AlertaServiceTest

# Ejecutar tests de accesos (Fase 5)
php artisan test tests/Unit/AccesoServiceTest.php

# Ejecutar tests de RBAC (Fase 6) y offline sync (Fase 7)
DB_PORT=5434 ./vendor/bin/phpunit --testsuite Unit --filter RbacTest
DB_PORT=5434 ./vendor/bin/phpunit --testsuite Unit --filter OfflineSyncTest

# Tests de la API del portal cliente (Fase 8)
DB_PORT=5434 ./vendor/bin/phpunit --testsuite Unit --filter PortalApiTest

# Tests de Fase 9 (N+1 y cache del dashboard)
DB_PORT=5434 ./vendor/bin/phpunit --testsuite Unit --filter EagerLoadingTest
DB_PORT=5434 ./vendor/bin/phpunit --testsuite Unit --filter DashboardCacheTest

# Suite completa (94 tests). Desde el host se necesita DB_PORT=5434
DB_PORT=5434 ./vendor/bin/phpunit --testsuite Unit

# Ejecutar command de cierre de turnos
php artisan turnos:cerrar-dia
```
