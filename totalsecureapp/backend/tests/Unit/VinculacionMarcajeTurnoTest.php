<?php

namespace Tests\Unit;

use App\Services\TurnoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Turno;
use Tests\TestCase;

/**
 * Vinculacion del marcaje biometrico con el turno programado.
 *
 * Antes el marcaje se guardaba y ahi terminaba: enlazarlo exigia una llamada
 * aparte que la app nunca hacia, asi que tu_marcada_entrada quedaba en null, el
 * cumplimiento marcaba 0% y el cierre automatico declaraba ausentes a guardias
 * que si habian trabajado. Ahora BiometriaController lo enlaza al guardar.
 */
class VinculacionMarcajeTurnoTest extends TestCase
{
    use RefreshDatabase;

    private TurnoService $turnos;
    private int $local;
    private int $guardia = 1;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '9999999999', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('testing'),
            'usu_nmbcom' => 'Guardia Test', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 't@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->local = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Terminal', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        $this->turnos = app(TurnoService::class);
    }

    private function programar(string $inicio, string $fin, string $estado = 'programado'): Turno
    {
        return Turno::create([
            'tu_ins_code'             => $this->local,
            'tu_usu_id'               => $this->guardia,
            'tu_fecha'                => now()->toDateString(),
            'tu_hora_inicio_prevista' => $inicio,
            'tu_hora_fin_prevista'    => $fin,
            'tu_estado'               => $estado,
            'tu_state'                => true,
        ]);
    }

    private function buscar(string $hora, bool $entrada): ?Turno
    {
        return $this->turnos->buscarTurnoParaMarcaje(
            $this->guardia,
            $this->local,
            Carbon::parse(now()->toDateString() . ' ' . $hora),
            $entrada
        );
    }

    // ── Elegir el turno correcto ──

    public function test_con_un_solo_turno_lo_encuentra(): void
    {
        $turno = $this->programar('08:00', '16:00');

        $this->assertSame($turno->tu_id, $this->buscar('07:55', true)->tu_id);
    }

    public function test_con_dos_turnos_elige_el_mas_cercano_a_la_marcacion(): void
    {
        $mañana = $this->programar('06:00', '14:00');
        $noche  = $this->programar('22:00', '06:00');

        // Marcar a las 05:50 debe abrir el de las 06:00, no el de las 22:00.
        $this->assertSame($mañana->tu_id, $this->buscar('05:50', true)->tu_id);
        $this->assertSame($noche->tu_id, $this->buscar('21:40', true)->tu_id);
    }

    public function test_la_salida_busca_el_turno_en_curso(): void
    {
        $this->programar('06:00', '14:00');                       // programado
        $abierto = $this->programar('14:00', '22:00', 'en_curso'); // el que abrio

        $this->assertSame($abierto->tu_id, $this->buscar('22:05', false)->tu_id);
    }

    public function test_sin_turno_programado_devuelve_null(): void
    {
        // Hay instituciones que no usan turnos: el marcaje debe guardarse igual.
        $this->assertNull($this->buscar('08:00', true));
    }

    public function test_un_turno_inactivo_no_se_considera(): void
    {
        $turno = $this->programar('08:00', '16:00');
        $turno->tu_state = false;
        $turno->save();

        $this->assertNull($this->buscar('08:00', true));
    }

    // ── El enlace ──

    public function test_la_entrada_abre_el_turno_y_calcula_la_tardanza(): void
    {
        $turno = $this->programar('08:00', '16:00');
        $marcaje = Carbon::parse(now()->toDateString() . ' 08:17');

        $this->turnos->vincularEntrada($turno, 123, $marcaje);
        $turno->refresh();

        $this->assertSame('en_curso', $turno->tu_estado);
        $this->assertSame(17, $turno->tu_minutos_tardanza);
        $this->assertSame(123, (int) $turno->tu_bio_entrada_code);
        $this->assertNotNull($turno->tu_marcada_entrada);
    }

    public function test_llegar_temprano_no_genera_tardanza(): void
    {
        $turno = $this->programar('08:00', '16:00');

        $this->turnos->vincularEntrada($turno, 1, Carbon::parse(now()->toDateString() . ' 07:45'));

        $this->assertSame(0, $turno->refresh()->tu_minutos_tardanza);
    }

    public function test_la_salida_cierra_el_turno_y_calcula_las_extras(): void
    {
        $turno = $this->programar('08:00', '16:00', 'en_curso');

        $this->turnos->vincularSalida($turno, 456, Carbon::parse(now()->toDateString() . ' 16:40'));
        $turno->refresh();

        $this->assertSame('completado', $turno->tu_estado);
        $this->assertSame(40, $turno->tu_minutos_extras);
        $this->assertSame(456, (int) $turno->tu_bio_salida_code);
    }

    // ── Lo que activa ──

    public function test_el_turno_vinculado_alimenta_el_cumplimiento(): void
    {
        $turno = $this->programar('08:00', '16:00');

        $stats = app(\App\Services\DashboardStatsService::class);
        $antes = $stats->cumplimientoTurnos($this->local);
        $this->assertSame(0, $antes['con_entrada']);

        $this->turnos->vincularEntrada($turno, 1, Carbon::parse(now()->toDateString() . ' 08:30'));

        // El observer de Turno invalida el cache, asi que el tablero lo refleja
        // sin esperar un TTL.
        $despues = $stats->cumplimientoTurnos($this->local);
        $this->assertSame(1, $despues['con_entrada']);
        $this->assertSame(100.0, $despues['porcentaje_entrada']);
        $this->assertSame(30, $despues['minutos_tardanza']);
    }
}
