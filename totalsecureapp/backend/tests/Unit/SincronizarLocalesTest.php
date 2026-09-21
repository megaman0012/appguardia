<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Los locales, puestos al día contra la lista final del cliente.
 *
 * ⚠️ **Lo que este test protege es que NO se borre nada.** Con la lista del
 * 2026-09-21, borrar los 104 locales que quedaban fuera habría fallado (40
 * vacantes con FK `NO ACTION`), destruido en cascada 3.388 movimientos con sus
 * 6.802 detalles, y dejado **22.857 filas huérfanas** —18.707 vínculos,
 * **2.741 biometrías**, 1.149 rondas— porque esas tablas no tienen clave
 * foránea al local. Eso no falla ni avisa: los reportes empiezan a mostrar
 * vacíos.
 */
class SincronizarLocalesTest extends TestCase
{
    use RefreshDatabase;

    private string $archivo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->archivo = storage_path('app/test-locales.xlsx');
    }

    protected function tearDown(): void
    {
        @unlink($this->archivo);
        parent::tearDown();
    }

    /** @param array<int, array<int, string>> $filas */
    private function excel(array $filas): void
    {
        $libro = new Spreadsheet();
        $hoja  = $libro->getActiveSheet();

        $hoja->fromArray(
            array_merge([['Código', 'Local', 'Cliente', 'País', 'Ciudad', 'Dirección', 'Email', 'Estado']], $filas),
            null, 'A1',
        );

        (new Xlsx($libro))->save($this->archivo);
    }

    private function cliente(int $org, string $nombre): void
    {
        DB::table('organizacion')->updateOrInsert(['org_code' => $org], [
            'org_descripcion' => $nombre, 'org_estado' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function local(int $ins, string $nombre, ?int $org = null, bool $activo = true): void
    {
        DB::table('organizacion_institucion')->updateOrInsert(['ins_code' => $ins], [
            'ins_descripcion' => $nombre, 'ins_cliente_id' => $org, 'ins_ciudad' => 'GUAYAQUIL',
            'ins_estado' => $activo, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function correr(array $opciones = []): void
    {
        $this->artisan('locales:sincronizar', array_merge(
            ['--archivo' => 'test-locales.xlsx'],
            $opciones,
        ))->assertSuccessful();
    }

    public function test_actualiza_los_que_estan_en_la_lista(): void
    {
        $this->cliente(1, 'COPA');
        $this->local(45, 'COPA PAX', 1);

        $this->excel([['45', 'PAX', 'COPA', 'Ecuador', 'Guayaquil', 'Av. X', 'a@b.com', '1']]);
        $this->correr(['--ejecutar' => true]);

        $l = DB::table('organizacion_institucion')->where('ins_code', 45)->first();

        $this->assertSame('PAX', $l->ins_descripcion);
        $this->assertSame('Guayaquil', $l->ins_ciudad);
        $this->assertSame('Av. X', $l->ins_direccion);
    }

    /** El corazón del asunto: se desactivan, y todo lo que cuelga sobrevive. */
    public function test_los_que_sobran_se_desactivan_sin_perder_su_historia(): void
    {
        $this->cliente(1, 'COPA');
        $this->local(45, 'PAX', 1);
        $this->local(99, 'PUESTO RETIRADO', 1);

        // Historia colgando del que se retira.
        DB::table('user_has_biometria')->insert([
            'bio_user_id' => 1, 'bio_ins_code' => 99, 'bio_is_entrada' => 1,
            'bio_state' => 1, 'bio_created_at' => now(),
        ]);

        $this->excel([['45', 'PAX', 'COPA', 'Ecuador', 'Guayaquil', '', '', '1']]);
        $this->correr(['--ejecutar' => true]);

        $retirado = DB::table('organizacion_institucion')->where('ins_code', 99)->first();

        $this->assertNotNull($retirado, 'el local NO se borra: borrarlo dejaría su historia huérfana');
        $this->assertFalse((bool) $retirado->ins_estado);

        $this->assertSame(1, DB::table('user_has_biometria')->where('bio_ins_code', 99)->count(),
            'la biometría es un dato personal irrecuperable: tiene que seguir ahí');
    }

    public function test_crea_el_cliente_que_falta_solo_si_se_lo_piden(): void
    {
        $this->local(45, 'PAX');
        $this->excel([['45', 'PAX', 'CHILENITA', 'Ecuador', 'Guayaquil', '', '', '1']]);

        $this->correr(['--ejecutar' => true]);
        $this->assertSame(0, DB::table('organizacion')->where('org_descripcion', 'CHILENITA')->count());

        $this->correr(['--ejecutar' => true, '--crear-clientes' => true]);

        $org = DB::table('organizacion')->where('org_descripcion', 'CHILENITA')->first();
        $this->assertNotNull($org);
        $this->assertSame((int) $org->org_code,
            (int) DB::table('organizacion_institucion')->where('ins_code', 45)->value('ins_cliente_id'));
    }

    /**
     * Un campo vacío en el archivo NO borra lo que hay.
     *
     * La lista es de locales, no un volcado completo de cada ficha: si alguien
     * exporta sin la columna de dirección, no se puede perder la que existe.
     */
    public function test_un_campo_vacio_no_borra_el_dato_existente(): void
    {
        $this->local(45, 'PAX');
        DB::table('organizacion_institucion')->where('ins_code', 45)
            ->update(['ins_direccion' => 'Av. Original', 'ins_email' => 'x@y.com']);

        $this->excel([['45', 'PAX', '', 'Ecuador', 'Guayaquil', '', '', '1']]);
        $this->correr(['--ejecutar' => true]);

        $l = DB::table('organizacion_institucion')->where('ins_code', 45)->first();

        $this->assertSame('Av. Original', $l->ins_direccion);
        $this->assertSame('x@y.com', $l->ins_email);
    }

    public function test_reactiva_un_local_inactivo_que_vuelve_a_la_lista(): void
    {
        $this->local(45, 'PAX', null, activo: false);
        $this->excel([['45', 'PAX', '', 'Ecuador', 'Guayaquil', '', '', '1']]);

        $this->correr(['--ejecutar' => true]);

        $this->assertTrue((bool) DB::table('organizacion_institucion')->where('ins_code', 45)->value('ins_estado'));
    }

    /**
     * Las columnas se resuelven por su encabezado, no por posición.
     *
     * Un export del panel puede cambiar el orden, y una lectura posicional
     * leería la ciudad como dirección sin que nada fallara.
     */
    public function test_lee_las_columnas_por_encabezado_no_por_posicion(): void
    {
        $this->local(45, 'VIEJO');

        $libro = new Spreadsheet();
        $libro->getActiveSheet()->fromArray([
            ['Estado', 'Ciudad', 'Local', 'Código'],
            ['1', 'Manta', 'NUEVO', '45'],
        ], null, 'A1');
        (new Xlsx($libro))->save($this->archivo);

        $this->correr(['--ejecutar' => true]);

        $l = DB::table('organizacion_institucion')->where('ins_code', 45)->first();

        $this->assertSame('NUEVO', $l->ins_descripcion);
        $this->assertSame('Manta', $l->ins_ciudad);
    }

    /**
     * ⚠️ Un local con turnos programados no se desactiva.
     *
     * Pasó de verdad el 2026-09-21: la lista dejaba fuera 4 locales con **268
     * turnos, 134 de hoy en adelante**. `CerrarTurnosDelDia` solo recorre
     * locales activos, así que esos turnos dejaron de cerrarse esa misma noche
     * — sin error y sin aviso: el puesto simplemente desaparece del proceso.
     *
     * Una lista de locales puede venir incompleta; la programación de turnos es
     * un hecho. Ante la contradicción gana el turno.
     */
    public function test_no_desactiva_un_local_con_turnos_programados(): void
    {
        $this->local(45, 'PAX');
        $this->local(99, 'CON TURNOS');

        $puesto = DB::table('puesto')->insertGetId([
            'pu_ins_code' => 99, 'pu_nombre' => 'Garita', 'pu_estado' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ], 'pu_id');

        DB::table('turno')->insert([
            'tu_usu_id' => 1, 'tu_ins_code' => 99, 'tu_puesto_id' => $puesto,
            'tu_fecha' => now()->addDays(3)->toDateString(),
            'tu_hora_inicio_prevista' => '07:00', 'tu_hora_fin_prevista' => '19:00',
            'tu_estado' => 'programado', 'tu_state' => 1,
            'tu_created_at' => now(), 'tu_updated_at' => now(),
        ]);

        $this->excel([['45', 'PAX', '', 'Ecuador', 'Guayaquil', '', '', '1']]);
        $this->correr(['--ejecutar' => true]);

        $this->assertTrue(
            (bool) DB::table('organizacion_institucion')->where('ins_code', 99)->value('ins_estado'),
            'desactivarlo lo sacaría del cierre diario de turnos sin que nada lo avise',
        );
    }

    public function test_la_simulacion_no_escribe(): void
    {
        $this->local(45, 'VIEJO');
        $this->local(99, 'SE RETIRA');
        $this->excel([['45', 'NUEVO', '', 'Ecuador', 'Guayaquil', '', '', '1']]);

        $this->correr();

        $this->assertSame('VIEJO', DB::table('organizacion_institucion')->where('ins_code', 45)->value('ins_descripcion'));
        $this->assertTrue((bool) DB::table('organizacion_institucion')->where('ins_code', 99)->value('ins_estado'));
    }

    public function test_correrlo_dos_veces_no_cambia_nada(): void
    {
        $this->local(45, 'VIEJO');
        $this->local(99, 'SE RETIRA');
        $this->excel([['45', 'NUEVO', '', 'Ecuador', 'Guayaquil', '', '', '1']]);

        $this->correr(['--ejecutar' => true]);
        $estado = DB::table('organizacion_institucion')->orderBy('ins_code')->get()->toJson();

        $this->correr(['--ejecutar' => true]);

        $this->assertSame($estado, DB::table('organizacion_institucion')->orderBy('ins_code')->get()->toJson());
    }
}
