# AGENTS.md — Total Secure App

## Estructura del proyecto

- `backend/` — API Laravel 8.75 (modular) que consume la app. Es el backend `coredt360` migrado, con historia git local completa. Módulo principal de la app: `Modules/MobileApp`.
- `src/` — Código de la app Expo (SDK 57).
- `android/` — Proyecto nativo Android generado con `expo run:android` (se versiona para que el APK se pueda compilar en otro servidor sin `expo prebuild`).
- `apk_extracted/` — APK original descompilada (referencia). `appdeguardias.apk` es el APK original.
- `HISTORIAL_DE_CHAT.md` — Resumen del trabajo previo (en la carpeta padre).

## Backend

- **Git / remote:** el monorepo (`appguardia/`) tiene el remote `origin` = `git@github.com:megaman0012/appguardia.git`, y `main` sigue a `origin/main`. Commitear localmente cuando corresponda, pero **no hacer `push`/`pull`/`fetch` sin autorización explícita del usuario**, y no agregar ni cambiar remotes por iniciativa propia.
- **Propiedad de archivos:** normalizada a `server-gea` (2026-08-21) con `sudo chown -R server-gea:server-gea` sobre todo el monorepo; git ya no necesita `safe.directory`. Si vuelve a aparecer «posesión dudosa detectada» es que algo corrió como `root` otra vez. Docker sí sigue requiriendo `sudo` (el usuario no está en el grupo `docker`), y `sudo` pide contraseña interactiva, así que **desde una sesión de agente no se puede levantar Docker**: usar el PHP local (8.3) contra Postgres en el puerto host `5434` (`DB_PORT=5434 php artisan ...`), o pedir al usuario que corra el comando en una terminal real.
- **Ejecución con Docker (recomendado).** Todo el stack (nginx + PHP-FPM 8.3 + PostgreSQL 16) corre con Docker Compose desde `backend/`:
  - `docker compose up -d` — levanta todo. Backend en http://localhost:3031, Postgres en `127.0.0.1:5434` (host) / `db:5432` (red docker).
  - `docker compose exec backend php artisan migrate` — comandos de Laravel dentro del contenedor.
  - `docker compose logs -f` — logs; `docker compose down` — detiene sin borrar datos (volumen `pgdata`).
  - Las credenciales de BD se toman del `.env` (variables `DB_*`). `DB_HOST` se sobrescribe a `db` dentro de los contenedores.
- `.env` está en `.gitignore` (contiene credenciales SMTP reales, no versionar). `composer.phar` también ignorado. `.env.example` está actualizado para Postgres sin secretos.
- Autenticación API: Sanctum (bearer token). Las rutas de la app están en `backend/Modules/MobileApp/Routes/api.php` (`POST api/login`, `api/instituciones`, `api/rondas`, …). El prefijo real es `api/` (el `i/` del APK original era un alias del proxy).
- Middleware CORS/seguridad personalizados (`App\Http\Middleware\HandleCors`, `SecurityHeaders`, `App\Services\CorsService`) se corrigieron para funcionar en PHP 8.3.
- **BD migrada y sembrada.** `php artisan migrate` crea todo el esquema (incluye tablas de negocio de la app). `php artisan db:seed` crea:
  - Usuario demo: cédula `1234567890` (roles Vigilante **y** Supervisor, con gestión activa). **La contraseña no es `123456`**: se unificó el 18/08/2026 y está en `HISTORIAL_DE_CHAT.md` y en `Credencial única todos.txt` (este último sí está en `.gitignore`). Verificada el 2026-08-23 contra `POST /api/login`. ⚠️ **Esa clave está en un archivo versionado (`HISTORIAL_DE_CHAT.md`) y por tanto ya en el remoto de GitHub**: debería rotarse y sacarse del repo. Para probar la API sin usarla, generar un token con `DB_PORT=5434 php artisan tinker --execute='...createToken("probe")->plainTextToken'`.
  - Institución demo con 2 marcadores QR y un checklist de inventario con 2 productos.
  - Parámetro `access` (login) y roles `Supervisor`/`Vigilante`.
