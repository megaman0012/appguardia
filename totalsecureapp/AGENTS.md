# AGENTS.md — Total Secure App

## Estructura del proyecto

- `backend/` — API Laravel 8.75 (modular) que consume la app. Es el backend `coredt360` migrado, con historia git local completa. Módulo principal de la app: `Modules/MobileApp`.
- `src/` — Código de la app Expo (SDK 57).
- `android/` — Proyecto nativo Android generado con `expo run:android` (se versiona para que el APK se pueda compilar en otro servidor sin `expo prebuild`).
- `apk_extracted/` — APK original descompilada (referencia). `appdeguardias.apk` es el APK original.
- `docs/historia/HISTORIAL_DE_CHAT.md` — Resumen del trabajo previo (en la carpeta padre).

## Backend

- **Git / remote:** el monorepo (`appguardia/`) tiene el remote `origin` = `git@github.com:megaman0012/appguardia.git`, y `main` sigue a `origin/main`. Commitear localmente cuando corresponda, pero **no hacer `push`/`pull`/`fetch` sin autorización explícita del usuario**, y no agregar ni cambiar remotes por iniciativa propia.
- **Propiedad de archivos:** todo el monorepo debe pertenecer al usuario que opera el servidor, no a `root`; si aparece «posesión dudosa detectada» es que algo corrió como `root`. El dueño **depende del servidor**, no del proyecto: era `server-gea` en el equipo de desarrollo original y es `server-dt` en el servidor de 2026-09-07. No dar por hecho ninguno de los dos: verificar con `ls -ld`.
  - **El PHP-FPM del contenedor corre con UID 1000** (lo fija el `Dockerfile`), así que los archivos que escribe Laravel salen con ese UID. Mientras el dueño del repo sea el UID 1000 del host coincide; si no, hay que ajustar el `Dockerfile`.
  - **Si el usuario está en el grupo `docker`, `docker compose` no necesita `sudo`** y una sesión de agente puede levantar el stack entera. Comprobarlo con `id`. Solo cuando no lo está aplica el camino alterno: PHP local contra Postgres en el puerto host `5434` (`DB_PORT=5434 php artisan ...`), o pedir el comando en una terminal real.
- **Ejecución con Docker (recomendado).** Todo el stack (nginx + PHP-FPM 8.3 + PostgreSQL 16) corre con Docker Compose desde `backend/`:
  - `docker compose up -d` — levanta todo. Backend en http://localhost:3031, Postgres en `127.0.0.1:5434` (host) / `db:5432` (red docker).
  - `docker compose exec backend php artisan migrate` — comandos de Laravel dentro del contenedor.
  - `docker compose logs -f` — logs; `docker compose down` — detiene sin borrar datos (volumen `pgdata`).
  - Las credenciales de BD se toman del `.env` (variables `DB_*`). `DB_HOST` se sobrescribe a `db` dentro de los contenedores.
  - **El scheduler va por cron del host, no dentro del contenedor.**
    `scripts/schedule-run.sh` envuelve el `docker compose exec ... schedule:run`
    y se instala en el crontab del **dueño del repo** (no de root: root no está
    en el grupo `docker` ni es dueño de `storage/`). Instalado el 2026-09-07 en
    `server-dt`, con log en `storage/logs/schedule-cron.log`. **Sin esto no hay
    detección de faltas**: `turnos:revisar-cobertura` corre cada 5 minutos y es
    lo único que descubre un puesto vacío a tiempo para cubrirlo;
    `turnos:cerrar-dia` corre a las 23:55, cuando ya no sirve.
    El script usa rutas absolutas a propósito: cron trae un `PATH` mínimo y
    `docker` a secas no se resuelve.
  - **El proyecto de Compose se llama `backend`**, porque Docker lo deriva del nombre del directorio y el `docker-compose.yml` vive en `backend/`. De ahí el volumen `backend_pgdata` y la red `backend_default`. En un servidor con varios proyectos eso es ambiguo, pero **cambiarlo con `name:` no es cosmético**: Compose lo tomaría como un proyecto nuevo, dejaría `backend_pgdata` huérfano y arrancaría con una base vacía. Si algún día se cambia, va con volcado y restauración, no en caliente.
  - **`docker/postgres/init/`** se monta en `/docker-entrypoint-initdb.d`. Postgres solo lo ejecuta al **inicializar un volumen vacío**, así que agregar un script ahí no afecta a una instalación existente: hay que aplicarlo a mano además. Hoy solo crea `coredt360_testing` (ver Pruebas). En producción eso deja una base de pruebas vacía sin usar; es ruido conocido y aceptado, no un error.
  - **`memory_limit` de PHP: 512M**, fijado en `conf.d/zz-memory.ini` desde el `Dockerfile`. Con los 128M por defecto `php artisan test` se cae con `Allowed memory size exhausted` **a mitad de la suite**, y las docenas de tests que quedan marcados como fallidos parecen bugs de lógica. No bajarlo.
- `.env` está en `.gitignore` (contiene credenciales SMTP reales, no versionar). `composer.phar` también ignorado. `.env.example` está actualizado para Postgres sin secretos.
- Autenticación API: Sanctum (bearer token). Las rutas de la app están en `backend/Modules/MobileApp/Routes/api.php` (`POST api/login`, `api/instituciones`, `api/rondas`, …). El prefijo real es `api/` (el `i/` del APK original era un alias del proxy).
- Middleware CORS/seguridad personalizados (`App\Http\Middleware\HandleCors`, `SecurityHeaders`, `App\Services\CorsService`) se corrigieron para funcionar en PHP 8.3.
- **BD migrada y sembrada.** `php artisan migrate` crea todo el esquema (incluye tablas de negocio de la app). `php artisan db:seed` crea:
  - Usuario demo: cédula `1234567890` (roles Vigilante **y** Supervisor, con gestión activa). **En un clon nuevo la contraseña ES `123456`**, porque es lo que `DatabaseSeeder` escribe en claro (`'usu_password' => '123456'`). Verificado el 2026-09-07 contra `POST /api/login` en una base recién sembrada.
    - ⚠️ Corrige lo que decía antes esta línea («la contraseña no es `123456`»). La clave unificada del 18/08/2026 que figura en `docs/historia/HISTORIAL_DE_CHAT.md` era la de **aquella base ya en marcha**, puesta después de sembrar; no es lo que produce el seeder. Confundir las dos cuesta un rato de «clave incorrecta» creyendo que el clon quedó mal.
    - Por lo mismo, **rotar la clave en la base no arregla nada por sí solo**: el próximo `db:seed` la devuelve a `123456`. Para producción hay que tocar el seeder o no correrlo, y crear el administrador con `usuario:crear`.
    - ⚠️ La clave del piloto sigue en texto plano en `docs/historia/HISTORIAL_DE_CHAT.md`, **versionado y ya en el remoto de GitHub**: tratarla como comprometida donde se haya reutilizado. (`Credencial única todos.txt` sí está en `.gitignore`.)
    - Para probar la API sin usar ninguna credencial, generar un token: `docker compose exec -u 1000 backend php artisan tinker --execute='...createToken("probe")->plainTextToken'`.
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

## Validacion de presencia y geocerca

**El login NO valida ubicacion.** Se autentica desde cualquier lugar; verificado
el 2026-09-07 haciendo `POST api/login` sin enviar coordenada alguna. La
presencia se comprueba en el **marcaje**, no en la entrada al sistema, y tiene
sentido: la app vive en la tablet del puesto (ver «Donde vive la app»), asi que
lo que hay que probar es que el guardia estaba ahi cuando marco. Si alguien pide
«que no puedan entrar si no estan en el punto», eso **no es lo que hace hoy** y
es un cambio de diseño, no un bug.

`App\Services\PresenceValidationService` es el unico lugar donde se resuelve
esto. Radio por local en `organizacion_institucion.ins_radio_tolerancia_metros`
(100 m por defecto), distancia por Haversine.

| Modulo | Que hace | Bloquea |
|---|---|---|
| **Biometria** (marcaje) | `validarUbicacion()`: GPS contra el primer marcador activo | **Si**, fuera del radio se rechaza |
| **Rondas** (QR) | `validarPresencia()`: QR + GPS + geocerca contra el marcador del QR | **Si** |
| **Accesos** | `medirUbicacion()`: mide y lo deja escrito | **No**, a proposito |
| **Novedades** | nada | — |

- **Rondas no puede fail-open**: el marcador sale del QR descifrado, y sin
  marcador el QR es invalido. Falla cerrado por construccion.
- **Biometria si podia**, y ese era el hueco (abajo).
- **Accesos mide pero no rechaza.** Un visitante legitimo no se puede quedar
  afuera porque el GPS del dispositivo ande mal, y la app manda `0/0` cuando no
  obtuvo ubicacion. Rechazar ahi es una decision de negocio que todavia no se
  tomo; el dato ya queda para poder tomarla con numeros.
- **`0/0` no es una coordenada, es «no se sabe».** `medirUbicacion()` lo descarta
  explicitamente: medirlo contra el golfo de Guinea daria una distancia enorme y
  perfectamente falsa. Igual con null y con texto no numerico.

### El fail-open de la geocerca (encontrado y corregido el 2026-09-07)

**Un local SIN marcador activo aceptaba marcajes desde cualquier lugar, en
silencio.** `validarUbicacion()` medía solo si encontraba un marcador; si no,
devolvia `valido = true` sin mirar nada. Comprobado en vivo: desactivando los
marcadores, un marcaje desde **Quito, a 273 km del local**, entro con
`distancia_m: 0` y quedo en la base **indistinguible** de uno hecho en la garita.
Ningun test lo cubria, y por eso nadie lo habia notado.

Lo que se decidio: **aceptar pero marcar**, no bloquear. Un guardia no puede
perder su asistencia porque a alguien le falto configurar el local, y en
produccion un local mal cargado dejaria a su gente sin poder marcar a las 6 de la
mañana. Pero deja de ser invisible.

- El servicio devuelve **`verificado`** aparte de `valido`. Son cosas distintas:
  `valido` es «se acepta», `verificado` es «lo comprobe de verdad». Colapsarlas
  fue exactamente el origen del hueco.
- Migracion `2026_09_07_100001_add_verificacion_ubicacion`:
  `bio_ubicacion_verificada` + `bio_distancia_m` en `user_has_biometria`, y
  `ac_ubicacion_verificada` + `ac_distancia_m` en `acceso`. **La columna se lee
  junto con la distancia**, porque hay cuatro estados y un booleano solo no
  alcanza:

  | verificada | distancia | Significa |
  |---|---|---|
  | `true` | numero | Se midio y estaba **dentro** del radio |
  | `false` | numero | Se midio y estaba **fuera** (solo pasa en accesos: biometria lo rechaza antes) |
  | `false` | `null` | **No se pudo medir**: el local no tiene marcador activo, o el dispositivo no dio ubicacion |
  | `null` | `null` | Fila anterior a la migracion: no se sabe |

- **Fuera del radio sale con `verificado = true`.** Se rechaza justamente porque
  se pudo medir. Marcarlo como no verificado confundiria «esta lejos» con «no se
  sabe donde esta», que es la distincion que hace util la columna.
