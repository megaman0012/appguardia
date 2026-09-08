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

## 4. Etapa 1 — Desatascar Composer (sin subir nada todavía)

`composer.json` tiene **nueve dependencias clavadas sin `^`**, y algunas son
bloqueos duros para Laravel 10:

| Paquete | Pin actual | Problema |
|---|---|---|
| `laravel/framework` | `8.75` | Sin `^`: ni los parches de la 8.83 entran |
| `monolog/monolog` | `2.9.2` | Laravel 10 exige monolog **^3** |
| `symfony/mime` | `5.4.*` | Laravel 10 exige **^6.2** |
| `symfony/mailer` | `6.0` | Idem, clavado en la menor |
| `guzzlehttp/guzzle` | `7.15` | Debería ser `^7.8` |
| `nesbot/carbon` | `2.73.0` | Laravel 11+ va con **^3** |
| `league/uri-interfaces` | `7.0` | Transitiva, no debería estar aquí |
| `symfony/html-sanitizer` | `7.4.12` | Transitiva |
| `barryvdh/laravel-debugbar` | `v3.6.8` | Además está en `require`, no en `require-dev` |

Y dos paquetes que se pueden **quitar**, lo que ahorra trabajo en todas las
etapas siguientes:

- **`yajra/laravel-datatables-oracle` — 0 usos en el código.** Solo está
  registrado en `config/app.php` y `config/datatables.php`. Su v9 no pasa de
  Laravel 9, así que hoy es un bloqueo puro sin nada a cambio.
- **`facade/ignition`** hay que cambiarlo por `spatie/laravel-ignition` (el
  paquete se renombró en Laravel 9). No es opcional.

`barryvdh/laravel-debugbar` a `require-dev`: es la barra que ya se apagó por
configuración, pero seguirla instalando en producción no tiene sentido.

**Resultado de la etapa:** el proyecto sigue en Laravel 8 y funcionando, pero
Composer deja de tener nudos artificiales. Verificable con los 358 tests + las
dos pruebas de humo.

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
| **1** Desatascar Composer | 9 pins, quitar 1 paquete, cambiar ignition | Bajo | Sí |
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