- **Correcciones hechas a migraciones/modelos heredados:** tabla `user_has_gestions` (antes `users_gestions`, columnas `ug_finish`/auditoría añadidas), columnas `tokenable_gs`/`refresh_token`/`expires_at` en `personal_access_tokens`, migración de permisos ya no fuerza `mysql`, `config/auth.php` tiene provider `mobile_users` (el `sanctum` guard valida contra `Modules\MobileApp\Models\users`), y el modelo `users` ya no fuerza la contraseña a `123456` (solo la hashea al cambiarla).

## RBAC (Fase 6)

- Permisos granulares de la app móvil sembrados por la migración `2026_08_21_200001_seed_mobile_permissions` (`ps_codigo` 10-18). La migración **crea los roles `Supervisor`/`Vigilante` si no existen**, porque `DatabaseSeeder` corre después de las migraciones y en una BD nueva todavía no están; sin eso los permisos quedaban creados pero sin asignar.
- Middleware `permission.api:<permiso>` (`App\Http\Middleware\CheckPermission`) en las rutas de `Modules/MobileApp/Routes/api.php`. Sin token → 401; con token sin el permiso → 403 con `required_permission`.
- `POST api/seleccionar_perfil` devuelve los roles del usuario; `POST api/procesar_perfil` (`id`) valida que el rol le pertenezca y devuelve sus permisos. Los permisos del panel web legacy (secciones `ps_codigo` 1-2) **no se exponen por la API**: los filtra `App\Services\PermisosApiService`, que es el único lugar donde se resuelven los permisos que ve un cliente de la API (lo usan tanto `login` como `procesar_perfil`, que antes tenían su propia consulta y podían divergir). Excluye las secciones web en vez de permitir solo las móviles, para que un rol de una sección nueva —como el Portal Cliente, la 19— no quede sin permisos por olvido.
- `POST api/login` devuelve `abilities` (permisos granulares) y `perfiles` (nombres de rol). **`abilities` ya no son nombres de rol**, así que no usarlo para mostrar el perfil en la UI.
- Frontend: la app guarda perfil y permisos en `AuthContext` (`perfil`, `permisos`, helper `can()`), y `HomeScreen` muestra cada módulo según su permiso de lectura. El flujo es Login → `ProfileSelection` (se salta solo si hay un único perfil) → Selección de institución → Home.
- Trait `App\Traits\BelongsToInstitution` con scope `forInstitution()` en `ronda_cabecera`, `Alertas`, `Novedad`, `Acceso` e `InvMovimiento` (cada modelo declara su `$institutionColumn`).

## Offline sync (Fase 7)

- Los 5 endpoints que crean registros en campo (`biometria`, `rondas_detalle_gestion`, `rondas_detalle_qrcode`, `acceso`, `novedad_create`) son **idempotentes** por `client_uuid`: un reintento con el mismo uuid devuelve **200 con el registro existente** y `duplicado: true`, nunca un error. Contrato completo en `API-OFFLINE-SYNC.md` (raíz del monorepo).
- Columnas `<pref>_client_uuid` (unique) y `<pref>_sincronizado_en` en `user_has_biometria`, `ronda_detalle`, `acceso` y `novedad` (migración `2026_08_21_300001`).
- `App\Services\OfflineSyncService` centraliza la idempotencia: `buscar()`, `registrar()` (que además resuelve la carrera de dos reintentos simultáneos capturando el `unique_violation` 23505), `ocurridoEn()` y `sincronizadoEn()`.
- **La fecha del evento la envía el dispositivo** en `ocurrido_en`, no el servidor: un registro hecho sin señal debe conservar su hora real. Una fecha futura se recorta a "ahora". Al tocar estos controladores, no volver a `date('Y-m-d H:i:s')`.
- **Orden obligatorio:** la comprobación de idempotencia va **antes** de validar GPS, de guardar la foto y —en el QR de rondas— del guard de "espere 5 minutos". Si se reordena, un reintento legítimo se rechaza. Ver §6 de `API-OFFLINE-SYNC.md`.

## API Portal Cliente (Fase 8)

