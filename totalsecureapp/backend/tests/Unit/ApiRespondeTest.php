<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\MobileApp\Models\users;
use Tests\TestCase;

/**
 * Prueba de humo: **ninguna** ruta de la API devuelve 500.
 *
 * **Por que existe.** Es la otra mitad de la red de seguridad de la migracion
 * (ver `ROADMAP-MIGRACION.md`). El APK que esta instalado en las tablets habla
 * contra **57 rutas** y, antes de esto, los tests ejercitaban **12**. Recompilar
 * el APK son 21 minutos mas una instalacion por USB en cada dispositivo, asi que
 * el contrato de la API **no se puede romper**: si una subida de framework
 * revienta un endpoint, hay que enterarse en la suite y no cuando un guardia no
 * puede marcar su turno.
 *
 * ### Que comprueba exactamente, y que no
 *
 * Cada ruta se llama **con un token valido** para que el controlador se ejecute
 * de verdad, y se exige que la respuesta **no sea 5xx**. Un 401, 403, 404 o 422
 * son respuestas correctas: significan «te entendi y te digo que no». Un 500 es
 * una clase que no existe, una firma que cambio o una llamada a un metodo
 * inexistente -- exactamente lo que rompe una subida de major.
 *
 * No comprueba que la respuesta sea la **correcta**: eso es trabajo de los tests
 * de cada modulo (`BotonDePanicoTest`, `InventarioOfflineTest`,
 * `OfflineSyncTest`, `RbacTest`…). Esto es la malla gruesa que agarra lo que
 * ninguno de ellos toca.
 *
 * ⚠️ **Va guiado por las rutas registradas**, no por una lista escrita: un
 * endpoint nuevo entra solo.
 */
class ApiRespondeTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private int $insCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insCode = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Local de humo',
            'ins_estado'      => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], 'ins_code');

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'C', 'usu_password' => bcrypt('Prueba2026'),
            'usu_nmbcom' => 'Humo Uno', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'humo@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Administrador: con el rol mas amplio ninguna ruta se salta por falta de
        // permiso, y las que igual dan 403 tambien son respuestas validas.
        $rol = DB::table('roles')->where('name', 'Administrador')->value('id');
        DB::table('user_has_roles')->updateOrInsert(
            ['user_id' => 1, 'role_id' => $rol],
            ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
        );

        DB::table('user_has_gestions')->updateOrInsert(
            ['ug_user_id' => 1, 'ug_finish' => false],
            [
                'ug_ingreso' => now(), 'ug_state' => 1, 'ug_created_user' => 1,
                'ug_created_at' => now(), 'ug_updated_at' => now(),
            ]
        );

        DB::table('user_has_institucion')->updateOrInsert(
            ['ui_usu_id' => 1, 'ui_ins_code' => $this->insCode],
            ['ui_state' => 1]
        );

        $this->token = users::find(1)->createToken('humo')->plainTextToken;
    }

    /**
     * Las rutas registradas con prefijo `api/`.
     *
     * ⚠️ **Esto no puede ser un `dataProvider`.** Un proveedor corre antes de
     * que exista la aplicacion, asi que la fachada `Route` falla con «A facade
     * root has not been set» y `app_path()` con «Call to undefined method
     * Container::path()».
     *
     * La primera version lo esquivaba arrancando una **segunda** aplicacion ahi
     * mismo (`require bootstrap/app.php` + `bootstrap()`). Funcionaba, pero es
     * una trampa: `Facade::setFacadeApplication()` queda apuntando a esa
     * instancia desechable, que lee el `.env` de verdad y no el de
     * `phpunit.xml` -- o sea que pone la base de **produccion** al alcance de
     * los tests. Por eso ahora es **un solo test que recorre las rutas** con la
     * aplicacion que PHPUnit ya arranco.
     *
     * (El desastre que llevo a mirar esto -- corridas con 242 y 57 errores
     * «relation users does not exist» -- **no era esto**: eran procesos de
     * phpunit huerfanos de unas corridas que se habian colgado, vivos y
     * compitiendo por la base de pruebas, cada uno haciendo `migrate:fresh`
     * debajo del otro. Vale anotarlo: si la suite se pone no determinista, lo
     * primero es `ps` y `pg_stat_activity`, no el codigo.)
     *
     * @return array<int,array{0: string, 1: string}>
     */
    private function rutasDeApi(): array
    {
        $rutas = [];

        foreach (app('router')->getRoutes() as $ruta) {
            $uri = $ruta->uri();

            if (!str_starts_with($uri, 'api/')) {
                continue;
            }

            foreach ($ruta->methods() as $metodo) {
                if (in_array($metodo, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $rutas[] = [$metodo, $uri];
            }
        }

        sort($rutas);

        return $rutas;
    }

    public function test_ninguna_ruta_de_la_api_revienta(): void
    {
        $rutas = $this->rutasDeApi();

        // Si esto baja, alguien borro rutas o el recorrido dejo de encontrarlas
        // y el test estaria pasando sin probar nada.
        //
        // Eran 57 y quedaron 55: se borraron `GET /api/user` -- la ruta de
        // ejemplo de Laravel, que devolvia 500 -- y `GET /api/test-cors`. Los
        // encontro esta misma prueba.
        $this->assertGreaterThanOrEqual(55, count($rutas), 'Se perdieron rutas de la API');

        $payload = [
            'ins'         => $this->insCode,
            'ins_code'    => $this->insCode,
            'institucion' => $this->insCode,
        ];

        $reventadas = [];

        foreach ($rutas as list($metodo, $uri)) {
            // Los parametros de ruta se rellenan con 1: si el registro no
            // existe, el controlador debe responder «no encontrado», no
            // explotar.
            $camino = '/' . preg_replace('/\{[^}]+\}/', '1', $uri);

            $respuesta = $this->withHeaders([
                'Authorization' => "Bearer {$this->token}",
                'Accept'        => 'application/json',
            ])->json($metodo, $camino, $payload);

            if ($respuesta->status() >= 500) {
                $reventadas[] = sprintf(
                    "%s %s -> %d
   %s",
                    $metodo,
                    $uri,
                    $respuesta->status(),
                    mb_substr((string) $respuesta->getContent(), 0, 300)
                );
            }
        }

        $this->assertSame(
            [],
            $reventadas,
            count($reventadas) . " ruta(s) devolvieron 5xx:\n\n" . implode("\n\n", $reventadas)
        );
    }

}
