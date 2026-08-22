<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Administracion\Models\Acceso;
use Modules\Administracion\Models\Alertas;
use Modules\Administracion\Models\Novedad;
use Modules\Administracion\Models\ronda_cabecera;
use Modules\Administracion\Models\ronda_detalle;
use Modules\Administracion\Models\user_has_biometria;
use Modules\MobileApp\Models\users;
use Tests\TestCase;

/**
 * API del portal cliente (Fase 8).
 *
 * El punto que importa: un cliente ve solo las instituciones que tiene
 * asignadas. Cada listado se prueba contra datos existentes de OTRA institucion.
 */
class PortalApiTest extends TestCase
{
    use RefreshDatabase;

    /** Institucion del cliente. */
    private int $insPropia;
    /** Institucion de otro cliente: nada de aqui debe filtrarse. */
    private int $insAjena;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearUsuario(1, '1111111111', 'Cliente Uno');
        $this->crearUsuario(2, '2222222222', 'Guardia Dos');

        $this->insPropia = $this->crearInstitucion('Institucion Propia');
        $this->insAjena  = $this->crearInstitucion('Institucion Ajena');

        // El cliente (usuario 1) solo esta vinculado a su institucion.
        DB::table('user_has_institucion')->insert([
            'ui_usu_id' => 1, 'ui_ins_code' => $this->insPropia, 'ui_state' => 1,
        ]);