- Módulo propio `Modules/PortalApi`, prefijo **`api/portal`**, registrado en `modules_statuses.json`. **Solo GET**: es una capa de lectura sobre los mismos modelos y servicios que consume la app móvil, sin tablas ni consultas propias. El panel Filament no se tocó.
- 7 endpoints: `instituciones`, `resumen`, `biometria`, `rondas`, `novedades`, `accesos`, `alertas`. Documentados en `openapi.yaml` (raíz del monorepo).
- Autenticación Sanctum (`api.auth`) + permiso `portal.*` por ruta. El rol **`Cliente`** (migración `2026_08_21_400001`, sección `ps_codigo` 19) trae solo esos 7 permisos de lectura: no hereda ninguno de la app móvil, así que un token del portal no puede escribir en la app ni uno de la app leer aquí.
- **Los controllers del portal no consultan modelos.** Piden el contexto con `PortalController::contexto()` y de ahí sale el builder ya acotado: `$ctx->consulta(Modelo::class, 'columna_fecha')` (la columna es opcional cuando el recurso no se filtra por fecha). Es la única forma de abrir una consulta del portal, así que el filtro no es algo que haya que recordar aplicar. Al agregar un endpoint, no llamar `Modelo::where(...)` directamente.
- Un cliente ve únicamente sus instituciones de `user_has_institucion`; pedir una ajena con `ins_code` devuelve **403, no una lista vacía** (una vacía permitiría sondear qué códigos existen).
- `PortalApiTest` **descubre las rutas GET del router**, no las enumera: un endpoint nuevo queda cubierto solo. Falla si devuelve datos de otra institución, y también si su respuesta no expone `ins_code` en las filas ni el arreglo `instituciones` — o sea, si su aislamiento no es auditable. Si agregas un endpoint con otra forma de respuesta, hazla verificable en vez de excluirla del test.
- No usar global scopes de Eloquent para esto: `addGlobalScope()` es estático y se quedaría pegado en los procesos largos de la cola, afectando a la app móvil y a Filament.
- El scope `forInstitutions([])` del trait `BelongsToInstitution` **no devuelve nada** a propósito: "sin instituciones" debe significar "sin datos", nunca "todos".
- Paginación con tope duro de 200 filas (`PortalScopeService::POR_PAGINA_MAX`); rango por defecto, los últimos 30 días.

## Performance y QA (Fase 9)

- **Eager loading obligatorio en los resources de Filament.** Las columnas del tipo `institucion.organizacionSede.sede.ps_descripcion` disparan una consulta por relación y por fila. Cada resource declara su constante `RELACIONES_TABLA` y la aplica en `getEloquentQuery()`. Medido: 5N+1 consultas sin eso, o sea 126 por página con las 25 filas por defecto, contra 6 constantes. `EagerLoadingTest` recorre el directorio de resources y falla si uno usa columnas de relación sin declararlas, así que un resource nuevo no puede omitirlo.
- **Caché del dashboard: la invalidación es por evento, no por TTL.** `App\Services\DashboardStatsService` incluye un contador de versión por institución en la clave, y los observers de `Alertas` y `Turno` (registrados en `EventServiceProvider`) lo suben en cada escritura. **No usar `Cache::tags()`**: el driver configurado es `file` y lanzaría `BadMethodCallException`. La invalidación va en observers y no en los services para cubrir también las escrituras del panel y de los seeders.
- Índices compuestos en la migración `2026_08_21_500001`, con el orden igualdad→rango. Al agregar un filtro nuevo, revisar si necesita índice; el más caliente es `(ui_usu_id, ui_state)` de `user_has_institucion`, que se consulta en cada request del portal y en cada validación de institución de la app.
- Tras desplegar índices, correr `ANALYZE`: sin estadísticas frescas el planner los ignora.
- `CHECKLIST-DESPLIEGUE-V2.md` (raíz del monorepo) tiene el backup obligatorio, el orden de las migraciones y el rollback por fase. **La migración `2026_08_21_100002` borra `ac_nombre_contrato` sin destino**, así que su `down()` no lo recupera: solo el backup.

## Limpieza del sistema de salud heredado (2026-08-24)

El backend venía de `coredt360`/HagpAsist, un sistema hospitalario, y arrastraba código que no era de guardias. Se eliminó por completo (migración `2026_08_24_100001`, reversible):

