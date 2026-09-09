<?php

namespace Tests\Unit;

use App\Filament\Pages\ListadoBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Session;
use Tests\TestCase;

/**
 * Prueba de humo: **todas** las pantallas de listado del panel se dibujan.
 *
 * **Por que existe.** Es la red de seguridad de la migracion a Laravel y
 * Filament actuales (ver `ROADMAP-MIGRACION.md`). Hay 27 recursos y, antes de
 * esto, solo 10 pantallas tenian algun test que las dibujara: **17 no tenian
 * ninguno**. Una subida de Filament rompe sobre todo el **renderizado** --un
 * icono que cambio de nombre, una columna que ya no existe, un `getActions()`
 * que ahora se llama distinto--, y sin esto de los 17 se enteraba el usuario, no
 * la suite.
 *
 * El caso mas claro es el de los iconos: pasar a Heroicons v2 renombra varios de
 * los 42 que usa el proyecto, y un icono inexistente **revienta al renderizar**.
 * Con este test, encontrarlos es correr la suite; sin el, es abrir 27 pantallas
 * a mano.
 *
 * ⚠️ **Va guiado por `glob`, no por una lista escrita.** Un recurso nuevo entra
 * solo. Una lista a mano se desactualiza en el primer recurso que alguien
 * agregue, y entonces el test da una falsa sensacion de cobertura.
 */
class PanelSeDibujaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'C', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Admin', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'a@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // El panel se dibuja segun el perfil activo: como Administrador se ven
        // todas las pantallas y ninguna queda fuera por alcance.
        Session::put('usuID', 1);
        Session::put('usuPF', 'Administrador');
    }

    /**
     * ⚠️ El `dataProvider` de PHPUnit corre **antes** de arrancar la
     * aplicacion, asi que aca no se puede usar `app_path()`: revienta con «Call
     * to undefined method Container::path()». La ruta va relativa a este
     * archivo.
     */
    private const CARPETA_RECURSOS = __DIR__ . '/../../app/Filament/Resources';

    /**
     * La clase de la pagina «index» de un recurso.
     *
     * ⚠️ **La forma cambio entre versiones de Filament**, y hay que aguantar
     * las dos: en Filament 2 `getPages()` devolvia arreglos
     * `['class' => ..., 'route' => ...]`; en Filament 3 devuelve objetos
     * `PageRegistration` con `getPage()`. Cuando subimos a la 3, el proveedor
     * de datos empezo a devolver **cero recursos** y el test pasaba a verde sin
     * probar nada -- lo unico que lo delato fue el guardia que cuenta 27.
     */
    private static function claseDeIndice(string $recurso): ?string
    {
        $indice = $recurso::getPages()['index'] ?? null;

        if ($indice === null) {
            return null;
        }

        if (is_array($indice)) {
            return $indice['class'] ?? null;
        }

        if (is_object($indice) && method_exists($indice, 'getPage')) {
            return $indice->getPage();
        }

        return null;
    }

    /** @return array<string,array{0: string, 1: string}> */
    public static function listados(): array
    {
        $casos = [];

        foreach (glob(self::CARPETA_RECURSOS . '/*Resource.php') as $ruta) {
            $recurso = 'App\\Filament\\Resources\\' . basename($ruta, '.php');

            if (!class_exists($recurso)) {
                continue;
            }

            $pagina = self::claseDeIndice($recurso);

            if ($pagina === null || !class_exists($pagina)) {
                continue;
            }

            $casos[class_basename($recurso)] = [$pagina, class_basename($recurso)];
        }

        return $casos;
    }

    /**
     * @dataProvider listados
     */
    public function test_el_listado_se_dibuja(string $pagina, string $recurso): void
    {
        Livewire::test($pagina)->assertSuccessful();
    }

    public function test_estan_todos_los_recursos(): void
    {
        // Si este numero baja, alguien dejo un recurso sin pagina de listado y el
        // dataProvider lo salteo en silencio.
        $enDisco = count(glob(self::CARPETA_RECURSOS . '/*Resource.php'));

        $this->assertSame($enDisco, count(self::listados()));
        $this->assertGreaterThanOrEqual(27, $enDisco);
    }

    public function test_todos_los_listados_heredan_de_la_base(): void
    {
        // De `ListadoBase` cuelgan las migas de pan y el boton de descarga. Un
        // listado que no herede se queda sin las dos cosas y nadie lo nota.
        $sueltos = [];

        foreach (self::listados() as $recurso => list($pagina)) {
            if (!is_subclass_of($pagina, ListadoBase::class)) {
                $sueltos[] = $recurso;
            }
        }

        $this->assertSame([], $sueltos);
    }

    public function test_los_widgets_del_tablero_se_dibujan(): void
    {
        // Los widgets se rompen distinto que los listados: `getCards()` pasa a
        // llamarse `getStats()` en Filament 3.
        $rotos = [];

        foreach (glob(app_path('Filament/Widgets/*.php')) as $ruta) {
            $widget = 'App\\Filament\\Widgets\\' . basename($ruta, '.php');

            if (!class_exists($widget) || (new \ReflectionClass($widget))->isAbstract()) {
                continue;
            }

            try {
                Livewire::test($widget)->assertSuccessful();
            } catch (\Throwable $e) {
                $rotos[] = class_basename($widget) . ': ' . $e->getMessage();
            }
        }

        $this->assertSame([], $rotos);
    }
}