- En el panel: columna **Ubicacion** (badge) y filtro **«Solo ubicacion sin
  verificar»** en Biometria y en Accesos. El filtro es el punto: sin el habria que
  exportar a Excel para encontrar esas filas, y por eso nadie las miraria.
- `VerificacionUbicacionTest` fija los cuatro estados. **Al tocar la geocerca,
  correrlo**: es lo unico que impide volver al fail-open silencioso.
- **La marca la calcula el servidor, no la manda el cliente.** En accesos se
  asigna a `$datos` **despues** de `$request->all()`, asi que un cliente que
  mande `ubicacion_verificada=true` la ve sobreescrita; en biometria va por
  asignacion directa al modelo, fuera de `$fillable`. Si algun dia se reordena
  ese `$datos[...] = ...`, la columna pasa a ser un campo que el dispositivo
  puede rellenar y deja de servir para auditar.

**Pendiente de negocio:** decidir si a partir de cierto punto se bloquea el
marcaje de un local sin marcadores. Hoy no se bloquea. Lo que hay que mirar antes
es cuantas filas salen con `verificada = false`; si son muchas, el problema es la
carga de datos y no la regla.

## Inventario: un solo juego de tablas (arreglado el 2026-09-07)

**El guardia escribia en unas tablas y el panel leia otras.** FASE1 diseño el
juego nuevo para reemplazar al viejo, la app movil se movio, y el panel se quedo
atras. Un inventario hecho desde la tablet no aparecia en el panel, y al reves;
peor todavia, `DatabaseSeeder` sembraba las tablas VIEJAS, asi que el inventario
de demostracion **no existia para la app** y su pantalla salia vacia. No lo
provocaba ninguna migracion: pasaba desde que se hizo FASE1.

Ahora **todo apunta al juego nuevo**:

| Tabla vieja (ya no se usa) | Tabla viva |
|---|---|
| `inv_productos` | `inv_producto_catalogo` |
| `inv_listas_productos` | `inv_lista` |
| `inv_lista_producto_items` | `inv_lista_item` |
| `inv_movimientos` | `inv_movimiento_cabecera` |
| `inv_movimiento_detalles` | `inv_movimiento_detalle` |

- **No fue un cambio de nombres, fue un cambio de forma.** En `inv_movimientos`
  una fila era el ciclo COMPLETO, con cuatro juegos de columnas
  (`mov_recep_asig_*`, `mov_recep_*`, `mov_devol_*`, `mov_devol_entreg_*`). Ahora
  **cada fila es UN evento** y su tipo esta en `mc_tipo`
  (`recepcion`/`devolucion`/`baja`). Ya no existe una fila con "fecha de
  recepcion" y "fecha de devolucion" a la vez: hay una `mc_fecha` y el tipo dice
  de que es. El filtro de toggles que mostraba y escondia "campos de recepcion" y
  "campos de devolucion" desaparecio porque dejo de tener sentido.
- Igual en el detalle: `md_cant_asign`/`md_cant_recep`/`md_cant_devol`/
  `md_cant_final` se reducen a **esperada** (`md_cantidad_default`, que viene de la
  lista) y **contada** (`md_cantidad_real`). Y `md_estado` dejo de ser booleano:
  es `ok`/`falta`/`danado`.
- **Los productos son POR LOCAL** (`ipc_ins_code`); en `inv_productos` eran
  globales. Por eso `InvProductoResource` ahora tiene alcance por perfil, que
  antes no le hacia falta, y el selector de producto de una lista solo ofrece los
  del local de esa lista.
- **En `Lista`, `items()` y `productos()` NO son lo mismo.** `items()` es el
  hasMany a las filas del pivote (lo que se edita en el panel) y `productos()` es
  un belongsToMany a los productos. En el modelo viejo `productos()` era un
  hasMany al pivote, con el nombre equivocado. Apuntar el RelationManager a
  `productos` devuelve modelos de producto y las columnas de cantidad quedan
  vacias.
- **Los 4 resources declaran `$slug`.** Filament deriva la ruta del nombre del
  modelo: sin el slug, `/admin/inv-movimientos` se habria convertido en
  `/admin/movimiento-cabeceras` y el boton «Detalles» habria dejado de resolver.

Tres cosas que estaban rotas y se arreglaron de paso:

1. **El filtro de fecha de movimientos consultaba `mov_fecha_recepcion`**, que no
   es una columna: usarlo reventaba con error de SQL. Ahora va contra `mc_fecha`.
2. **`InvMovimientoDetalleResource` no tenia `canViewAny()`** y filtraba solo por
   el `?mov=` de la URL. Como el id es un entero consecutivo, un supervisor leia
   el inventario de cualquier local cambiando el numero. Ahora el alcance se
   aplica **subiendo al movimiento** con `whereHas`, porque el detalle no guarda
   el local. Verificado: con un local ajeno la tabla sale vacia, con el propio se
   ven las filas.
3. **El RelationManager escribia `im_updated_user`**, que no es columna de esa
   tabla: la auditoria de quien editaba se perdia en silencio.

**Al tocar estas pantallas, probarlas con filas reales.** Las cuatro devolvian 200
con las tablas vacias antes y despues del cambio; lo que demuestra algo es que
aparezcan los datos (ver `PantallasConDatosTest`). El seeder ahora deja un
movimiento con una diferencia a proposito —2 esperados, 1 contado, estado
`falta`— porque el caso interesante del inventario es el que no cuadra.

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

- **Eager loading obligatorio en los resources de Filament.** Las columnas del tipo `institucion.cliente.org_descripcion` disparan una consulta por relación y por fila. Cada resource declara su constante `RELACIONES_TABLA` y la aplica en `getEloquentQuery()`. Medido: 5N+1 consultas sin eso, o sea 126 por página con las 25 filas por defecto, contra 6 constantes. `EagerLoadingTest` recorre el directorio de resources y falla si uno usa columnas de relación sin declararlas, así que un resource nuevo no puede omitirlo.
- **Caché del dashboard: la invalidación es por evento, no por TTL.** `App\Services\DashboardStatsService` incluye un contador de versión por institución en la clave, y los observers de `Alertas` y `Turno` (registrados en `EventServiceProvider`) lo suben en cada escritura. **No usar `Cache::tags()`**: el driver configurado es `file` y lanzaría `BadMethodCallException`. La invalidación va en observers y no en los services para cubrir también las escrituras del panel y de los seeders.
- Índices compuestos en la migración `2026_08_21_500001`, con el orden igualdad→rango. Al agregar un filtro nuevo, revisar si necesita índice; el más caliente es `(ui_usu_id, ui_state)` de `user_has_institucion`, que se consulta en cada request del portal y en cada validación de institución de la app.
- Tras desplegar índices, correr `ANALYZE`: sin estadísticas frescas el planner los ignora.
- **Los tests de turnos congelan el reloj** (`Carbon::setTestNow`). Sin eso, media docena de casos que arman un turno «de hoy, 06:00 a 14:00» empezaban a fallar solos cuando el suite corría después de las 14:00. Al escribir un test que dependa de la hora, congelar el reloj en el `setUp` y liberarlo en el `tearDown`.
- **Renderizar una tabla vacía no prueba nada.** `PantallasConDatosTest` dibuja las pantallas con filas reales, porque los errores de formato aparecen con el primer registro: `TurnoResource` respondía 500 en cuanto existía un turno sin marcar, que es el estado normal de todo turno futuro.
- `CHECKLIST-DESPLIEGUE-V2.md` (raíz del monorepo) tiene el backup obligatorio, el orden de las migraciones y el rollback por fase. **La migración `2026_08_21_100002` borra `ac_nombre_contrato` sin destino**, así que su `down()` no lo recupera: solo el backup.

## Limpieza del sistema de salud heredado (2026-08-24)

El backend venía de `coredt360`/HagpAsist, un sistema hospitalario, y arrastraba código que no era de guardias. Se eliminó por completo (migración `2026_08_24_100001`, reversible):

- Módulo `Formularios` entero: Epicrisis (formulario 006 del MSP) y Referencia (053). No funcionaban: consultaban tablas de un HIS (`capbas`, `epiman`, `ingresos`, `maedia`…) sobre una conexión `hagphosv` que nunca existió.
- Pantalla de Personas (`administracion/persona.index`), su controlador, vistas y assets.
- 7 tablas y sus modelos: `persona`, `tipo_documento`, `tipo_genero`, `tipo_pais`, `tipo_especialidad`, `tipo_servicio`, `referencia_motivo`. Ninguna la usaba el sistema de guardias.
- `LoginController@login_check_temp`: login contra la intranet del hospital (`DB::connection('intranet')`, `perm_epicrisis`), código muerto sin ruta.
- Secciones de permisos 1 (Administración) y 2 (Formularios), reemplazadas por la 3 (Panel) con el permiso `admin`.

**Al agregar permisos web, no reutilizar `ps_codigo` 1 ni 2.** `PermisosApiService::SECCIONES_WEB` sigue listando 1, 2 y 3 para que una base vieja que aún las tenga no filtre permisos web hacia la API.

## Eliminación del nivel «sede» (2026-08-27)

`sede`, `organizacion_sede` y `organizacion_institucion.ins_so_code` eran otra
herencia de coredt360: un nivel intermedio (organización → sede → institución) que
hacía lo mismo que hoy hacen el **cliente** (`ins_cliente_id`) y la **geografía**
(país → provincia → ciudad → local). Nunca se usó — las tres tablas estaban
vacías y ningún local tenía sede — y su único efecto era una columna en blanco en
media docena de pantallas más tres menús que no llevaban a nada.

- Se eliminó con la migración `2026_08_27_100001_eliminar_sede`, que **antes de
  borrar rescata el cliente** que colgaba de la sede hacia `ins_cliente_id`. En
  desarrollo no había nada que rescatar; en producción no se puede asumir lo
  mismo.
- `down()` recrea la estructura pero **no los datos**: el vínculo local→sede solo
  vuelve desde un backup. Mismo criterio que `2026_08_21_100002`.
- **`organizacion` NO se tocó: esa es la tabla de clientes** (ahí va DHL), y es a
  donde apuntan ahora las columnas que antes llegaban por la cadena de sede.
- La unicidad del nombre de un local colgaba de la sede; ahora se acota por
  **ciudad**: «Bodega Norte» puede existir en Quito y en Guayaquil.

**No volver a introducir un nivel entre cliente y local.** Si hiciera falta
modelar contratos con vigencias distintas para un mismo cliente, eso es una tabla
nueva con ese nombre, no la resurrección de `sede`.

## Panel: dos fallos de compatibilidad ya resueltos (2026-08-24)

