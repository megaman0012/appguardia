<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Configuración del panel de administración.
 *
 * **Reemplaza a `config/filament.php`**, que en Filament 3 ya no existe: todo lo
 * que antes eran claves de un arreglo ahora son llamadas encadenadas sobre el
 * `Panel`. No es un renombre, es una reescritura, y por eso conviene tener a
 * mano de dónde salió cada cosa:
 *
 * | Antes, en `config/filament.php` | Ahora |
 * |---|---|
 * | `path` | `->path()` |
 * | `brand` | `->brandName()` |
 * | `auth.guard` | `->authGuard()` |
 * | `auth.pages.login` | `->login()` |
 * | `layout.max_content_width` | `->maxContentWidth()` |
 * | `layout.sidebar.groups.are_collapsible` | `->collapsibleNavigationGroups()` |
 * | `layout.sidebar.is_collapsible_on_desktop` | `->sidebarCollapsibleOnDesktop()` |
 * | `pages`/`resources`/`widgets` + `namespace` | `->discoverX(in:, for:)` |
 * | `default_filesystem_disk` | `->defaultFilesystemDisk()` |
 * | `google_fonts` | `->font()` |
 * | `middleware.base` / `middleware.auth` | `->middleware()` / `->authMiddleware()` |
 *
 * Y tres cosas que vivían en `AppServiceProvider::boot()` con la API vieja
 * —`Filament::registerNavigationGroups()`, `registerUserMenuItems()` y
 * `registerStyles()` dentro de `Filament::serving()`— se mudaron acá. Esa era la
 * causa del primer 500 tras instalar: `registerNavigationGroups()` no existe en
 * Filament 3, y como fallaba en el `boot()` de un proveedor **se caía la
 * aplicación entera, incluida la API que usan las tablets**.
 *
 * `user_model` desapareció: Filament 3 toma el modelo del *provider* del guard,
 * y `config/auth.php` ya apunta a `Modules\Acceso\Models\users`.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')

            /*
             * El tema propio. Ver `resources/css/filament/admin/theme.css`.
             *
             * ⚠️ **El CSS compilado (`public/build/`) va versionado.** Aca el
             * arbol de trabajo *es* produccion y desplegar es `git pull`. Si
             * esto dependiera de que `npm run build` corra en el servidor, un
             * fallo de compilacion no dejaria el panel «sin estilos»: Filament
             * busca el manifiesto de Vite y, si no esta, **revienta con un 500
             * en todas las paginas**. Versionando el resultado, `npm` solo hace
             * falta para cambiar el tema, no para servirlo.
             *
             * Al tocar el CSS: `npm run build` y commitear `public/build/`.
             * `ElTemaEstaCompiladoTest` comprueba que el manifiesto exista.
             */
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->path(env('FILAMENT_PATH', 'admin'))
            ->homeUrl('/')
            ->brandName(env('APP_NAMES', 'Total Secure'))

            /*
             * El logo, con el metodo de la libreria.
             *
             * ⚠️ En Filament 2 esto se hacia sobrescribiendo la vista
             * `vendor/filament/components/brand.blade.php`. Esa vista **no
             * existe en la 3**, asi que el override quedaba inerte: el panel
             * salia con el nombre y sin el escudo, y no avisaba de nada. El
             * archivo se borro.
             */
            ->brandLogo(fn (): string => asset('images/logo.png'))
            ->brandLogoHeight('2rem')

            /*
             * El icono de la pestaña del navegador.
             *
             * No estaba declarado, asi que Filament no ponia ninguno y el
             * navegador caia en `/favicon.ico` de la raiz -- que estaba **vacio,
             * 0 bytes** --, dejando a la vista el icono guardado de una version
             * anterior del sitio. Por eso en la pestaña seguia apareciendo el
             * logo viejo aunque el de la cabecera ya fuera el nuevo.
             */
            ->favicon(fn (): string => asset('images/favicon.ico'))
            /*
             * ⚠️ **No se declara `->login()` a proposito.** Este panel nunca uso
             * la pantalla de ingreso de Filament: `routes/web.php` tiene una
             * ruta manual en `/admin/login` que redirige a `/acceso/login`, la
             * del modulo Acceso, donde se entra con cedula y se elige perfil.
             *
             * En Filament 2 habia una clase `App\Http\Livewire\Auth\Login`
             * registrada en el config para eso, pero la ruta manual la tapaba:
             * era **codigo inalcanzable**, y se borro al migrar.
             */
            ->authGuard(env('FILAMENT_AUTH_GUARD', 'web'))

            // 'full' = sin ancho maximo. Con el tope de 7xl que trae Filament,
            // en un monitor ancho el listado queda como un recuadro con espacio
            // vacio a los lados, y estas tablas tienen muchas columnas.
            ->maxContentWidth(Width::Full) // Filament 4: el enum `MaxWidth` se llama `Width`

            ->sidebarCollapsibleOnDesktop(false)
            ->collapsibleNavigationGroups(true)

            /*
             * Orden de los grupos del menu lateral.
             *
             * Sin esto, Filament los ordena por el `navigationSort` mas bajo de
             * sus items, y como cada grupo empieza en 1 el orden queda
             * arbitrario: «Inventario» aparecia arriba de «Centros de
             * operacion».
             *
             * El criterio es frecuencia de uso, no jerarquia de datos: arriba lo
             * que se mira todos los dias y abajo lo que se configura una vez.
             */
            ->navigationGroups([
                'Operación',            // turnos, cuadrantes, coberturas: el dia a dia
                'Reportería',           // lo que el guardia registro en campo
                'Inventario',
                'Centros de operación', // clientes, locales y puestos: se cargan y se dejan
                'Ubicación geográfica', // catalogo
                'Configuración',        // usuarios y permisos
            ])

            ->userMenuItems([
                MenuItem::make()
                    ->label('Seleccionar Perfil')
                    ->url(fn (): string => route('acceso.perfil'))
                    ->icon('heroicon-o-link'),
            ])

            /*
             * La campanita del panel.
             *
             * Es «la web» donde el usuario espera ver una alerta de panico. Sin
             * esto, el aviso de una emergencia no tenia donde aparecer: el unico
             * rastro era el contador del tablero, que solo cambia si alguien
             * entra y refresca.
             *
             * El sondeo es corto a proposito. El valor por defecto de Filament
             * son 30 s, y en una emergencia medio minuto de espera es mucho;
             * son usuarios internos y pocos, asi que el costo de consultar mas
             * seguido es bajo.
             */
            /*
             * La alarma de emergencias, en TODAS las paginas.
             *
             * ⚠️ El widget del tablero solo se monta en el tablero, asi que una
             * emergencia que entraba mientras alguien trabajaba en Usuarios o en
             * Turnos **no sonaba**: no habia nada montado que la detectara. Con
             * el render hook, el componente viaja con el panel entero.
             */
            /*
             * La logica de audio de la alarma, compartida por el widget del
             * tablero y el componente global. Iba copiada en los dos y por eso
             * el mismo fallo volvio cuatro veces. En HEAD_END para que la
             * funcion exista antes de que Alpine monte cualquier `x-data`.
             */
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn (): \Illuminate\Contracts\View\View => view('partials.alarma-de-emergencia-js'),
            )

            ->renderHook(
                \Filament\View\PanelsRenderHook::BODY_END,
                /*
                 * Se monta por CLASE y no por alias: este proyecto viene de
                 * Laravel 8 y no tiene activo el autodescubrimiento de
                 * componentes de Livewire en `App\Livewire`, asi que
                 * `@livewire('aviso-de-emergencia')` fallaba con
                 * «Unable to find component» y **tumbaba todas las paginas del
                 * panel**.
                 */
                fn (): string => \Livewire\Livewire::mount(
                    \App\Livewire\AvisoDeEmergencia::class
                ),
            )

            ->databaseNotifications()
            ->databaseNotificationsPolling('10s')

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                /*
                 * `FilamentInfoWidget` NO va: es el panel promocional de
                 * Filament, con su version y enlaces a filamentphp.com y a
                 * GitHub. Venia registrado desde el `config/filament.php` de la
                 * version 2. Es un panel que ven los clientes; por el mismo
                 * criterio con el que se apago el logo de Filament del pie,
                 * tampoco va su tarjeta en el tablero.
                 */
            ])

            /*
             * `default_filesystem_disk` **no tiene equivalente en el Panel** de
             * Filament 3: sigue siendo la clave `filament.default_filesystem_disk`
             * de `config/filament.php`, que es el unico resto de ese archivo que
             * la version 3 conserva. Se dejo el archivo con esa sola clave.
             */
            ->font('DM Sans')

            /*
             * No se registra `public/css/filament-styles.css`: **el archivo
             * esta vacio, 0 bytes**. En Filament 2 se cargaba con
             * `Filament::registerStyles()` dentro de `Filament::serving()`, o
             * sea que el panel venia pidiendo una hoja sin contenido en cada
             * carga. Si alguna vez hay estilos propios, van con
             * `->assets([Css::make('total-secure', asset(...))])`.
             */

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            /*
             * ⚠️ **El `Authenticate` propio, no el de Filament.**
             *
             * `App\Http\Middleware\Authenticate::redirectTo()` manda a
             * `acceso.login`, que es donde de verdad se entra. El de Filament
             * (`Filament\Http\Middleware\Authenticate`) intenta resolver la
             * pantalla de ingreso **del panel**, y como este panel no declara
             * ninguna, la excepcion terminaba en el manejador por defecto de
             * Laravel pidiendo `route('login')`: cualquier pagina del panel
             * devolvia **500 «Route [login] not defined»**.
             *
             * En Filament 2 esto ya era asi: `config/filament.php` traia
             * `'auth' => ['middleware' => ['auth']]`, o sea el alias del
             * middleware propio.
             */
            ->authMiddleware([
                \App\Http\Middleware\Authenticate::class,
            ]);
    }
}
