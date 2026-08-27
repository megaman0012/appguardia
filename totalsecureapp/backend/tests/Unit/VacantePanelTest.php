<?php

namespace Tests\Unit;

use App\Filament\Resources\VacanteResource\Pages\ListVacantes;
use App\Services\VacanteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Modules\Administracion\Models\Puesto;
use Modules\Administracion\Models\Turno;
use Modules\Administracion\Models\TurnoPostulacion;
use Modules\Administracion\Models\TurnoVacante;
use Tests\TestCase;

/**
 * La pantalla de cobertura, ejercida como la usa el supervisor.
 *
 * El servicio ya está probado aparte; acá se verifica que los botones estén
 * cableados a él, que el alcance por local siga valiendo (un supervisor no puede
 * ver ni resolver la falta de un local ajeno) y que el perfil que no opera no
 * llegue a la pantalla.
 */
class VacantePanelTest extends TestCase
{
    use RefreshDatabase;

    private int $local;
    private int $localAjeno;
    private int $supervisor = 1;
    private int $guardia = 2;

    protected function setUp(): void
    {
        parent::setUp();

        // La hora a la que se corre el suite no puede cambiar el resultado: un
        // turno de 06:00 a 14:00 "de hoy" ya terminó si las pruebas corren a las
        // 17:00, y media docena de casos empezaban a fallar solos por la tarde.
        Carbon::setTestNow(Carbon::parse('2026-09-01 07:00:00'));

        // El Líder Operativo tiene alcance por país, así que los locales
        // necesitan ciudad para que los vea.
        $ecuador = (int) DB::table('pais')->where('pa_iso2', 'EC')->value('pa_id');
        $provincia = DB::table('provincia')->where('pr_pa_id', $ecuador)->value('pr_id');
        $ciudad = DB::table('ciudad')->where('cd_pr_id', $provincia)->value('cd_id')
            ?: DB::table('ciudad')->insertGetId([
                'cd_pr_id' => $provincia, 'cd_nombre' => 'Ciudad de prueba', 'cd_estado' => true,
                'created_at' => now(), 'updated_at' => now(),
            ], 'cd_id');

        $this->local      = $this->crearLocal('Terminal', $ciudad);
        $this->localAjeno = $this->crearLocal('Local ajeno', $ciudad);

        $this->crearUsuario($this->supervisor, '1111111111', 'Sofia Supervisora', null);
        $this->crearUsuario($this->guardia, '2222222222', 'Ruth Volante', 'Vigilante');

        DB::table('user_has_institucion')->insert([
            'ui_usu_id' => $this->supervisor, 'ui_ins_code' => $this->local, 'ui_state' => 1,
        ]);
        DB::table('user_has_institucion')->insert([
            'ui_usu_id' => $this->guardia, 'ui_ins_code' => $this->local, 'ui_state' => 1,
        ]);
        DB::table('users')->where('id', $this->guardia)->update(['usu_acepta_extras' => true]);

        Session::put('usuID', $this->supervisor);
        Session::put('usuPF', 'Supervisor');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function crearLocal(string $nombre, $ciudadId): int
    {
        return DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => $nombre, 'ins_estado' => true, 'ins_cd_id' => $ciudadId,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');
    }

    /** Pasa la sesión al perfil de Líder Operativo, con su país asignado. */
    private function comoLider(): void
    {
        $ecuador = (int) DB::table('pais')->where('pa_iso2', 'EC')->value('pa_id');

        DB::table('user_has_pais')->updateOrInsert(
            ['up_usu_id' => $this->supervisor, 'up_pa_id' => $ecuador],
            ['up_estado' => true]
        );

        Session::put('usuPF', 'Lider Operativo');
    }

    private function crearUsuario(int $id, string $cedula, string $nombre, ?string $rol): void
    {
        DB::table('users')->updateOrInsert(['id' => $id], [
            'usu_cedula' => $cedula, 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => $nombre, 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => $cedula . '@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($rol) {
            $rolId = DB::table('roles')->where('name', $rol)->value('id');
            DB::table('user_has_roles')->updateOrInsert(
                ['user_id' => $id, 'role_id' => $rolId],
                ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
            );
        }
    }

    private function vacante(?int $local = null): TurnoVacante
    {
        $local = $local ?? $this->local;

        $puesto = Puesto::create([
            'pu_ins_code' => $local, 'pu_nombre' => 'Garita ' . $local, 'pu_estado' => true,
        ]);

        $turno = Turno::create([
            'tu_ins_code' => $local, 'tu_usu_id' => $this->supervisor,
            'tu_puesto_id' => $puesto->pu_id,
            'tu_fecha' => Carbon::today()->toDateString(),
            'tu_hora_inicio_prevista' => '06:00', 'tu_hora_fin_prevista' => '14:00',
            'tu_estado' => 'programado', 'tu_state' => true,
        ]);

        return app(VacanteService::class)->crearDesdeTurno($turno);
    }

    private function pantalla()
    {
        return Livewire::test(ListVacantes::class);
    }

    // ── Lo que ve cada perfil ──

    public function test_el_supervisor_ve_la_vacante_de_su_local(): void
    {
        // La vacante se crea antes de montar la pantalla: al revés, PHP evalúa
        // primero el componente y la tabla se renderiza vacía.
        $vacante = $this->vacante();

        $this->pantalla()->assertCanSeeTableRecords([$vacante]);
    }

    public function test_no_ve_la_falta_de_un_local_ajeno(): void
    {
        // Su alcance es por local: una falta de otro sitio no es asunto suyo, y
        // resolverla sería decidir sobre gente que no supervisa.
        $propia = $this->vacante();
        $ajena  = $this->vacante($this->localAjeno);

        $this->pantalla()
            ->assertCanSeeTableRecords([$propia])
            ->assertCanNotSeeTableRecords([$ajena]);
    }

    public function test_el_vigilante_no_entra_a_la_pantalla(): void
    {
        // Su lado de esto es la app, donde se postula; no resuelve coberturas.
        Session::put('usuPF', 'Vigilante');

        $this->pantalla()->assertForbidden();
    }

    // ── Las acciones ──

    public function test_confirmar_la_falta_la_pone_en_oferta(): void
    {
        $vacante = $this->vacante();

        // La acción pide confirmación, así que montarla abre el modal y la
        // ejecución es el paso siguiente.
        $this->pantalla()
            ->call('mountTableAction', 'abrir', $vacante->tv_id)
            ->call('callMountedTableAction');

        $vacante = $vacante->fresh();
        $this->assertSame(TurnoVacante::ABIERTA, $vacante->tv_estado);
        $this->assertSame($this->supervisor, (int) $vacante->tv_abierta_por);
        $this->assertTrue($vacante->admitePostulaciones());
    }

    public function test_el_lider_elige_quien_cubre_y_se_crea_el_turno(): void
    {
        $vacante = $this->vacante();
        $servicio = app(VacanteService::class);
        $servicio->abrir($vacante, $this->supervisor);
        $servicio->postular($vacante, $this->guardia);
        $postulacion = TurnoPostulacion::first();
        $this->comoLider();

        $this->pantalla()
            ->call('mountTableAction', 'confirmarCobertura', $vacante->tv_id)
            ->set('mountedTableActionData.tp_id', $postulacion->tp_id)
            ->call('callMountedTableAction');

        $this->assertSame(TurnoVacante::CUBIERTA, $vacante->fresh()->tv_estado);
        $this->assertSame(
            $this->guardia,
            (int) Turno::where('tu_observaciones', 'like', 'Cobertura%')->first()->tu_usu_id
        );
    }

    public function test_la_consola_tambien_asigna_la_cobertura(): void
    {
        // Una falta a las tres de la mañana no espera a que el líder despierte.
        // La Consola atiende 24/7 y ve toda la operación, sin filtro de local.
        $vacante = $this->vacante($this->localAjeno);
        $servicio = app(VacanteService::class);
        $servicio->abrir($vacante, $this->supervisor);
        DB::table('user_has_institucion')->insert([
            'ui_usu_id' => $this->guardia, 'ui_ins_code' => $this->localAjeno, 'ui_state' => 1,
        ]);
        $servicio->postular($vacante, $this->guardia);
        Session::put('usuPF', 'Consola');

        $this->pantalla()
            ->call('mountTableAction', 'confirmarCobertura', $vacante->tv_id)
            ->set('mountedTableActionData.tp_id', TurnoPostulacion::first()->tp_id)
            ->call('callMountedTableAction');

        $this->assertSame(TurnoVacante::CUBIERTA, $vacante->fresh()->tv_estado);
    }

    public function test_la_consola_ve_las_faltas_de_cualquier_local(): void
    {
        $ajena = $this->vacante($this->localAjeno);
        Session::put('usuPF', 'Consola');

        $this->pantalla()->assertCanSeeTableRecords([$ajena]);
    }

    public function test_el_supervisor_no_asigna_la_cobertura(): void
    {
        // Cubrir un puesto ante el cliente es responsabilidad del Líder
        // Operativo. El Supervisor confirma la falta y ve a los postulados,
        // pero la asignación no es suya.
        $vacante = $this->vacante();
        $servicio = app(VacanteService::class);
        $servicio->abrir($vacante, $this->supervisor);
        $servicio->postular($vacante, $this->guardia);

        $this->pantalla()
            ->call('mountTableAction', 'confirmarCobertura', $vacante->tv_id)
            ->set('mountedTableActionData.tp_id', TurnoPostulacion::first()->tp_id)
            ->call('callMountedTableAction');

        $this->assertSame(TurnoVacante::ABIERTA, $vacante->fresh()->tv_estado);
        $this->assertSame(0, Turno::where('tu_observaciones', 'like', 'Cobertura%')->count());
    }

    public function test_el_supervisor_si_confirma_la_falta(): void
    {
        // Lo que sí es suyo: es quien está mirando el turno y puede llamar al
        // guardia para saber si viene o no.
        $vacante = $this->vacante();

        $this->pantalla()
            ->call('mountTableAction', 'abrir', $vacante->tv_id)
            ->call('callMountedTableAction');

        $this->assertSame(TurnoVacante::ABIERTA, $vacante->fresh()->tv_estado);
    }

    public function test_cerrar_la_vacante_cuando_el_guardia_aparece(): void
    {
        $vacante = $this->vacante();

        $this->pantalla()
            ->call('mountTableAction', 'cancelar', $vacante->tv_id)
            ->call('callMountedTableAction');

        $this->assertSame(TurnoVacante::CANCELADA, $vacante->fresh()->tv_estado);
    }

    public function test_una_vacante_ya_cubierta_no_se_puede_volver_a_ofrecer(): void
    {
        $vacante = $this->vacante();
        $servicio = app(VacanteService::class);
        $servicio->abrir($vacante, $this->supervisor);
        $servicio->postular($vacante, $this->guardia);
        $servicio->confirmar($vacante, TurnoPostulacion::first()->tp_id, $this->supervisor);

        // La acción no está visible, y forzarla tampoco debe reabrirla.
        $this->pantalla()
            ->call('mountTableAction', 'abrir', $vacante->tv_id)
            ->call('callMountedTableAction');

        $this->assertSame(TurnoVacante::CUBIERTA, $vacante->fresh()->tv_estado);
    }
}
