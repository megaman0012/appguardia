<?php

namespace Tests\Unit;

use App\Services\PlantillaTurnoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Plantilla;
use Modules\Administracion\Models\PlantillaAsignacion;
use Modules\Administracion\Models\PlantillaFranja;
use Modules\Administracion\Models\Puesto;
use Modules\Administracion\Models\Turno;
use Tests\TestCase;

/**
 * Plantilla de turnos: cuadrante semanal que genera los turnos del periodo.
 *
 * El valor no esta en crear filas sino en lo que se revisa ANTES: solapes,
 * guardias sin vinculo al local, puestos de otro local y franjas sin cubrir.
 * Nada de eso lo detecta una planilla de Excel.
 */
class PlantillaTurnoTest extends TestCase
{
    use RefreshDatabase;

    private PlantillaTurnoService $servicio;
    private int $local;
    private int $otroLocal;
    private int $guardiaA = 1;
    private int $guardiaB = 2;
    private Puesto $puesto;
    private Plantilla $plantilla;

    // Septiembre 2026: el 1 cae martes.
    private Carbon $desde;
    private Carbon $hasta;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([[1, '1111111111', 'Guardia A'], [2, '2222222222', 'Guardia B']] as [$id, $ced, $nom]) {
            DB::table('users')->updateOrInsert(['id' => $id], [
                'usu_cedula' => $ced, 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
                'usu_nmbcom' => $nom, 'usu_ape1' => 'T', 'usu_ape2' => 'T',
                'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => $ced . '@e.com',
                'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->local     = $this->crearLocal('Terminal');
        $this->otroLocal = $this->crearLocal('Otro local');

        // Ambos guardias vinculados al local, salvo donde el test diga lo contrario.
        foreach ([$this->guardiaA, $this->guardiaB] as $u) {
            DB::table('user_has_institucion')->insert([
                'ui_usu_id' => $u, 'ui_ins_code' => $this->local, 'ui_state' => 1,
            ]);
        }

        $this->puesto = Puesto::create([
            'pu_ins_code' => $this->local, 'pu_nombre' => 'Garita', 'pu_estado' => true,
        ]);

        $this->plantilla = Plantilla::create([
            'pl_ins_code' => $this->local, 'pl_nombre' => 'Cuadrante',
        ]);

        $this->desde = Carbon::parse('2026-09-01');
        $this->hasta = Carbon::parse('2026-09-30');
        $this->servicio = app(PlantillaTurnoService::class);
    }

    private function crearLocal(string $nombre): int
    {
        return DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => $nombre, 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');
    }

    private function franja(int $dia, string $inicio, string $fin, ?Puesto $puesto = null): PlantillaFranja
    {
        return PlantillaFranja::create([
            'pf_pl_id'       => $this->plantilla->pl_id,
            'pf_puesto_id'   => ($puesto ?? $this->puesto)->pu_id,
            'pf_dia_semana'  => $dia,
            'pf_hora_inicio' => $inicio,
            'pf_hora_fin'    => $fin,
        ]);
    }

    private function asignar(PlantillaFranja $franja, int $usuario, ?string $desde = null, ?string $hasta = null): void
    {
        PlantillaAsignacion::create([
            'pa_pf_id'  => $franja->pf_id,
            'pa_usu_id' => $usuario,
            'pa_desde'  => $desde,
            'pa_hasta'  => $hasta,
        ]);
    }

    private function generar(): array
    {
        return $this->servicio->generar($this->plantilla, $this->desde, $this->hasta);
    }

    // ── Generación ──

    public function test_genera_un_turno_por_cada_dia_que_corresponde(): void
    {
        // Lunes a viernes de septiembre 2026 son 22 días.
        foreach ([1, 2, 3, 4, 5] as $dia) {
            $this->asignar($this->franja($dia, '06:00', '14:00'), $this->guardiaA);
        }

        $r = $this->generar();

        $this->assertSame([], $r['errores']);
        $this->assertSame(22, $r['creados']);
        $this->assertSame(22, Turno::where('tu_plantilla_id', $this->plantilla->pl_id)->count());
    }

    public function test_el_turno_generado_queda_completo(): void
    {
        $this->asignar($this->franja(3, '06:00', '14:00'), $this->guardiaA);
        $this->generar();

        $turno = Turno::first();

        $this->assertSame($this->local, (int) $turno->tu_ins_code);
        $this->assertSame($this->puesto->pu_id, (int) $turno->tu_puesto_id);
        $this->assertSame('programado', $turno->tu_estado);
        $this->assertSame(3, Carbon::parse($turno->tu_fecha)->dayOfWeekIso, 'Debe caer en miércoles');
    }

    public function test_publicar_cambia_el_estado_de_la_plantilla(): void
    {
        $this->asignar($this->franja(1, '06:00', '14:00'), $this->guardiaA);
        $this->assertSame(Plantilla::BORRADOR, $this->plantilla->pl_estado);

        $this->generar();

        $this->assertSame(Plantilla::PUBLICADA, $this->plantilla->fresh()->pl_estado);
    }

    public function test_la_vigencia_de_la_asignacion_acota_los_turnos(): void
    {
        // Un reemplazo de dos semanas no obliga a rehacer la plantilla.
        $this->asignar($this->franja(1, '06:00', '14:00'), $this->guardiaA, '2026-09-01', '2026-09-14');

        $r = $this->generar();

        // Lunes dentro del 1 al 14 de septiembre: el 7 y el 14.
        $this->assertSame(2, $r['creados']);
    }

    // ── Validaciones: lo que Excel no ve ──

    public function test_detecta_al_guardia_en_dos_turnos_a_la_vez(): void
    {
        $this->asignar($this->franja(1, '06:00', '14:00'), $this->guardiaA);
        $this->asignar($this->franja(1, '12:00', '20:00'), $this->guardiaA);

        $r = $this->generar();

        $this->assertNotEmpty($r['errores']);
        $this->assertStringContainsString('dos turnos a la vez', implode(' ', $r['errores']));
        $this->assertSame(0, $r['creados'], 'Con errores no debe generar nada');
        $this->assertSame(0, Turno::count());
    }

    public function test_detecta_al_guardia_sin_vinculo_con_el_local(): void
    {
        DB::table('user_has_institucion')->where('ui_usu_id', $this->guardiaB)->delete();
        $this->asignar($this->franja(1, '06:00', '14:00'), $this->guardiaB);

        $r = $this->generar();

        // Sin el vinculo no podria ni marcar: la app lo rechazaria.
        $this->assertStringContainsString('no está vinculado', implode(' ', $r['errores']));
        $this->assertSame(0, $r['creados']);
    }

    public function test_detecta_un_puesto_de_otro_local(): void
    {
        $ajeno = Puesto::create([
            'pu_ins_code' => $this->otroLocal, 'pu_nombre' => 'Garita ajena', 'pu_estado' => true,
        ]);
        $this->asignar($this->franja(1, '06:00', '14:00', $ajeno), $this->guardiaA);

        $r = $this->generar();

        $this->assertStringContainsString('pertenece a otro local', implode(' ', $r['errores']));
    }

    public function test_avisa_de_las_franjas_sin_cubrir(): void
    {
        $this->asignar($this->franja(1, '06:00', '14:00'), $this->guardiaA);
        $this->franja(2, '06:00', '14:00'); // martes sin nadie

        $r = $this->generar();

        // Es aviso, no error: la plantilla se publica igual y el hueco queda
        // visible antes del día, no cuando el puesto amanece vacío.
        $this->assertSame([], $r['errores']);
        $this->assertStringContainsString('Sin cubrir', implode(' ', $r['avisos']));
        $this->assertGreaterThan(0, $r['creados']);
    }

    public function test_avisa_del_descanso_insuficiente(): void
    {
        // Cierra 22:00-06:00 del lunes y abre 08:00 el martes: 2 h de descanso.
        $this->asignar($this->franja(1, '22:00', '06:00'), $this->guardiaA);
        $this->asignar($this->franja(2, '08:00', '16:00'), $this->guardiaA);

        $r = $this->generar();

        $this->assertSame([], $r['errores']);
        $this->assertStringContainsString('descansaría', implode(' ', $r['avisos']));
    }

    public function test_una_plantilla_sin_franjas_no_genera_nada(): void
    {
        $r = $this->generar();

        $this->assertNotEmpty($r['errores']);
        $this->assertSame(0, $r['creados']);
    }

    // ── Republicar sin pisar lo ocurrido ──

    public function test_regenerar_conserva_los_turnos_ya_marcados(): void
    {
        $franja = $this->franja(1, '06:00', '14:00');
        $this->asignar($franja, $this->guardiaA);
        $this->generar();

        // El guardia marca uno de los turnos.
        $marcado = Turno::where('tu_plantilla_id', $this->plantilla->pl_id)->first();
        $marcado->tu_marcada_entrada = now();
        $marcado->tu_estado = 'en_curso';
        $marcado->save();

        $r = $this->generar();

        $this->assertSame(1, $r['conservados']);
        $this->assertNotNull(
            Turno::find($marcado->tu_id)->tu_marcada_entrada,
            'Rehacer el cuadrante no puede borrar lo que ya ocurrió'
        );
    }

    public function test_regenerar_no_toca_los_turnos_cargados_a_mano(): void
    {
        // Un turno sin plantilla: lo creó alguien en el panel.
        $manual = Turno::create([
            'tu_ins_code' => $this->local, 'tu_usu_id' => $this->guardiaB,
            'tu_fecha' => '2026-09-07', 'tu_hora_inicio_prevista' => '20:00',
            'tu_hora_fin_prevista' => '23:00', 'tu_estado' => 'programado', 'tu_state' => true,
        ]);

        $this->asignar($this->franja(1, '06:00', '14:00'), $this->guardiaA);
        $this->generar();
        $this->generar();

        $this->assertNotNull(Turno::find($manual->tu_id), 'Los turnos manuales no los toca la plantilla');
    }

    public function test_regenerar_no_duplica(): void
    {
        $this->asignar($this->franja(1, '06:00', '14:00'), $this->guardiaA);

        $primera = $this->generar()['creados'];
        $segunda = $this->generar()['creados'];

        $this->assertSame($primera, $segunda);
        $this->assertSame($primera, Turno::where('tu_plantilla_id', $this->plantilla->pl_id)->count());
    }
}