- **`FILAMENT_LIVEWIRE` debe quedar VACÍO en el `.env`.** Alimenta `asset_url` de `config/livewire.php`, y Livewire arma la URL de su JS como `<valor>/livewire/livewire.js`. Vacío da **`/livewire/livewire.js`**, que es la ruta que Livewire registra de verdad (`route:list | grep livewire`) y responde 200. Esa es la URL que hay que verificar; `/vendor/livewire/livewire.js` **no existe** en esta instalación y da 404 aunque el panel esté sano. Traía `http://localhost:3031/coredt360/public` (resto de la instalación original en subdirectorio), así que el JS daba **404** y el panel se renderizaba pero **no respondía a nada**: el selector de columnas, los filtros, la búsqueda y los modales quedaban muertos. Vacío produce la ruta relativa, que funciona en cualquier host. No poner el dominio con `/admin`.
- **Shims de Laravel 9 en `AppServiceProvider::registrarShimsDeLaravel9()`.** Filament 2.17 usa dos APIs que Eloquent/Support recién traen desde Laravel 9 y en 8.75 no existen. Ambos macros se autodesactivan si el método aparece, así que al subir a Laravel 9+ se pueden borrar.
  - `Stringable::toHtmlString()` — Filament la llama al renderizar `helperText` y `hint`: `Str::of($helperText)->markdown()->sanitizeHtml()->toHtmlString()`. **Sin el shim, cualquier formulario con `helperText` responde 500.** No hace falta shim para `sanitizeHtml()`: la registra el propio Filament.
  - `Model::resolveRouteBindingQuery()` — Filament 2.17 llama a `$model->resolveRouteBindingQuery(...)`, método que Eloquent recién trae desde Laravel 9; en 8.75 no existe y **todas** las páginas de edición del panel respondían 500. Se registra como macro del Builder (`Model::__call` reenvía ahí), lo que cubre los ~20 modelos sin tocarlos. El shim se autodesactiva si el método existe, así que al subir a Laravel 9+ se puede borrar.

## Accesos: seis columnas muertas, y la mas llena invisible (2026-09-08)

La pantalla de Accesos listaba `ac_patente`, `ac_is_sello`, `ac_is_neumatico`,
`ac_is_carro`, `ac_pta_llave` y `ac_kms`. **Esas columnas ya no existen en
`acceso`**: v2 las normalizo en `acceso_vehiculo`. Filament no se queja de una
columna que no existe -- la pinta vacia -- asi que eran **seis columnas muertas
ocupando ancho** desde que se hizo esa normalizacion.

Ahora salen de la relacion `vehiculo`, y de paso aparecio la que faltaba:
**`av_empresa` esta llena en el 92% de los accesos y no se veia en ninguna
parte.** Tambien se sumo `visitante.avi_persona_visita`, que es donde el ETL
rescato `ac_nombre_contrato`.

`vehiculo` y `visitante` se agregaron a `RELACIONES_TABLA`; sin eso
`EagerLoadingTest` falla, que es exactamente para lo que existe.

### Columnas casi vacias: ocultas por defecto, no borradas

Medido sobre las filas reales, no a ojo. `toggleable(isToggledHiddenByDefault:
true)` las saca de la vista inicial pero las deja en el selector de columnas:
borrarlas perderia el dato de las filas que si lo tienen.

| Columna | Llenado real | |
|---|---:|---|
| `acceso.ac_distancia_m` | 0% | todo lo migrado viene sin medir |
| `acceso.ac_temperatura` | 2,3% | |
| `acceso.ac_rut_acomp` / `ac_nomb_acomp` | 2-3% | |
| `acceso.ac_bicicleta` | 7,4% | |
| `visitante.avi_persona_visita` | 0,4% | |
| `user_has_biometria.bio_distancia_m` | 0% | idem, todo lo migrado |

La pantalla de Accesos paso de **22 columnas a 15 visibles**.

### El modo oscuro NO estaba forzado

Anotado porque es facil de suponer al revés: `FILAMENT_DARK=true` **no fuerza**
el tema oscuro, **habilita que cada usuario elija** entre claro, oscuro y «segun
el sistema». El interruptor ya esta en el menu de usuario y no habia nada que
arreglar.

## Escritorio: cuatro filas con titulo (2026-09-08)

**El escritorio era una pared de ceros.** Los tres widgets median alertas activas
(0: todas las migradas estan finalizadas), cumplimiento de turnos (0: v1 no tenia
turnos) y estado de WhatsApp (sin configurar, ocupando el ancho completo arriba).
Mientras tanto habia 452 marcajes, 460 rondas y 218 accesos en 7 dias que nadie
veia.

Ahora son cuatro filas, en este orden:

1. **Actividad de los últimos 7 días** — marcajes, rondas, accesos, novedades,
   cada uno **comparado con los 7 dias anteriores**.
2. **Estado del sistema** — locales sin punto QR, locales activos sin actividad,
   usuarios sin perfil, marcajes sin verificar.
3. **Alertas** (el widget que ya existia).
4. **Turnos de hoy** (idem).

### Por que 7 dias y no «hoy»

Un escritorio que mide el dia corriente **amanece en cero todas las mañanas**, y
el turno de la noche lo abre sin nada que mirar. Peor: mientras la operacion no
arranque se ve vacio y parece roto. La ventana de 7 dias siempre tiene contenido y
sigue sirviendo despues.

Y cada tarjeta compara con la semana anterior, porque el numero solo no dice nada:
452 marcajes esta bien o mal segun si la semana pasada fueron 400 o 900. **Una
caida se pinta en ambar, no en rojo**: puede ser un puesto abandonado, pero
tambien un feriado o un contrato que termino.

### `AcotaPorAlcance`: el bug que dejaba el escritorio en blanco

Los widgets acotaban por `user_has_institucion`, y con eso **un Administrador o
la Consola veian CEROS**: su alcance es global, asi que no estan vinculados a
ningun local y la lista salia vacia. El escritorio quedaba en blanco justo para
los perfiles que tienen que verlo todo.

El trait `App\Filament\Widgets\Concerns\AcotaPorAlcance` resuelve los tres
alcances de `PerfilPanel` en un solo lugar. **`null` y `[]` no son lo mismo**:
`null` es «sin filtro» y `[]` es «no ve nada»; un lider sin paises cae en `[]` a
proposito.

### Detalles de la fila «Estado del sistema»

- **Cada tarjeta enlaza al listado donde se arregla.** Un numero que no dice
  adonde ir se mira una vez y despues se ignora.
- «Locales sin punto QR» muestra **cuantos de ellos ya reciben marcajes**, que es
  el dato que importa: uno que todavia no opera es una tarea pendiente, uno que ya
  marca esta acumulando asistencia que nadie va a poder auditar.
- «Usuarios sin perfil» **no se acota por local**, porque un usuario sin perfil
  tampoco tiene por que tener local: filtrarlo lo esconderia de quien puede
  arreglarlo. Solo lo ve quien tiene alcance global.
- «Marcajes sin verificar» cuenta `false`, **no `null`**. `null` es «fila anterior
  a la migracion»: contar los 12.664 marcajes de v1 llenaria la tarjeta de un
  problema que ya no se puede arreglar.

### `stats-con-titulo.blade.php`

**Filament 2 no soporta encabezado en `StatsOverviewWidget`**: su vista pinta las
tarjetas y nada mas, y `$heading` se ignora en silencio. Con dos filas de cuatro
tarjetas quedaban ocho numeros seguidos sin distinguir cual mide operacion y cual
mide configuracion. La vista propia agrega titulo y una linea de ayuda, leidos de
`getEncabezado()` y `getAyuda()`.

### WhatsApp solo aparece si esta configurado

`EstadoWhatsapp::canView()` exige `WHATSAPP_URL`. Sin el canal, la tarjeta decia
«no configurado» ocupando la franja mas visible del escritorio. Cuando se
configure vuelve sola, y ahi si tiene algo que decir.

### Bitácora y Perfiles y permisos: visibles

Tenian `shouldRegisterNavigation = false` y solo se llegaba escribiendo la URL.

## Panel: como se presenta la informacion (2026-09-08)

Revision de presentacion sobre el panel ya cargado con los datos reales.

### Los nombres del menu decian otra cosa que los datos

El cambio de fondo. `organizacion` **es la tabla de clientes** (ahi va DHL) y
`organizacion_institucion` son **los locales**; el menu los llamaba
«Organizacion» y «Organizacion > Institucion». Los documentos de este repo
hablan de clientes y locales en todas sus paginas, asi que el panel era el unico
lugar donde se llamaban de otra forma -- y justo el lugar donde alguien aprende
el sistema.

| Antes | Ahora |
|---|---|
| Organizacion | **Clientes** |
| Organizacion > Institucion | **Locales** |
| Usuarios > Perfiles | **Perfil por usuario** |
| Usuarios > Institucion | **Locales por usuario** |
| Usuarios > Gestion | **Gestiones** |
| Listas > Productos | **Listas** |
| Inventario Equipamento | **Movimientos** |

- **El `>` de las etiquetas desaparecio.** Fingia una jerarquia en el nombre
  cuando para eso estan los grupos, y dejaba cuatro items del menu empezando con
  la palabra «Usuarios».
- **Habia DOS items llamados «Perfiles»**: `RolesResource` (el catalogo de
  perfiles) y `UserHasRolesResource` (que perfil tiene cada usuario). Ahora son
  «Perfiles y permisos» y «Perfil por usuario».
- Acentos donde faltaban: Biometria→Biometría, Bitacora→Bitácora,
  Reporteria→Reportería, y «Equipamento»→ ya no aplica.

### Los grupos se reordenaron por frecuencia de uso

`Filament::registerNavigationGroups()` en `AppServiceProvider`. **Sin eso
Filament ordena los grupos por el `navigationSort` mas bajo de sus items**, y
como cada grupo empieza en 1 el orden sale arbitrario: «Inventario» aparecia
arriba de «Centros de operacion».

Orden: **Operación** (turnos, cuadrantes, cobertura: el dia a dia) → **Reportería**
(lo que el guardia registro) → **Inventario** → **Centros de operación** (clientes,
locales, puestos: se cargan y se dejan) → **Ubicación geográfica** (catalogo) →
**Configuración**.

- **`Puestos de trabajo` salio de «Ubicación geográfica».** Un puesto no es
  geografia: es una posicion dentro de un local, asi que va con Clientes y
  Locales.
- **El inventario quedo junto.** Los movimientos estaban en «Reportería»,
  separados de sus propios productos y listas.

### `modelLabel` declarado en los 26 recursos

Filament arma con eso las migas, el boton «Crear …» y el aviso de tabla vacia.
Sin declararlo los deriva del nombre de la clase, y despues de repuntar el
inventario a los modelos de FASE1 las migas de crear un producto decian
**«Producto Catalogos»**.

### Sin migas en los listados

`App\Filament\Pages\ListadoBase` (la extienden las 27 paginas de listado)
devuelve `[]` en `getBreadcrumbs()`. En un listado las migas eran «Novedades /
Listado»: el segundo eslabon es el encabezado que la pagina ya muestra debajo, y
el primero apunta a la pagina en la que ya estas.

**En crear y editar se dejan**, porque ahi el primer eslabon es el camino de
vuelta al listado -- la unica forma comoda de salir de un formulario sin guardar.

Va como clase base y no como override de la vista Blade de Filament porque la
vista recibe solo el arreglo de migas: no sabe si esta en un listado o en un
formulario.

## Panel: ancho y barra de depuracion (2026-09-07)

- **`max_content_width` = `'full'`** en `config/filament.php`. Con `null`
  Filament aplica su tope de 7xl (~1280 px) y el listado queda como un recuadro
  con espacio vacio a los lados. Estas tablas tienen muchas columnas -- cliente,
  local, guardia, fecha, ubicacion, distancia -- y con el tope hay que ir
  corriendo la barra horizontal para leer una fila completa.
