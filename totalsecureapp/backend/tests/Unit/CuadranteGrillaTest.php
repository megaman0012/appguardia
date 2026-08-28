<?php

namespace Tests\Unit;

use App\Services\CuadranteGrilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Plantilla;
use Modules\Administracion\Models\PlantillaAsignacion;
use Modules\Administracion\Models\PlantillaFranja;
use Modules\Administracion\Models\Puesto;
use Tests\TestCase;

/**
 * La grilla del cuadrante.
 *
 * Una lista de franjas ordenada por día no deja ver lo que importa: dónde queda
 * un puesto sin cubrir y dónde un guardia está en dos lugares a la vez. Lo que se
 * prueba acá es la detección, no el dibujo: si la grilla se ve linda pero no
 * marca el choque del domingo a la noche, no sirve para nada.
 */
class CuadranteGrillaTest extends TestCase
{
    use RefreshDatabase;

    private CuadranteGrilla $servicio;
    private Plantilla $plantilla;
    private Puesto $garita;
    private Puesto $anden;
    private int $guardiaA = 1;
    private int $guardiaB = 2;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([[1, 'Ana Torres'], [2, 'Luis Paez']] as [$id, $nombre]) {
            DB::table('users')->updateOrInsert(['id' => $id], [
                'usu_cedula' => str_pad((string) $id, 10, '0', STR_PAD_LEFT),
                'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
                'usu_nmbcom' => $nombre, 'usu_ape1' => 'T', 'usu_ape2' => 'T',
                'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => $id . '@e.com',
                'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $local = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Terminal', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        $this->garita = Puesto::create(['pu_ins_code' => $local, 'pu_nombre' => 'Garita', 'pu_estado' => true]);
        $this->anden  = Puesto::create(['pu_ins_code' => $local, 'pu_nombre' => 'Andén', 'pu_estado' => true]);

        $this->plantilla = Plantilla::create(['pl_ins_code' => $local, 'pl_nombre' => 'Cuadrante']);
        $this->servicio = app(CuadranteGrilla::class);
    }

    private function franja(int $dia, string $inicio, string $fin, ?int $guardia = null, ?Puesto $puesto = null): PlantillaFranja
    {
        $franja = PlantillaFranja::create([
            'pf_pl_id'       => $this->plantilla->pl_id,
            'pf_puesto_id'   => ($puesto ?? $this->garita)->pu_id,
            'pf_dia_semana'  => $dia,
            'pf_hora_inicio' => $inicio,
            'pf_hora_fin'    => $fin,
        ]);

        if ($guardia) {
            PlantillaAsignacion::create(['pa_pf_id' => $franja->pf_id, 'pa_usu_id' => $guardia]);
        }

        return $franja;
    }

    /** @return string[] motivos de esa franja dentro de la grilla armada */
    private function motivosDe(int $pfId): array
    {
        foreach ($this->servicio->armar($this->plantilla)['filas'] as $fila) {
            foreach ($fila['celdas'] as $celdas) {
                foreach ($celdas as $celda) {
                    if ($celda['pf_id'] === $pfId) {
                        return $celda['motivos'];
                    }
                }
            }
        }

        return [];
    }

    // ── Lo que la grilla tiene que hacer ver ──

    public function test_marca_la_franja_que_no_tiene_a_nadie(): void
    {
        // Un puesto sin guardia asignado va a amanecer vacío. Es el hueco que en
        // una lista de franjas pasa desapercibido.
        $sola = $this->franja(1, '06:00', '14:00');

        $this->assertContains(CuadranteGrilla::SIN_CUBRIR, $this->motivosDe($sola->pf_id));
    }

    public function test_marca_al_guardia_puesto_en_dos_lugares_a_la_vez(): void
    {
        $manana = $this->franja(1, '06:00', '14:00', $this->guardiaA);
        $encima = $this->franja(1, '12:00', '20:00', $this->guardiaA, $this->anden);

        $this->assertContains(CuadranteGrilla::CONFLICTO, $this->motivosDe($manana->pf_id));
        $this->assertContains(CuadranteGrilla::CONFLICTO, $this->motivosDe($encima->pf_id));
    }

    public function test_detecta_el_choque_del_domingo_a_la_noche_con_el_lunes(): void
    {
        // El patrón es semanal y circular: el turno de domingo 22:00 a 06:00
        // termina el lunes de la MISMA semana. Sin tratarlo así, el relevo más
        // típico del negocio nunca se marcaría.
        $domingo = $this->franja(7, '22:00', '06:00', $this->guardiaA);
        $lunes   = $this->franja(1, '05:00', '13:00', $this->guardiaA);

        $this->assertContains(CuadranteGrilla::CONFLICTO, $this->motivosDe($domingo->pf_id));
        $this->assertContains(CuadranteGrilla::CONFLICTO, $this->motivosDe($lunes->pf_id));
    }

    public function test_avisa_del_descanso_corto_sin_llamarlo_conflicto(): void
    {
        // Sale 14:00 y vuelve 20:00: seis horas. No es un error, pero el que
        // arma el cuadrante tiene que verlo antes de publicarlo.
        $sale  = $this->franja(1, '06:00', '14:00', $this->guardiaA);
        $entra = $this->franja(1, '20:00', '23:00', $this->guardiaA, $this->anden);

        $this->assertContains(CuadranteGrilla::DESCANSO, $this->motivosDe($sale->pf_id));
        $this->assertNotContains(CuadranteGrilla::CONFLICTO, $this->motivosDe($entra->pf_id));
    }

    public function test_ocho_horas_de_descanso_ya_no_se_avisan(): void
    {
        $this->franja(1, '06:00', '14:00', $this->guardiaA);
        $noche = $this->franja(1, '22:00', '06:00', $this->guardiaA, $this->anden);

        $this->assertSame([], $this->motivosDe($noche->pf_id));
    }

    public function test_un_cuadrante_sano_no_marca_nada(): void
    {
        $lunes  = $this->franja(1, '06:00', '14:00', $this->guardiaA);
        $martes = $this->franja(2, '06:00', '14:00', $this->guardiaB, $this->anden);

        $this->assertSame([], $this->motivosDe($lunes->pf_id));
        $this->assertSame([], $this->motivosDe($martes->pf_id));
    }

    public function test_dos_guardias_distintos_en_el_mismo_horario_no_es_conflicto(): void
    {
        // Dos puestos cubiertos a la misma hora por personas distintas es lo
        // normal, no un problema.
        $garita = $this->franja(1, '06:00', '14:00', $this->guardiaA);
        $anden  = $this->franja(1, '06:00', '14:00', $this->guardiaB, $this->anden);

        $this->assertSame([], $this->motivosDe($garita->pf_id));
        $this->assertSame([], $this->motivosDe($anden->pf_id));
    }

    // ── Cómo queda armada ──

    public function test_agrupa_por_puesto_y_ubica_cada_franja_en_su_dia(): void
    {
        $this->franja(3, '06:00', '14:00', $this->guardiaA);
        $this->franja(5, '06:00', '14:00', $this->guardiaB, $this->anden);

        $grilla = $this->servicio->armar($this->plantilla);

        $this->assertCount(2, $grilla['filas']);

        $garita = collect($grilla['filas'])->firstWhere('puesto', 'Garita');
        $this->assertCount(1, $garita['celdas'][3], 'La franja del miércoles va en la columna del miércoles');
        $this->assertCount(0, $garita['celdas'][5]);
        $this->assertSame(['Ana Torres'], $garita['celdas'][3][0]['guardias']);
    }

    public function test_el_turno_que_cruza_la_medianoche_dura_ocho_horas_no_dieciseis(): void
    {
        // 22:00 a 06:00 son 8 horas. Contarlo al revés inflaría las horas de todo
        // el equipo de la noche.
        $this->franja(1, '22:00', '06:00', $this->guardiaA);

        $guardias = $this->servicio->armar($this->plantilla)['guardias'];

        $this->assertSame(8.0, $guardias[$this->guardiaA]['horas']);
    }

    public function test_suma_las_horas_semanales_de_cada_guardia(): void
    {
        // Es lo que evita el cuadrante donde uno hace 40 horas y otro 8 sin que
        // nadie lo note hasta la planilla.
        foreach ([1, 2, 3, 4, 5] as $dia) {
            $this->franja($dia, '06:00', '14:00', $this->guardiaA);
        }
        $this->franja(6, '06:00', '14:00', $this->guardiaB, $this->anden);

        $guardias = $this->servicio->armar($this->plantilla)['guardias'];

        $this->assertSame(40.0, $guardias[$this->guardiaA]['horas']);
        $this->assertSame(5, $guardias[$this->guardiaA]['turnos']);
        $this->assertSame(8.0, $guardias[$this->guardiaB]['horas']);
    }

    public function test_el_resumen_cuenta_los_problemas(): void
    {
        $this->franja(1, '06:00', '14:00');                        // sin cubrir
        $this->franja(2, '06:00', '14:00', $this->guardiaA);
        $this->franja(2, '12:00', '20:00', $this->guardiaA, $this->anden); // conflicto

        $resumen = $this->servicio->armar($this->plantilla)['resumen'];

        $this->assertSame(3, $resumen['franjas']);
        $this->assertSame(1, $resumen['sin_cubrir']);
        $this->assertSame(2, $resumen['conflictos'], 'El choque marca las dos franjas');
    }

    public function test_una_plantilla_vacia_no_revienta(): void
    {
        $grilla = $this->servicio->armar($this->plantilla);

        $this->assertSame([], $grilla['filas']);
        $this->assertSame(0, $grilla['resumen']['franjas']);
    }
}
