<?php

namespace Tests\Unit;

use App\Filament\Pages\ResumenDeInventarioPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Session;
use Tests\TestCase;

/**
 * El resumen clientes × productos que pidió el cliente, dibujado con datos.
 *
 * ⚠️ **Con las tablas vacías esta pantalla devuelve 200 igual.** Es la lección
 * que ya dejó escrita `PantallasConDatosTest` en este proyecto: lo que demuestra
 * algo es que los números aparezcan, no que la página cargue.
 */
class ResumenDeInventarioPantallaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'C', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Admin', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'a@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Session::put('usuID', 1);
        Session::put('usuPF', 'Administrador');

        $this->datos();
    }

    private function datos(): void
    {
        DB::table('organizacion')->updateOrInsert(['org_code' => 1], [
            'org_descripcion' => 'JBGYE', 'org_estado' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('inv_producto_catalogo')->updateOrInsert(['ipc_id' => 10], [
            'ipc_nombre' => 'Cámara corporal', 'ipc_ins_code' => null,
            'ipc_activo' => true, 'ipc_created_at' => now(),
        ]);

        foreach ([[101, 'Garita Norte', 2], [102, 'Garita Sur', 3]] as [$ins, $nom, $cant]) {
            DB::table('organizacion_institucion')->updateOrInsert(['ins_code' => $ins], [
                'ins_descripcion' => $nom, 'ins_cliente_id' => 1, 'ins_estado' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $lista = DB::table('inv_lista')->insertGetId([
                'li_ins_code' => $ins, 'li_nombre' => 'Seguridad física',
                'li_activo' => true, 'li_created_at' => now(),
            ], 'li_id');

            DB::table('inv_lista_item')->insert([
                'lia_lista_id' => $lista, 'lia_producto_id' => 10,
                'lia_cantidad_default' => $cant, 'lia_activo' => true,
                'lia_created_at' => now(),
            ]);
        }
    }

    public function test_dibuja_el_cliente_el_producto_y_el_total(): void
    {
        Livewire::test(ResumenDeInventarioPage::class)
            ->assertSee('JBGYE')
            ->assertSee('Cámara corporal')
            ->assertSee('5');   // 2 + 3
    }

    public function test_el_desglose_por_local_se_abre_al_pulsar(): void
    {
        $c = Livewire::test(ResumenDeInventarioPage::class);

        // Cerrado: los locales no están.
        $c->assertDontSee('Garita Norte');

        $c->call('alternar', 1)
            ->assertSee('Garita Norte')
            ->assertSee('Garita Sur');

        // Y se cierra con el mismo clic.
        $c->call('alternar', 1)->assertDontSee('Garita Norte');
    }

    /**
     * 51 de los 188 locales de producción no tienen cliente.
     *
     * Descartarlos haría que el total cuadrara en pantalla y estuviera
     * mintiendo: ese equipo está repartido en algún sitio.
     */
    public function test_los_locales_sin_cliente_salen_en_su_propia_fila(): void
    {
        DB::table('organizacion_institucion')->updateOrInsert(['ins_code' => 999], [
            'ins_descripcion' => 'Local huérfano', 'ins_cliente_id' => null,
            'ins_estado' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $lista = DB::table('inv_lista')->insertGetId([
            'li_ins_code' => 999, 'li_nombre' => 'L', 'li_activo' => true,
            'li_created_at' => now(),
        ], 'li_id');

        DB::table('inv_lista_item')->insert([
            'lia_lista_id' => $lista, 'lia_producto_id' => 10,
            'lia_cantidad_default' => 7, 'lia_activo' => true, 'lia_created_at' => now(),
        ]);

        Livewire::test(ResumenDeInventarioPage::class)
            ->assertSee('Sin cliente asignado')
            ->assertSee('7');
    }

    /**
     * La descarga produce un archivo, y con el desglose dentro.
     *
     * ⚠️ En pantalla el desglose por local se abre al pulsar la fila. **En un
     * Excel no hay dónde pulsar**, así que el archivo lleva el desglose de
     * todos los clientes: bajarse los totales pelados obligaría a volver al
     * panel para cada pregunta.
     */
    public function test_la_descarga_genera_un_excel_con_el_desglose(): void
    {
        \Maatwebsite\Excel\Facades\Excel::fake();

        // El nombre lleva la marca de tiempo, así que se congela el reloj para
        // poder afirmarlo exacto.
        \Carbon\Carbon::setTestNow('2026-09-21 10:30:00');

        Livewire::test(ResumenDeInventarioPage::class)
            ->callAction('descargar');

        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('resumen-inventario-20260921-103000.xlsx');

        \Carbon\Carbon::setTestNow();
    }

    public function test_el_archivo_lleva_los_locales_y_no_solo_los_totales(): void
    {
        $pagina = new ResumenDeInventarioPage();

        // El cliente 1 tiene dos locales: tienen que estar en el desglose que
        // se manda al Excel, esté o no abierta la fila en pantalla.
        $desglose = $pagina->desgloseDe(1);

        $this->assertCount(2, $desglose);
        $this->assertSame(
            ['Garita Norte', 'Garita Sur'],
            collect($desglose)->pluck('nombre')->sort()->values()->all(),
        );
    }
}