- **`DEBUGBAR_ENABLED=false`.** `barryvdh/laravel-debugbar` se enciende solo con
  `APP_DEBUG=true` y pinta su barra al pie del navegador. Se apago porque
  **recolecta todas las consultas y las incrusta en el HTML**: con tablas de
  decenas de miles de filas se nota, y expone consultas y configuracion a quien
  mire la pantalla. Ponerla en true solo para diagnosticar algo puntual.
  - ⚠️ **Esta declarada en `require`, no en `require-dev`**, asi que viaja a
    produccion. Con `APP_DEBUG=false` no se muestra, pero el lugar correcto es
    `require-dev`.
  - **El `APP_DEBUG` del `docker-compose.yml` gana sobre el del `.env`** (Docker
    exporta la variable y phpdotenv no sobreescribe lo que ya esta en el
    entorno). Por eso se apaga con `DEBUGBAR_ENABLED`, que compose no define.

## Inventario: el bug que activo la migracion (2026-09-08)

**El mas importante de esta ronda, y lo introdujo el ETL.**

`saveListMov` no permite abrir una recepcion si ya hay una. El chequeo original
preguntaba, sin filtro de fecha, si existia **alguna** fila de tipo `recepcion`
para (local, lista, guardia). Y alcanzaba, porque `finishListMov` **muta** la fila:
al cerrar el turno le cambia `mc_tipo` de `recepcion` a `devolucion`, asi que un
ciclo cerrado deja de tener fila de recepcion.

**El ETL cargo una fila por evento**, no una que muta: 5.963 recepciones y 5.916
devoluciones. Se eligio asi porque v1 guardaba las dos fechas en columnas
distintas y colapsarlas habria perdido la de recepcion. Pero con eso, las
recepciones historicas se leian como **abiertas**:

> **589 combinaciones bloqueadas: 212 guardias en 91 locales** no habrian podido
> registrar inventario nunca mas. Y no daba error de sistema: devolvia «Ya existe
> una recepción registrada para esta lista», que parece una validacion correcta.

**La regla correcta es «sin devolucion posterior»**, no «existe una recepcion».
Con eso quedan **47** recepciones abiertas, que son exactamente los ciclos que en
v1 nunca se cerraron. Se desbloquean 542 combinaciones.

Es un arreglo **de servidor: no obliga a recompilar el APK.** El contrato de la
respuesta (`message` + `id`) no se toco.

### Lo que NO se hizo, y por que

Se penso un **indice unico parcial** para cerrar la carrera entre dos toques
seguidos (el mismo recurso que `turno_vacante_turno_viva_unique`). Se descarto:
un guardia recibe la misma lista **una vez por turno**, asi que la unicidad por
(local, lista, guardia) es falsa -- los datos migrados tienen 346 grupos que la
violarian. Lo que distingue una recepcion abierta de una cerrada no son las
claves, es si tiene una devolucion posterior, y eso no cabe en un indice unico.

**La carrera sigue abierta.** Cerrarla de verdad necesita `client_uuid` en este
endpoint, como los cinco de campo, y eso **si** obliga a cambiar la app.

### Las dos optimizaciones que si entraron

- **Un solo INSERT para los detalles** en vez de uno por producto: eran cuatro
  viajes a la base por movimiento (4 items por lista en promedio), dentro de la
  transaccion y sobre la red movil de una tablet. Con `insert()` masivo Eloquent
  **no llena los timestamps**, asi que hay que ponerlos a mano o quedan nulos.
- **`idx_movimiento_ciclo`** `(mc_ins_code, mc_lista_id, mc_usuario_id, mc_tipo,
  mc_fecha)`. Antes el chequeo entraba por `idx_movimiento_lista` y filtraba las
  otras cuatro condiciones a mano, unas 90 filas por lista. Ahora el plan usa el
  indice en **las dos** partes de la consulta: 3 filas leidas.

### Lo que ya estaba bien

`allListByInst` -- el endpoint que corre en cada tablet al abrir inventario --
usa carga anticipada con seleccion de columnas: **3 consultas y 5,5 ms de SQL**,
sin importar cuantas listas tenga el local. No habia nada que arreglar ahi.

## Los listados abren mostrando solo lo activo (2026-09-08)

`App\Filament\Tables\FiltroDeEstado`, en ocho pantallas: Usuarios, Locales,
Clientes, Marcadores QR, Locales por usuario, Gestiones, Productos y Listas de
inventario.

**Por que.** Aca nada se borra: un guardia que se va, un local que cierra o un
cliente que termina contrato se **desactivan**, porque su historial de rondas,
marcajes y accesos tiene que seguir consultable. El efecto es que los listados
mezclan lo que opera con lo que ya no. En Clientes es lo mas notorio: **6 de 21
son bajas**.

**Filament 2 NO tiene pestañas en los listados** — `getTabs()` y la clase `Tab`
son de Filament 3, no existen en 2.17. Asi que no hay «Activos | Inactivos |
Todos» arriba de la tabla; el equivalente es este filtro con `->default('activos')`,
que se maneja desde el panel de filtros. **Al subir a Filament 3 esto se puede
convertir en pestañas de verdad.**

| Recurso | Columna | «Activo» es |
|---|---|---|
| Usuarios | `usu_state` | `1` (entero, no booleano) |
| Locales por usuario | `ui_state` | `1` (entero) |
| Gestiones | `ug_finish` | **`false`** — la columna dice si TERMINO, esta invertida |
| Locales, Clientes, Marcadores, Productos, Listas | `*_estado` / `*_activo` | `true` |

- **La opcion «Inactivos» incluye los NULL** (`orWhereNull`). Sin eso una fila
  con la columna vacia no seria activa ni inactiva: invisible en las dos
  opciones.
- ⚠️ **Un filtro por defecto es la trampa de «no aparece, entonces no existe».**
  Alguien busca a un guardia dado de baja, no lo encuentra y lo crea de nuevo.
  Dos cosas lo contienen: Filament muestra un indicador de filtro activo debajo
  de la barra de busqueda, y el alta de usuarios valida `usu_cedula` unica
  **contra toda la tabla**, inactivos incluidos, asi que el duplicado se rechaza.
  (Ojo: esa unicidad es solo de la aplicacion — **no hay indice unico en la
  base**, solo la clave primaria.)

Verificado por la URL del filtro sobre los datos reales: con `activos` aparece
DHL y no «Banco del Pacífico»; con `inactivos` al reves; con `todos` los dos.

**Cinco de los ocho recursos ya tenian un `->filters([])` VACIO.** Insertar un
segundo bloque dejaba dos llamadas a `->filters()` en la misma tabla, y la
segunda pisa a la primera en silencio: parece funcionar y descarta los filtros
que hubiera. Hay que fusionar, no agregar.

## Elegir un usuario: por nombre Y por cedula (2026-09-08)

`App\Filament\Forms\SelectorDeUsuario` — un solo lugar, cinco usos:
`Locales por usuario`, `Gestiones`, `Perfil por usuario`, `Turnos` y las
asignaciones de las franjas del cuadrante.

**El problema.** Los cinco cargaban los 839 usuarios activos en un arreglo de
opciones y dejaban que `searchable()` filtrara el texto **en el navegador**:

- Tres mostraban solo `usu_nmbcom`, asi que **buscar por cedula no encontraba
  nada**. Y la cedula es lo que el guardia dice por telefono y lo que trae su
  credencial; buscar por nombre obliga a saber como esta escrito
  («CASTRO ALVARES ANDRES ARTURO»: sin tildes y con los apellidos primero).
- Los otros dos si la encontraban, **pero por coincidencia**: la cedula estaba
  pegada en la etiqueta. Acortar ese texto habria roto la busqueda por cedula
  sin que nada avisara.

Ahora la busqueda es explicita y va a la base: `usu_nmbcom ILIKE` **o**
`usu_cedula LIKE`, con tope de 50. Verificado: cedula completa, cedula parcial
(«092551»), apellido, y nombre en minusculas contra una base que los guarda en
mayusculas.

- **`ILIKE` para el nombre, `LIKE` para la cedula.** Los nombres estan en
  mayusculas y nadie los escribe asi al buscar; la cedula son digitos.
- **`getOptionLabelUsing` incluye a los INACTIVOS**, a diferencia de la
  busqueda: un vinculo de un guardia dado de baja apareceria vacio y se veria
  como un dato corrupto.
- De paso deja de cargar 839 filas en cada render del formulario. Verificado:
  cero cedulas incrustadas en el HTML de los formularios de alta.

### La trampa de la inyeccion por nombre

**El parametro de `getSearchResultsUsing` DEBE llamarse `$search`.** Filament
inyecta los argumentos de la clausura **por nombre**: `Select::getSearchResults`
pasa `'query'`, `'search'` y `'searchQuery'`. Con cualquier otro nombre intenta
resolverlo del contenedor de servicios y revienta con
`BindingResolutionException` -- **y recien cuando alguien escribe en la caja**, no
al cargar la pagina. La primera version usaba `$busqueda` y pasaba todas las
verificaciones de HTTP 200 estando roto.

Se comprueba armando un `ComponentContainer` de verdad y llamando
`getSearchResults()` y `getOptionLabel()`; el HTML no sirve para esto, porque la
vista de Filament pide la etiqueta **por Livewire, asincronica**, asi que nunca
aparece en la respuesta inicial.

### Rutas de edicion habilitadas (2026-09-08)

`Locales por usuario` y `Perfil por usuario` tenian la ruta de edicion
**comentada** en su `getPages()`: solo se podia crear, asi que corregir un
vinculo obligaba a borrarlo y volver a crearlo. Descomentadas.

En `Locales por usuario` el selector de usuario esta `disabledOn('edit')`: al
editar se cambia el local, no la persona. Cambiar las dos cosas seria borrar un
vinculo y crear otro disfrazado de edicion.

## Cambiar la contraseña de un usuario (2026-09-08)

Accion **«Cambiar contraseña»** en Usuarios, visible para quien puede gestionar
personal (Administrador y Lider Operativo).

**Existe porque no habia forma de hacerlo.** El unico camino web era «Olvido su
contraseña» en el login, que **manda un correo**, y **505 de los 878 usuarios no
tienen correo cargado**: para ellos ese flujo no existe. Encima `MAIL_HOST` es
`mailhog`, un capturador de desarrollo, asi que hoy no sale ningun correo real.
La alternativa era entrar por linea de comandos al servidor.

- **No afecta a la app movil.** Panel y app leen el mismo hash de
  `users.usu_password`, asi que la clave nueva sirve en la tablet **sin
  recompilar el APK**.
- **Hashea explicitamente con `Hash::make`.** `UsersResource` usa
  `Modules\Acceso\Models\users`, que **NO tiene** el mutador que si tiene el de
  MobileApp: lo tiene comentado, y encima forzaba `'123456'`. Guardar el texto
  plano dejaria al usuario sin poder entrar y la clave legible en la base.
