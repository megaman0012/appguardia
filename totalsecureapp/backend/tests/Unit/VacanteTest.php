<?php

namespace Tests\Unit;

use App\Services\PlantillaTurnoService;
use App\Services\VacanteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Plantilla;
use Modules\Administracion\Models\PlantillaAsignacion;
use Modules\Administracion\Models\PlantillaFranja;
use Modules\Administracion\Models\Puesto;
use Modules\Administracion\Models\Turno;
use Modules\Administracion\Models\TurnoPostulacion;
use Modules\Administracion\Models\TurnoVacante;
use Tests\TestCase;

/**
 * Cobertura de un turno que quedó vacío.
 *
 * Lo que se fija aquí no es "crear filas", es el criterio: cuándo el sistema
 * puede sospechar una falta y cuándo no le corresponde decidir, a quién se le
 * puede ofrecer el turno sin mandarlo a un lugar donde no puede entrar o sin
 * dormir, y que cubrir una falta no borre la falta.
 */
class VacanteTest extends TestCase
{
    use RefreshDatabase;

    private VacanteService $servicio;
    private int $local;
    private int $otroLocalMismaCiudad;
    private Puesto $puesto;

    private int $ausente = 1;
    private int $disponible = 2;
    private int $otroDisponible = 3;
    private int $noQuiereExtras = 4;
    private int $deLaCiudad = 5;

    protected function setUp(): void
    {
        parent::setUp();

        $ecuador = (int) DB::table('pais')->where('pa_iso2', 'EC')->value('pa_id');
        $provincia = DB::table('provincia')->where('pr_pa_id', $ecuador)->value('pr_id');
        $ciudad = DB::table('ciudad')->where('cd_pr_id', $provincia)->value('cd_id')
            ?: DB::table('ciudad')->insertGetId([
                'cd_pr_id' => $provincia, 'cd_nombre' => 'Ciudad de prueba', 'cd_estado' => true,
                'created_at' => now(), 'updated_at' => now(),
            ], 'cd_id');

        $this->local = $this->crearLocal('Terminal de carga', $ciudad);
        $this->otroLocalMismaCiudad = $this->crearLocal('Bodega central', $ciudad);

        $this->crearGuardia($this->ausente, '1111111111', 'Ausente Perez', $this->local, false);
        $this->crearGuardia($this->disponible, '2222222222', 'Disponible Uno', $this->local, true);
        $this->crearGuardia($this->otroDisponible, '3333333333', 'Disponible Dos', $this->local, true);
        $this->crearGuardia($this->noQuiereExtras, '4444444444', 'No Quiere', $this->local, false);
        $this->crearGuardia($this->deLaCiudad, '5555555555', 'De La Ciudad', $this->otroLocalMismaCiudad, true);

        $this->puesto = Puesto::create([
            'pu_ins_code' => $this->local, 'pu_nombre' => 'Garita', 'pu_estado' => true,
        ]);

        $this->servicio = app(VacanteService::class);
    }

    private function crearLocal(string $nombre, $ciudadId): int
    {
        return DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => $nombre, 'ins_estado' => true, 'ins_cd_id' => $ciudadId,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');
    }

