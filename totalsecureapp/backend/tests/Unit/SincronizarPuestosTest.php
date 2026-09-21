<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Los puestos, contra la lista final.
 *
 * ⚠️ El archivo engancha cada puesto a su local **por el nombre del local**: no
 * trae códigos. Y nombraba 14 locales que no existen —nombres consolidados como
 * `SWISSPORT` en vez de los cinco `SWISSPORT MATRIZ`, `CARGA`…—, que contradicen
 * la lista de locales. Se decidió que manda la lista de locales, así que esos
 * puestos **se informan y no se tocan**.
 *
 * Lo que más protege este test es que **renombrar no pierda los turnos**: los
 * puestos actuales se generaron con el nombre del local, y la lista les da
 * nombre propio. Crear uno nuevo y desactivar el viejo se llevaría por delante
 * la programación.
 */
class SincronizarPuestosTest extends TestCase
{
    use RefreshDatabase;

    private string $archivo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->archivo = storage_path('app/test-puestos.xlsx');
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
        $libro->getActiveSheet()->fromArray(
            array_merge([['Local', 'Ciudad', 'Puesto', 'Descripción', 'Turnos', 'Activo']], $filas),
            null, 'A1',
        );
        (new Xlsx($libro))->save($this->archivo);
    }

    private function local(int $ins, string $nombre, bool $activo = true): void
    {
        DB::table('organizacion_institucion')->updateOrInsert(['ins_code' => $ins], [
            'ins_descripcion' => $nombre, 'ins_estado' => $activo,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function puesto(int $ins, string $nombre): int
    {
        return (int) DB::table('puesto')->insertGetId([
            'pu_ins_code' => $ins, 'pu_nombre' => $nombre, 'pu_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'pu_id');
    }

    private function turno(int $puesto, int $ins): void
    {
        DB::table('turno')->insert([
            'tu_usu_id' => 1, 'tu_ins_code' => $ins, 'tu_puesto_id' => $puesto,
            'tu_fecha' => now()->addDays(2)->toDateString(),
            'tu_hora_inicio_prevista' => '07:00', 'tu_hora_fin_prevista' => '19:00',
            'tu_estado' => 'programado', 'tu_state' => 1,
            'tu_created_at' => now(), 'tu_updated_at' => now(),
        ]);
    }

    private function correr(array $o = []): void
    {
        $this->artisan('puestos:sincronizar',
            array_merge(['--archivo' => 'test-puestos.xlsx'], $o))->assertSuccessful();
    }

    /** El caso mayoritario: 55 de los 137 puestos reales. */
    public function test_renombra_conservando_el_puesto_y_sus_turnos(): void
    {
        $this->local(1, 'JBGYE CEMENTERIO');
        $id = $this->puesto(1, 'JBGYE CEMENTERIO');
        $this->turno($id, 1);

        $this->excel([['JBGYE CEMENTERIO', '', 'puesto 1', '', '1', '1']]);
        $this->correr(['--ejecutar' => true]);

        $this->assertSame(1, DB::table('puesto')->where('pu_ins_code', 1)->count(),
            'no debe crear otro: el puesto se renombra');

        $p = DB::table('puesto')->where('pu_id', $id)->first();

        $this->assertSame('puesto 1', $p->pu_nombre);
        $this->assertSame(1, DB::table('turno')->where('tu_puesto_id', $id)->count(),
            'crear uno nuevo y apagar el viejo se llevaría por delante la programación');
    }

    public function test_crea_los_que_faltan_cuando_hay_varios(): void
    {
        $this->local(1, 'CC. LAS AMERICAS');
        $this->puesto(1, 'GARITA');

        $this->excel([
            ['CC. LAS AMERICAS', '', 'GARITA', '', '0', '1'],
            ['CC. LAS AMERICAS', '', 'CONDOMINIO', '', '0', '1'],
        ]);
        $this->correr(['--ejecutar' => true]);

        $this->assertSame(['CONDOMINIO', 'GARITA'],
            DB::table('puesto')->where('pu_ins_code', 1)->orderBy('pu_nombre')->pluck('pu_nombre')->all());
    }

    /** ⚠️ Nunca. Ya pasó con los locales el mismo día. */
    public function test_no_desactiva_un_puesto_con_turnos(): void
    {
        $this->local(1, 'LOTERÍA MATRIZ');
        $viejo = $this->puesto(1, 'LOTERÍA MATRIZ');
        $this->puesto(1, 'otro');
        $this->turno($viejo, 1);

        // La lista solo trae «otro»: al viejo le tocaría desactivarse.
        $this->excel([['LOTERÍA MATRIZ', '', 'otro', '', '0', '1']]);
        $this->correr(['--ejecutar' => true]);

        $this->assertTrue((bool) DB::table('puesto')->where('pu_id', $viejo)->value('pu_estado'),
            'apagarlo sacaría sus turnos del cierre diario sin que nada lo avise');
    }

    public function test_desactiva_el_que_sobra_si_no_tiene_turnos(): void
    {
        $this->local(1, 'X');
        $sobra = $this->puesto(1, 'VIEJO');
        $this->puesto(1, 'A');
        $this->puesto(1, 'B');

        $this->excel([['X', '', 'A', '', '0', '1'], ['X', '', 'B', '', '0', '1']]);
        $this->correr(['--ejecutar' => true]);

        $this->assertFalse((bool) DB::table('puesto')->where('pu_id', $sobra)->value('pu_estado'));
    }

    /**
     * ⚠️ Un local que no existe activo no se crea: se informa.
     *
     * El archivo nombraba `SWISSPORT` donde la base tiene cinco locales
     * separados. Crearlo sería hacer la consolidación que la lista de locales
     * —que es la que manda— no pidió.
     */
    public function test_ignora_los_puestos_sin_local_activo(): void
    {
        $this->local(1, 'SWISSPORT MATRIZ');
        $this->local(2, 'RETIRADO', activo: false);

        $this->excel([
            ['SWISSPORT', '', 'MATRIZ', '', '0', '1'],
            ['RETIRADO', '', 'puesto 1', '', '0', '1'],
        ]);
        $this->correr(['--ejecutar' => true]);

        $this->assertSame(0, DB::table('puesto')->count(), 'no se crea nada sin local activo');
        $this->assertSame(1, DB::table('organizacion_institucion')->where('ins_descripcion', 'SWISSPORT MATRIZ')->count(),
            'tampoco se crean locales');
    }

    /** Un local que la lista no menciona se deja como está. */
    public function test_no_toca_los_locales_que_la_lista_no_menciona(): void
    {
        $this->local(1, 'EN LA LISTA');
        $this->local(2, 'FUERA DE LA LISTA');
        $intacto = $this->puesto(2, 'SU PUESTO');
        $this->puesto(1, 'EN LA LISTA');

        $this->excel([['EN LA LISTA', '', 'puesto 1', '', '0', '1']]);
        $this->correr(['--ejecutar' => true]);

        $p = DB::table('puesto')->where('pu_id', $intacto)->first();

        $this->assertSame('SU PUESTO', $p->pu_nombre);
        $this->assertTrue((bool) $p->pu_estado);
    }

    public function test_una_descripcion_vacia_no_borra_la_que_hay(): void
    {
        $this->local(1, 'X');
        $id = $this->puesto(1, 'A');
        DB::table('puesto')->where('pu_id', $id)->update(['pu_descripcion' => 'Original']);

        $this->excel([['X', '', 'A', '', '0', '1']]);
        $this->correr(['--ejecutar' => true]);

        $this->assertSame('Original', DB::table('puesto')->where('pu_id', $id)->value('pu_descripcion'));
    }

    public function test_la_simulacion_no_escribe(): void
    {
        $this->local(1, 'X');
        $this->puesto(1, 'X');

        $this->excel([['X', '', 'puesto 1', '', '0', '1']]);
        $this->correr();

        $this->assertSame('X', DB::table('puesto')->where('pu_ins_code', 1)->value('pu_nombre'));
    }

    public function test_correrlo_dos_veces_no_cambia_nada(): void
    {
        $this->local(1, 'X');
        $this->puesto(1, 'X');

        $this->excel([['X', '', 'puesto 1', 'Desc', '0', '1']]);

        $this->correr(['--ejecutar' => true]);
        $estado = DB::table('puesto')->orderBy('pu_id')->get(['pu_id', 'pu_nombre', 'pu_estado'])->toJson();

        $this->correr(['--ejecutar' => true]);

        $this->assertSame($estado,
            DB::table('puesto')->orderBy('pu_id')->get(['pu_id', 'pu_nombre', 'pu_estado'])->toJson());
    }
}