- **Mismas reglas que el flujo de la app** (minimo 8, una mayuscula, una
  minuscula y un numero). Si aqui se permitiera algo mas debil, el usuario
  quedaria con una clave que su propia app rechaza al intentar cambiarla.
- **Limpia `remember_token`**: si habia un enlace de recuperacion sin usar, deja
  de servir. Es lo que hace el flujo de la app.
- Queda registrada en la bitacora (`control_log_filament`), **sin la contraseña**.

Verificado sobre un guardia real: el hash cambia, queda como bcrypt y no como
texto plano, y `POST api/login` acepta la clave nueva. El hash original se
restauro desde v1 al terminar la prueba.

## Roles y alcance de datos

Cinco roles. **No escribir listas de perfiles a mano**: usar `App\Support\PerfilPanel`, que centraliza lo que antes vivía en 24 comprobaciones repartidas en 20 archivos.

| Rol | Panel | Qué hace | Ve |
|---|---|---|---|
| `Administrador` | ✅ | Sistemas: todo, incluida la configuración (clientes, geografía, catálogos) | **Todo, sin filtro** |
| `Lider Operativo` | ✅ | Da de alta guardias, asigna rol/local/puesto. Crea locales | **Los locales de su(s) país(es)** (`user_has_pais`) |
| `Supervisor` | ✅ | Observa guardias y turnos, atiende alertas. Locales en **solo lectura** | Sus locales (`user_has_institucion`) |
| `Consola` | ✅ | Central **24/7**: consigue el reemplazo cuando falta un guardia y confirma la cobertura | **Todo, sin filtro** |
| `Vigilante` | ❌ | App móvil | Sus locales |
| `Cliente` | ❌ | Portal de solo lectura | Sus locales |

- `PerfilPanel::localesDelUsuario()` devuelve `null` = sin filtro, `[]` = **no ve nada**. Un líder sin países asignados cae en `[]`, no en `null`: una configuración incompleta no debe convertirse en acceso global.
- Un local **sin ciudad** no pertenece a ningún país, así que ningún líder lo ve. Es deliberado: mejor que falte a que se cuele en el alcance de un país ajeno.
- **`puedeAsignarCobertura()` es una capacidad propia**, no se deduce de `puedeAdministrarLocales()`. Asignar una cobertura es del Líder Operativo, pero una falta a las tres de la mañana no espera a que despierte: la Consola trabaja 24/7 y también asigna. Lo que la Consola **no** hace es crear locales, puestos ni cuadrantes, ni dar de alta personal (`/admin/users` le responde 403).
- La Consola tiene **alcance global** a propósito: acotarla a un local o a un país la dejaría sin ver justo la falta que tiene que resolver de madrugada.
- `shouldRegisterNavigation()` **solo oculta el menú**. Para bloquear la ruta hace falta `canViewAny()`, que es lo que Filament consulta para abortar con 403.

## Puesto de trabajo y turnos

- **`puesto`**: la posición concreta dentro de un local (garita, andén, sala de monitoreo). **No confundir con `institucion_marcadores`**: un marcador es un punto QR que el guardia escanea al pasar en una ronda; un puesto es donde se queda durante su turno. Por eso son tablas distintas y `turno` referencia a las dos.
- `turno.tu_puesto_id` es **nullable**: no todos los locales dividen el trabajo en puestos. La FK va con `restrict` para que reorganizar puestos no borre el historial de turnos cumplidos.
- **Ojo con `tu_estado` vs `tu_state`**: `tu_estado` es varchar (`programado`, `en_curso`, `completado`, `ausente`, `inasistente`) y `tu_state` es el booleano de activo. Filtrar los activos con `where('tu_estado', true)` **no falla: devuelve 0 filas en silencio**. Fue el bug que dejaba al guardia sin ver su turno en la app.
- `TurnoResource` existe desde 2026-08-24. Antes **nada creaba turnos** —`TurnoService` solo vincula marcajes y cierra el día— así que la tabla estaba vacía y el widget "Cumplimiento de turnos" mostraba siempre cero.
- **El marcaje se vincula al turno automáticamente** (`BiometriaController::vincularConTurno`, desde 2026-08-25). Antes exigía una llamada aparte a `turnos-vincular-marcaje` que la app nunca hacía, así que `tu_marcada_entrada` quedaba en null y el cumplimiento marcaba 0%. `turnos-vincular-marcaje` sigue existiendo para el caso manual.
  - Elegir el turno usa `TurnoService::buscarTurnoParaMarcaje()`, no `buscarTurnoProgramado()`: con dos turnos en el día (mañana y noche) hay que tomar el de hora más cercana, no el primero.
  - La tardanza se calcula contra **`ocurrido_en`** (la hora real del evento), no contra la de llegada al servidor: un marcaje sincronizado horas después inventaría una tardanza.
  - La vinculación **nunca hace fallar el marcaje**: si no hay turno o algo revienta, la biometría ya quedó guardada, que es lo que no se puede perder.
- Programar turnos y definir puestos es de quien administra locales (Administrador y Líder Operativo). El Supervisor los consulta: es su tablero, no su planificación.

## Cuadrante de turnos (plantilla)

- **`plantilla` → `plantilla_franja` → `plantilla_asignacion`**: patrón **semanal**, no lista de fechas. Un cuadrante real se repite ("Juan cubre Garita, lunes a viernes, 06–14"); modelarlo fecha por fecha obligaría a rehacerlo cada mes. `pf_dia_semana` es ISO (1=lunes…7=domingo), igual que `Carbon::dayOfWeekIso`.
- La salida son filas en `turno`. **`turno.tu_plantilla_id` marca cuáles generó una plantilla**, y es lo que permite regenerar sin tocar los turnos cargados a mano (quedan con ese campo en null).
- **Regenerar nunca pisa lo ya ocurrido**: se borran solo los turnos de esa plantilla **sin marcaje**; los que el guardia ya marcó se conservan y se informan. Rehacer el cuadrante a mitad de mes no puede borrar lo que pasó.
- `PlantillaTurnoService::validar()` corre antes de generar. **Errores bloquean** (guardia en dos turnos a la vez, guardia sin vínculo al local, puesto de otro local) y **avisos no** (franja sin cubrir, descanso corto): eso último son decisiones del negocio, no datos inválidos.
- `turno` no tiene ninguna restricción de solape en base, por eso la validación de solapes vive en el servicio.

### La grilla

- `/admin/plantillas/{id}/grilla` muestra la semana como cuadrícula: **puestos en las filas, días en las columnas**. `CuadranteGrilla` arma los datos; la página es de solo lectura.
- Es también **la vista que le faltaba al Supervisor**: veía el listado de cuadrantes pero no podía abrir ninguno, porque el único detalle era la pantalla de edición y esa la tiene cerrada. La acción «Ver grilla» del listado es visible para todo el que opera; «Editar franjas» solo para quien administra locales.
- **La semana es circular.** Los intervalos se calculan en minutos de la semana (0 a 10.079) y el que se pasa del final vuelve al principio. Sin eso, el choque más típico del negocio —el relevo de la noche del domingo pisando el lunes— no se detectaría nunca. Hay un test que lo fija.
- Tres estados por celda, y el más grave manda el color: **choque** (el mismo guardia en dos lugares a la vez, rojo), **sin cubrir** (naranja) y **descanso corto** (menos de 8 h, amarillo). El choque impide generar los turnos; los otros dos no, pero se ven antes de publicar.
- Debajo, **la carga semanal por guardia**. Es lo que evita el cuadrante donde uno hace 60 horas y otro 8 sin que nadie lo note hasta la planilla; por encima de 48 h la cifra se resalta.
- Un turno de 22:00 a 06:00 **cuenta 8 horas, no 16**. Medirlo al revés inflaría las horas de todo el equipo de la noche.
- Los colores van en `style` y no en clases de Tailwind: Filament 2 sirve un CSS ya compilado y una clase que ninguna de sus vistas use puede no existir en ese archivo.

### Carga por CSV

- `PlantillaImportService` importa **franjas y asignaciones, no turnos**: los turnos los sigue generando `PlantillaTurnoService` en un segundo paso. Así la carga masiva pasa por las mismas validaciones que la carga manual, en vez de tener un camino propio que se salte los solapes.
- **El local no va en el archivo**: lo define la plantilla. Pedirlo por fila solo abriría la puerta a que no coincida.
- Es tolerante con lo que sale del Excel de una oficina: BOM, Windows-1252, CRLF, separador `;` o `,`, día como `LUN`/`lunes`/`3`, hora como `6:00` o `06:00:00`, y el nombre del puesto sin acentos ni mayúsculas. Un archivo perfectamente válido no puede fallar con «puesto no encontrado» porque Excel guardó los acentos en otra codificación.
- **O entra todo o no entra nada.** Con un solo error no se escribe ninguna fila y el cuadrante anterior queda en pie: media carga es peor que ninguna. Las filas repetidas son aviso, no error, y se cargan una sola vez.
- El botón **Descargar modelo** entrega el CSV con los puestos del local ya escritos (y con BOM, para que Excel no rompa los acentos). Que el líder no tipee los nombres evita la mitad de los errores de carga.
- El archivo subido se borra apenas se vuelca en la plantilla: conservarlo solo acumularía copias del cuadrante en disco.
- **El Supervisor no puede abrir el editor del cuadrante** (`canEdit` → 403), así que no alcanza con ocultarle los botones de carga. Su lectura del cuadrante es **la grilla** (`/admin/plantillas/{id}/grilla`), que sí puede abrir.

## Cobertura de turnos (vacantes)

Qué hacer cuando un puesto queda vacío. Un turno se descubre por tres motivos
—el guardia no llegó, avisó que no viene, o el cliente pidió refuerzo— pero el
problema es el mismo, así que hay **un solo objeto**: `turno_vacante`, con tres
formas de abrirse. `turno_postulacion` guarda quién se ofrece.

- **El sistema detecta la falta, pero no la declara.** `turnos:revisar-cobertura` corre cada 5 minutos y deja la vacante en estado `detectada`: no se le avisa a nadie hasta que una persona confirma. Los marcajes se pueden hacer sin señal y sincronizar horas después, así que «no marcó» no significa «no vino»; si el reloj abriera convocatorias solo, publicaríamos vacantes por un teléfono sin cobertura.
  - Existe aparte de `turnos:cerrar-dia`, que corre a las 23:55 y marca las ausencias del día. Enterarse a esa hora de que el puesto de las 06:00 quedó vacío no sirve para cubrirlo.
  - Lo que descarta una vacante es que el turno **termine**, no que empiece: toda cobertura de una falta llega tarde por definición.
  - Un índice único **parcial** (`turno_vacante_turno_viva_unique`) impide que dos pasadas del detector abran dos vacantes para el mismo turno.
