<?php

namespace Tests\Unit;

use App\Services\AlertaService;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Alertas;
use Modules\Administracion\Models\AlertaDetalle;
use Modules\Administracion\Models\AlertaHistorial;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AlertaServiceTest extends TestCase
{
    use RefreshDatabase;

    private AlertaService $alertaService;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(
            ['id' => 1],
            [
                'usu_cedula'   => '9999999999',
                'usu_tipdoc'   => 'CC',
                'usu_password' => bcrypt('testing'),
                'usu_nmbcom'   => 'Usuario Test',
                'usu_ape1'     => 'Test',
                'usu_ape2'     => 'Test',
                'usu_nmb1'     => 'Usuario',
                'usu_nmb2'     => 'Test',
                'usu_email'    => 'test@example.com',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        $this->alertaService = app(AlertaService::class);
    }

    public function test_crear_alerta_genera_historial(): void
    {
        $alerta = Alertas::factory()->create([
            'al_estado_alerta' => 'pendiente',
            'al_prioridad' => 'alta',
        ]);

        AlertaHistorial::registrar(
            $alerta->al_code,
            'creada',
            $alerta->al_created_user,
            'Alerta creada'
        );

        $this->assertDatabaseHas('alertas_historial', [
            'ah_al_code' => $alerta->al_code,
            'ah_accion' => 'creada',
        ]);
    }

    public function test_asignar_detalle_a_alerta(): void
    {
        $alerta = Alertas::factory()->create([
            'al_estado_alerta' => 'pendiente',
        ]);

        $detalle = AlertaDetalle::create([
            'ad_al_code' => $alerta->al_code,
            'ad_usuario_asignado' => 1,
            'ad_prioridad' => $alerta->al_prioridad,
            'ad_estado' => 'asignada',
            'ad_fecha_asignacion' => now(),
            'ad_created_user' => $alerta->al_created_user,
        ]);

        $alerta->update(['al_estado_alerta' => 'en_atencion']);

        $this->assertDatabaseHas('alertas_detalle', [
            'ad_al_code' => $alerta->al_code,
            'ad_estado' => 'asignada',
        ]);
        $this->assertEquals('en_atencion', $alerta->fresh()->al_estado_alerta);
    }

    public function test_resolver_alerta_calcula_tiempo(): void
    {
        $alerta = Alertas::factory()->create([
            'al_estado_alerta' => 'en_atencion',
            'al_fecha' => now()->subMinutes(10),
        ]);

        $detalle = AlertaDetalle::create([
            'ad_al_code' => $alerta->al_code,
            'ad_usuario_asignado' => 1,
            'ad_prioridad' => 'media',
            'ad_estado' => 'asignada',
            'ad_fecha_asignacion' => now()->subMinutes(10),
            'ad_created_user' => 1,
        ]);

        $detalle->marcarResuelta('Atendida correctamente');

        $this->assertEquals('resuelta', $detalle->fresh()->ad_estado);
        $this->assertEquals('finalizada', $alerta->fresh()->al_estado_alerta);
        $this->assertGreaterThan(0, $detalle->fresh()->ad_tiempo_respuesta_seg);
    }

    public function test_escalada_marca_detalle(): void
    {
        $alerta = Alertas::factory()->create([
            'al_estado_alerta' => 'en_atencion',
            'al_fecha' => now()->subMinutes(35),
        ]);

        $detalle = AlertaDetalle::create([
            'ad_al_code' => $alerta->al_code,
            'ad_usuario_asignado' => 1,
            'ad_prioridad' => 'media',
            'ad_estado' => 'asignada',
            'ad_fecha_asignacion' => now()->subMinutes(35),
            'ad_created_user' => 1,
        ]);

        $detalle->update(['ad_estado' => 'escalada']);

        $this->assertEquals('escalada', $detalle->fresh()->ad_estado);
    }

    public function test_cancelar_alerta(): void
    {
        $alerta = Alertas::factory()->create([
            'al_estado_alerta' => 'pendiente',
        ]);

        $alerta->update(['al_estado_alerta' => 'cancelada']);

        $this->assertEquals('cancelada', $alerta->fresh()->al_estado_alerta);
    }

    public function test_estadisticas_por_prioridad(): void
    {
        $alertas = Alertas::factory()->count(3)->create([
            'al_prioridad' => 'critica',
            'al_estado_alerta' => 'pendiente',
        ]);

        $this->assertEquals(3, Alertas::porPrioridad('critica')->count());
    }

    public function test_historial_registra_acciones(): void
    {
        $alerta = Alertas::factory()->create();

        AlertaHistorial::registrar($alerta->al_code, 'creada', 1, 'test');
        AlertaHistorial::registrar($alerta->al_code, 'asignada', 1, 'test');

        $this->assertEquals(2, $alerta->historial()->count());
    }

    public function test_alerta_con_detalle_y_historial(): void
    {
        $alerta = Alertas::factory()->create([
            'al_estado_alerta' => 'pendiente',
            'al_prioridad' => 'alta',
        ]);

        AlertaHistorial::registrar($alerta->al_code, 'creada', 1, 'Creada');

        AlertaDetalle::create([
            'ad_al_code' => $alerta->al_code,
            'ad_usuario_asignado' => 1,
            'ad_prioridad' => 'alta',
            'ad_estado' => 'asignada',
            'ad_fecha_asignacion' => now(),
            'ad_created_user' => 1,
        ]);

        $this->assertDatabaseHas('alertas', ['al_code' => $alerta->al_code]);
        $this->assertDatabaseHas('alertas_historial', ['ah_al_code' => $alerta->al_code]);
        $this->assertDatabaseHas('alertas_detalle', ['ad_al_code' => $alerta->al_code]);
    }
}
