# Migración a Laravel y Filament actuales

Rama: `migracion-laravel-filament`. Consultado contra Packagist el **2026-09-08**.

## 1. Punto de partida

| | Hoy | Última publicada | Majors de atraso |
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

## 5. Etapa 2 — Laravel 8 → 10

Se pasa por la 9 (`composer.json` → `^9.0`, correr, verificar) y de ahí a la 10.
Saltar directo suele funcionar, pero cuando falla no se sabe qué major lo
rompió.

Lo que hay que tocar, con las cuentas de este proyecto:

- **Dependencias de desarrollo**: `nunomaduro/collision` ^5 → ^7,
  `phpunit/phpunit` ^9.5 → ^10, `fakerphp/faker` sigue.
- **`laravel/sanctum` 2 → 3**. La tabla `personal_access_tokens` no cambia (13
  filas hoy, y el APK usa esos tokens), pero hay que revisar
  `config/sanctum.php`. **Ojo: si los tokens se invalidan, las tablets tienen
  que volver a iniciar sesión.** Es la primera cosa a verificar en cada paso.
- **`spatie/laravel-permission` 5 → 6**. Acoplamiento **superficial**:
  `Modules\Acceso\Models\Role` y `Permission` implementan sus *contratos* pero
  extienden `Model` a secas, y el sistema de permisos real son las tablas
  propias (`user_has_roles` con `ru_code`, `role_has_permissions`,
  `permission_section`). No se usan los traits ni el middleware de Spatie. Riesgo
  bajo.
- **`nwidart/laravel-modules` 8 → 10**. Buena noticia: los módulos se autocargan
  con `psr-4` normal (`"Modules\\": "Modules/"`), **sin** el
  `wikimedia/composer-merge-plugin` que hace difícil esta subida en otros
  proyectos. Son 4 módulos y 106 archivos PHP.
- **El esqueleto NO hay que reorganizarlo.** Laravel 11 introdujo el esqueleto
  delgado (`bootstrap/app.php` en vez de `app/Http/Kernel.php`), pero **es
  opcional**: una app que viene de la 8 puede seguir con `Kernel.php`,
  `Handler.php` y `RouteServiceProvider` tal como están. Aquí son 4 archivos y
  213 líneas en total; convertirlos es una decisión aparte, no un requisito.

**Verificación de la etapa:** 358 tests + las dos pruebas de humo + login real
desde el APK contra las dos IP públicas.

## 6. Etapa 3 — Filament 2 → 3 y Livewire 2 → 3 (la etapa cara)

Es un solo movimiento porque Filament 3 exige Livewire 3.

### La superficie a migrar, contada

| | |
|---|---:|
| Recursos | 27 |
| Páginas de recurso | 81 |
| Relation managers | 5 |
| Widgets | 6 |
| Páginas propias | 1 |
| Vistas Blade propias del panel | 4 |

### Los cambios mecánicos, con ocurrencias reales

| Cambio | Ocurrencias | Archivos |
|---|---:|---:|
| `Filament\Resources\Form` → `Filament\Forms\Form` | 32 | 32 |
| `Filament\Resources\Table` → `Filament\Tables\Table` | 32 | 32 |
| `Filament\Pages\Actions` → `Filament\Actions` | 70 | 69 |
| `getActions()` → `getHeaderActions()` en páginas | 33 | 32 |
| `BooleanColumn` → `IconColumn::make()->boolean()` | 41 | 22 |
| `->bulkActions([...])` → envuelto en `BulkActionGroup` | 31 | 31 |
| `BadgeColumn` → `TextColumn::make()->badge()` | 32 | 16 |
| `ToggleColumn` (sigue, cambia el sitio) | 16 | 12 |
| `->size('sm')` → enum `TextColumnSize` | 176 | 26 |
| `modalSubheading()` → `modalDescription()` | 8 | 4 |
| `modalButton()` → `modalSubmitActionLabel()` | 1 | 1 |
| `getCards()` → `getStats()` en widgets | 4 | 4 |
| `->relationship('x','y')` → argumentos con nombre | 8 | 7 |

Casi todo es sustitución de texto, pero **`->size(` con 176 usos es el que
duele**: hay que distinguir `TextColumn->size()` de otras llamadas homónimas.

### Los tres cambios que NO son sustituir texto

1. **`config/filament.php` deja de existir como configuración del panel.**
   Filament 3 usa un *panel provider*
   (`app/Providers/Filament/AdminPanelProvider.php`). Ahí se vuelven a declarar
   la marca, el tema, el ancho de contenido (`'full'`, que este proyecto puso a
   propósito), el modo oscuro y el registro de recursos. Es reescritura, no
   renombre.
2. **Los iconos cambian de nombre.** `blade-ui-kit/blade-heroicons` ^1 → ^2, que
   es Heroicons v1 → v2: **96 usos, 42 iconos distintos**. Los renombrados
   incluyen `download` → `arrow-down-tray`, `search` → `magnifying-glass`,
   `collection` → `rectangle-stack`, `office-building` → `building-office`,
   `view-grid` → `squares-2x2`, `user-add` → `user-plus`, `exclamation` →
   `exclamation-triangle`, `location-marker` → `map-pin`, `qrcode` → `qr-code`.
   Un icono inexistente **revienta al renderizar**, y por eso la prueba de humo
   del panel de la Etapa 0 es la que convierte esto en un trabajo de diez
   minutos en vez de una cacería.
3. **Las 4 vistas Blade propias hay que rehacerlas**: la grilla del cuadrante,
   los dos widgets con vista propia y el override de la marca
   (`vendor/filament/components/brand.blade.php`). Las de `vendor/` dependen de
   la estructura interna de Filament 2 y **no** van a existir igual.

### Y `pxlrbt/filament-excel` 1 → 2

Lo exige Filament 3 (`v2.5.0` pide `filament/filament: ^3.0`). Toca
`App\Filament\Tables\Descarga`, que es **un solo archivo** porque la descarga se
centralizó ahí: los 27 listados no se tocan.

### Lo que se gana, concreto

- **Pestañas de verdad en los listados.** `getTabs()` y la clase `Tab` son de
  Filament 3. Hoy «Activos | Inactivos | Todos» está resuelto con un filtro con
  valor por defecto (`App\Filament\Tables\FiltroDeEstado`, en 8 pantallas)
  justamente porque en 2.17 no existen. Es el pendiente que ya está anotado en
  el código.
- `StatsOverviewWidget` acepta título, así que
  `resources/views/filament/widgets/stats-con-titulo.blade.php` —que existe solo
  para eso— desaparece.
- Livewire 3 trae `wire:model` diferido por defecto: menos peticiones por
  tecleo en los formularios largos.

## 7. Etapa 4 — Laravel 10 → 12 y Filament 3 → 4

Ya sobre stack moderno, y por eso barata:

- Laravel 10 → 11 → 12. Aquí sí entra `nesbot/carbon` ^3 y `sanctum` ^4.
- `nwidart/laravel-modules` → 12 (o 13, que pide PHP ^8.3 y ya lo tenemos).
- Filament 3 → 4: los cambios de esta versión son bastante menores que 2 → 3;
  Livewire se queda en 3.
- `pxlrbt/filament-excel` → 3 o 4.

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
| **2** Laravel 8 → 10 | Framework, sanctum, permission, modules | Medio | Sí |
| **3** Filament 2 → 3 + Livewire 3 | 27 recursos, 81 páginas, 96 iconos, 4 vistas | **Alto** | Sí |
| **4** Laravel 12 + Filament 4 | Saltos cortos sobre stack moderno | Bajo | — |

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