- **El aviso sale en dos olas.** Primero los guardias del propio local, que ya tienen la acreditación del cliente; a los 30 minutos sin postulantes se abre al resto de la ciudad. Los locales con `ins_requiere_acreditacion` **nunca escalan**: ofrecerle un turno a alguien que no puede entrar al sitio es peor que no ofrecerlo, porque se presenta y lo paran en el control.
- **Elegibilidad**: rol Vigilante, `usu_acepta_extras` activo, vinculado a un local del alcance, sin turno solapado y con 8 h de descanso. Son las mismas reglas que valida el cuadrante, en `VacanteService::motivoParaNoCubrir()`.
- **`usu_acepta_extras` es un opt-in.** Si se le avisara a todos, en dos semanas nadie miraría los avisos. Viaja en la respuesta del login para que la app lo muestre sin otra consulta.
- **Quién hace qué**: el **Supervisor** confirma que la falta es real (es quien está mirando el turno y puede llamar al guardia) y puede cerrar la vacante si el guardia apareció. **Elegir quién cubre es del Líder Operativo** (`puedeAdministrarLocales`), porque cubrir un puesto ante el cliente es su responsabilidad. No gana el más rápido: el panel muestra las horas ya programadas del mes de cada postulante, para que la cobertura no caiga siempre en el que más mira el teléfono.
  - **Hueco conocido**: si el líder no está disponible de madrugada, la vacante queda abierta hasta que aparezca. Hoy no hay delegación automática; si hace falta, el camino sería habilitar al supervisor pasados N minutos sin decisión.
- **Cubrir la falta no borra la falta.** El turno original queda del que no llegó, en estado `ausente`; se crea un turno **nuevo** para quien cubre. Reasignar el turno borraría el dato que después hay que poder mirar.
- **El turno de cobertura lleva `tu_plantilla_id` en null** a propósito, como los cargados a mano: si quedara marcado como generado por la plantilla, republicar el cuadrante lo borraría y el puesto volvería a quedar vacío. Hay un test que lo fija.
- Postularse es idempotente (`tp_client_uuid`) como los cinco endpoints de campo. Una postulación sincronizada tarde sobre una vacante ya cubierta responde con un mensaje claro, no con un error.
### Dónde vive la app (esto cambia todo el diseño de avisos)

**La app corre en tablets que están EN los puestos, no en el teléfono personal
del guardia.** No se distribuye al público ni se instala en dispositivos propios.

De ahí se desprende casi todo lo demás:

- El guardia que puede cubrir un turno **está franco, en su casa, sin la app**.
  Una notificación push no lo alcanza: sonaría en una tablet de un puesto.
- **WhatsApp no es un canal más, es EL canal** para convocar reemplazos. Por eso
  el número y el consentimiento del guardia no son un dato opcional.
- Su respuesta tiene que **volver a entrar sola** por el webhook. Si alguien
  tuviera que leer los mensajes y cargarlos a mano, a las tres de la mañana no
  pasaría.
- La pantalla «Turnos disponibles» de la app sigue existiendo y sirve —un guardia
  en su puesto puede tomar un turno extra desde la tablet— pero **no es el camino
  principal**. No hay que optimizar esa pantalla a costa de WhatsApp.

### Avisos

- `NotificadorVacante` decide **a quién** se le avisa; `config/avisos.php` decide **por dónde**. Los canales implementan `App\Services\Avisos\CanalDeAviso`: hoy `CanalPush` (Expo) y `CanalWhatsApp` (Evolution). `CanalBitacora` existe para depurar sin base de datos, pero no está en la lista por defecto.
- **Cada intento se guarda en `aviso_envio`**, haya salido o no, y se ve en el panel (Operación → Avisos enviados). Es lo que permite responder «¿le avisamos a alguien?» cuando un puesto amanece vacío; sin eso la única respuesta posible sería «debería haber salido».
- **`ae_direccion` separa lo que mandó el sistema de lo que contestó el guardia.** Las respuestas por WhatsApp viven en la misma tabla, y sin esa marca un «no puedo cubrirlo» del guardia se leía en el panel como si fuera un mensaje que la empresa había enviado. El filtro «Solo los que no llegaron» aplica únicamente a lo saliente: una respuesta entrante siempre «llegó», la escribió el guardia.
- **Se registran las dos respuestas, el sí y el no** (`respuesta_afirmativa` / `respuesta_negativa`). El «no» importa tanto como el «sí»: la central deja de esperar esa respuesta y sabe a quién ya no volver a llamar. El «sí» es además el comprobante de que el guardia aceptó, y a qué hora.
- Los canales devuelven un `ResultadoDeAviso`, no un booleano, porque **«no se intentó» y «falló» son problemas distintos**: uno se arregla cargando un dato (falta el número, falta el consentimiento, el guardia nunca abrió la app) y el otro levantando un servicio (el gateway no responde). La columna Motivo del panel muestra exactamente eso.
- **WhatsApp va por Evolution API**, un gateway open source que corre **aparte** del proyecto (contenedor propio) y con el que se habla por HTTP. `CanalWhatsApp` + `EvolutionApi` es todo el código nuestro; si mañana se cambia de gateway, se reemplaza `EvolutionApi` y nada más. Guía de instalación y operación: `WHATSAPP-EVOLUTION.md` en la raíz del monorepo.
  - **Es un cliente NO oficial de WhatsApp**: el número puede ser bloqueado sin aviso. Por eso ningún camino del sistema depende de él, y por eso la guía insiste en usar un número dedicado y no el operativo de la empresa.
  - **Se auto-desactiva**: mientras `WHATSAPP_URL`, `WHATSAPP_INSTANCIA` y `WHATSAPP_API_KEY` estén vacías, el canal no intenta nada y cada aviso queda registrado como «no se intentó». No hay que comentar código para operar sin WhatsApp.
  - `usu_acepta_whatsapp` es un consentimiento **aparte** de `usu_acepta_extras`: aceptar trabajar de más no es aceptar que le escriban al teléfono personal.
  - `NumeroWhatsapp` normaliza el número (`0987654321` → `593987654321`) y valida antes de enviar. **Un número mal formado no da error**: WhatsApp lo acepta y el mensaje se pierde, así que registrarlo como enviado sería mentirle a quien después pregunte.
  - El cuerpo de `sendText` es el de Evolution **v2** (`{number, text}`); en v1 era `{number, textMessage:{text}}`. Se ajusta solo en `EvolutionApi::enviarTexto()`.
- **SMS no está implementado.** La interfaz `CanalDeAviso` deja el lugar hecho.
- **Hoy el aviso no suena en el teléfono**: falta `google-services.json`. La pantalla «Turnos disponibles» de la app no depende de eso y funciona igual; el push es un acelerador, no el canal.
- **Un aviso nunca puede tumbar la operación.** Hay dos capas de protección y las dos tienen test: `NotificadorVacante` atrapa el fallo de cada canal por separado, y `VacanteService::avisar()` atrapa el fallo del notificador entero (por ejemplo, un canal mal escrito en el config). El envío de la confirmación va **fuera** de la transacción que asigna el turno.
- **Al escalar solo se avisa a los que antes no podían ver la vacante.** Repetirle el aviso a quien ya lo recibió y no se postuló no agrega información.
- La falta recién detectada **no se le avisa a los guardias**, solo al líder y al supervisor del local: todavía no está confirmada y podría ser un teléfono sin señal.

### Respuestas por WhatsApp (webhook)

- `POST api/whatsapp/webhook/{token}` recibe los mensajes entrantes de Evolution. **Fuera de `auth:sanctum`** (Evolution no tiene token de usuario) y protegido por `WHATSAPP_WEBHOOK_TOKEN`: sin ese valor la ruta responde **404**. Quien tenga el token puede simular que un guardia aceptó un turno.
- Siempre responde **200** salvo token inválido. Un error haría que Evolution reintente el mismo mensaje en bucle.
- Ignora: mensajes propios (`key.fromMe` — los salientes vuelven por el webhook y el sistema se contestaría a sí mismo), grupos (`@g.us`) y mensajes sin texto.
- `RespuestaWhatsapp` interpreta la respuesta. La convocatoria lleva el **número de la vacante** (`Para tomarlo responda: SI 4821`) porque un guardia puede tener dos ofertas abiertas a la vez.
  - Con **una sola** oferta vigente, un «si» pelado alcanza.
  - Con **dos o más** y sin código, **pide aclaración en vez de adivinar**: elegir mal manda a alguien al puesto equivocado.
  - Un «no» no postula pero **queda registrado**: la central deja de esperar esa respuesta.
  - Antes de aceptar se revalida `motivoParaNoCubrir()`, y si no puede se le dice **por qué** («ya tiene un turno a esa hora» es información útil; «no se pudo» no).
- Las ofertas vigentes salen de `aviso_envio` (WhatsApp, enviados, últimas 24 h), así que no hace falta otra tabla para saber a quién se le ofreció qué.
- Al aceptar se dispara `postulacionRecibida()` → **Consola y Líder**. Sin eso, la respuesta del guardia quedaría esperando a que alguien entre al panel a mirar.

### Avisar con tiempo, y bajas

- `POST api/turnos-avisar-ausencia` (permiso `vacantes.avisar_ausencia`): el guardia reporta desde la app que no podrá cubrir un turno futuro, con motivo (`aviso`, `enfermedad`, `permiso`). `POST api/turnos-proximos` lista sus turnos por venir; `turnos-del-dia` solo trae los de hoy y avisar sirve justamente para los que vienen.
- **El aviso no convoca solo**: nace `detectada` y lo confirma el responsable. Si bastara con avisar para que el sistema convoque a otro, cualquiera podría soltar su turno sin que nadie lo revise. Un motivo que no esté en la lista se guarda como `aviso`, para que el cliente no pueda meter cualquier valor en la columna.
- **Renuncia o desvinculación**: acción «Registrar baja» en Usuarios (Líder/Administrador). Cierra `pa_hasta` de sus asignaciones del cuadrante —no las borra, el histórico de quién cubría qué no se toca—, abre una vacante **ya ofrecida** por cada turno futuro y opcionalmente desactiva al usuario. Sin esto, el cuadrante seguiría mostrándolo asignado semanas enteras y cada mañana alguien descubriría el puesto vacío de nuevo.
- **Una baja no le cuenta ausencias al que se fue**: sus turnos futuros quedan con `tu_state = false` en vez de `ausente`. Marcarlos como ausencia le cargaría una falta por cada día que ya no trabajaba y ensuciaría el cumplimiento del local.
- Endpoints móviles en la sección de permisos **21** (`vacantes.ver`, `vacantes.postular`), asignados al rol Vigilante. El panel no usa esos permisos sino `PerfilPanel`.
- `VacanteResource` declara `$slug = 'vacantes'`; sin eso Filament derivaría la ruta del modelo y quedaría `/admin/turno-vacantes`.

## Datos de ejemplo

`CuadranteEjemploSeeder` **no** se llama desde `DatabaseSeeder`: se corre a mano
con `php artisan db:seed --class=CuadranteEjemploSeeder`. Es idempotente. Siembra
3 puestos, 5 guardias (uno volante, sin franjas fijas, para que la pantalla de
cobertura tenga a quién ofrecerle algo) y un cuadrante semanal con los turnos del
mes. La contraseña de esos guardias **se copia del hash de un usuario existente**
en vez de escribir una clave nueva en el repositorio.

## Interfaz Web (panel)

El backend trae dos capas web sobre el mismo dominio `http://localhost:3031`:

