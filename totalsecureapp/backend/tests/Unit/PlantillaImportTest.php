<?php

namespace Tests\Unit;

use App\Services\PlantillaImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Plantilla;
use Modules\Administracion\Models\PlantillaAsignacion;
use Modules\Administracion\Models\PlantillaFranja;
use Modules\Administracion\Models\Puesto;
use Tests\TestCase;

/**
 * Carga del cuadrante desde CSV.
 *
 * Lo que se prueba no es "leer un CSV" sino que un archivo hecho en el Excel de
 * una oficina —con acentos rotos, punto y coma, BOM y horas sin cero— entre sin
 * que nadie lo tenga que limpiar a mano, y que uno con datos malos no entre a
 * medias: o carga todo o no carga nada.
 */
class PlantillaImportTest extends TestCase
{
    use RefreshDatabase;

    private PlantillaImportService $servicio;
    private int $local;
    private int $otroLocal;
    private Plantilla $plantilla;
    private array $archivos = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([[1, '1111111111', 'Ana Torres'], [2, '2222222222', 'Luis Paez'], [3, '3333333333', 'Sin Vinculo']] as [$id, $ced, $nom]) {
            DB::table('users')->updateOrInsert(['id' => $id], [
                'usu_cedula' => $ced, 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
                'usu_nmbcom' => $nom, 'usu_ape1' => 'T', 'usu_ape2' => 'T',
                'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => $ced . '@e.com',
                'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->local     = $this->crearLocal('Terminal');
        $this->otroLocal = $this->crearLocal('Bodega');

        foreach ([1, 2] as $u) {
            DB::table('user_has_institucion')->insert([
                'ui_usu_id' => $u, 'ui_ins_code' => $this->local, 'ui_state' => 1,
            ]);
        }

        // Nombre con acento y mayusculas: asi lo escribe la gente en el panel.
        Puesto::create(['pu_ins_code' => $this->local, 'pu_nombre' => 'ANDÉN DE CARGA', 'pu_estado' => true]);
        Puesto::create(['pu_ins_code' => $this->local, 'pu_nombre' => 'Garita', 'pu_estado' => true]);
        Puesto::create(['pu_ins_code' => $this->otroLocal, 'pu_nombre' => 'Patio', 'pu_estado' => true]);

        $this->plantilla = Plantilla::create(['pl_ins_code' => $this->local, 'pl_nombre' => 'Cuadrante']);
        $this->servicio  = app(PlantillaImportService::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->archivos as $ruta) {
            @unlink($ruta);
        }
        parent::tearDown();
    }

    private function crearLocal(string $nombre): int
    {
        return DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => $nombre, 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');
    }

    /** Escribe el CSV tal cual, sin tocar codificacion ni saltos de linea. */
    private function csv(string $contenido): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'cuadrante') . '.csv';
        file_put_contents($ruta, $contenido);
        $this->archivos[] = $ruta;

        return $ruta;
    }

    private function importar(string $contenido): array
    {
        return $this->servicio->importar($this->plantilla, $this->csv($contenido));
    }

    private function analizar(string $contenido): array
    {
        return $this->servicio->analizar($this->plantilla, $this->csv($contenido));
    }

    // ── Carga válida ──

    public function test_carga_las_franjas_y_las_asignaciones(): void
    {
        $r = $this->importar(
            "cedula,puesto,dia,hora_inicio,hora_fin\n" .
            "1111111111,Garita,LUN,06:00,14:00\n" .
            "2222222222,Garita,LUN,14:00,22:00\n"
        );

        $this->assertSame([], $r['errores']);
        $this->assertSame(2, $r['franjas']);
        $this->assertSame(2, $r['asignaciones']);
        $this->assertSame(2, PlantillaFranja::where('pf_pl_id', $this->plantilla->pl_id)->count());
    }

