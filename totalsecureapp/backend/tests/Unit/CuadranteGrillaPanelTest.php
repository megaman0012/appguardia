<?php

namespace Tests\Unit;

use App\Filament\Resources\PlantillaResource\Pages\VerGrilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Modules\Administracion\Models\Plantilla;
use Modules\Administracion\Models\PlantillaAsignacion;
use Modules\Administracion\Models\PlantillaFranja;
use Modules\Administracion\Models\Puesto;
use Tests\TestCase;

/**
 * La grilla en el panel.
 *
 * Su razón de ser es doble: mostrar el cuadrante de un vistazo, y darle al
 * Supervisor la lectura que no tenía. Hasta ahora veía el listado de cuadrantes
 * pero no podía abrir ninguno, porque la única vista del detalle era la de
 * edición y esa la tiene cerrada.
 */
class CuadranteGrillaPanelTest extends TestCase
{
    use RefreshDatabase;

    private int $local;
    private int $localAjeno;
    private int $usuario = 1;
    private int $ecuador;
    private Plantilla $plantilla;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => $this->usuario], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Ana Torres', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'a@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // El Líder Operativo tiene alcance por país, así que el local necesita
        // ciudad para entrar en su vista.
        $this->ecuador = (int) DB::table('pais')->where('pa_iso2', 'EC')->value('pa_id');
        $provincia = DB::table('provincia')->where('pr_pa_id', $this->ecuador)->value('pr_id');
        $ciudad = DB::table('ciudad')->where('cd_pr_id', $provincia)->value('cd_id')
            ?: DB::table('ciudad')->insertGetId([
                'cd_pr_id' => $provincia, 'cd_nombre' => 'Ciudad de prueba', 'cd_estado' => true,
                'created_at' => now(), 'updated_at' => now(),
            ], 'cd_id');

        $this->local      = $this->crearLocal('Terminal', $ciudad);
        $this->localAjeno = $this->crearLocal('Local ajeno', $ciudad);

        DB::table('user_has_institucion')->insert([
            'ui_usu_id' => $this->usuario, 'ui_ins_code' => $this->local, 'ui_state' => 1,
        ]);

        $puesto = Puesto::create([
            'pu_ins_code' => $this->local, 'pu_nombre' => 'Garita', 'pu_estado' => true,
        ]);

        $this->plantilla = Plantilla::create([
            'pl_ins_code' => $this->local, 'pl_nombre' => 'Cuadrante regular',
        ]);

        $franja = PlantillaFranja::create([
            'pf_pl_id' => $this->plantilla->pl_id, 'pf_puesto_id' => $puesto->pu_id,
            'pf_dia_semana' => 1, 'pf_hora_inicio' => '06:00', 'pf_hora_fin' => '14:00',
        ]);
        PlantillaAsignacion::create(['pa_pf_id' => $franja->pf_id, 'pa_usu_id' => $this->usuario]);

        Session::put('usuID', $this->usuario);
        Session::put('usuPF', 'Supervisor');
    }

    private function crearLocal(string $nombre, $ciudadId = null): int
    {
        return DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => $nombre, 'ins_estado' => true, 'ins_cd_id' => $ciudadId,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');
    }

    private function comoLider(): void
    {
        DB::table('user_has_pais')->updateOrInsert(
            ['up_usu_id' => $this->usuario, 'up_pa_id' => $this->ecuador],
            ['up_estado' => true]
        );

        Session::put('usuPF', 'Lider Operativo');
    }

    private function grilla(?int $id = null)
    {
        return Livewire::test(VerGrilla::class, ['record' => $id ?? $this->plantilla->pl_id]);
    }

    // ── El agujero que esta pantalla tapa ──

    public function test_el_supervisor_por_fin_puede_ver_el_cuadrante(): void
    {
        $this->grilla()
            ->assertSuccessful()
            ->assertSee('Garita')
            ->assertSee('Ana Torres');
    }

    public function test_pero_sigue_sin_poder_editarlo(): void
    {
        // Para él el cuadrante es de solo lectura: consulta la operación, no la
        // define.
        $this->grilla()->assertDontSee('Editar franjas');
    }

    public function test_el_lider_ve_el_acceso_a_edicion(): void
    {
        $this->comoLider();

        $this->grilla()->assertSee('Editar franjas');
    }

    public function test_el_vigilante_no_entra(): void
    {
        Session::put('usuPF', 'Vigilante');

        $this->grilla()->assertForbidden();
    }

    public function test_no_se_puede_espiar_el_cuadrante_de_otro_local(): void
    {
        // El alcance vale también acá: la URL es adivinable, así que la pantalla
        // no puede confiar en que nadie escriba otro id.
        $ajena = Plantilla::create([
            'pl_ins_code' => $this->localAjeno, 'pl_nombre' => 'Cuadrante ajeno',
        ]);

        try {
            $this->grilla($ajena->pl_id);
            $this->fail('Debería haber sido imposible abrir el cuadrante de otro local');
        } catch (\Throwable $e) {
            // Livewire envuelve la excepción al renderizar; lo que importa es que
            // el registro no se resuelva.
            $this->assertStringContainsString('No query results', $e->getMessage());
        }
    }

    // ── Lo que la grilla muestra ──

    public function test_marca_en_pantalla_la_franja_sin_guardia(): void
    {
        PlantillaFranja::create([
            'pf_pl_id' => $this->plantilla->pl_id,
            'pf_puesto_id' => Puesto::first()->pu_id,
            'pf_dia_semana' => 3, 'pf_hora_inicio' => '06:00', 'pf_hora_fin' => '14:00',
        ]);

        $this->grilla()
            ->assertSee('Sin asignar')
            ->assertSee('Sin guardia asignado');
    }

    public function test_muestra_la_carga_semanal_de_cada_guardia(): void
    {
        $this->grilla()
            ->assertSee('Carga semanal por guardia')
            ->assertSee('8 h');
    }

    public function test_un_cuadrante_vacio_lo_dice_en_vez_de_romperse(): void
    {
        $vacia = Plantilla::create([
            'pl_ins_code' => $this->local, 'pl_nombre' => 'Sin franjas',
        ]);

        $this->grilla($vacia->pl_id)
            ->assertSuccessful()
            ->assertSee('todavía no tiene franjas');
    }
}
