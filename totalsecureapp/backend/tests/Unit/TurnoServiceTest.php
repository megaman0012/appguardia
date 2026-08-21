<?php

namespace Tests\Unit;

use App\Services\TurnoService;
use Carbon\Carbon;
use Modules\Administracion\Models\Turno;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TurnoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TurnoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TurnoService();
    }

    /** @test */
    public function calcular_tardanza_cuando_llega_tarde()
    {
        $tardanza = $this->service->calcularTardanza('08:00:00', Carbon::parse('2026-08-20 08:15:00'));
        $this->assertEquals(15, $tardanza);
    }

    /** @test */
    public function calcular_tardanza_cuando_llega_a_tiempo()
    {
        $tardanza = $this->service->calcularTardanza('08:00:00', Carbon::parse('2026-08-20 08:00:00'));
        $this->assertEquals(0, $tardanza);
    }

    /** @test */
    public function calcular_tardanza_cuando_llega_antes()
    {
        $tardanza = $this->service->calcularTardanza('08:00:00', Carbon::parse('2026-08-20 07:55:00'));
        $this->assertEquals(0, $tardanza);
    }

    /** @test */
    public function calcular_minutos_extras_cuando_sale_tarde()
    {
        $extras = $this->service->calcularMinutosExtras('20:00:00', Carbon::parse('2026-08-20 20:30:00'));
        $this->assertEquals(30, $extras);
    }

    /** @test */
    public function calcular_minutos_extras_cuando_sale_a_tiempo()
    {
        $extras = $this->service->calcularMinutosExtras('20:00:00', Carbon::parse('2026-08-20 20:00:00'));
        $this->assertEquals(0, $extras);
    }

    /** @test */
    public function calcular_minutos_extras_cuando_sale_antes()
    {
        $extras = $this->service->calcularMinutosExtras('20:00:00', Carbon::parse('2026-08-20 19:50:00'));
        $this->assertEquals(0, $extras);
    }

    /** @test */
    public function cerrar_turnos_sin_marcacion_cambia_estado_a_ausente()
    {
        $turno = Turno::create([
            'tu_ins_code' => 1,
            'tu_usu_id' => 1,
            'tu_fecha' => Carbon::yesterday()->toDateString(),
            'tu_hora_inicio_prevista' => '08:00:00',
            'tu_hora_fin_prevista' => '20:00:00',
            'tu_estado' => 'programado',
        ]);

        $marcados = $this->service->cerrarTurnosSinMarcacion(1);
        $this->assertEquals(1, $marcados);

        $turno->refresh();
        $this->assertEquals('ausente', $turno->tu_estado);
    }

    /** @test */
    public function buscar_turno_programado_encuentra_turno()
    {
        $turno = Turno::create([
            'tu_ins_code' => 1,
            'tu_usu_id' => 1,
            'tu_fecha' => Carbon::today()->toDateString(),
            'tu_hora_inicio_prevista' => '08:00:00',
            'tu_hora_fin_prevista' => '20:00:00',
            'tu_estado' => 'programado',
        ]);

        $encontrado = $this->service->buscarTurnoProgramado(1, 1, Carbon::today());
        $this->assertNotNull($encontrado);
        $this->assertEquals($turno->tu_id, $encontrado->tu_id);
    }
}