- Módulo `Formularios` entero: Epicrisis (formulario 006 del MSP) y Referencia (053). No funcionaban: consultaban tablas de un HIS (`capbas`, `epiman`, `ingresos`, `maedia`…) sobre una conexión `hagphosv` que nunca existió.
- Pantalla de Personas (`administracion/persona.index`), su controlador, vistas y assets.
- 7 tablas y sus modelos: `persona`, `tipo_documento`, `tipo_genero`, `tipo_pais`, `tipo_especialidad`, `tipo_servicio`, `referencia_motivo`. Ninguna la usaba el sistema de guardias.
- `LoginController@login_check_temp`: login contra la intranet del hospital (`DB::connection('intranet')`, `perm_epicrisis`), código muerto sin ruta.
- Secciones de permisos 1 (Administración) y 2 (Formularios), reemplazadas por la 3 (Panel) con el permiso `admin`.

**Al agregar permisos web, no reutilizar `ps_codigo` 1 ni 2.** `PermisosApiService::SECCIONES_WEB` sigue listando 1, 2 y 3 para que una base vieja que aún las tenga no filtre permisos web hacia la API.

## Panel: dos fallos de compatibilidad ya resueltos (2026-08-24)

- **`FILAMENT_LIVEWIRE` debe quedar VACÍO en el `.env`.** Livewire arma la URL de su JS como `<valor>/vendor/livewire/livewire.js`. Traía `http://localhost:3031/coredt360/public` (resto de la instalación original en subdirectorio), así que el JS daba **404** y el panel se renderizaba pero **no respondía a nada**: el selector de columnas, los filtros, la búsqueda y los modales quedaban muertos. Vacío produce la ruta relativa, que funciona en cualquier host. No poner el dominio con `/admin`.
- **Shims de Laravel 9 en `AppServiceProvider::registrarShimsDeLaravel9()`.** Filament 2.17 usa dos APIs que Eloquent/Support recién traen desde Laravel 9 y en 8.75 no existen. Ambos macros se autodesactivan si el método aparece, así que al subir a Laravel 9+ se pueden borrar.
  - `Stringable::toHtmlString()` — Filament la llama al renderizar `helperText` y `hint`: `Str::of($helperText)->markdown()->sanitizeHtml()->toHtmlString()`. **Sin el shim, cualquier formulario con `helperText` responde 500.** No hace falta shim para `sanitizeHtml()`: la registra el propio Filament.
  - `Model::resolveRouteBindingQuery()` — Filament 2.17 llama a `$model->resolveRouteBindingQuery(...)`, método que Eloquent recién trae desde Laravel 9; en 8.75 no existe y **todas** las páginas de edición del panel respondían 500. Se registra como macro del Builder (`Model::__call` reenvía ahí), lo que cubre los ~20 modelos sin tocarlos. El shim se autodesactiva si el método existe, así que al subir a Laravel 9+ se puede borrar.

## Roles y alcance de datos

Cinco roles. **No escribir listas de perfiles a mano**: usar `App\Support\PerfilPanel`, que centraliza lo que antes vivía en 24 comprobaciones repartidas en 20 archivos.

| Rol | Panel | Qué hace | Ve |
|---|---|---|---|
| `Administrador` | ✅ | Sistemas: todo, incluida la configuración (clientes, geografía, catálogos) | **Todo, sin filtro** |
| `Lider Operativo` | ✅ | Da de alta guardias, asigna rol/local/puesto. Crea locales | **Los locales de su(s) país(es)** (`user_has_pais`) |
| `Supervisor` | ✅ | Observa guardias y turnos, atiende alertas. Locales en **solo lectura** | Sus locales (`user_has_institucion`) |
| `Vigilante` | ❌ | App móvil | Sus locales |
| `Cliente` | ❌ | Portal de solo lectura | Sus locales |

- `PerfilPanel::localesDelUsuario()` devuelve `null` = sin filtro, `[]` = **no ve nada**. Un líder sin países asignados cae en `[]`, no en `null`: una configuración incompleta no debe convertirse en acceso global.
- Un local **sin ciudad** no pertenece a ningún país, así que ningún líder lo ve. Es deliberado: mejor que falte a que se cuele en el alcance de un país ajeno.
- `shouldRegisterNavigation()` **solo oculta el menú**. Para bloquear la ruta hace falta `canViewAny()`, que es lo que Filament consulta para abortar con 403.

## Puesto de trabajo y turnos

