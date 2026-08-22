<?php

namespace Tests\Unit;

use App\Services\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Alertas;
use Modules\Administracion\Models\Turno;
use Tests\TestCase;

/**
 * Cache del dashboard con invalidacion por evento (Fase 9).
 *
 * Lo que se verifica no es solo que cachee, sino que un cambio se refleje de
 * inmediato: un TTL ciego dejaria al supervisor viendo un contador viejo despues
 * de atender una alerta.
 */
class DashboardCacheTest extends TestCase
{
    use RefreshDatabase;

    private DashboardStatsService $stats;
    private int $insA;
    private int $insB;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '9999999999', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('testing'),
            'usu_nmbcom' => 'Test', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 't@e.com',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->insA = $this->crearInstitucion('Institucion A');
        $this->insB = $this->crearInstitucion('Institucion B');

        $this->stats = app(DashboardStatsService::class);
    }

    private function crearInstitucion(string $desc): int
    {
        return DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => $desc, 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');
    }

    private function crearAlerta(int $insCode, string $estado = 'pendiente', string $prioridad = 'media'): Alertas
    {
        return Alertas::create([
            'al_ins_code' => $insCode, 'al_usu_id' => 1, 'al_anio' => 2026,
            'al_estado' => 1, 'al_prioridad' => $prioridad, 'al_estado_alerta' => $estado,
            'al_observacion' => 'Alerta', 'al_fecha' => now(), 'al_created_user' => 1,
        ]);
    }

    private function crearTurno(int $insCode, ?string $marcadaEntrada = null, int $tardanza = 0): Turno
    {
        return Turno::create([
            'tu_ins_code' => $insCode, 'tu_usu_id' => 1, 'tu_fecha' => now()->toDateString(),
            'tu_hora_inicio_prevista' => '08:00', 'tu_hora_fin_prevista' => '16:00',
            'tu_marcada_entrada' => $marcadaEntrada,
            'tu_minutos_tardanza' => $tardanza,
            'tu_estado' => 'programado',
        ]);
    }

    private function contarConsultas(callable $fn): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    // ── Que efectivamente cachee ──

    public function test_la_segunda_lectura_no_vuelve_a_consultar(): void
    {
        $this->crearAlerta($this->insA);

        $primera = $this->contarConsultas(fn () => $this->stats->alertasActivas($this->insA));
        $segunda = $this->contarConsultas(fn () => $this->stats->alertasActivas($this->insA));

        $this->assertGreaterThan(0, $primera, 'La primera lectura deberia consultar la BD');
        $this->assertSame(0, $segunda, 'La segunda deberia salir del cache sin tocar la BD');
    }

    // ── Invalidacion por evento, no por TTL ──

    public function test_crear_una_alerta_refresca_el_contador_de_inmediato(): void
    {
        $this->crearAlerta($this->insA);
        $this->assertSame(1, $this->stats->alertasActivas($this->insA)['activas']);

        $this->crearAlerta($this->insA);

        // Sin invalidacion por evento esto seguiria diciendo 1 hasta que venciera el TTL.
        $this->assertSame(2, $this->stats->alertasActivas($this->insA)['activas']);
    }

    public function test_atender_una_alerta_refresca_el_contador_de_inmediato(): void
    {
        $alerta = $this->crearAlerta($this->insA, 'pendiente');
        $this->assertSame(1, $this->stats->alertasActivas($this->insA)['pendientes']);

        $alerta->al_estado_alerta = 'finalizada';
        $alerta->save();

        $datos = $this->stats->alertasActivas($this->insA);
        $this->assertSame(0, $datos['activas']);
        $this->assertSame(0, $datos['pendientes']);
    }

    public function test_borrar_una_alerta_refresca_el_contador(): void
    {
        $alerta = $this->crearAlerta($this->insA);
        $this->assertSame(1, $this->stats->alertasActivas($this->insA)['activas']);

        $alerta->delete();

        $this->assertSame(0, $this->stats->alertasActivas($this->insA)['activas']);
    }

    public function test_marcar_entrada_refresca_el_cumplimiento(): void
    {
        $turno = $this->crearTurno($this->insA);
        $this->assertSame(0.0, $this->stats->cumplimientoTurnos($this->insA)['porcentaje_entrada']);

        $turno->tu_marcada_entrada = now();
        $turno->save();

        $this->assertSame(100.0, $this->stats->cumplimientoTurnos($this->insA)['porcentaje_entrada']);
    }

    // ── Aislamiento entre instituciones ──

    public function test_invalidar_una_institucion_no_recalcula_la_otra(): void
    {
        $this->crearAlerta($this->insA);
        $this->crearAlerta($this->insB);

        // Ambas quedan cacheadas.
        $this->stats->alertasActivas($this->insA);
        $this->stats->alertasActivas($this->insB);

        // Un cambio en A no debe tirar el cache de B.
        $this->crearAlerta($this->insA);

        $consultasB = $this->contarConsultas(fn () => $this->stats->alertasActivas($this->insB));
        $this->assertSame(0, $consultasB, 'El cache de la institucion B no deberia invalidarse por un cambio en A');

        $this->assertSame(2, $this->stats->alertasActivas($this->insA)['activas']);
        $this->assertSame(1, $this->stats->alertasActivas($this->insB)['activas']);
    }

    public function test_el_cumplimiento_se_cachea_por_fecha(): void
    {
        $this->crearTurno($this->insA, now()->toDateString());

        $hoy = $this->stats->cumplimientoTurnos($this->insA, now()->toDateString());
        $ayer = $this->stats->cumplimientoTurnos($this->insA, now()->subDay()->toDateString());

        $this->assertSame(1, $hoy['total']);
        $this->assertSame(0, $ayer['total']);
    }

    // ── El contador de version ──

    public function test_la_version_arranca_en_uno_y_sube_al_invalidar(): void
    {
        $this->assertSame(1, $this->stats->version(DashboardStatsService::DOMINIO_ALERTAS, $this->insA));

        $this->stats->invalidar(DashboardStatsService::DOMINIO_ALERTAS, $this->insA);

        $this->assertSame(2, $this->stats->version(DashboardStatsService::DOMINIO_ALERTAS, $this->insA));
    }

    public function test_sin_turnos_el_cumplimiento_no_se_reporta_como_cero_por_ciento(): void
    {
        $datos = $this->stats->cumplimientoTurnos($this->insA);

        // 'total' es lo que permite distinguir "0% de cumplimiento" de "no aplica".
        $this->assertSame(0, $datos['total']);
        $this->assertSame(0.0, $datos['porcentaje_entrada']);
    }
}