**Puntos de entrada.** `routes/web.php` solo tiene redirecciones: `/` y
`/admin/login` van a `/acceso/login`. **La raíz existe desde 2026-09-07**; antes
no había ninguna ruta para `/` y respondía el 404 de Laravel, que en un
despliegue nuevo se lee como «el servidor quedó mal» cuando lo único que pasaba
es que el sistema no tiene portada pública. Al agregar rutas web, no dejar la
raíz sin destino.

- **Panel legacy (`/acceso/login`)** — login por cédula (web `login_check` → selección de perfil → menú). Los campos del formulario son **`usu_cedula` y `password`** (no `usu_password`, que es el de la API), y `POST /acceso/procesar_perfil` espera **`code`**: el id del rol **cifrado con AES**, tal como sale en el `data-code` de la pantalla de perfiles. No confundirlo con el `POST api/procesar_perfil` de la app, que sí recibe un `id` en claro. Mandarle un `id` responde «este perfil no tiene permisos asignados», que hace pensar en un problema de permisos cuando el rol los tiene. Usa las vistas AdminLTE de `Modules/{Acceso,Administracion,Formularios}`.
  - Usuario demo: cédula `1234567890` (misma credencial unificada del 18/08/2026, ver la sección Backend; **no** es `123456`). Tiene perfiles `Vigilante` y `Supervisor`.
  - El menú se arma desde `role_has_permissions → permissions → permission_section` (seeder `seedWebAccess`).
  - Tras elegir el perfil Supervisor el login redirige **directo a `/admin`** (el permiso `admin`, sección `ps_codigo` 3, es la ruta de destino: el JS hace `location.href = urlbase + '/' + data.link`). El perfil Vigilante no tiene permisos web a propósito — usa la app móvil — así que si lo selecciona en la web recibe «No tiene los permisos necesarios».
- **Panel Filament (`/admin`)** — el panel de administración real del negocio de la app (Rondas, Accesos, Novedades, Alertas, Inventario, Bitácora, Usuarios, Perfiles, Locales, Cuadrante, Cobertura de turnos, etc.). Está protegido por `canAccessFilament()` (requiere perfil `Supervisor`/`Administrador`), por lo que se entra **después** de hacer el login web y seleccionar el perfil Supervisor. El login propio de Filament apunta a `usu_email` (`App\Http\Livewire\Auth\Login`) y además la ruta `/admin/login` redirige a `/acceso/login` (diseño intencional).

Fixes aplicados a la web (commit `d42858a`):
- `Html::style/script` fueron eliminados en spatie/laravel-html v3 → reemplazados por `<link>`/`<script>` en las vistas Blade.
- Se creó `storage/framework/sessions` (faltaba y rompía sesiones en Postgres/Docker).
- Migraciones nuevas: `visible` en roles, catálogos web, `log`/`log_trafico`, `organizacion`, `ru_code` en `user_has_roles`.
- `orderBy` de `pr_posicion` corregido para Postgres (`REPLACE` no aplica a `double`).

## Flujo de cambio de contraseña (`Modules/MobileApp`):
  - `POST api/solicitud_paswchg` (`usu_cedula`) → envía correo con el link de cambio y además devuelve `user_id` y `token` para que la app continúe el flujo sin depender del correo.
  - `POST api/procesar_paswchg` (`user_id`, `password`, `password2`) → valida (mínimo 8 caracteres, mayúscula/minúscula/número, coincidencia, distinta a la anterior) y guarda el hash nuevo. Limpia `remember_token`.
  - El `LoginController` de MobileApp **no** debe usar `message_json()` como función global (no existe); siempre `$this->message_json()`.

## Limpieza de artefactos regenerables (2026-09-08)

La carpeta pesaba **6,5 GB** y quedo en **2,3 GB**. Se borro solo lo que se
puede reconstruir, y **cada cosa se valido antes**, no se asumio.

| Borrado | Peso | Como se recupera | Que se valido antes |
|---|---:|---|---|
| `android/app/build`, `android/build`, `android/.gradle` | 1,8 G | `./gradlew :app:assembleRelease` | Que el APK de `apk/` sea **identico por sha256** al de la carpeta de compilacion |
| `images.zip` | 1,3 G | ya no hace falta | 45.320 rutas presentes en `public/images`, y **300 archivos al azar comparados por md5: 300 identicos** |
| `node_modules` | 1,2 G | `npm install` (25 s) | `package-lock.json` versionado |
| `storage/debugbar/*` | 34 M | se rehace solo | Debugbar apagado; se conservo el `.gitignore` de adentro |

### `vendor/` NO se borro, y es importante saber por que

Son 107 MB y `composer install` los rehace, asi que entraba en la lista. Pero
`docker-compose.yml` monta `./:/var/www`, o sea que **el contenedor sirve desde
el disco**: comprobado creando un archivo testigo en `vendor/` y viendolo aparecer
dentro del contenedor.

Borrarlo **tumba el panel y la API al instante**, con datos reales en produccion.
107 MB no valen una caida.

### Lo que se conserva y no es basura

| | Peso | |
|---|---:|---|
| `backend/public/images` | 1,3 G | **Las 45.319 fotos. Irreemplazables** |
| `apk/` | 100 M | APK firmado + keystore + su LEEME |
| `apk_extracted` | 127 M | El APK original de v1, descompilado. **Versionado**: mete ~46 MB al repo |
| `coredt360_bk.sql` | 31 M | El volcado de v1. Conservar hasta que la migracion lleve semanas validada |
| `datos-v1/2025.rar` | 83 M | Sacado del directorio web; contenido sin revisar |

### Verificado despues de borrar

Panel y API responden 200 por localhost, IP de red **y las dos IP publicas**; una
foto de 2026 se sirve con `200 image/jpeg`; las 45.319 siguen en su sitio;
`npm install` + `npx tsc --noEmit` pasan limpio, y el APK se recompila.

## APK: dos IP con respaldo, y el puerto 3031 (2026-09-08)

Configuracion lista para compilar. **Compilar exige JDK y SDK de Android, que
este servidor NO tiene** (ver «Que falta para compilar»).

### `apiHosts`: una lista, no un host

`app.json` pasa a llevar **dos IP publicas** en `expo.extra.apiHosts`, la
primera es la principal:

```json
"extra": {
  "apiHosts": ["181.198.245.50", "181.188.232.50"],
  "apiScheme": "http",
  "apiPort": 3031
}
```

`apiHost` (singular) sigue funcionando y se usa si no hay `apiHosts`.
`constants.ts` expone **`API_URLS`** (todas, en orden) y mantiene `API_URL` como
la primera para no romper lo que ya la importaba.

### El respaldo solo actua ante fallo DE RED

`api.ts` arranca por la principal y pasa a la siguiente **unicamente cuando no
hubo respuesta** (sin ruta, conexion rechazada, timeout).

**Esa distincion es lo que hace que sea seguro.** Si el backend contesta --
aunque sea 401, 422 o 500 -- esta vivo, y reintentar contra el otro host no
arregla nada: repetiria la operacion contra otra direccion. Un marcaje que
respondio 422 no se debe reenviar a otro servidor.

- Cada host se prueba **una vez** (`_hostsIntentados`). Sin ese tope, con los dos
  enlaces caidos la promesa no se resolveria nunca y la pantalla quedaria
  cargando para siempre en vez de mostrarle el error al guardia.
- El host elegido vive **en memoria, no en AsyncStorage**, a proposito: al
  reiniciar la app se vuelve a intentar por la principal. Recordarlo entre
  arranques dejaria a la tablet pegada al enlace secundario durante semanas sin
  que nadie se entere.

### Puerto 3031 y no 80

Por decision del usuario. **Se saltea el nginx del host**, que es el unico lugar
donde despues van TLS, limites y logs; y el router tiene que reenviar el **3031**,
no el 80. A cambio no depende del reparto del puerto 80 con la v1.

Verificado: `http://192.168.3.124:3031/api/login` emite token. El 3031 esta
publicado por Docker con DNAT desde `0.0.0.0`, asi que alcanza con reenviarlo.

### El logo va en la pantalla de carga, no en el icono

`logo.png` es de **144x144** y el icono de Android quiere 1024x1024: estirarlo
11 veces en una tablet lo deja pixelado. Por eso el icono sigue siendo
`assets/icon.png` (1024x1024) y el logo va al splash.

Y no se estira: `assets/splash-logo.png` es un lienzo de **1024x1024 con el logo
centrado a su tamaño nativo de 144 px** sobre blanco. Asi la pantalla de carga
escala el lienzo ~1,5x en vez de escalar el logo 11x. Se compuso con un script
de PNG en Python puro porque el servidor no tiene Pillow ni ImageMagick.

**Cuando haya un logo de 1024x1024 o vectorial**, reemplazarlo y recompilar: con
eso se puede usar tambien como icono.

### El toolchain de compilacion en este servidor (2026-09-08)

Instalado. Antes no habia nada: `java` no existia y `ANDROID_HOME` estaba sin
definir.

| | |
|---|---|
| JDK | `java-21-openjdk-devel` en `/usr/lib/jvm/java-21-openjdk` |
| SDK Android | `/home/server-dt/android-sdk` (**450 MB**, no los 5-10 GB estimados) |
| Componentes | `platform-tools`, `platforms;android-36`, `build-tools;36.0.0` |
| Gradle | 9.3.1, lo baja el wrapper a `~/.gradle` (3,2 GB de cache) |

**Va JDK 21 y no 17** porque el repositorio de esta distribucion solo ofrece 21.
Gradle 9.3.1 y el AGP de Expo SDK 57 lo soportan; la compilacion salio limpia.

El SDK vive en el home de `server-dt` y no en `/opt` a proposito: `/` tenia 24 GB
libres contra 58 GB de `/home`.

```bash
cd totalsecureapp/android
JAVA_HOME=/usr/lib/jvm/java-21-openjdk \
ANDROID_HOME=/home/server-dt/android-sdk \
./gradlew :app:assembleRelease
```

`android/local.properties` (con `sdk.dir`) esta en `.gitignore`: es propio de
esta maquina.

Primera compilacion **30 min** (baja Gradle y las dependencias); las siguientes
**5 min**.

### ⚠️ `android/` esta versionado: `app.json` NO regenera los recursos nativos

La trampa que costo una compilacion de mas. `android/` se versiona para poder
compilar sin `expo prebuild`, y **eso significa que los plugins de Expo no vuelven
a correr**. Consecuencia:

- **`expo.extra` SI se aplica**: se genera en `assets/app.config` en cada
  compilacion. Las IP y el puerto entraron al primer intento.
- **La imagen del splash NO**: es un recurso nativo
  (`res/drawable-*/splashscreen_logo.png`) que solo se rehace con `prebuild`. El
  primer APK salio con el splash viejo aunque `app.json` ya apuntaba al nuevo.

Se resolvio regenerando los cinco archivos de densidad a mano en vez de correr
`prebuild`, que rehace toda la carpeta `android/` y se llevaria los ajustes
manuales que tenga.

**El logo se dibuja a 144dp en todas las densidades** (144, 216, 288, 432 y 576
px para mdpi…xxxhdpi, en lienzos de 288dp). Asi ocupa el mismo tamaño fisico en
cualquier pantalla, con el minimo escalado posible desde un original de 144 px.
El escalado es bilineal, no por vecino mas cercano: al ampliar, suavizar se ve
mejor que dejar bloques.