- **`puesto`**: la posición concreta dentro de un local (garita, andén, sala de monitoreo). **No confundir con `institucion_marcadores`**: un marcador es un punto QR que el guardia escanea al pasar en una ronda; un puesto es donde se queda durante su turno. Por eso son tablas distintas y `turno` referencia a las dos.
- `turno.tu_puesto_id` es **nullable**: no todos los locales dividen el trabajo en puestos. La FK va con `restrict` para que reorganizar puestos no borre el historial de turnos cumplidos.
- **Ojo con `tu_estado` vs `tu_state`**: `tu_estado` es varchar (`programado`, `en_curso`, `completado`, `ausente`, `inasistente`) y `tu_state` es el booleano de activo. Filtrar los activos con `where('tu_estado', true)` **no falla: devuelve 0 filas en silencio**. Fue el bug que dejaba al guardia sin ver su turno en la app.
- `TurnoResource` existe desde 2026-08-24. Antes **nada creaba turnos** —`TurnoService` solo vincula marcajes y cierra el día— así que la tabla estaba vacía y el widget "Cumplimiento de turnos" mostraba siempre cero.
- Programar turnos y definir puestos es de quien administra locales (Administrador y Líder Operativo). El Supervisor los consulta: es su tablero, no su planificación.

## Interfaz Web (panel)

El backend trae dos capas web sobre el mismo dominio `http://localhost:3031`:

- **Panel legacy (`/acceso/login`)** — login por cédula (web `login_check` → selección de perfil → menú). Usa las vistas AdminLTE de `Modules/{Acceso,Administracion,Formularios}`.
  - Usuario demo: cédula `1234567890` (misma credencial unificada del 18/08/2026, ver la sección Backend; **no** es `123456`). Tiene perfiles `Vigilante` y `Supervisor`.
  - El menú se arma desde `role_has_permissions → permissions → permission_section` (seeder `seedWebAccess`).
  - Tras elegir el perfil Supervisor el login redirige **directo a `/admin`** (el permiso `admin`, sección `ps_codigo` 3, es la ruta de destino: el JS hace `location.href = urlbase + '/' + data.link`). El perfil Vigilante no tiene permisos web a propósito — usa la app móvil — así que si lo selecciona en la web recibe «No tiene los permisos necesarios».
- **Panel Filament (`/admin`)** — el panel de administración real del negocio de la app (Rondas, Accesos, Novedades, Alertas, Inventario, Bitácora, Usuarios, Perfiles, Instituciones, Sedes, etc.). Está protegido por `canAccessFilament()` (requiere perfil `Supervisor`/`Administrador`), por lo que se entra **después** de hacer el login web y seleccionar el perfil Supervisor. El login propio de Filament apunta a `usu_email` (`App\Http\Livewire\Auth\Login`) y además la ruta `/admin/login` redirige a `/acceso/login` (diseño intencional).

Fixes aplicados a la web (commit `d42858a`):
- `Html::style/script` fueron eliminados en spatie/laravel-html v3 → reemplazados por `<link>`/`<script>` en las vistas Blade.
- Se creó `storage/framework/sessions` (faltaba y rompía sesiones en Postgres/Docker).
- Migraciones nuevas: `visible` en roles, catálogos web, `log`/`log_trafico`, `organizacion`/`sede`/`organizacion_sede`, `ru_code` en `user_has_roles`.
- `orderBy` de `pr_posicion` corregido para Postgres (`REPLACE` no aplica a `double`).

## Flujo de cambio de contraseña (`Modules/MobileApp`):
  - `POST api/solicitud_paswchg` (`usu_cedula`) → envía correo con el link de cambio y además devuelve `user_id` y `token` para que la app continúe el flujo sin depender del correo.
  - `POST api/procesar_paswchg` (`user_id`, `password`, `password2`) → valida (mínimo 8 caracteres, mayúscula/minúscula/número, coincidencia, distinta a la anterior) y guarda el hash nuevo. Limpia `remember_token`.
  - El `LoginController` de MobileApp **no** debe usar `message_json()` como función global (no existe); siempre `$this->message_json()`.

## App Expo

Expo SDK 57. Leer docs versionadas en https://docs.expo.dev/versions/v57.0.0/ antes de escribir código.

