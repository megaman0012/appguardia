<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Acceso;
use Modules\Administracion\Models\Alertas;
use Modules\Administracion\Models\Novedad;
use Modules\Administracion\Models\ronda_cabecera;
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

        ronda_cabecera::create([
            'rc_usu_code'     => 2,
            'rc_ins_code'     => $insCode,
            'rc_fecha_inicio' => now(),
            'rc_estado'       => 1,
            'rc_estado_ronda' => 'Finalizada',
        ]);

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
        return $this->getJson('/api/portal/' . $uri, $headers);
    }

    private const LISTADOS = ['biometria', 'rondas', 'novedades', 'accesos', 'alertas'];

    // ── Autenticacion y permisos ──

    public function test_sin_token_responde_401(): void
    {
        foreach (self::LISTADOS as $uri) {
            $this->getPortal($uri, null)->assertStatus(401);
        }
        $this->getPortal('instituciones', null)->assertStatus(401);
        $this->getPortal('resumen', null)->assertStatus(401);
    }

    public function test_un_token_de_la_app_movil_no_puede_leer_el_portal(): void
    {
        // Un Vigilante no tiene permisos portal.*, aunque este autenticado.
        $token = $this->tokenCon('Vigilante');

        foreach (self::LISTADOS as $uri) {
            $this->getPortal($uri, $token)->assertStatus(403);
        }
        $this->getPortal('resumen', $token)->assertStatus(403);
    }

    public function test_cliente_sin_instituciones_asignadas_responde_403(): void
    {
        // Usuario 2 tiene rol Cliente pero ninguna institucion vinculada.
        $token = $this->tokenCon('Cliente', 2);

        $this->getPortal('instituciones', $token)->assertStatus(403);
        $this->getPortal('resumen', $token)->assertStatus(403);
        $this->getPortal('novedades', $token)->assertStatus(403);
    }

    // ── Alcance por institucion ──

    public function test_cliente_ve_solo_sus_instituciones(): void
    {
        $token = $this->tokenCon('Cliente');

        $r = $this->getPortal('instituciones', $token);

        $r->assertStatus(200);
        $r->assertJsonCount(1, 'datos');
        $r->assertJsonPath('datos.0.ins_code', $this->insPropia);
    }

    public function test_ningun_listado_devuelve_datos_de_otra_institucion(): void
    {
        $token = $this->tokenCon('Cliente');

        foreach (self::LISTADOS as $uri) {
            $r = $this->getPortal($uri, $token);
            $r->assertStatus(200);

            $datos = $r->json('datos');
            $this->assertCount(1, $datos, "El listado {$uri} deberia traer solo 1 registro");

            foreach ($datos as $fila) {
                $this->assertSame(
                    $this->insPropia,
                    $fila['ins_code'],
                    "El listado {$uri} filtro datos de otra institucion"
                );
            }
        }
    }

    public function test_pedir_una_institucion_ajena_responde_403_y_no_vacio(): void
    {
        $token = $this->tokenCon('Cliente');

        // 403 y no una lista vacia: una respuesta vacia dejaria sondear que
        // codigos de institucion existen.
        foreach (self::LISTADOS as $uri) {
            $this->getPortal($uri . '?ins_code=' . $this->insAjena, $token)->assertStatus(403);
        }
        $this->getPortal('resumen?ins_code=' . $this->insAjena, $token)->assertStatus(403);
    }

    public function test_pedir_la_institucion_propia_funciona(): void
    {
        $token = $this->tokenCon('Cliente');

        $r = $this->getPortal('novedades?ins_code=' . $this->insPropia, $token);

        $r->assertStatus(200);
        $r->assertJsonCount(1, 'datos');
        $r->assertJsonPath('datos.0.observacion', 'Novedad de ' . $this->insPropia);
    }

    // ── Filtros ──

    public function test_el_rango_de_fechas_excluye_lo_que_queda_fuera(): void
    {
        $token = $this->tokenCon('Cliente');

        $r = $this->getPortal('novedades?desde=2020-01-01&hasta=2020-01-31', $token);

        $r->assertStatus(200);
        $r->assertJsonCount(0, 'datos');
    }

    public function test_por_pagina_no_supera_el_tope(): void
    {
        $token = $this->tokenCon('Cliente');

        $r = $this->getPortal('novedades?por_pagina=99999', $token);

        $r->assertStatus(200);
        $r->assertJsonPath('paginacion.por_pagina', 200);
    }

    public function test_un_rango_invertido_no_devuelve_vacio_en_silencio(): void
    {
        $token = $this->tokenCon('Cliente');

        // desde > hasta: se normaliza en vez de producir un vacio inexplicable.
        $r = $this->getPortal('novedades?desde=' . now()->addDay()->format('Y-m-d')
            . '&hasta=' . now()->subDay()->format('Y-m-d'), $token);

        $r->assertStatus(200);
        $r->assertJsonCount(1, 'datos');
    }

    // ── Resumen ──

    public function test_resumen_cuenta_solo_la_institucion_propia(): void
    {
        $token = $this->tokenCon('Cliente');

        $r = $this->getPortal('resumen', $token);

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
