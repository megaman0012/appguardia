<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\TurnoVacante;
use Tests\TestCase;

/**
 * Los datos de demostración, y las tres condiciones que hacen que se vean.
 *
 * En la app del guardia no aparecía nada en «Mi horario» ni en «Turnos
 * disponibles», y no era un fallo del código: eran los datos. Este test fija las
 * condiciones que hay que cumplir, porque fallando cualquiera **la pantalla sale
 * vacía sin decir por qué**:
 *
 *  1. El guardia necesita turnos (no tenía ninguno).
 *  2. Las vacantes deben estar en estado `abierta` — `scopeAbiertas()` no trae
 *     las `detectada`, que era el estado de las 9 que existían.
 *  3. El guardia debe estar **vinculado al local** de la vacante, que es lo que
 *     exige `VacanteService::motivoParaNoCubrir()`.
 */
class DatosDeDemostracionTest extends TestCase
{
    use RefreshDatabase;

    private int $guardia = 1;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '0925514630', 'usu_tipdoc' => 'C', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Guardia Prueba', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'g@e.com',
            'usu_state' => 1, 'usu_acepta_extras' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function local(int $ins, string $nombre, int $puestos, bool $activo = true, bool $vinculado = true): void
    {
        DB::table('organizacion_institucion')->updateOrInsert(['ins_code' => $ins], [
            'ins_descripcion' => $nombre, 'ins_estado' => $activo,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        for ($i = 1; $i <= $puestos; $i++) {
            DB::table('puesto')->insert([
                'pu_ins_code' => $ins, 'pu_nombre' => "puesto {$i}", 'pu_estado' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if ($vinculado) {
            DB::table('user_has_institucion')->insert([
                'ui_code' => (DB::table('user_has_institucion')->max('ui_code') ?? 0) + 1,
                'ui_usu_id' => $this->guardia, 'ui_ins_code' => $ins, 'ui_state' => 1,
                'ui_created_at' => now(), 'ui_updated_at' => now(),
            ]);
        }
    }

    private function correr(array $o = []): void
    {
        $this->artisan('demo:turnos-y-cobertura',
            array_merge(['--cedula' => '0925514630'], $o))->assertSuccessful();
    }

    public function test_crea_turnos_pasados_y_futuros(): void
    {
        $this->local(144, 'HOSPITAL SEMEDIC', 5);

        $this->correr(['--ejecutar' => true]);

        $turnos = DB::table('turno')->where('tu_usu_id', $this->guardia)->get();

        $this->assertGreaterThan(0, $turnos->count());

        // Pasados completados: sin historia, la pantalla de cumplimiento va vacía.
        $this->assertGreaterThan(0, $turnos->where('tu_estado', 'completado')->count());
        $this->assertGreaterThan(0, $turnos->where('tu_estado', 'programado')->count());
    }

    /** ⚠️ `detectada` no la trae `scopeAbiertas()`: tienen que ser `abierta`. */
    public function test_las_vacantes_quedan_abiertas_y_en_su_local(): void
    {
        $this->local(144, 'HOSPITAL SEMEDIC', 5);

        $this->correr(['--ejecutar' => true]);

        $v = DB::table('turno_vacante')->get();

        $this->assertGreaterThan(0, $v->count());
        $this->assertSame($v->count(), $v->where('tv_estado', TurnoVacante::ABIERTA)->count(),
            'en «detectada» no las trae scopeAbiertas() y la pantalla sale vacía');
        $this->assertSame($v->count(), $v->where('tv_ins_code', 144)->count(),
            'fuera de un local vinculado, motivoParaNoCubrir() las descarta');
    }

    /**
     * ⚠️ Las vacantes van en los días LIBRES del guardia.
     *
     * Se me pasó al escribirlo: las puse en días de su propia jornada y
     * `VacanteService` las descartó por solapamiento, con toda la razón — nadie
     * puede cubrir un turno mientras hace el suyo. El síntoma era el mismo que
     * veníamos a arreglar: la pantalla vacía.
     */
    public function test_las_vacantes_no_chocan_con_los_turnos_del_guardia(): void
    {
        $this->local(144, 'HOSPITAL SEMEDIC', 5);

        $this->correr(['--ejecutar' => true]);

        $diasDeTurno = DB::table('turno')->where('tu_usu_id', $this->guardia)
            ->pluck('tu_fecha')->map(fn ($f) => substr((string) $f, 0, 10))->all();

        foreach (DB::table('turno_vacante')->pluck('tv_fecha') as $fecha) {
            $this->assertNotContains(substr((string) $fecha, 0, 10), $diasDeTurno,
                'una vacante en un día que ya trabaja se descarta por solapamiento');
        }
    }

    /** Un sitio con varias posiciones se presenta mejor que una oficina de una. */
    public function test_elige_el_local_con_mas_puestos(): void
    {
        $this->local(22, 'Oficina Garzota', 1);
        $this->local(144, 'HOSPITAL SEMEDIC', 5);

        $this->correr(['--ejecutar' => true]);

        $this->assertSame(144,
            (int) DB::table('turno')->where('tu_usu_id', $this->guardia)->value('tu_ins_code'));
    }

    public function test_no_usa_un_local_inactivo_ni_uno_sin_vinculo(): void
    {
        $this->local(90, 'INACTIVO CON MUCHOS', 9, activo: false);
        $this->local(91, 'ACTIVO SIN VINCULO', 8, vinculado: false);
        $this->local(144, 'EL BUENO', 2);

        $this->correr(['--ejecutar' => true]);

        $this->assertSame(144,
            (int) DB::table('turno')->where('tu_usu_id', $this->guardia)->value('tu_ins_code'));
    }

    public function test_falla_claro_si_no_hay_local_utilizable(): void
    {
        $this->artisan('demo:turnos-y-cobertura', ['--cedula' => '0925514630', '--ejecutar' => true])
            ->expectsOutputToContain('no tiene ningún local activo, vinculado y con puestos')
            ->assertFailed();
    }

    public function test_limpiar_borra_solo_lo_suyo(): void
    {
        $this->local(144, 'HOSPITAL SEMEDIC', 5);

        // Un turno real que NO debe tocarse.
        DB::table('turno')->insert([
            'tu_usu_id' => 1, 'tu_ins_code' => 144, 'tu_fecha' => '2020-01-01',
            'tu_hora_inicio_prevista' => '07:00', 'tu_hora_fin_prevista' => '19:00',
            'tu_estado' => 'completado', 'tu_state' => 1,
            'tu_created_at' => now(), 'tu_updated_at' => now(),
        ]);

        $this->correr(['--ejecutar' => true]);
        $this->correr(['--limpiar' => true, '--ejecutar' => true]);

        $quedan = DB::table('turno')->get();

        $this->assertCount(1, $quedan, 'el turno real tiene que sobrevivir');
        $this->assertSame('2020-01-01', $quedan->first()->tu_fecha);
        $this->assertSame(0, DB::table('turno_vacante')->count());
    }

    public function test_correrlo_dos_veces_no_duplica(): void
    {
        $this->local(144, 'HOSPITAL SEMEDIC', 5);

        $this->correr(['--ejecutar' => true]);
        $n = DB::table('turno')->count();
        $v = DB::table('turno_vacante')->count();

        $this->correr(['--ejecutar' => true]);

        $this->assertSame($n, DB::table('turno')->count());
        $this->assertSame($v, DB::table('turno_vacante')->count());
    }

    public function test_la_simulacion_no_escribe(): void
    {
        $this->local(144, 'HOSPITAL SEMEDIC', 5);

        $this->correr();

        $this->assertSame(0, DB::table('turno')->count());
    }
}