- **Conectada al backend real.** `src/services/api.ts` apunta a `API_URL` de `src/utils/constants.ts`: el host se toma en orden de prioridad de `Constants.expoConfig.extra.apiHost` (app.json, `192.168.100.212`), luego del `hostUri` de Expo, y como último recurso `localhost`. Puerto 3031 → funciona en web, emulador y dispositivo físico en la misma red.
- **APK release standalone:** `./gradlew :app:assembleRelease` genera `android/app/build/outputs/apk/release/app-release.apk` (~99 MB, firmado con el debug keystore, JS embebido → **no necesita Metro**). La IP del servidor se configura en `expo.extra.apiHost` de `app.json` (si cambia la IP, editarla y recompilar). `expo-build-properties` habilita `usesCleartextTraffic` (HTTP local). El APK debug (`app-debug.apk`) en cambio SÍ requiere Metro corriendo.
- **Flujo actual completo:**
  - Login (`POST api/login` con `usu_cedula`/`usu_password`) → guarda `access_token` + `usuario` en AsyncStorage (interceptor agrega `Authorization: Bearer`).
  - Selección de Institución → Home.
  - Home con los módulos **construidos y conectados**: Rondas (lista, detalle, escaneo QR), Accesos (lista + formulario), Novedades (lista + creación), Alertas, Inventario (lista + detalle/checklist), Biometría y Perfil.
  - Recuperación de contraseña: `PasswordResetRequestScreen` → `PasswordResetScreen` (formulario de nueva clave) → login.
  - Notificaciones push: `src/services/notifications.ts` registra el token push (`POST api/token/save`).
- Correcciones de esta etapa: `tsconfig.json` excluye `backend/` (vendor rompía `tsc`), `HomeScreen` usa el usuario del contexto. (`ProfileSelectionScreen` estaba roto y quedó reescrito y conectado en la Fase 6, ver la sección RBAC.)
- **Versiones alineadas con SDK 57** vía `npx expo install` (`expo`, `react-native-safe-area-context`, `react-native-screens`).
- `android/` se versiona para portabilidad. Las salidas de build (`**/build/`, `.gradle`, `local.properties`) están ignoradas con `.gitignore` propio dentro de `android/`.

## Verificación de funcionamiento (2026-08-18)

Se verificó el estado completo del proyecto:

| Componente | Estado | Detalle |
|------------|--------|---------|
| Backend Docker | ✅ Funcionando | Contenedores `ts_app`, `ts_db`, `ts_nginx` activos en puerto 3031 |
| API REST | ✅ Funcionando | `POST /api/login` responde correctamente con JSON |
| Frontend Expo | ✅ Compila | TypeScript sin errores, bundle genera ~1MB |
| Web export | ✅ Funcionando | Export estático en `dist/`, servido en `0.0.0.0:8081` |
| Flujo Login → Home | ✅ Completo | Login → Selección institución → Home → Módulos |
| Pantallas módulos | ✅ Todas operativas | Rondas, Accesos, Novedades, Alertas, Inventario, Biometría, Perfil |

**Para acceder desde otra PC en la misma red:**
1. Levantar Expo: `npx expo start --web --port 8081` (o export estático: `npx expo export --platform web`)
2. Abrir `http://192.168.100.212:8081` desde el navegador

**Nota:** El error "React Native DevTools" al iniciar Expo es esperado en servidores Linux sin interfaz gráfica (Electron no tiene `--no-sandbox`). No afecta al funcionamiento de la app.

## Pendientes conocidos

- **Firebase/Google Play:** falta `google-services.json` para notificaciones push en dispositivos reales en producción (la app ya registra el token; sin Firebase no llega la notificación a teléfonos). Se debe evitar versionar el archivo con credenciales reales en el repo.
- **Cambio de contraseña por email:** la app puede completar el flujo porque la API devuelve el token; si se quiere estricto por correo, usar deep linking (`Linking` + scheme).
- **Compilar APK:** el APK **release standalone** ya se compiló (`android/app/build/outputs/apk/release/app-release.apk`, ~99 MB, firmado con debug keystore, `com.dt360.coreapp` v1.0.0, targetSdk 36). Para producción real: generar keystore propio y usar `npx expo prebuild`/EAS. El APK debug (`app-debug.apk`, ~190 MB) solo funciona con Metro levantado (`npx expo start`).
- **Unificar `Role` con `roles`** (`Modules/Acceso/Models/`): no son duplicados exactos, `Role` es el modelo de Spatie declarado en `config/permission.php` y `roles` es el `$model` de `RolesResource` de Filament. Unificarlos exige refactorizar el resource; queda pendiente de decisión.
