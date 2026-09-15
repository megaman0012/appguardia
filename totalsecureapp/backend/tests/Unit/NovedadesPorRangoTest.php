<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Novedad;
use Modules\MobileApp\Models\users;
use Tests\TestCase;

/**
 * Las novedades del puesto, con filtro de dias.
 *
 * ⚠️ El endpoint devolvia **un solo dia y solo lo propio**: al recibir el puesto,
 * el guardia entrante no tenia forma de leer lo que habia pasado en el turno
 * anterior, que es justamente para lo que sirve una bitacora.
 *
 * Los dos parametros nuevos son opcionales a proposito: el APK ya instalado no
 * los manda y tiene que seguir funcionando igual que antes.
 */
class NovedadesPorRangoTest extends TestCase
{
    use RefreshDatabase;

    private int $insCode;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insCode = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Garita', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        $this->crearUsuario(1, 'Guardia Uno', 'Vigilante');
        $this->crearUsuario(2, 'Guardia Dos', 'Vigilante');

        $this->token = users::find(1)->createToken('novedades-test')->plainTextToken;

        // Del guardia 1: una de hoy y una de hace tres dias.
        $this->crearNovedad(1, 'Portón abierto hoy', now());
        $this->crearNovedad(1, 'Luz quemada hace tres días', now()->subDays(3));
        // Del guardia 2, de hoy: la del companero de turno.
        $this->crearNovedad(2, 'Novedad del compañero', now());
    }

    private function crearUsuario(int $id, string $nombre, string $rol): void
    {
        DB::table('users')->updateOrInsert(['id' => $id], [
            'usu_cedula' => str_repeat((string) $id, 10), 'usu_tipdoc' => 'CC',
            'usu_password' => bcrypt('x'), 'usu_nmbcom' => $nombre,
            'usu_ape1' => 'T', 'usu_ape2' => 'T', 'usu_nmb1' => 'T', 'usu_nmb2' => 'T',
            'usu_email' => "g{$id}@e.com", 'usu_state' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $rolId = DB::table('roles')->where('name', $rol)->value('id');
        DB::table('user_has_roles')->updateOrInsert(
            ['user_id' => $id, 'role_id' => $rolId],
            ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
        );
        DB::table('user_has_institucion')->updateOrInsert(
            ['ui_usu_id' => $id, 'ui_ins_code' => $this->insCode],
            ['ui_state' => 1, 'ui_created_at' => now(), 'ui_updated_at' => now()]
        );
    }

    private function crearNovedad(int $usuId, string $texto, $fecha): void
    {
        Novedad::create([
            'nv_usu_id' => $usuId, 'nv_ins_code' => $this->insCode,
            'nv_observacion' => $texto, 'nv_fecha_hora' => $fecha, 'nv_estado' => 1,
        ]);
    }

    /** @param array<string,mixed> $extra */
    private function listar(array $extra = []): array
    {
        $r = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/novedad_listbydate', array_merge([
                'date' => now()->format('Y-m-d'),
                'ins_code' => $this->insCode,
            ], $extra));

        return array_column($r->json('nvNovedad') ?? [], 'nv_observacion');
    }

    /** Sin parametros nuevos: exactamente lo de antes. */
    public function test_sin_parametros_devuelve_solo_las_propias_de_hoy(): void
    {
        $obs = $this->listar();

        $this->assertContains('Portón abierto hoy', $obs);
        $this->assertNotContains('Luz quemada hace tres días', $obs);
        $this->assertNotContains('Novedad del compañero', $obs);
    }

    public function test_con_siete_dias_trae_tambien_las_anteriores(): void
    {
        $obs = $this->listar(['dias' => 7]);

        $this->assertContains('Portón abierto hoy', $obs);
        $this->assertContains('Luz quemada hace tres días', $obs);
    }

    public function test_el_rango_no_alcanza_lo_que_quedo_fuera(): void
    {
        $obs = $this->listar(['dias' => 2]);

        $this->assertNotContains('Luz quemada hace tres días', $obs);
    }

    public function test_con_alcance_local_ve_las_del_companero(): void
    {
        $obs = $this->listar(['alcance' => 'local']);

        $this->assertContains('Novedad del compañero', $obs);
    }

    /** Con las de todos, hace falta saber quien escribio cada una. */
    public function test_el_listado_dice_quien_escribio_cada_novedad(): void
    {
        $r = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/novedad_listbydate', [
                'date' => now()->format('Y-m-d'),
                'ins_code' => $this->insCode,
                'alcance' => 'local',
            ]);

        $autores = array_column($r->json('nvNovedad'), 'nv_autor');

        $this->assertContains('Guardia Dos', $autores);
    }

    public function test_un_rango_desmedido_se_rechaza(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/novedad_listbydate', [
                'date' => now()->format('Y-m-d'),
                'ins_code' => $this->insCode,
                'dias' => 400,
            ])
            ->assertJsonPath('success', false);
    }
}
