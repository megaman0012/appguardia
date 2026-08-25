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

### Carga por CSV

- `PlantillaImportService` importa **franjas y asignaciones, no turnos**: los turnos los sigue generando `PlantillaTurnoService` en un segundo paso. Así la carga masiva pasa por las mismas validaciones que la carga manual, en vez de tener un camino propio que se salte los solapes.
- **El local no va en el archivo**: lo define la plantilla. Pedirlo por fila solo abriría la puerta a que no coincida.
- Es tolerante con lo que sale del Excel de una oficina: BOM, Windows-1252, CRLF, separador `;` o `,`, día como `LUN`/`lunes`/`3`, hora como `6:00` o `06:00:00`, y el nombre del puesto sin acentos ni mayúsculas. Un archivo perfectamente válido no puede fallar con «puesto no encontrado» porque Excel guardó los acentos en otra codificación.
- **O entra todo o no entra nada.** Con un solo error no se escribe ninguna fila y el cuadrante anterior queda en pie: media carga es peor que ninguna. Las filas repetidas son aviso, no error, y se cargan una sola vez.
- El botón **Descargar modelo** entrega el CSV con los puestos del local ya escritos (y con BOM, para que Excel no rompa los acentos). Que el líder no tipee los nombres evita la mitad de los errores de carga.
- El archivo subido se borra apenas se vuelca en la plantilla: conservarlo solo acumularía copias del cuadrante en disco.
- **El Supervisor no puede abrir el editor del cuadrante** (`canEdit` → 403), así que no alcanza con ocultarle los botones de carga: hoy directamente no ve el detalle. Su vista de solo lectura del cuadrante es la grilla pendiente.

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
