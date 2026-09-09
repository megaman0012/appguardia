# Migración a Laravel y Filament actuales

Rama: `migracion-laravel-filament`. Consultado contra Packagist el **2026-09-08**.

## 1. Punto de partida

> **Estado al cerrar la Etapa 4:** Laravel **12.69.2**, Filament **4.13.1**,
> Livewire **3.8.8**, **389 tests en verde** y **cero avisos de seguridad**. La
> tabla de abajo es el punto de partida, que se conserva para poder leer de
> dónde se venía.

| | Al empezar | Última publicada | Majors de atraso |
|---|---|---|---:|
| `laravel/framework` | **8.75.0** | 13.31.0 | **5** |
| `filament/filament` | **2.17.59** | 5.8.1 | **3** |
| `livewire/livewire` | **2.12.8** | 4.4.4 | **2** |
| `laravel/sanctum` | 2.15.1 | 4.3.3 | 2 |
| `nwidart/laravel-modules` | 8.6.0 | 13.0.0 | 5 |
| `spatie/laravel-permission` | 5.11.1 | 8.3.0 | 3 |
| `pxlrbt/filament-excel` | 1.1.14 | 4.1.0 | 3 |

PHP del contenedor: **8.3.33**. `composer.json` declara `^8.1`. PostgreSQL 16.14.

Laravel 8 **dejó de recibir parches de seguridad en enero de 2023**. Es el dato
que manda: no es una modernización estética.

## 2. El orden no es opcional: lo fija el retículo de dependencias

Lo que cada versión de Filament exige, leído de su `composer.json`:

| Filament | PHP | Laravel | Livewire |
|---|---|---|---|
| **3.3.55** | ^8.1 | **≥ 10.45** | ^3.5 |
| **4.13.1** | ^8.2 | **≥ 11.28** | ^3.7 |
| **5.8.1** | ^8.2 | **≥ 11.28** | **^4.1** |

De ahí tres consecuencias que definen todo el plan:

1. **No se puede tocar Filament antes de estar en Laravel 10.45.** Cualquier
   intento de subir el panel primero lo bloquea Composer.
2. **Filament 3 es una parada obligatoria.** No hay salto de 2 a 4: los cambios
   de API se acumulan y la guía oficial de upgrade va de major en major.
3. **Filament 5 añade Livewire 4.** Filament 4 se queda en Livewire 3, que es el
   que ya vamos a tener que aprender. Una pieza móvil menos.

### Destino recomendado: Laravel 12 + Filament 4

Y no Laravel 13 + Filament 5 en el mismo movimiento. Razón: Filament 4 corre
sobre Laravel 11, 12 **y 13**, así que llegar a Filament 4 sobre Laravel 12 deja
el último salto (12 → 13) como un cambio **solo de framework**, sin tocar el
panel. Al revés —13 + Filament 5— hay que estrenar Laravel 13, Filament 5 y
Livewire 4 a la vez, y si algo se rompe no se sabe cuál de los tres fue.

Una vez en Laravel 12 + Filament 4, subir a 13 y a Filament 5 son dos pasos
cortos e independientes.

## 3. Etapa 0 — La red de seguridad ✅ HECHA

**Es la etapa que no se puede saltar**, y la única que da un beneficio inmediato
aunque la migración se posponga. Está en esta rama.

Hay 358 tests, pero medidos contra lo que la migración va a romper hay dos
agujeros grandes:

| | Total | Con test que lo ejercite | Sin cubrir |
|---|---:|---:|---:|
| Pantallas del panel (recursos) | 27 | 10 | **17** |
| Rutas de la API que consume el APK | 57 | 12 | **45** |

Una subida de Filament rompe **el renderizado**, y 17 recursos no tienen ni un
test que los dibuje: se enteraría el usuario, no el pipeline. Y el APK instalado
en las tablets habla contra 57 endpoints de los que 45 nadie verifica.

**Qué se hizo:** dos pruebas de humo guiadas por datos, no 74 tests a mano.

- **`tests/Unit/PanelSeDibujaTest.php`** — recorre los recursos de verdad
  (`glob` sobre `app/Filament/Resources`) y dibuja **los 27 listados** con
  Livewire, más los widgets del tablero. Si aparece un recurso nuevo, entra
  solo. Comprueba además que todos hereden de `ListadoBase`, de donde cuelgan
  las migas y el botón de descarga.
- **`tests/Unit/ApiRespondeTest.php`** — llama a **las 55 rutas** con prefijo
  `api/`, cada una **con un token válido** para que el controlador se ejecute de
  verdad, y exige que ninguna sea 5xx. Un 401, 403, 404 o 422 son respuestas
  correctas: «te entendí y te digo que no». Un 500 es una clase que no existe o
  una firma que cambió, que es exactamente lo que rompe una subida de major.

La suite pasó de **358 a 444 tests**.

### Y encontró un 500 en la primera corrida

`GET /api/user` devolvía **500**. Es la ruta de ejemplo que trae Laravel:
`routes/api.php` importa `Illuminate\Support\Facades\Route` pero **nunca**
`Illuminate\Http\Request`, así que el `Request $request` del cierre resolvía al
*alias de la fachada* y `Request::user()` no existe. Llevaba ahí desde el
andamiaje inicial.

