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

        $this->local      = $this->crearLocal('Terminal');
        $this->localAjeno = $this->crearLocal('Local ajeno');

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

    private function crearLocal(string $nombre): int
    {
        return DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => $nombre, 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');
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

    public function test_elegir_quien_cubre_crea_el_turno_de_cobertura(): void
    {
        $vacante = $this->vacante();
        $servicio = app(VacanteService::class);
        $servicio->abrir($vacante, $this->supervisor);
        $servicio->postular($vacante, $this->guardia);
        $postulacion = TurnoPostulacion::first();

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