    private function crearGuardia(int $id, string $cedula, string $nombre, int $local, bool $extras): void
    {
        DB::table('users')->updateOrInsert(['id' => $id], [
            'usu_cedula' => $cedula, 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => $nombre, 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => $cedula . '@e.com',
            'usu_state' => 1, 'usu_acepta_extras' => $extras,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('user_has_institucion')->updateOrInsert(
            ['ui_usu_id' => $id, 'ui_ins_code' => $local],
            ['ui_state' => 1]
        );

        $rol = DB::table('roles')->where('name', 'Vigilante')->value('id');
        DB::table('user_has_roles')->updateOrInsert(
            ['user_id' => $id, 'role_id' => $rol],
            ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
        );
    }

    private function turno(string $fecha, string $inicio, string $fin, ?int $usuario = null, ?int $local = null): Turno
    {
        return Turno::create([
            'tu_ins_code'             => $local ?? $this->local,
            'tu_usu_id'               => $usuario ?? $this->ausente,
            'tu_puesto_id'            => $this->puesto->pu_id,
            'tu_fecha'                => $fecha,
            'tu_hora_inicio_prevista' => $inicio,
            'tu_hora_fin_prevista'    => $fin,
            'tu_estado'               => 'programado',
            'tu_state'                => true,
        ]);
    }

    /** Una vacante ya abierta, lista para recibir postulaciones. */
    private function vacanteAbierta(?Turno $turno = null): TurnoVacante
    {
        $turno = $turno ?? $this->turno(Carbon::today()->toDateString(), '06:00', '14:00');

        $vacante = $this->servicio->crearDesdeTurno($turno);

        return $this->servicio->abrir($vacante, 99);
    }

    // ── Detección: cuándo el sistema puede sospechar ──

    public function test_detecta_el_turno_que_empezo_y_nadie_marco(): void
    {
        $this->turno(Carbon::today()->toDateString(), '06:00', '14:00');

        $detectadas = $this->servicio->detectar(Carbon::today()->setTime(7, 0));

        $this->assertSame(1, $detectadas);
        $this->assertSame(TurnoVacante::DETECTADA, TurnoVacante::first()->tv_estado);
    }

    public function test_no_detecta_nada_dentro_de_la_tolerancia(): void
    {
        // A los 10 minutos el guardia puede estar entrando por la puerta.
        $this->turno(Carbon::today()->toDateString(), '06:00', '14:00');

        $this->assertSame(0, $this->servicio->detectar(Carbon::today()->setTime(6, 10)));
        $this->assertSame(0, TurnoVacante::count());
    }

    public function test_no_detecta_si_el_guardia_ya_marco(): void
    {
        $turno = $this->turno(Carbon::today()->toDateString(), '06:00', '14:00');
        $turno->tu_marcada_entrada = Carbon::today()->setTime(5, 58);
        $turno->save();

        $this->assertSame(0, $this->servicio->detectar(Carbon::today()->setTime(7, 0)));
    }

    public function test_no_detecta_un_turno_que_ya_termino(): void
    {
        // Cubrirlo no tendría sentido: el turno se acabó.
        $this->turno(Carbon::today()->toDateString(), '06:00', '14:00');

        $this->assertSame(0, $this->servicio->detectar(Carbon::today()->setTime(15, 0)));
    }

    public function test_dos_pasadas_del_detector_no_duplican_la_vacante(): void
    {
        // El comando corre cada 5 minutos: sin esto abriría una vacante nueva
        // en cada pasada por el mismo turno.
        $this->turno(Carbon::today()->toDateString(), '06:00', '14:00');

        $this->servicio->detectar(Carbon::today()->setTime(7, 0));
        $this->servicio->detectar(Carbon::today()->setTime(7, 5));

        $this->assertSame(1, TurnoVacante::count());
    }

    public function test_detectar_no_avisa_a_nadie_todavia(): void
    {
        // Un marcaje puede llegar tarde por falta de señal: declarar la falta es
        // decisión de una persona, no del reloj.
        $this->turno(Carbon::today()->toDateString(), '06:00', '14:00');
        $this->servicio->detectar(Carbon::today()->setTime(7, 0));

        $vacante = TurnoVacante::first();

        $this->assertFalse($vacante->admitePostulaciones());
        $this->assertNull($vacante->tv_abierta_en);
    }

    // ── A quién se le ofrece ──

    public function test_solo_se_ofrece_a_quien_pidio_turnos_extra(): void
    {
        $elegibles = $this->servicio->elegibles($this->vacanteAbierta())->pluck('id')->all();

        $this->assertContains($this->disponible, $elegibles);
        $this->assertNotContains($this->noQuiereExtras, $elegibles, 'No activó "quiero turnos extra"');
    }

    public function test_no_se_le_ofrece_al_que_falto(): void
    {
        $elegibles = $this->servicio->elegibles($this->vacanteAbierta())->pluck('id')->all();

        $this->assertNotContains($this->ausente, $elegibles);
    }

    public function test_no_se_le_ofrece_a_quien_ya_tiene_turno_a_esa_hora(): void
    {
        $this->turno(Carbon::today()->toDateString(), '06:00', '14:00', $this->disponible);

        $elegibles = $this->servicio->elegibles($this->vacanteAbierta())->pluck('id')->all();

        $this->assertNotContains($this->disponible, $elegibles);
    }

    public function test_no_se_le_ofrece_a_quien_quedaria_sin_descanso(): void
    {
        // Sale a las 02:00 y el turno empieza a las 06:00: cuatro horas.
        $this->turno(Carbon::yesterday()->toDateString(), '18:00', '02:00', $this->disponible);

        $motivo = $this->servicio->motivoParaNoCubrir($this->vacanteAbierta(), $this->disponible);

        $this->assertStringContainsString('Descansaría', $motivo);
    }

    // ── Las dos olas ──

    public function test_la_primera_ola_no_sale_del_local(): void
    {
        $elegibles = $this->servicio->elegibles($this->vacanteAbierta())->pluck('id')->all();

        $this->assertContains($this->disponible, $elegibles);
        $this->assertNotContains($this->deLaCiudad, $elegibles, 'Todavía es la ola del local');
    }

    public function test_al_escalar_entra_el_resto_de_la_ciudad(): void
    {
        $vacante = $this->vacanteAbierta();
        $vacante->tv_abierta_en = Carbon::now()->subHour();
        $vacante->save();

        $this->assertSame(1, $this->servicio->escalarAlcance());

        $elegibles = $this->servicio->elegibles($vacante->fresh())->pluck('id')->all();

        $this->assertContains($this->deLaCiudad, $elegibles);
    }

    public function test_un_local_con_acreditacion_propia_nunca_escala(): void
    {
        // Ofrecerle el turno a alguien que no puede entrar al sitio es peor que
        // no ofrecerlo: se presenta y lo paran en el control.
        DB::table('organizacion_institucion')
            ->where('ins_code', $this->local)
            ->update(['ins_requiere_acreditacion' => true]);

        $vacante = $this->vacanteAbierta();
        $vacante->tv_abierta_en = Carbon::now()->subHour();
        $vacante->save();

        $this->assertSame(0, $this->servicio->escalarAlcance());
        $this->assertSame(TurnoVacante::ALCANCE_LOCAL, $vacante->fresh()->tv_alcance);
    }

    public function test_no_escala_si_ya_hay_a_quien_elegir(): void
    {
        $vacante = $this->vacanteAbierta();
        $vacante->tv_abierta_en = Carbon::now()->subHour();
        $vacante->save();
        $this->servicio->postular($vacante, $this->disponible);

        $this->assertSame(0, $this->servicio->escalarAlcance());
    }

    // ── Postulación ──

    public function test_postularse_dos_veces_no_duplica(): void
    {
        $vacante = $this->vacanteAbierta();

        $this->servicio->postular($vacante, $this->disponible, 'uuid-1');
        $r = $this->servicio->postular($vacante, $this->disponible, 'uuid-1');

        $this->assertTrue($r['duplicado']);
        $this->assertSame(1, TurnoPostulacion::count());
    }

    public function test_una_vacante_cubierta_ya_no_admite_postulaciones(): void
    {
        $vacante = $this->vacanteAbierta();
        $this->servicio->postular($vacante, $this->disponible);
        $p = TurnoPostulacion::first();
        $this->servicio->confirmar($vacante, $p->tp_id, 99);

        $this->assertFalse($vacante->fresh()->admitePostulaciones());
    }

    public function test_retirarse_libera_la_postulacion(): void
    {
        $vacante = $this->vacanteAbierta();
        $this->servicio->postular($vacante, $this->disponible);

        $this->assertTrue($this->servicio->retirar($vacante, $this->disponible));
        $this->assertSame(TurnoPostulacion::RETIRADA, TurnoPostulacion::first()->tp_estado);
    }

    // ── Confirmación ──

    public function test_confirmar_crea_el_turno_de_cobertura(): void
    {
        $vacante = $this->vacanteAbierta();
        $this->servicio->postular($vacante, $this->disponible);

        $r = $this->servicio->confirmar($vacante, TurnoPostulacion::first()->tp_id, 99);

        $this->assertSame($this->disponible, (int) $r['turno']->tu_usu_id);
        $this->assertSame($this->puesto->pu_id, (int) $r['turno']->tu_puesto_id);
        $this->assertSame(TurnoVacante::CUBIERTA, $r['vacante']->tv_estado);
    }

    public function test_el_turno_de_cobertura_no_pertenece_a_ninguna_plantilla(): void
    {
        // Si quedara marcado como generado por la plantilla, republicar el
        // cuadrante lo borraría y el puesto volvería a quedar vacío.
        $vacante = $this->vacanteAbierta();
        $this->servicio->postular($vacante, $this->disponible);

        $r = $this->servicio->confirmar($vacante, TurnoPostulacion::first()->tp_id, 99);

        $this->assertNull($r['turno']->tu_plantilla_id);
    }

    public function test_cubrir_la_falta_no_borra_la_falta(): void
    {
        $turno = $this->turno(Carbon::today()->toDateString(), '06:00', '14:00');
        $vacante = $this->vacanteAbierta($turno);
        $this->servicio->postular($vacante, $this->disponible);

        $this->servicio->confirmar($vacante, TurnoPostulacion::first()->tp_id, 99);

        // El turno original sigue siendo del que faltó, marcado como ausencia.
        $original = $turno->fresh();
        $this->assertSame($this->ausente, (int) $original->tu_usu_id);
        $this->assertSame('ausente', $original->tu_estado);
        $this->assertSame(2, Turno::count(), 'La falta y la cobertura son dos registros');
    }

    public function test_confirmar_descarta_a_los_demas_postulantes(): void
    {
        $vacante = $this->vacanteAbierta();
        $this->servicio->postular($vacante, $this->disponible);
        $this->servicio->postular($vacante, $this->otroDisponible);

        $elegido = TurnoPostulacion::where('tp_usu_id', $this->disponible)->first();
        $this->servicio->confirmar($vacante, $elegido->tp_id, 99);

        $this->assertSame(
            TurnoPostulacion::RECHAZADA,
            TurnoPostulacion::where('tp_usu_id', $this->otroDisponible)->first()->tp_estado
        );
    }

    public function test_no_se_puede_confirmar_dos_veces(): void
    {
        $vacante = $this->vacanteAbierta();
        $this->servicio->postular($vacante, $this->disponible);
        $this->servicio->postular($vacante, $this->otroDisponible);
        $primera = TurnoPostulacion::where('tp_usu_id', $this->disponible)->first();
        $segunda = TurnoPostulacion::where('tp_usu_id', $this->otroDisponible)->first();

        $this->servicio->confirmar($vacante, $primera->tp_id, 99);

        $this->expectException(\RuntimeException::class);
        $this->servicio->confirmar($vacante->fresh(), $segunda->tp_id, 99);
    }

    public function test_no_se_confirma_a_quien_ya_no_puede_cubrir(): void
    {
        // Se postuló temprano y después le programaron otro turno encima.
        $vacante = $this->vacanteAbierta();
        $this->servicio->postular($vacante, $this->disponible);
        $this->turno(Carbon::today()->toDateString(), '06:00', '14:00', $this->disponible);

        $this->expectException(\RuntimeException::class);
        $this->servicio->confirmar($vacante, TurnoPostulacion::first()->tp_id, 99);
    }

    // ── Integración con el cuadrante ──

    public function test_republicar_el_cuadrante_no_borra_el_turno_de_cobertura(): void
    {
        $plantilla = Plantilla::create(['pl_ins_code' => $this->local, 'pl_nombre' => 'Cuadrante']);
        $franja = PlantillaFranja::create([
            'pf_pl_id' => $plantilla->pl_id, 'pf_puesto_id' => $this->puesto->pu_id,
            'pf_dia_semana' => Carbon::today()->dayOfWeekIso,
            'pf_hora_inicio' => '06:00', 'pf_hora_fin' => '14:00',
        ]);
        PlantillaAsignacion::create(['pa_pf_id' => $franja->pf_id, 'pa_usu_id' => $this->ausente]);

        $servicioPlantilla = app(PlantillaTurnoService::class);
        $servicioPlantilla->generar($plantilla, Carbon::today(), Carbon::today());

        $turnoDelCuadrante = Turno::where('tu_plantilla_id', $plantilla->pl_id)->first();
        $vacante = $this->vacanteAbierta($turnoDelCuadrante);
        $this->servicio->postular($vacante, $this->disponible);
        $cobertura = $this->servicio->confirmar($vacante, TurnoPostulacion::first()->tp_id, 99)['turno'];

        $servicioPlantilla->generar($plantilla, Carbon::today(), Carbon::today());

        $this->assertNotNull(
            Turno::find($cobertura->tu_id),
            'Republicar el cuadrante no puede dejar el puesto vacío otra vez'
        );
    }

    // ── Avisar con tiempo ──

    public function test_el_guardia_avisa_que_no_va_a_poder_cubrir(): void
    {
        // Avisar la noche anterior deja horas para conseguir reemplazo; que se
        // descubra a las 06:20 deja minutos.
        $turno = $this->turno(Carbon::tomorrow()->toDateString(), '06:00', '14:00');

        $r = $this->servicio->avisarAusencia($turno, 'enfermedad', 'Certificado médico');

        $this->assertFalse($r['duplicada']);
        $this->assertSame('enfermedad', $r['vacante']->tv_motivo);
        $this->assertSame('Certificado médico', $r['vacante']->tv_observaciones);
    }

    public function test_el_aviso_no_convoca_solo(): void
    {
        // Si bastara con avisar para que el sistema convoque a otro, cualquiera
        // podría soltar su turno sin que nadie lo revise.
        $turno = $this->turno(Carbon::tomorrow()->toDateString(), '06:00', '14:00');

        $r = $this->servicio->avisarAusencia($turno, 'permiso');

        $this->assertSame(TurnoVacante::DETECTADA, $r['vacante']->tv_estado);
        $this->assertFalse($r['vacante']->admitePostulaciones());
    }

    public function test_avisar_dos_veces_el_mismo_turno_no_abre_dos_vacantes(): void
    {
        $turno = $this->turno(Carbon::tomorrow()->toDateString(), '06:00', '14:00');

        $this->servicio->avisarAusencia($turno, 'enfermedad');
        $r = $this->servicio->avisarAusencia($turno, 'enfermedad');

        $this->assertTrue($r['duplicada']);
        $this->assertSame(1, TurnoVacante::count());
    }

    public function test_un_motivo_desconocido_se_registra_como_aviso(): void
    {
        // El motivo llega del cliente: no puede meter un valor arbitrario en la
        // columna y romper los filtros del panel.
        $turno = $this->turno(Carbon::tomorrow()->toDateString(), '06:00', '14:00');

        $r = $this->servicio->avisarAusencia($turno, 'cualquier-cosa');

        $this->assertSame('aviso', $r['vacante']->tv_motivo);
    }

    public function test_al_cubrir_un_aviso_queda_escrito_el_motivo(): void
    {
        $turno = $this->turno(Carbon::today()->toDateString(), '06:00', '14:00');
        $vacante = $this->servicio->avisarAusencia($turno, 'enfermedad')['vacante'];
        $this->servicio->abrir($vacante, 99);
        $this->servicio->postular($vacante, $this->disponible);

        $this->servicio->confirmar($vacante, TurnoPostulacion::first()->tp_id, 99);

        $this->assertStringContainsString('Enfermedad', $turno->fresh()->tu_observaciones);
    }

    // ── Baja del guardia ──

    public function test_la_baja_libera_todos_los_turnos_futuros(): void
    {
        // Una renuncia no es la falta de un día: sin esto, cada mañana alguien
        // descubriría el puesto vacío otra vez.
        $this->turno(Carbon::today()->addDays(1)->toDateString(), '06:00', '14:00');
        $this->turno(Carbon::today()->addDays(2)->toDateString(), '06:00', '14:00');
        $this->turno(Carbon::today()->addDays(3)->toDateString(), '06:00', '14:00');

        $r = $this->servicio->darDeBaja($this->ausente, Carbon::today(), 'Renuncia', 99);

        $this->assertSame(3, $r['vacantes']);
        $this->assertSame(3, TurnoVacante::where('tv_motivo', TurnoVacante::BAJA)->count());
    }

    public function test_la_baja_no_toca_los_turnos_ya_trabajados(): void
    {
        $pasado = $this->turno(Carbon::today()->subDays(3)->toDateString(), '06:00', '14:00');
        $pasado->tu_estado = 'completado';
        $pasado->tu_marcada_entrada = Carbon::today()->subDays(3)->setTime(6, 0);
        $pasado->save();

        $this->servicio->darDeBaja($this->ausente, Carbon::today(), 'Renuncia', 99);

        $this->assertSame('completado', $pasado->fresh()->tu_estado);
        $this->assertSame(0, TurnoVacante::count());
    }

    public function test_la_baja_cierra_su_vigencia_en_el_cuadrante(): void
    {
        // Se cierra la vigencia en vez de borrar la asignación: el histórico de
        // quién cubría qué no se toca.
        $plantilla = Plantilla::create(['pl_ins_code' => $this->local, 'pl_nombre' => 'Cuadrante']);
        $franja = PlantillaFranja::create([
            'pf_pl_id' => $plantilla->pl_id, 'pf_puesto_id' => $this->puesto->pu_id,
            'pf_dia_semana' => 1, 'pf_hora_inicio' => '06:00', 'pf_hora_fin' => '14:00',
        ]);
        PlantillaAsignacion::create(['pa_pf_id' => $franja->pf_id, 'pa_usu_id' => $this->ausente]);

        $this->servicio->darDeBaja($this->ausente, Carbon::today(), 'Renuncia', 99);

        $asignacion = PlantillaAsignacion::first();
        $this->assertNotNull($asignacion->pa_hasta);
        $this->assertSame(
            Carbon::yesterday()->toDateString(),
            Carbon::parse($asignacion->pa_hasta)->toDateString()
        );
    }

    public function test_la_baja_no_le_cuenta_ausencias_al_que_se_fue(): void
    {
        // Marcar cada turno restante como ausencia le cargaría una falta por día
        // que ya no trabajaba, y ensuciaría el cumplimiento del local.
        $turno = $this->turno(Carbon::today()->addDay()->toDateString(), '06:00', '14:00');
        $this->servicio->darDeBaja($this->ausente, Carbon::today(), 'Renuncia', 99);

        $vacante = TurnoVacante::first();
        $this->servicio->postular($vacante, $this->disponible);
        $this->servicio->confirmar($vacante, TurnoPostulacion::first()->tp_id, 99);

        $turno = $turno->fresh();
        $this->assertNotSame('ausente', $turno->tu_estado);
        $this->assertFalse((bool) $turno->tu_state, 'El turno del que se fue queda desactivado');
    }

    public function test_las_vacantes_de_una_baja_ya_nacen_ofrecidas(): void
    {
        // La baja ya la confirmó quien la registró: no hace falta que otra
        // persona vuelva a confirmar turno por turno.
        $this->turno(Carbon::today()->addDay()->toDateString(), '06:00', '14:00');

        $this->servicio->darDeBaja($this->ausente, Carbon::today(), 'Renuncia', 99);

        $this->assertSame(TurnoVacante::ABIERTA, TurnoVacante::first()->tv_estado);
    }

    // ── Cierre ──

    public function test_vencer_cierra_las_que_ya_no_se_pueden_cubrir(): void
    {
        $turno = $this->turno(Carbon::yesterday()->toDateString(), '06:00', '14:00');
        $this->vacanteAbierta($turno);

        $this->assertSame(1, $this->servicio->vencer());
        $this->assertSame(TurnoVacante::VENCIDA, TurnoVacante::first()->tv_estado);
    }

    public function test_cancelar_cierra_la_vacante_y_sus_postulaciones(): void
    {
        $vacante = $this->vacanteAbierta();
        $this->servicio->postular($vacante, $this->disponible);

        $this->servicio->cancelar($vacante, 99, 'El guardia apareció');

        $this->assertSame(TurnoVacante::CANCELADA, $vacante->fresh()->tv_estado);
        $this->assertSame(TurnoPostulacion::RECHAZADA, TurnoPostulacion::first()->tp_estado);
    }
}