Se borró en vez de arreglarse: no la usa nadie —el APK no la tiene en
`constants.ts`—, el perfil sale por `/api/seleccionar_perfil` y
`/api/procesar_perfil`, y una ruta de andamiaje que responde 500 es peor que no
tenerla. Con ella se fue `GET /api/test-cors`, una prueba manual de cuando se
configuró el portal.

De ahí que las rutas sean 55 y no 57.

### Dos trampas de PHPUnit que costaron encontrar

Un `dataProvider` corre **antes de que exista la aplicación**:

- `app_path()` falla con «Call to undefined method `Container::path()`». La ruta
  a los recursos va relativa al archivo del test.
- La fachada `Route` falla con «A facade root has not been set». Hay que
  arrancar una instancia desechable (`require bootstrap/app.php` + `bootstrap()`)
  solo para enumerar las rutas.

Sin la Etapa 0, el resto del plan es a ciegas. Con ella, cada etapa se valida en
un minuto.

## 4. Etapa 1 — Desatascar Composer ✅ HECHA

Resultado: **Laravel 8.75.0 → 8.83.29**, `minimum-stability` de `dev` a
`stable`, ocho paquetes fuera, y **un CVE de severidad alta cerrado**. Los 389
tests pasan y la API responde por las dos IP públicas.

### 🔓 Lo más importante: se cerró CVE-2024-52301

Al poner `minimum-stability: stable`, Composer **se negó a instalar cualquier
Laravel 8.83.x** porque toda la línea arrastra avisos de seguridad. Antes eso
estaba tapado: con `dev` resolvía a `8.x-dev`, una **rama de desarrollo**, y los
avisos van sobre versiones publicadas.

De los cuatro avisos que afectan a Laravel 8, uno **sí tiene arreglo dentro de
la 8.x**:

| Aviso | Severidad | Afecta | ¿Arreglable en 8.x? |
|---|---|---|---|
| **PKSA-w7xr-vk7n-rstm** (CVE-2024-52301) — manipulación del entorno por query string | **alta** | `<8.83.28` | **Sí → cerrado** |
| PKSA-3r5d-mb8f-1qw9 — inyección CRLF en la regla `email` | alta | `<12.60.0` | No |
| PKSA-8qx3-n5y5-vvnd (CVE-2025-27515) — bypass de validación de archivos | media | `<10.48.29` | No |
| PKSA-m5cs-t1y6-qpcs — confusión de rutas en URLs firmadas temporales | media | `<12.61.1` | No |

Estábamos en **8.75.0**, o sea **expuestos**, y este servidor tiene
`register_argc_argv = On`, que es justo la precondición del CVE. Subir a
8.83.29 lo cierra.

Los otros tres se dejan explícitos en `config.policy.advisories.ignore-id`
**menos** el que sí se arregla: dejarlo bloqueado es lo que **obliga** a Composer
a instalar 8.83.28 o superior. Y no tienen arreglo posible: Laravel 8 no recibe
parches de seguridad desde enero de 2023. **Se cierran subiendo de major, no
antes.**

### 🔓 dompdf: seis avisos, y una mitigación que sí se pudo aplicar hoy

`dompdf/dompdf` 2.0.8 —la última que admite Laravel 8— arrastra seis avisos
(`<3.1.6`), todos **media o baja**: lectura de archivos locales y filtración de
existencia de rutas vía SVG, agotamiento de recursos por BMP declarados
enormes, y un salto del *chroot*. Los críticos de dompdf son todos `<2.0.x`, así
que no aplican.

Los seis necesitan HTML o SVG controlado por quien ataca, y **`enable_remote` es
lo que los hace alcanzables**. Se apagó en `config/dompdf.php`: la única vista
que se renderiza a PDF es la hoja imprimible del marcador QR, y desde hoy carga
el logo por `public_path()` —ruta de disco, no URL—, así que no hay ninguna
carga remota en ningún PDF. Verificado: la hoja sigue saliendo igual, 42.102
bytes con el logo incrustado.

La solución de fondo es `barryvdh/laravel-dompdf` ^3.1, que usa dompdf 3 y
**exige Laravel ≥ 9**. Es la razón más concreta para no dejar la Etapa 2 para
más adelante.

### Los pins, resueltos

| Paquete | Antes | Ahora |
|---|---|---|
| `laravel/framework` | `8.75` | **`^8.83`** → v8.83.29 |
| `monolog/monolog` | `2.9.2` | fuera (transitiva) → 2.11.1 |
| `symfony/mime` | `5.4.*` | fuera (transitiva) |
| `symfony/mailer` | `6.0` | fuera — Laravel 8 usa swiftmailer |
| `symfony/html-sanitizer` | `7.4.12` | fuera (transitiva) |
| `league/uri-interfaces` | `7.0` | fuera (transitiva) |
| `guzzlehttp/guzzle` | `7.15` | `^7.8` → 7.15.5 |
| `nesbot/carbon` | `2.73.0` | `^2.73` |
| `minimum-stability` | **`dev`** | **`stable`** |