        $this->sembrarDatos($this->insPropia);
        $this->sembrarDatos($this->insAjena);
    }

    // ── Helpers de armado ──

    private function crearUsuario(int $id, string $cedula, string $nombre): void
    {
        DB::table('users')->updateOrInsert(
            ['id' => $id],
            [
                'usu_cedula'   => $cedula,
                'usu_tipdoc'   => 'CC',
                'usu_password' => bcrypt('testing'),
                'usu_nmbcom'   => $nombre,
                'usu_ape1'     => 'Test',
                'usu_ape2'     => 'Test',
                'usu_nmb1'     => 'Test',
                'usu_nmb2'     => 'Test',
                'usu_email'    => $cedula . '@example.com',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );
    }

    private function crearInstitucion(string $descripcion): int
    {
        return DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => $descripcion,
            'ins_estado'      => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], 'ins_code');
    }

    /** Un registro de cada tipo por institucion. */
    private function sembrarDatos(int $insCode): void
    {
        user_has_biometria::create([
            'bio_user_id'    => 2,
            'bio_ins_code'   => $insCode,
            'bio_image_name' => 'foto.jpg',
            'bio_lat'        => '-2.18',
            'bio_lng'        => '-79.88',
            'bio_state'      => true,
        ]);

        $ronda = ronda_cabecera::create([
            'rc_usu_code'     => 2,
            'rc_ins_code'     => $insCode,
            'rc_fecha_inicio' => now(),
            'rc_estado'       => 1,
            'rc_estado_ronda' => 'Finalizada',
        ]);

        // Dos puntos recorridos, para que puntos_recorridos se verifique contra un
        // valor distinto de cero (un conteo roto tambien daria 0).
        foreach ([1, 2] as $n) {
            ronda_detalle::create([
                'rd_usu_id'     => 2,
                'rd_ins_code'   => $insCode,
                'rd_rc_id'      => $ronda->rc_id,
                'rd_observacion' => 'Punto ' . $n,
                'rd_fecha_hora' => now(),
                'rd_estado'     => 1,
                'rd_lat'        => '-2.18',
                'rd_lng'        => '-79.88',
            ]);
        }

        Novedad::create([
            'nv_usu_id'      => 2,
            'nv_ins_code'    => $insCode,
            'nv_observacion' => 'Novedad de ' . $insCode,
            'nv_fecha_hora'  => now(),
            'nv_estado'      => 1,
            'nv_lat'         => '-2.18',
            'nv_lng'         => '-79.88',
        ]);

        Acceso::create([
            'ac_usu_id'        => 2,
            'ac_ins_code'      => $insCode,
            'ac_tipo'          => 'peatonal',
            'ac_is_entrada'    => 1,
            'ac_lat'           => '-2.18',
            'ac_lng'           => '-79.88',
            'ac_estado_acceso' => Acceso::ESTADO_EN_CURSO,
            'ac_estado'        => true,
        ]);

        Alertas::factory()->create([
            'al_ins_code' => $insCode,
            'al_usu_id'   => 2,
        ]);
    }

    private function tokenCon(string $rol, int $userId = 1): string
    {
        $roleId = DB::table('roles')->where('name', $rol)->value('id');
        $this->assertNotNull($roleId, "El rol {$rol} deberia existir por migracion");

        DB::table('user_has_roles')->updateOrInsert(
            ['user_id' => $userId, 'role_id' => $roleId],
            ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
        );

        return users::find($userId)->createToken('portal-test')->plainTextToken;
    }

    private function getPortal(string $uri, ?string $token)
    {
        $headers = $token ? ['Authorization' => "Bearer {$token}"] : [];
        return $this->getJson('/' . ltrim($uri, '/'), $headers);
    }

    /**
     * Rutas GET del portal leidas del router, no escritas a mano.
     *
     * Asi un endpoint nuevo queda cubierto por los tests de aislamiento sin que
     * nadie tenga que acordarse de agregarlo aqui: si no filtra, falla.
     *
     * @return string[]
     */
    private function rutasGetDelPortal(): array
    {
        $rutas = [];

        foreach (Route::getRoutes() as $ruta) {
            if (!str_starts_with($ruta->uri(), 'api/portal/')) {
                continue;
            }
            if (!in_array('GET', $ruta->methods(), true)) {
                continue;
            }
            // Las rutas con parametros necesitarian valores de ejemplo; hoy no hay.
            if (str_contains($ruta->uri(), '{')) {
                continue;
            }
            $rutas[$ruta->uri()] = true;
        }

        return array_keys($rutas);
    }

    // ── Autenticacion y permisos ──

    public function test_las_rutas_del_portal_se_descubren(): void
    {
        // Guarda contra un test que pase por vacio: si el descubrimiento se rompe,
        // los tests de aislamiento no verificarian nada y seguirian en verde.
        $rutas = $this->rutasGetDelPortal();

        $this->assertGreaterThanOrEqual(7, count($rutas), 'Se esperaban al menos los 7 endpoints del portal');
    }

    public function test_sin_token_responde_401(): void
    {
        foreach ($this->rutasGetDelPortal() as $uri) {
            $this->getPortal($uri, null)->assertStatus(401, "{$uri} deberia exigir token");
        }
    }

    public function test_un_token_de_la_app_movil_no_puede_leer_el_portal(): void
    {
        // Un Vigilante no tiene permisos portal.*, aunque este autenticado.
        $token = $this->tokenCon('Vigilante');

        foreach ($this->rutasGetDelPortal() as $uri) {
            $this->getPortal($uri, $token)
                ->assertStatus(403, "{$uri} deberia rechazar un token sin permisos portal.*");
        }
    }

    public function test_cliente_sin_instituciones_asignadas_responde_403(): void
    {
        // Usuario 2 tiene rol Cliente pero ninguna institucion vinculada.
        $token = $this->tokenCon('Cliente', 2);

        $this->getPortal('api/portal/instituciones', $token)->assertStatus(403);
        $this->getPortal('api/portal/resumen', $token)->assertStatus(403);
        $this->getPortal('api/portal/novedades', $token)->assertStatus(403);
    }

    // ── Alcance por institucion ──

    public function test_cliente_ve_solo_sus_instituciones(): void
    {
        $token = $this->tokenCon('Cliente');

        $r = $this->getPortal('api/portal/instituciones', $token);

        $r->assertStatus(200);
        $r->assertJsonCount(1, 'datos');
        $r->assertJsonPath('datos.0.ins_code', $this->insPropia);
    }

    public function test_ningun_endpoint_devuelve_datos_de_otra_institucion(): void
    {
        $token = $this->tokenCon('Cliente');

        foreach ($this->rutasGetDelPortal() as $uri) {
            $r = $this->getPortal($uri, $token);
            $r->assertStatus(200, "{$uri} deberia responder 200 para un cliente valido");

            $cuerpo = $r->json();
            $verificado = false;

            // Filas con ins_code: todas deben ser de la institucion propia.
            foreach ($cuerpo['datos'] ?? [] as $fila) {
                if (array_key_exists('ins_code', $fila)) {
                    $this->assertSame(
                        $this->insPropia,
                        $fila['ins_code'],
                        "{$uri} devolvio datos de otra institucion"
                    );
                    $verificado = true;
                }
            }

            // Endpoints que declaran el alcance en vez de filas (resumen).
            if (array_key_exists('instituciones', $cuerpo)) {
                $this->assertSame(
                    [$this->insPropia],
                    $cuerpo['instituciones'],
                    "{$uri} declaro un alcance mas amplio que la institucion propia"
                );
                $verificado = true;
            }

            // Un endpoint cuya respuesta no expone ni ins_code ni instituciones no
            // se puede auditar aqui: falla a proposito para que quien lo agregue
            // lo haga verificable, en vez de quedar fuera del test en silencio.
            $this->assertTrue(
                $verificado,
                "{$uri} no expone ins_code en sus filas ni el arreglo instituciones, "
                . "asi que su aislamiento no es verificable"
            );
        }
    }

    public function test_pedir_una_institucion_ajena_responde_403_y_no_vacio(): void
    {
        $token = $this->tokenCon('Cliente');

        // 403 y no una lista vacia: una respuesta vacia dejaria sondear que
        // codigos de institucion existen.
        foreach ($this->rutasGetDelPortal() as $uri) {
            $this->getPortal($uri . '?ins_code=' . $this->insAjena, $token)
                ->assertStatus(403, "{$uri} deberia rechazar una institucion ajena");
        }
    }

    public function test_rondas_cuenta_los_puntos_de_su_propia_ronda(): void
    {
        $token = $this->tokenCon('Cliente');

        $r = $this->getPortal('api/portal/rondas', $token);

        $r->assertStatus(200);
        $r->assertJsonCount(1, 'datos');
        // Dos puntos sembrados en la ronda propia; los de la ronda ajena no cuentan.
        $r->assertJsonPath('datos.0.puntos_recorridos', 2);
    }

    public function test_pedir_la_institucion_propia_funciona(): void
    {
        $token = $this->tokenCon('Cliente');

        $r = $this->getPortal('api/portal/novedades?ins_code=' . $this->insPropia, $token);

        $r->assertStatus(200);
        $r->assertJsonCount(1, 'datos');
        $r->assertJsonPath('datos.0.observacion', 'Novedad de ' . $this->insPropia);
    }

    // ── Filtros ──

    public function test_el_rango_de_fechas_excluye_lo_que_queda_fuera(): void
    {
        $token = $this->tokenCon('Cliente');

        $r = $this->getPortal('api/portal/novedades?desde=2020-01-01&hasta=2020-01-31', $token);

        $r->assertStatus(200);
        $r->assertJsonCount(0, 'datos');
    }

    public function test_por_pagina_no_supera_el_tope(): void
    {
        $token = $this->tokenCon('Cliente');

        $r = $this->getPortal('api/portal/novedades?por_pagina=99999', $token);

        $r->assertStatus(200);
        $r->assertJsonPath('paginacion.por_pagina', 200);
    }

    public function test_un_rango_invertido_no_devuelve_vacio_en_silencio(): void
    {
        $token = $this->tokenCon('Cliente');

        // desde > hasta: se normaliza en vez de producir un vacio inexplicable.
        $r = $this->getPortal('api/portal/novedades?desde=' . now()->addDay()->format('Y-m-d')
            . '&hasta=' . now()->subDay()->format('Y-m-d'), $token);

        $r->assertStatus(200);
        $r->assertJsonCount(1, 'datos');
    }

    // ── Resumen ──

    public function test_resumen_cuenta_solo_la_institucion_propia(): void
    {
        $token = $this->tokenCon('Cliente');

        $r = $this->getPortal('api/portal/resumen', $token);

        $r->assertStatus(200);
        // Hay un registro de cada tipo por institucion; solo debe contar el propio.
        $r->assertJsonPath('totales.marcajes', 1);
        $r->assertJsonPath('totales.rondas', 1);
        $r->assertJsonPath('totales.novedades', 1);
        $r->assertJsonPath('totales.accesos', 1);
        $r->assertJsonPath('totales.accesos_en_curso', 1);
        $r->assertJsonPath('totales.alertas', 1);
        $r->assertJsonPath('totales.alertas_pendientes', 1);
        $r->assertJsonPath('instituciones', [$this->insPropia]);
    }

    // ── El scope del trait ──

    public function test_for_institutions_con_arreglo_vacio_no_devuelve_todo(): void
    {
        // "Sin instituciones" tiene que significar "sin datos", nunca "todos".
        $this->assertSame(0, Novedad::forInstitutions([])->count());
        $this->assertSame(2, Novedad::count());
    }

    public function test_la_api_del_portal_es_solo_lectura(): void
    {
        $token = $this->tokenCon('Cliente');

        // No hay verbos de escritura declarados en el modulo.
        foreach (['novedades', 'accesos', 'alertas'] as $uri) {
            $this->postJson('/api/portal/' . $uri, [], ['Authorization' => "Bearer {$token}"])
                ->assertStatus(405);
        }
    }
}