    public function test_dos_guardias_en_la_misma_franja_comparten_una_sola_franja(): void
    {
        // Un puesto doblado no son dos franjas: es una franja con dos guardias.
        $r = $this->importar(
            "cedula,puesto,dia,hora_inicio,hora_fin\n" .
            "1111111111,Garita,LUN,06:00,14:00\n" .
            "2222222222,Garita,LUN,06:00,14:00\n"
        );

        $this->assertSame(1, $r['franjas']);
        $this->assertSame(2, $r['asignaciones']);
        $this->assertSame(2, PlantillaAsignacion::count());
    }

    public function test_importar_reemplaza_el_cuadrante_anterior(): void
    {
        $this->importar("cedula,puesto,dia,hora_inicio,hora_fin\n1111111111,Garita,LUN,06:00,14:00\n");
        $r = $this->importar("cedula,puesto,dia,hora_inicio,hora_fin\n2222222222,Garita,MAR,14:00,22:00\n");

        // El archivo es la fuente de verdad del cuadrante: no se acumula.
        $this->assertSame(1, $r['franjas']);
        $this->assertSame(1, PlantillaFranja::where('pf_pl_id', $this->plantilla->pl_id)->count());
        $this->assertSame(2, (int) PlantillaFranja::first()->pf_dia_semana);
        $this->assertSame(1, PlantillaAsignacion::count(), 'Las asignaciones viejas caen con su franja');
    }

    // ── Lo que llega del Excel de una oficina ──