Y fuera del `require`:

- **`yajra/laravel-datatables-oracle`** — 0 usos. Se quitó también de
  `config/app.php` y se borró `config/datatables.php`.
- **`facade/ignition`** → **`spatie/laravel-ignition`** ^1.7 (el paquete se
  renombró en Laravel 9; la v1 admite Laravel ^8.77, que es justo lo que
  habilita el nuevo pin).
- **`barryvdh/laravel-debugbar`** pasó a `require-dev`.

Quedan 4 paquetes marcados como abandonados (`swiftmailer`, `tgalopin/html-sanitizer`,
`league/uri-parser`, `maximebf/debugbar`): son dependencias de Laravel 8 y del
debugbar, y desaparecen con el framework.

### 🐛 Y apareció un cuelgue que nadie había visto

Después de subir a 8.83.29, **toda la suite se colgaba sin decir nada**: PHPUnit
imprimía su cabecera y se quedaba quieto para siempre. La causa:

```php
// database/migrations/2019_08_19_000000_create_failed_jobs_table.php
protected $connection = 'mysql';
```

Dos migraciones (`create_failed_jobs_table` y `create_permission_section_table`)
declaraban la conexión **`mysql`**, herencia de cuando el proyecto corría sobre
MySQL en v1. Este proyecto es PostgreSQL, y la conexión `mysql` de
`config/database.php` toma `DB_HOST` y `DB_PORT` del `.env` — o sea que apuntaba
**el driver de MySQL contra el puerto 5432 de Postgres**. El TCP conecta,
el cliente manda el saludo de MySQL, y se queda esperando una respuesta que
nunca llega: cuelgue indefinido, sin error ni traza.

**Laravel 8.75 no respetaba ese `$connection` y 8.83 sí.** Por eso la tabla
`failed_jobs` existe en Postgres en producción y la migración figura aplicada en
el lote 1: históricamente corrió sobre la conexión por defecto. Quitadas las dos
líneas, las 56 migraciones corren completas.

### Dos cosas para la próxima vez

- **Si la suite se pone no determinista, mirar `ps` antes que el código.** Tuve
  corridas con 242 errores «relation users does not exist», luego 57, luego
  ninguno. No era el código: eran **procesos de phpunit huérfanos** de las
  corridas que se habían colgado, vivos y haciendo `migrate:fresh` debajo unos
  de otros. `pg_stat_activity` mostraba 35 sesiones sobre la base de pruebas.
- **`composer update` toca producción al instante**, porque `vendor/` está
  montado en vivo (`./:/var/www`). Se hizo con `artisan down` puesto: 16
  segundos de 503 con `Retry-After`, en vez de errores de clase inexistente
  llegando a las tablets. Respaldo previo de `composer.json`, `composer.lock` y
  `vendor/` completo.

### Lo que sigue bloqueado por Laravel 8

`laravel/sail` (1.25 → 1.67) y `spatie/laravel-html` (3.5 → 3.13) tienen
actualizaciones **dentro de su propio major** y no se pueden tomar: las dos
exigen Laravel ≥ 9. Es el techo de esta etapa.

## 5. Etapa 2 — Laravel 8 → 10 ✅ HECHA

**Laravel 8.83.29 → 10.50.3.** Los 389 tests pasan, el panel dibuja sus 27
listados, las 55 rutas de la API responden y la app entra por las dos IP
públicas. Filament 2.17.59 y Livewire 2.12.8 **no se tocaron**.

### Se saltó la parada en Laravel 9, y por una razón

El plan decía pasar por la 9 para poder atribuir fallos. Medido contra los
avisos de seguridad reales, esa parada es **peor** que el destino:

| Versión | Avisos que la afectan |
|---|---:|
| Laravel 9.52.22 | **4** |
| Laravel 10.50.3 | **3** |
| Laravel 12.69.2 | **0** |
| Laravel 13.31.0 | **0** |

Laravel 9 arrastra un aviso **más** que la 10 —`CVE-2025-27515`, bypass de
validación de archivos, arreglado en 10.48.29— y su soporte de seguridad
terminó en febrero de 2024. Desplegar ahí, aunque fuera un rato, es desplegar
algo estrictamente peor. Y el dato que importa: **solo Laravel 12 y 13 tienen
cero avisos conocidos.**

El framework quedó en `^10.48.29`, no en `^10.0`, justamente para no poder caer
por debajo del parche de ese CVE.

### 🔓 Los seis avisos de dompdf, cerrados

`barryvdh/laravel-dompdf` 2.2.0 → **3.1.2**, y con él `dompdf/dompdf` 2.0.8 →
**3.1.6**. Los seis avisos que en la Etapa 1 hubo que tolerar **ya no existen**.
La auditoría pasó de **9 avisos en 2 paquetes a 3 en 1**.

Los 3 que quedan son los de Laravel que no tienen arreglo por debajo de la 12, y
la propia auditoría lo confirma: `CVE-2026-48019` afecta a `<12.60.0` y a
`<13.10.0`.

