<?php

namespace Tests\Unit;

use App\Filament\Resources\NovedadResource;
use App\Support\PerfilPanel;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
use Modules\Administracion\Models\Novedad;
use Tests\TestCase;

/**
 * Registrar una novedad desde el panel, con su foto.
 *
 * ⚠️ `NovedadResource::form()` devolvia un schema **vacio** -- `$schema->schema([])`
 * -- y sin embargo el recurso tenia registradas sus paginas de crear y editar.
 * El resultado es el que se reporto: «en la web guardo los registros pero no se
 * guardo las fotos». No fallaba la subida: **no habia ningun campo en el
 * formulario**, asi que se guardaba una novedad en blanco.
 *
 * Es el tipo de hueco que ningun test veia porque `PanelSeDibujaTest` comprueba
 * que las paginas cargan, y una pagina con un formulario vacio carga perfecto.
 */
class NovedadDesdeElPanelTest extends TestCase
{
    use RefreshDatabase;

    private int $localPropio;
    private int $localAjeno;
    private array $archivosCreados = [];

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Supervisor Uno', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 's@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->localPropio = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Local propio', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        $this->localAjeno = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Local ajeno', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        DB::table('user_has_institucion')->insert([
            'ui_usu_id' => 1, 'ui_ins_code' => $this->localPropio, 'ui_state' => 1,
            'ui_created_at' => now(), 'ui_updated_at' => now(),
        ]);

        Session::put('usuID', 1);
        Session::put('usuPF', PerfilPanel::ADMINISTRADOR);
    }

    protected function tearDown(): void
    {
        foreach ($this->archivosCreados as $ruta) {
            File::delete($ruta);
        }

        parent::tearDown();
    }

    /** @return string[] nombres de los campos del formulario */
    private function camposDelFormulario(): array
    {
        $schema = NovedadResource::form(Schema::make());

        return array_values(array_filter(array_map(
            fn ($componente) => method_exists($componente, 'getName') ? $componente->getName() : null,
            $schema->getComponents()
        )));
    }

    public function test_el_formulario_no_esta_vacio(): void
    {
        $this->assertNotEmpty(
            $this->camposDelFormulario(),
            'El formulario de novedades no tiene campos: se guardarian registros en blanco.'
        );
    }

    public function test_el_formulario_tiene_el_campo_de_foto(): void
    {
        $this->assertContains('nv_foto', $this->camposDelFormulario());
    }

    public function test_el_formulario_pide_local_observacion_y_fecha(): void
    {
        $campos = $this->camposDelFormulario();

        $this->assertContains('nv_ins_code', $campos);
        $this->assertContains('nv_observacion', $campos);
        $this->assertContains('nv_fecha_hora', $campos);
    }

    /** Una foto guardada como ruta relativa, que es lo que hace el panel. */
    public function test_la_foto_guardada_con_ruta_relativa_se_encuentra(): void
    {
        $relativa = 'novedad/' . now()->format('Y/m/d') . '/prueba_panel.jpg';
        $absoluta = public_path('images/' . $relativa);

        File::ensureDirectoryExists(dirname($absoluta));
        File::put($absoluta, 'contenido de prueba');
        $this->archivosCreados[] = $absoluta;

        $novedad = Novedad::create([
            'nv_usu_id' => 1, 'nv_ins_code' => $this->localPropio,
            'nv_observacion' => 'Con foto', 'nv_fecha_hora' => now(),
            'nv_foto' => $relativa, 'nv_estado' => 1,
        ]);

        $this->assertStringContainsString($relativa, $novedad->imagen_url);
    }

    /**
     * Y la forma vieja --solo el nombre, con la carpeta reconstruida desde
     * `nv_fecha_hora`-- tiene que seguir funcionando: asi estan guardadas todas
     * las novedades que ya existen.
     */
    public function test_la_foto_guardada_solo_con_el_nombre_sigue_funcionando(): void
    {
        $fecha = now();
        $absoluta = public_path('images/novedad/' . $fecha->format('Y/m/d') . '/vieja.jpg');

        File::ensureDirectoryExists(dirname($absoluta));
        File::put($absoluta, 'contenido de prueba');
        $this->archivosCreados[] = $absoluta;

        $novedad = Novedad::create([
            'nv_usu_id' => 1, 'nv_ins_code' => $this->localPropio,
            'nv_observacion' => 'Foto vieja', 'nv_fecha_hora' => $fecha,
            'nv_foto' => 'vieja.jpg', 'nv_estado' => 1,
        ]);

        $this->assertStringContainsString('vieja.jpg', $novedad->imagen_url);
    }

    public function test_una_novedad_sin_foto_no_inventa_una_url(): void
    {
        $novedad = Novedad::create([
            'nv_usu_id' => 1, 'nv_ins_code' => $this->localPropio,
            'nv_observacion' => 'Sin foto', 'nv_fecha_hora' => now(),
            'nv_estado' => 1,
        ]);

        $this->assertNull($novedad->imagen_url);
    }

    public function test_una_foto_que_no_esta_en_disco_no_devuelve_url(): void
    {
        $novedad = Novedad::create([
            'nv_usu_id' => 1, 'nv_ins_code' => $this->localPropio,
            'nv_observacion' => 'Foto perdida', 'nv_fecha_hora' => now(),
            'nv_foto' => 'novedad/2020/01/01/no_existe.jpg', 'nv_estado' => 1,
        ]);

        $this->assertNull($novedad->imagen_url);
    }
}