    public function test_acepta_punto_y_coma_bom_y_acentos_de_windows(): void
    {
        $utf8 = "cedula;puesto;dia;hora_inicio;hora_fin\n" .
                "1111111111;ANDÉN DE CARGA;MIE;6:00;14:00\n";

        $r = $this->importar("\xEF\xBB\xBF" . mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8'));

        $this->assertSame([], $r['errores']);
        $this->assertSame(1, $r['franjas']);
        $this->assertSame('06:00', substr((string) PlantillaFranja::first()->pf_hora_inicio, 0, 5));
    }

    public function test_el_nombre_del_puesto_no_depende_de_acentos_ni_mayusculas(): void
    {
        // "Anden de carga" contra "ANDÉN DE CARGA": nadie deberia perder una hora por esto.
        $r = $this->importar("cedula,puesto,dia,hora_inicio,hora_fin\n1111111111,anden de carga,LUN,06:00,14:00\n");

        $this->assertSame([], $r['errores']);
        $this->assertSame(1, $r['franjas']);
    }

    public function test_acepta_el_dia_escrito_de_varias_formas(): void
    {
        $r = $this->importar(
            "cedula,puesto,dia,hora_inicio,hora_fin\n" .
            "1111111111,Garita,lunes,06:00,14:00\n" .
            "1111111111,Garita,MAR,06:00,14:00\n" .
            "1111111111,Garita,3,06:00,14:00\n"
        );

        $this->assertSame([], $r['errores']);
        $this->assertSame([1, 2, 3], PlantillaFranja::orderBy('pf_dia_semana')->pluck('pf_dia_semana')->map('intval')->all());
    }

    public function test_soporta_saltos_de_linea_de_windows(): void
    {
        $r = $this->importar("cedula,puesto,dia,hora_inicio,hora_fin\r\n1111111111,Garita,LUN,06:00,14:00\r\n");

        $this->assertSame([], $r['errores']);
        $this->assertSame(1, $r['franjas']);
    }

    // ── Datos que no deben entrar ──

    public function test_falta_una_columna_en_el_encabezado(): void
    {
        $r = $this->importar("cedula,puesto,dia,hora_inicio\n1111111111,Garita,LUN,06:00\n");

        $this->assertStringContainsString('hora_fin', implode(' ', $r['errores']));
        $this->assertSame(0, $r['franjas']);
    }

    public function test_cedula_desconocida(): void
    {
        $r = $this->importar("cedula,puesto,dia,hora_inicio,hora_fin\n1111111111,Garita,LUN,06:00,14:00\n9999999999,Garita,MAR,06:00,14:00\n");

        $this->assertStringContainsString('Fila 3', implode(' ', $r['errores']));
        $this->assertStringContainsString('9999999999', implode(' ', $r['errores']));
    }

    public function test_guardia_sin_vinculo_con_el_local(): void
    {
        // Sin el vinculo no podria marcar: cargarlo daria un cuadrante inservible.
        $r = $this->importar("cedula,puesto,dia,hora_inicio,hora_fin\n3333333333,Garita,LUN,06:00,14:00\n");

        $this->assertStringContainsString('no está vinculado', implode(' ', $r['errores']));
    }

    public function test_puesto_de_otro_local(): void
    {
        $r = $this->importar("cedula,puesto,dia,hora_inicio,hora_fin\n1111111111,Patio,LUN,06:00,14:00\n");

        $this->assertStringContainsString('no existe en este local', implode(' ', $r['errores']));
    }

    public function test_dia_y_hora_invalidos(): void
    {
        $r = $this->analizar(
            "cedula,puesto,dia,hora_inicio,hora_fin\n" .
            "1111111111,Garita,octubre,06:00,14:00\n" .
            "1111111111,Garita,LUN,25:00,14:00\n" .
            "1111111111,Garita,LUN,06:00,06:00\n"
        );

        $texto = implode(' ', $r['errores']);
        $this->assertStringContainsString('no reconocido', $texto);
        $this->assertStringContainsString('entrada inválida', $texto);
        $this->assertStringContainsString('misma hora', $texto);
        $this->assertSame([], $r['filas']);
    }

    public function test_una_fila_mala_no_deja_el_cuadrante_a_medias(): void
    {
        $this->importar("cedula,puesto,dia,hora_inicio,hora_fin\n1111111111,Garita,LUN,06:00,14:00\n");

        $r = $this->importar(
            "cedula,puesto,dia,hora_inicio,hora_fin\n" .
            "2222222222,Garita,MAR,06:00,14:00\n" .
            "9999999999,Garita,MIE,06:00,14:00\n"
        );

        // Con un error no se escribe nada, ni siquiera la fila buena: el
        // cuadrante anterior sigue en pie hasta que el archivo esté correcto.
        $this->assertNotEmpty($r['errores']);
        $this->assertSame(0, $r['franjas']);
        $this->assertSame(1, (int) PlantillaFranja::first()->pf_dia_semana, 'Debe seguir el cuadrante viejo');
    }

    public function test_archivo_sin_filas_de_datos(): void
    {
        $r = $this->importar("cedula,puesto,dia,hora_inicio,hora_fin\n");

        $this->assertNotEmpty($r['errores']);
        $this->assertSame(0, $r['franjas']);
    }

    // ── Avisos: entran, pero se dicen ──

    public function test_avisa_de_la_fila_repetida_y_la_carga_una_sola_vez(): void
    {
        $r = $this->importar(
            "cedula,puesto,dia,hora_inicio,hora_fin\n" .
            "1111111111,Garita,LUN,06:00,14:00\n" .
            "1111111111,Garita,LUN,06:00,14:00\n"
        );

        $this->assertSame([], $r['errores'], 'Repetir no es un dato inválido');
        $this->assertStringContainsString('repetida', implode(' ', $r['avisos']));
        $this->assertSame(1, $r['asignaciones']);
    }

    // ── Modelo de ejemplo ──

    public function test_el_modelo_trae_los_puestos_del_local_ya_escritos(): void
    {
        $csv = $this->servicio->plantillaDeEjemplo($this->plantilla);

        $this->assertStringContainsString(implode(';', PlantillaImportService::COLUMNAS), $csv);
        $this->assertStringContainsString('ANDÉN DE CARGA', $csv);
        $this->assertStringNotContainsString('Patio', $csv, 'No debe ofrecer puestos de otro local');
    }

    public function test_el_modelo_de_ejemplo_se_puede_volver_a_importar(): void
    {
        // El ida y vuelta tiene que cerrar: si el propio modelo no entra, el
        // formato que documentamos y el que leemos no son el mismo.
        $csv = $this->servicio->plantillaDeEjemplo($this->plantilla);
        $csv = str_replace('CEDULA_DEL_GUARDIA', '1111111111', $csv);

        $r = $this->importar($csv);

        $this->assertSame([], $r['errores']);
        $this->assertSame(10, $r['franjas'], '2 puestos × 5 días');
    }
}