`enable_remote` de dompdf **se queda apagado**: ya no hace falta para cerrar
nada, pero ninguna vista de PDF carga nada remoto, así que dejarlo encendido
solo agrega superficie.

### ⚠️ El bloqueo por avisos de Composer no se puede afinar

En Composer 2.10, `policy.advisories.ignore-id` y `policy.advisories.ignore`
**solo afectan al informe de `composer audit`, no al bloqueo del resolutor**. Lo
comprobé con las tres formas documentadas: por ID, por paquete como objeto y por
paquete como arreglo. Ninguna desbloquea la instalación; `composer config
policy.advisories.ignore-id` devuelve la lista correctamente, pero el resolutor
la ignora.

La única palanca que funciona es `policy.advisories.block: false`, que apaga el
bloqueo **entero**. Es lo que quedó, con una compensación: `config.audit.ignore`
tiene **solo los 3 avisos sin arreglo posible**, así que `composer audit` sigue
siendo útil —está en verde salvo por esos tres— y grita si aparece algo nuevo.

Con esto se pierde el truco de la Etapa 1 (dejar un aviso sin ignorar para
*forzar* una versión mínima). Se reemplaza por el pin explícito `^10.48.29`.

De paso se quitó `PKSA-ddwf-kgzy-ytq1` de la lista de silenciados: estaba ahí sin
ninguna explicación, no figura en el historial de git y no corresponde a ningún
paquete instalado. Si sigue aplicando, la auditoría lo dirá.

### 🐛 Lo que se rompió, y era invisible

**`spatie/laravel-permission` 5 → 6 convirtió en propiedades de instancia lo que
antes eran estáticas** (`PermissionRegistrar::$pivotRole`, `$pivotPermission`,
`$teams`, `$teamsKey`). Rompió en tres lugares distintos, cada uno con un
síntoma diferente:

1. **La migración `create_permission_tables`** moría con «Access to undeclared
   static property», así que `migrate:fresh` fallaba en el quinto paso y **toda
   la suite** con él.
2. **Las firmas de `Role` y `Permission`** ya no cumplían el contrato: v6 pide
   `?string $guardName` sin valor por defecto e `int|string $id`. Eso no es un
   aviso, es un **error fatal de PHP antes de arrancar**: cualquier comando
   moría.
3. **Las relaciones `permissions()` y `roles()`** usaban las estáticas en
   tiempo de ejecución. Síntoma: **44 de las 55 rutas de la API devolvían 500**,
   y lo único visible era el 500.

Los tres se resolvieron leyendo del mismo config del que la librería las leía en
v5 (`config('permission.column_names.*')`, `config('permission.teams')`), con
los mismos valores por defecto. En este proyecto son `role_id` y `permission_id`,
que es lo que tiene `role_has_permissions` en producción.

Vale subrayar por qué el acoplamiento es superficial y esto alcanzó: los modelos
implementan los *contratos* de Spatie pero extienden `Model` a secas, y el
sistema real de permisos son tablas propias (`user_has_roles` con `ru_code`,
`role_has_permissions`, `permission_section`).

### Lo que NO se rompió, contra lo esperado

- **Flysystem 1 → 3 no afectó las fotos.** Era el riesgo marcado en el plan, por
  las 45.319 imágenes. Resulta que `generalTrait::storeFiles()` usa
  `$file->move()` —sistema de archivos plano— y la línea con
  `Storage::disk('public')->put()` está comentada. Verificado igual: `Storage::put`,
  `Storage::path` y `Excel::store` funcionan, y una foto de 2025 se sirve con
  HTTP 200 y 4,4 MB.
- **swiftmailer salió y entró `symfony/mailer`** sin tocar nada: `MAIL_MAILER`
  apunta a mailhog y no hay envíos reales todavía.
- **El esqueleto se dejó como está.** `app/Http/Kernel.php`, `Handler.php` y
  `RouteServiceProvider` siguen en formato Laravel 8, que Laravel 10 acepta. El
  middleware de CORS es propio (`App\Http\Middleware\HandleCors`), no el de
  Fruitcake, así que la integración de CORS al framework no molestó: el preflight
  del portal sigue devolviendo los tres encabezados.
- **`nwidart/laravel-modules` 8.6 → 10.0.6** sin incidentes. Ayudó que los
  módulos se autocarguen con `psr-4` normal, sin el `composer-merge-plugin`.

### Un arreglo de regalo

**`php artisan schedule:list` volvió a funcionar.** Estaba roto en 8.75 —así lo
anotaba `AGENTS.md`— y ahora lista las tres tareas con su próxima ejecución.

### PHPUnit 9.6 → 10.5

Vino con el paquete de Laravel 10. `phpunit.xml` estaba en el esquema de la 9 y
lo migró la propia herramienta (`--migrate-configuration`): `<coverage>` pasa a
`<source>` y aparece `cacheDirectory`, que se agregó al `.gitignore` porque la
10 usa un **directorio** y no el archivo `.phpunit.result.cache`.

### Lo que se desbloqueó para las etapas siguientes

