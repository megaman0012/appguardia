<?php

namespace Tests\Unit;

use App\Filament\Resources\PlantillaResource\Pages\EditPlantilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Administracion\Models\Plantilla;
use Modules\Administracion\Models\PlantillaFranja;
use Modules\Administracion\Models\Puesto;
use Tests\TestCase;

/**
 * El botón de importar, ejercido desde la página real del panel.
 *
 * El servicio ya está probado aparte; lo que se verifica aquí es el cableado:
 * que el archivo que sube el líder llegue al servicio como una ruta legible y
 * no como un objeto de subida temporal, que no quede basura en el disco, y que
 * el Supervisor no vea el botón (para él el cuadrante es de solo lectura).
 */
class PlantillaImportPanelTest extends TestCase
{
    use RefreshDatabase;

    private Plantilla $plantilla;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Ana Torres', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'a@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // El local va en Ecuador porque el Líder Operativo tiene alcance por
        // país: uno sin ciudad no lo vería ni para importarle nada.
        $ecuador = (int) DB::table('pais')->where('pa_iso2', 'EC')->value('pa_id');
        $provincia = DB::table('provincia')->where('pr_pa_id', $ecuador)->value('pr_id');
        $ciudad = DB::table('ciudad')->where('cd_pr_id', $provincia)->value('cd_id')
            ?: DB::table('ciudad')->insertGetId([
                'cd_pr_id' => $provincia, 'cd_nombre' => 'Ciudad de prueba', 'cd_estado' => true,
                'created_at' => now(), 'updated_at' => now(),
            ], 'cd_id');

        DB::table('user_has_pais')->updateOrInsert(
            ['up_usu_id' => 1, 'up_pa_id' => $ecuador],
            ['up_estado' => true]
        );

        $local = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Terminal', 'ins_estado' => true, 'ins_cd_id' => $ciudad,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        DB::table('user_has_institucion')->insert([
            'ui_usu_id' => 1, 'ui_ins_code' => $local, 'ui_state' => 1,
        ]);

        Puesto::create(['pu_ins_code' => $local, 'pu_nombre' => 'Garita', 'pu_estado' => true]);

        $this->plantilla = Plantilla::create(['pl_ins_code' => $local, 'pl_nombre' => 'Cuadrante']);

        Session::put('usuID', 1);
        Session::put('usuPF', 'Lider Operativo');
    }

    private function pagina()
    {
        return Livewire::test(EditPlantilla::class, ['record' => $this->plantilla->pl_id]);
    }

    private function subir(string $contenido): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('cuadrante.csv', $contenido);
    }

    public function test_el_archivo_subido_llega_al_servicio_y_carga_el_cuadrante(): void
    {
        $this->pagina()
            ->call('mountAction', 'importar')
            ->set('mountedActionData.archivo', $this->subir(
                "cedula;puesto;dia;hora_inicio;hora_fin\n1111111111;Garita;LUN;06:00;14:00\n"
            ))
            ->call('callMountedAction')
            ->assertHasNoErrors();

        $this->assertSame(1, PlantillaFranja::where('pf_pl_id', $this->plantilla->pl_id)->count());
    }

    public function test_el_archivo_no_queda_guardado_en_el_servidor(): void
    {
        $this->pagina()
            ->call('mountAction', 'importar')
            ->set('mountedActionData.archivo', $this->subir(
                "cedula;puesto;dia;hora_inicio;hora_fin\n1111111111;Garita;LUN;06:00;14:00\n"
            ))
            ->call('callMountedAction');

        // Ya quedó volcado en la plantilla: conservarlo solo acumularía copias
        // del cuadrante en disco.
        $this->assertEmpty(Storage::disk('local')->files('importaciones'));
    }

    public function test_un_archivo_con_errores_no_escribe_nada(): void
    {
        $this->pagina()
            ->call('mountAction', 'importar')
            ->set('mountedActionData.archivo', $this->subir(
                "cedula;puesto;dia;hora_inicio;hora_fin\n9999999999;Garita;LUN;06:00;14:00\n"
            ))
            ->call('callMountedAction');

        $this->assertSame(0, PlantillaFranja::where('pf_pl_id', $this->plantilla->pl_id)->count());
    }

    public function test_el_boton_de_descarga_entrega_el_modelo_en_csv(): void
    {
        // Sin modal, Filament ejecuta la acción dentro del propio mountAction:
        // ahí es donde Livewire adjunta la descarga.
        $r = $this->pagina()->call('mountAction', 'descargarModelo');

        $r->assertFileDownloaded('cuadrante-' . $this->plantilla->pl_id . '.csv');

        $csv = base64_decode(data_get($r->payload, 'effects.download.content'));

        // Con BOM: sin él, Excel abre "ANDÉN" como "ANDÃ‰N" y el archivo vuelve
        // con los puestos irreconocibles.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Garita', $csv);
    }

    public function test_el_lider_ve_los_botones_de_carga(): void
    {
        $this->pagina()
            ->assertSee('Importar cuadrante')
            ->assertSee('Descargar modelo');
    }

    public function test_el_supervisor_no_puede_abrir_el_editor_del_cuadrante(): void
    {
        // Para él el cuadrante es de solo lectura, y Filament no deja ni entrar
        // a la pantalla de edición: no alcanza con ocultarle los botones.
        Session::put('usuPF', 'Supervisor');

        $this->pagina()->assertForbidden();
    }
}