### Keystore de produccion (2026-09-08)

| | |
|---|---|
| Archivo | `/home/server-dt/keystores/totalsecureapp-release.jks` (**fuera del repo**) |
| Formato | PKCS12, RSA 4096, SHA384withRSA |
| Alias | `totalsecureapp` |
| Vigencia | hasta **2054-01-24** (10.000 dias) |
| Titular | `CN=Total Secure App, OU=Sistemas, O=Total Pacific Group, L=Guayaquil, ST=Guayas, C=EC` |
| Huella SHA-256 | `E0:84:FD:69:65:AA:55:A2:45:81:8F:C6:4E:77:CF:1C:F7:DE:16:28:E3:2C:34:9B:CA:87:44:6C:AF:1C:96:A7` |

**Las credenciales viven en `~/.gradle/gradle.properties`** (modo 600), no en el
proyecto: `~/.gradle` esta fuera del arbol de trabajo, asi que **ningun
`git add` lo puede arrastrar al repositorio**. Las cuatro propiedades son
`TSA_STORE_FILE`, `TSA_STORE_PASSWORD`, `TSA_KEY_ALIAS` y `TSA_KEY_PASSWORD`.

PKCS12 **no admite contraseñas distintas** para el almacen y la clave: keytool
avisa que ignora `-keypass`. Las dos son la misma, y es el comportamiento normal
del formato.

#### Respaldos (2026-09-08)

Tres copias identicas, verificadas por sha256:

| Donde | Para que |
|---|---|
| `/home/server-dt/keystores/totalsecureapp-release.jks` | el que usa Gradle |
| `/home/server-dt/keystores/respaldos/…-20260908.jks` | sobrevive un borrado accidental |
| `apk/totalsecureapp-release.jks` | junto al APK, para bajarlo |

Con `apk/LEEME-keystore.txt`, que trae alias, vigencia y huella SHA-256 **pero no
la contrasena**: si el .jks y su clave viajan juntos, quien consiga el paquete
tiene todo.

⚠️ **Las tres copias estan en el mismo disco**, asi que protegen contra un
borrado, no contra una falla de disco ni contra perder el servidor. El respaldo
de verdad es una copia **fuera de esta maquina**.

Y una trampa que aparecio al hacerlo: **`.gitignore` cubria `*.apk` pero no el
`.jks`** que se puso al lado. `apk/` figuraba como no ignorado, asi que un
`git add -A` habria publicado la clave de firma en GitHub. Ahora se ignoran
`/apk/`, `*.jks` y `*.keystore`, con excepcion para
`android/app/debug.keystore`, que es el publico de Android y si va versionado.

#### Por que esto no se puede perder

Android identifica una app por **paquete + clave de firma**. Si se pierde el
keystore:

- **No se puede volver a actualizar la app instalada.** Android rechaza un APK
  cuya firma no coincide con la instalada: hay que **desinstalar y reinstalar**,
  con lo que se pierden los datos locales de la tablet (incluida la cola de
  registros hechos sin señal).
- En Google Play seria peor: no se puede volver a publicar bajo el mismo paquete.
- **Un keystore filtrado tampoco se puede revocar.** Quien lo tenga puede firmar
  actualizaciones haciendose pasar por la app.

**Respaldarlo fuera de este servidor**, junto con su contraseña y por separado de
ella.

#### La firma se elige sola, y el build lo dice

`app/build.gradle` usa `signingConfigs.release` cuando existe `TSA_STORE_FILE` y
cae al de depuracion cuando no. La reserva existe para que otra maquina pueda
compilar para probar sin tener el keystore.

Y lo anuncia en la salida, porque **los dos APK se ven iguales** hasta que
alguien intenta instalar uno encima del otro:

```
> Task :app:assembleRelease
Firma release: keystore de PRODUCCION (totalsecureapp)
```

⚠️ **El APK de depuracion que se genero antes NO se puede actualizar con este.**
Si alguna tablet ya tiene instalado el anterior, hay que desinstalarlo primero.

### APK compilado y verificado (2026-09-08)

`app-release.apk`, **100 MB**. Copia en `apk/` de la raiz del monorepo
(`*.apk` esta en `.gitignore`).

Verificado **dentro del APK**, no solo que compilara:

| | |
|---|---|
| Paquete | `com.dt360.coreapp` v1.0.0, targetSdk 36 |
| Nombre | Total Secure App |
| `apiHosts` | `["181.198.245.50", "181.188.232.50"]` en `assets/app.config` |
| Puerto / esquema | 3031 / http |
| Cleartext HTTP | habilitado (sin esto Android 9+ bloquea el trafico) |
| Splash | los 5 PNG por densidad, **identicos pixel a pixel** a los generados desde `logo.png` |

**Firmado con el keystore de PRODUCCION** (ver la seccion siguiente).

### Reenvio del router: funcionando (2026-09-08)

Las **dos** IP publicas responden en el 3031, y la API emite token por las dos:

```
http://181.198.245.50:3031/acceso/login -> 200
http://181.188.232.50:3031/acceso/login -> 200
POST /api/login por ambas               -> access_token
```

Latencia ~0,04 s por las dos, igual que por la IP local. (La primera medicion dio
0,77 s en la primaria; era el costo de abrir la conexion, no una diferencia de
ruta. Repetida, se igualan.)

> La prueba se hizo **desde dentro de la red**: el router hace hairpin NAT y
> permite salir y volver. Eso demuestra que la regla de reenvio existe y llega al
> servidor. Lo unico que no cubre es que el ISP bloquee el 3031 entrante; si una
> tablet con datos moviles no conecta pero por wifi si, ese es el sospechoso.

## App Expo

Expo SDK 57. Leer docs versionadas en https://docs.expo.dev/versions/v57.0.0/ antes de escribir código.

- **Conectada al backend real.** `src/services/api.ts` apunta a `API_URL` de `src/utils/constants.ts`: el host se toma en orden de prioridad de `Constants.expoConfig.extra.apiHost` (app.json), luego del `hostUri` de Expo, y como último recurso `localhost`. ⚠️ **`apiHost` es una IP concreta y hay que cambiarla al mover el backend**: si apunta a una máquina que no existe en esa red, el login falla por timeout sin decir por qué, y al ir el JS embebido en el APK release hay que **recompilar**. Desde el 2026-09-07 apunta a la IP pública `181.188.232.50` por el **puerto 80** (`apiScheme: http`, `apiPort: 80`), a través del nginx del host. **`apiPort` va explícito**: con esquema `http` y sin él, `constants.ts` cae al 3031. Todo el detalle —reparto del puerto 80, qué reenviar en el router, y los riesgos de ir por IP sin HTTPS— en **`DESPLIEGUE.md`**. Puerto 3031 → funciona en web, emulador y dispositivo físico en la misma red.
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
| Backend Docker | ✅ Funcionando | Contenedores `ts_backend`, `ts_db`, `ts_nginx` activos en puerto 3031 |
| API REST | ✅ Funcionando | `POST /api/login` responde correctamente con JSON |
| Frontend Expo | ✅ Compila | TypeScript sin errores, bundle genera ~1MB |
| Web export | ✅ Funcionando | Export estático en `dist/`, servido en `0.0.0.0:8081` |
| Flujo Login → Home | ✅ Completo | Login → Selección institución → Home → Módulos |
| Pantallas módulos | ✅ Todas operativas | Rondas, Accesos, Novedades, Alertas, Inventario, Biometría, Perfil |

**Para acceder desde otra PC en la misma red:**
1. Levantar Expo: `npx expo start --web --port 8081` (o export estático: `npx expo export --platform web`)
2. Abrir `http://192.168.100.212:8081` desde el navegador

**Nota:** El error "React Native DevTools" al iniciar Expo es esperado en servidores Linux sin interfaz gráfica (Electron no tiene `--no-sandbox`). No afecta al funcionamiento de la app.

## Verificación del clon en servidor nuevo (2026-09-07)

Clonado limpio en `/home/server-dt/Documentos/totalsecureapp` (servidor
`192.168.3.124`) y levantado de cero con Docker. Todo verificado contra el
sistema corriendo, no contra el código:

| Componente | Estado | Detalle |
|---|---|---|
| Build + `composer install` | ✅ | 101 paquetes; `vendor/` no se versiona, el paso es obligatorio |
| Migraciones | ✅ | 47 migraciones |
| `db:seed` + `storage:link` | ✅ | Seed base (sin `CuadranteEjemploSeeder`) |
| `php artisan test` | ✅ | **285/285**, ~34 s |
| `POST /api/login` | ✅ | Token Sanctum + 40 permisos, cédula `1234567890` / `123456` |
| Login web → perfil → `/admin` | ✅ | Con el `code` cifrado del perfil Supervisor |
| Pantallas del panel Filament | ✅ | Las 13 responden 200, ninguna 500 |
| `/livewire/livewire.js` | ✅ | 200, con `FILAMENT_LIVEWIRE` vacío |

Tres cosas se corrigieron a raíz de esta verificación —la raíz que daba 404, el
`memory_limit` que tumbaba la suite y la base `coredt360_testing` que no
existía— y están documentadas en sus secciones. **Lo que sigue pendiente y no se
tocó** está abajo.

## Pendientes conocidos

- **Usuario demo con clave `123456` en el seeder**, en claro y versionado. Ver
  la sección Backend: hay que tocar `DatabaseSeeder`, no solo la base. Ya existe
  un administrador propio (`0912345678`), así que el demo se puede desactivar.
- **Bloquear o no el marcaje de un local sin marcadores.** Hoy se acepta y se
  marca como no verificado (ver «Validacion de presencia y geocerca»). La
  decisión conviene tomarla mirando cuántas filas salen con
  `verificada = false`, no antes.
- **`php artisan schedule:list` revienta** con
  `DateTime::setTimezone(): Argument #1 must be of type DateTimeZone, null
  given`. Es un bug de Laravel 8.75 (la opción `--timezone` llega null), no del
  proyecto. Para ver lo programado, leer `app/Console/Kernel.php`.

- **Firebase/Google Play:** falta `google-services.json` para notificaciones push en dispositivos reales en producción (la app ya registra el token; sin Firebase no llega la notificación a teléfonos). Se debe evitar versionar el archivo con credenciales reales en el repo.
- **Cambio de contraseña por email:** la app puede completar el flujo porque la API devuelve el token; si se quiere estricto por correo, usar deep linking (`Linking` + scheme).
- **Compilar APK:** el APK **release standalone** ya se compiló (`android/app/build/outputs/apk/release/app-release.apk`, ~99 MB, firmado con debug keystore, `com.dt360.coreapp` v1.0.0, targetSdk 36). Para producción real: generar keystore propio y usar `npx expo prebuild`/EAS. El APK debug (`app-debug.apk`, ~190 MB) solo funciona con Metro levantado (`npx expo start`).
- **Unificar `Role` con `roles`** (`Modules/Acceso/Models/`): no son duplicados exactos, `Role` es el modelo de Spatie declarado en `config/permission.php` y `roles` es el `$model` de `RolesResource` de Filament. Unificarlos exige refactorizar el resource; queda pendiente de decisión.