- `laravel/sail` 1.25 → **1.67** y `spatie/laravel-html` 3.5 → **3.13**, que en
  la Etapa 1 estaban en su techo.
- Los 4 paquetes abandonados bajaron a **2**, y los dos que quedan
  —`tgalopin/html-sanitizer` y `league/uri-parser`— los arrastra
  **`filament/support`**: se van con Filament 3.
- Y lo principal: **Filament 3 ya es instalable**, porque exige Laravel ≥ 10.45 y
  estamos en 10.50.3.

## 6. Etapa 3 — Filament 2 → 3 y Livewire 2 → 3 ✅ HECHA

| | Antes | Ahora |
|---|---|---|
| `filament/filament` | 2.17.59 | **3.3.55** |
| `livewire/livewire` | 2.12.8 | **3.8.8** |
| `pxlrbt/filament-excel` | 1.1.14 | **2.5.0** |
| `blade-ui-kit/blade-heroicons` | 1.7.0 | **2.7.0** |

**389 tests pasan.** El panel dibuja sus 27 listados y sus widgets, y verificado
en vivo con sesión real: tablero 72 KB, Alertas 327 KB, Accesos 523 KB, con los
CSS de Filament 3.3.55 cargando. **Y los 2 paquetes abandonados que quedaban
desaparecieron**: los arrastraba `filament/support` de la versión 2.

### El estimado del roadmap estaba mal en el punto más grande

Se contaron **176 usos de `->size('sm')`** como el trabajo más pesado. No hubo
que tocar ninguno: en Filament 3 la firma es
`size(TextColumnSize | string | Closure | null)`, o sea que **el texto sigue
siendo válido**. Lo mismo con dos clases que se daban por eliminadas:
`BooleanColumn` y `BadgeColumn` **siguen existiendo** como envoltorios marcados
como obsoletos (`@deprecated`), así que sus 73 usos funcionan tal cual. Se dejan
para la Etapa 4, que es donde Filament 4 los quita de verdad.

Lo que sí costó fue otra cosa: **la visibilidad de los métodos y las firmas de
los contratos**. Nada de eso estaba en el plan.

### ⚠️ Lo que rompió de verdad: visibilidad y firmas

PHP aborta al **cargar la clase** cuando una firma no encaja con la del padre.
No es un aviso ni un 500 en una pantalla: es la aplicación caída completa, API
incluida. Aparecieron seis casos, uno detrás de otro:

| Qué | Cambio en Filament 3 | Cuántos |
|---|---|---|
| `getBreadcrumbs()` | `protected` → **`public`** | 1 |
| `getTitle()` | `protected` → **`public`** | 26 |
| `shouldRegisterNavigation()` | `protected` → **`public`** | 17 |
| `getNavigationBadge()`, `getNavigationBadgeColor()` | `protected` → **`public`** | 2 |
| `EditRecord::save()` | gana `$shouldSendSavedNotification` | 3 |
| `RelationManager::form()`/`table()` | `static` → **de instancia** | 5 |
| `FilamentUser::canAccessFilament()` | → **`canAccessPanel(Panel $panel)`** | 1 |

Los que sí eran renombres mecánicos, con sus cuentas reales:

| Cambio | Ocurrencias |
|---|---:|
| `use Filament\Pages\Actions` → `Filament\Actions` | 68 |
| `Filament\Resources\Form` → `Filament\Forms\Form` | 32 |
| `Filament\Resources\Table` → `Filament\Tables\Table` | 32 |
| `getActions()` → `getHeaderActions()` en páginas | 31 |
| `modalSubheading()` → `modalDescription()` | 8 |
| `getCards()` → `getStats()` en widgets | 4 |
| `->unique(callback:)` → `modifyRuleUsing:` | 5 |
| `modalButton()` → `modalSubmitActionLabel()` | 1 |
| `Forms\Components\Tab` → `Components\Tabs\Tab` | 1 |

### Los iconos: 21 de 42, y la prueba de humo los encontró sola

Heroicons v1 → v2 renombra **21 de los 42 iconos** que usa el proyecto, en 39
lugares. Un icono inexistente **revienta al renderizar**, y ahí se vio para qué
servía la Etapa 0: en vez de abrir 27 pantallas a mano, se listaron los 1.288
iconos que trae el paquete, se cruzaron con los usados, y se verificó que cada
destino existiera **antes** de tocar nada.

Los renombres: `download`→`arrow-down-tray`, `search`→`magnifying-glass`,
`collection`→`rectangle-stack`, `office-building`→`building-office`,
`view-grid`→`squares-2x2`, `user-add`→`user-plus`, `user-remove`→`user-minus`,
`exclamation`→`exclamation-triangle`, `location-marker`→`map-pin`,
`qrcode`→`qr-code`, `login`→`arrow-right-on-rectangle`,
`speakerphone`→`megaphone`, `template`→`rectangle-group`,
`annotation`→`chat-bubble-bottom-center-text`, `chat-alt`→`chat-bubble-left-right`,
`clipboard-list`→`clipboard-document-list`, `switch-horizontal`→`arrows-right-left`,
`upload`→`arrow-up-tray`, y las tres de la variante sólida (`trending-up`,
`trending-down`, `exclamation`).

### `config/filament.php` → panel provider

Se reescribió como `App\Providers\Filament\AdminPanelProvider`, que lleva la
tabla de equivalencias documentada dentro. Y de paso salieron a la luz **tres
cosas que estaban mal desde antes**:

1. **`AppServiceProvider` tumbaba la aplicación entera.** Tenía
   `Filament::registerNavigationGroups()`, `registerUserMenuItems()` y
   `registerStyles()` dentro de `Filament::serving()`. La primera no existe en
   Filament 3, y como falla en el `boot()` de un proveedor **se cayó también la
   API que usan las tablets**, que no tiene nada que ver con el panel. Fue el
   primer 500 tras instalar.
2. **La hoja de estilos propia está vacía.** `public/css/filament-styles.css`
   son **0 bytes**, y el panel la venía pidiendo en cada carga desde la
   versión 2. No se registró.
3. **La pantalla de ingreso de Filament era inalcanzable.** Había una clase
   `App\Http\Livewire\Auth\Login` que sobrescribía `authenticate()` entero
   (límite de intentos, `attempt()`, regeneración de sesión, mensajes: 30
   líneas), pero `routes/web.php` tenía una **ruta manual** en `/admin/login`
   que redirige a `/acceso/login` —el login propio, con cédula y selección de
   perfil— y esa ruta la tapaba. Era código muerto en la versión 2 también, y se
   borró.

Y dos ajustes de arranque que costaron encontrar porque el síntoma era un 500 en
todo el panel:

- **El nombre de la ruta de ingreso.** Filament 2 lo leía del config y podía ser
  `filament.auth.login`; la 3 lo construye del id del panel y espera
  **`filament.admin.auth.login`**.
- **El middleware de autenticación.** Hay que usar el propio del proyecto
  (`App\Http\Middleware\Authenticate`, que redirige a `acceso.login`) y no el
  de Filament, que intenta resolver una pantalla de ingreso que este panel no
  declara. Con el de Filament, la excepción terminaba en el manejador de Laravel
  pidiendo `route('login')`, que no existe.

### Lo que se gana, ya cobrado

- **`stats-con-titulo.blade.php` se borró.** Esa vista existía **solo** porque
  Filament 2 no soportaba encabezado en `StatsOverviewWidget`; la 3 lo trae con
  `getHeading()` y `getDescription()`. Cuatro widgets dejaron de depender de una
  vista propia.
- **`BadgeColumn::enum()` desapareció** y se reemplazó por
  `App\Filament\Tables\Etiqueta::de()`, un solo lugar en vez de ocho cierres
  repetidos. `colors()`, en cambio, **sí sobrevive** (está en un *concern*, no en
  `TextColumn`).
- **Fuera el widget promocional de Filament.** `FilamentInfoWidget` venía
  registrado desde el config de la versión 2 y ponía la versión de Filament y
  enlaces a filamentphp.com **en el tablero de un panel que ven los clientes**.
  Por el mismo criterio con el que se apagó su logo del pie, se quitó.
- **El logo de la marca ahora usa `->brandLogo()`.** En la versión 2 estaba
  resuelto sobrescribiendo `vendor/filament/components/brand.blade.php`, una
  vista que **no existe en la 3**: el override quedaba inerte y el panel salía
  sin el escudo sin avisar de nada.

### La API de pruebas también cambió

Cuatro tests usaban la forma vieja y hubo que reescribirlos:

- `getCachedActions()` → **`getCachedHeaderActions()`**.
- `->call('mountAction', 'x')->set('mountedActionData.campo', …)->call('callMountedAction')`
  → **`->callAction('x', ['campo' => …])`**. La propiedad ya no se llama así, y
  los ayudantes oficiales no dependen de cómo se llame por dentro.
- Igual con las acciones de tabla → **`->callTableAction($nombre, $registro, $datos)`**.
- `$respuesta->payload` → **`$respuesta->effects`** (Livewire 3 no expone
  `payload`).

Y uno mejoró de fondo: `test_el_supervisor_no_asigna_la_cobertura` ponía la
propiedad a mano, **saltándose la comprobación de visibilidad**, y verificaba el
resultado de rebote. Ahora afirma lo que quiere afirmar:
`assertTableActionHidden()`.

### Cómo se hizo sin tirar la API abajo

No se usó `artisan down`: **el panel y la API son independientes**, así que
mientras se migraban los 27 recursos la API siguió respondiendo. La excepción
fue el fallo de `AppServiceProvider`, que sí tumbó todo hasta que se movieron los
tres registros al panel provider — y es la razón por la que ese arreglo fue lo
primero.

## 7. Etapa 4 — Laravel 12 y Filament 4 ✅ HECHA

| | Antes | Ahora |
|---|---|---|
| `laravel/framework` | 10.50.3 | **12.69.2** |
| `filament/filament` | 3.3.55 | **4.13.1** |
| `laravel/sanctum` | 3.3.3 | **4.3.3** |
| `nesbot/carbon` | 2.73.0 | **3.13.2** |
| `nwidart/laravel-modules` | 10.0.6 | **12.0.5** |
| `pxlrbt/filament-excel` | 2.5.0 | **3.6.1** |
| `phpunit/phpunit` | 10.5.64 | **11.5.56** |
| `livewire/livewire` | 3.8.8 | 3.8.8 (Filament 4 pide ^3.7) |

**389 tests pasan, sin deprecaciones.** Panel verificado en vivo con sesión real
y la API respondiendo por las dos IP públicas.

### 🔓 Cero avisos de seguridad

```
composer audit
No security vulnerability advisories found.
```

De **9 avisos en 2 paquetes** al empezar la Etapa 1 a **ninguno**. Y con eso se
pudo hacer lo que importa de verdad: **quitar la lista de silenciados y volver a
activar `policy.advisories.block`**. Ahora Composer se niega a instalar una
versión con avisos conocidos, en vez de solo informarlo. Ese guardia estuvo
apagado desde la Etapa 2 por la limitación de Composer 2.10, y era deuda: ahora
está pago.

Cero paquetes abandonados, también.

### ⚠️ Carbon 2 → 3: tres cosas rotas, dos de ellas en silencio

Es el cambio que más daño hizo, y ninguno de los síntomas apuntaba a Carbon.
`diffIn*()` cambió **dos** cosas a la vez: devuelve **float** en vez de int, y es
**con signo** en vez de valor absoluto.

| Dónde | Qué pasaba |
|---|---|
| `AlertaDetalle::marcarResuelta()` | Postgres rechazaba `600.899393` en una columna integer. **El único que fallaba a la vista.** |
| `generalTrait::calculoEdad()` | La fecha de nacimiento está en el pasado → `diffInYears()` daba **−36.3** → el `if ($anos >= 1)` daba falso y la edad se informaba **en horas** |
| `RondaController` (antirrebote de 5 min) | `$ahora->diffInMinutes($registro)` daba negativo → `< 5` **siempre verdadero** → «Ya registró este marcador, espere 5 minutos» en **cada** escaneo: el guardia no podía volver a marcar un punto nunca |
| `PostulacionesRelationManager` | El aviso de «llegó con retraso» no se mostraba nunca |
| 4 sitios con `intdiv($minutos, 60)` | `intdiv()` con float lanza **TypeError** |

Los 12 usos se revisaron uno por uno —ninguno quería el signo— y quedaron como
`(int) $a->diffInX($b, absolute: true)`. **Lo de las rondas es lo más grave: es
la función principal de la aplicación, y habría llegado a las tablets sin que
ningún test lo tocara** si no se hubiera revisado el listado completo de
`diffIn`.

### `nwidart/laravel-modules` 10 → 12

Dos cosas:

- **`config/modules.php` había que reemplazarlo.** El del proyecto era el de la
  versión 8 y listaba las clases de comando una por una; en la 12 esas clases se
  movieron y la clave `commands` apunta a un `ConsoleServiceProvider`. Con el
  viejo, **cualquier comando de artisan moría**. El config del proyecto no
  personalizaba nada, así que se tomó el nuevo y solo se le devolvieron las
  rutas del *generador*, para que un `module:make` futuro salga con la
  nomenclatura de los cuatro módulos que existen (`Routes/`, `Resources/`,
  `Config/`). Los módulos ya creados no dependen de eso: cada uno trae sus rutas
  en su propio `RouteServiceProvider`.
- **Arrastra `wikimedia/composer-merge-plugin`**, que Composer no instala sin
  permiso explícito. Se declaró en `allow-plugins` **como `false`**: el paquete
  se instala porque es dependencia, pero no ejecuta código en cada `install`. No
  hace falta — los cuatro módulos declaran `require: {}`, o sea que no tiene nada
  que fusionar.

### Filament 3 → 4: más barato de lo estimado, y por dos razones

El roadmap daba por hecho que había que migrar los **73 usos de `BooleanColumn` y
`BadgeColumn`** porque Filament 4 los eliminaba. **No los elimina**: siguen
ahí, todavía marcados como `@deprecated`. Cero trabajo.

Lo que sí cambió, y otra vez fue **tipos y ubicaciones**, no lógica:

| Cambio | Ocurrencias |
|---|---:|
| `Filament\Tables\Actions\*` → **`Filament\Actions\*`** (acciones unificadas) | 48 en 29 archivos |
| `Filament\Forms\Form` → **`Filament\Schemas\Schema`** | 32 archivos, 31 firmas |
| `$navigationIcon` → tipo `string\|BackedEnum\|null` | 27 |
| `$navigationGroup` → tipo `string\|UnitEnum\|null` | 3 |
| `StatsOverviewWidget\Card` → **`Stat`** | 4 widgets |
| `Page::$view` y `Widget::$view` → **dejan de ser `static`** | 2 |
| `Support\Enums\MaxWidth` → **`Width`** | 1 |

`Schema` conserva el método `schema()`, así que **los cuerpos de los 32
formularios no se tocaron**: solo el tipo del parámetro.

⚠️ **Un tropiezo propio que conviene anotar.** Al renombrar
`Tables\Actions\` → `Actions\` con un reemplazo de texto, en los archivos que
importaban `Filament\Tables` quedaron referencias **relativas**
(`Actions\EditAction`), que PHP resuelve contra el namespace actual:
`App\Filament\Resources\Actions\EditAction`. 18 listados fallaron con «Class
not found» hasta agregar el `use Filament\Actions;` en los 23 archivos que les
faltaba. Un reemplazo de namespace **no** es un reemplazo de texto.

### PHPUnit 10 → 11: 57 deprecaciones, a cero

PHPUnit 11 deprecó los metadatos en comentarios: **56 `@test` y 1
`@dataProvider`** pasaron a atributos (`#[Test]`, `#[DataProvider]`), con sus
imports. No es cosmético: dejar 57 avisos fijos hace que el siguiente aviso real
pase inadvertido. Los 389 tests siguen siendo 389.

### El único fallo que tumbó la API

Como en la Etapa 3, el panel y la API son independientes… salvo cuando falla el
`boot()` de un proveedor. `AdminPanelProvider` referenciaba
`Support\Enums\MaxWidth`, que en Filament 4 se llama `Width`, y eso dejó la
API en 500 hasta corregirlo. **Es siempre el mismo patrón: un error en el
proveedor del panel no es un problema del panel, es un problema de todo.**

### Lo que queda, y ya es opcional

Laravel **13.31.0** y Filament **5.8.1** son las últimas. Los dos saltos son
cortos desde acá:

- Laravel 12 → 13: cambio **solo de framework**; Filament 4 ya corre sobre 13.
- Filament 4 → 5: exige además **Livewire 4**, que es la pieza nueva.

Ninguno cierra vulnerabilidades —ya no hay— así que son mantenimiento, no
urgencia.

## 8. Riesgos propios de este proyecto

Esto no es una app de demostración, y hay cuatro cosas que hay que tener en la
cabeza en cada etapa:

1. **El APK instalado en las tablets no se puede recompilar a voluntad.** Una
   compilación completa son 21 minutos y una instalación por USB en cada
   dispositivo. **El contrato de la API tiene que quedar idéntico**: mismos
   nombres de campo, mismos códigos de estado. La prueba de humo de la API es la
   que lo vigila.
2. **El cifrado de los códigos QR.** `mervick/aes-everywhere` (`AES256`) cifra
   el contenido de los QR de los 118 marcadores **ya impresos y pegados en las
   garitas**. Si cambia la librería, la clave o el modo, **todos esos QR dejan de
   validar** y hay que reimprimir y volver a pegar. El paquete no declara
   restricción de Laravel, así que puede quedarse como está: **lo correcto acá es
   no tocarlo**.
3. **La base es de producción**, con 38.247 vínculos, 46.282 rondas y 45.319
   fotos. Cada etapa se prueba sobre una copia, no sobre esta.
4. **No hay worker de cola** (`QUEUE_CONNECTION=sync`). Nada de la migración lo
   necesita, pero es la pieza que falta para que las descargas grandes de
   reportería dejen de correr dentro de la petición. Buen momento para montarlo,
   porque el `supervisord` que haría falta se configura una vez.

## 9. Resumen de esfuerzo

| Etapa | Alcance | Riesgo | Se puede parar acá |
|---|---|---|---|
| **0** Red de seguridad ✅ | 2 tests guiados por datos (+86) | Ninguno | **Hecha** |
| **1** Desatascar Composer ✅ | 9 pins, 8 paquetes fuera, 8.75→8.83.29, **1 CVE alto cerrado** | Bajo | **Hecha** |
| **2** Laravel 8 → 10 ✅ | Framework, sanctum, permission, modules, **6 avisos de dompdf cerrados** | Medio | **Hecha** |
| **3** Filament 2 → 3 + Livewire 3 ✅ | 27 recursos, 39 iconos, visibilidades y firmas, **0 abandonados** | **Alto** | **Hecha** |
| **4** Laravel 12 + Filament 4 ✅ | Carbon 3, modules 12, acciones unificadas, **0 avisos** | Medio | **Hecha** |

La etapa 3 es la que concentra el trabajo, y las etapas 0 a 2 son las que la
hacen posible. Cada una deja el sistema funcionando y verificable: **ninguna
obliga a seguir con la siguiente**.

## 10. Lo que NO entra en esta migración

- **Reorganizar al esqueleto delgado de Laravel 11.** Es opcional y no aporta
  nada funcional; mezclarlo con la subida de versión hace imposible saber qué
  rompió qué.
- **Convertir el filtro de estado en pestañas.** Se puede recién en la etapa 3, y
  es una mejora de interfaz, no parte de la migración. Se hace después, con las
  pestañas ya disponibles.
- **Los puestos de trabajo** (ver `puestos-propuesta.csv` y
  `php artisan puestos:analizar`). Es un cambio de modelo de datos: mezclarlo con
  un cambio de framework es la forma más rápida de no poder depurar ninguno de
  los dos.
