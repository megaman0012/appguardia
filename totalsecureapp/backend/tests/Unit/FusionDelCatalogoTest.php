<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El catálogo de productos era 4 productos repetidos en 133 locales: 532 filas.
 *
 * Un bastón retráctil es el mismo objeto en los 133 sitios; lo que cambia por
 * local es **cuántos hay**, y eso ya vive en `inv_lista_item`. Agregar un quinto
 * producto eran 133 inserciones, y la duplicación ya se había degradado sola.
 *
 * Lo que fija este test es que la fusión **no pierda historia**: hay 23.799
 * filas de `inv_movimiento_detalle` apuntando al catálogo, y si el remapeo se
 * salta alguna queda un detalle huérfano — un conteo de inventario sin producto.
 */
class FusionDelCatalogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'C', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'T', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'a@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function local(int $ins, string $nombre = 'Local'): void
    {
        DB::table('organizacion_institucion')->updateOrInsert(['ins_code' => $ins], [
            'ins_descripcion' => $nombre . ' ' . $ins, 'ins_estado' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function producto(int $id, int $ins, string $nombre): int
    {
        DB::table('inv_producto_catalogo')->insert([
            'ipc_id' => $id, 'ipc_ins_code' => $ins, 'ipc_nombre' => $nombre,
            'ipc_activo' => true, 'ipc_created_at' => now(),
        ]);

        return $id;
    }

    private function lista(int $id, int $ins): int
    {
        DB::table('inv_lista')->insert([
            'li_id' => $id, 'li_ins_code' => $ins, 'li_nombre' => 'Seguridad física',
            'li_activo' => true, 'li_created_at' => now(),
        ]);

        return $id;
    }

    private function item(int $lista, int $producto, float $cant = 1): void
    {
        DB::table('inv_lista_item')->insert([
            'lia_lista_id' => $lista, 'lia_producto_id' => $producto,
            'lia_cantidad_default' => $cant, 'lia_activo' => true, 'lia_created_at' => now(),
        ]);
    }

    private function movimiento(int $id, int $ins, int $lista): int
    {
        DB::table('inv_movimiento_cabecera')->insert([
            'mc_id' => $id, 'mc_ins_code' => $ins, 'mc_lista_id' => $lista,
            'mc_tipo' => 'recepcion', 'mc_usuario_id' => 1, 'mc_fecha' => now(),
            'mc_estado' => 'pendiente', 'mc_created_at' => now(),
        ]);

        return $id;
    }

    private function detalle(int $mov, int $producto): void
    {
        DB::table('inv_movimiento_detalle')->insert([
            'md_movimiento_id' => $mov, 'md_producto_id' => $producto,
            'md_cantidad_default' => 1, 'md_cantidad_real' => 1,
            'md_estado' => 'ok', 'md_created_at' => now(),
        ]);
    }

    /** El escenario real en pequeño: un producto repetido en tres locales. */
    private function escenario(): void
    {
        foreach ([1, 2, 3] as $ins) {
            $this->local($ins);
        }

        $this->producto(10, 1, 'Linterna táctica');
        $this->producto(20, 2, 'Linterna táctica');
        $this->producto(30, 3, 'LINTERNA  TÁCTICA');   // mismo, con ruido

        foreach ([[1, 10], [2, 20], [3, 30]] as [$ins, $prod]) {
            $this->lista($ins * 100, $ins);
            $this->item($ins * 100, $prod, 2);
            $this->movimiento($ins * 1000, $ins, $ins * 100);
            $this->detalle($ins * 1000, $prod);
        }
    }

    public function test_tres_copias_quedan_en_una_global(): void
    {
        $this->escenario();

        $this->artisan('inventario:fusionar-catalogo', ['--ejecutar' => true])->assertSuccessful();

        $this->assertSame(1, DB::table('inv_producto_catalogo')->count());

        $p = DB::table('inv_producto_catalogo')->first();

        // El de id más bajo, no uno cualquiera: tiene que ser determinista.
        $this->assertSame(10, (int) $p->ipc_id);
        $this->assertNull($p->ipc_ins_code, 'nulo es lo que significa «global»');
    }

    public function test_no_se_pierde_un_solo_movimiento(): void
    {
        $this->escenario();

        $antes = DB::table('inv_movimiento_detalle')->count();

        $this->artisan('inventario:fusionar-catalogo', ['--ejecutar' => true])->assertSuccessful();

        $this->assertSame($antes, DB::table('inv_movimiento_detalle')->count());

        // Y ninguno queda huérfano: un conteo sin producto no se puede leer.
        $huerfanos = DB::table('inv_movimiento_detalle as d')
            ->leftJoin('inv_producto_catalogo as p', 'p.ipc_id', '=', 'd.md_producto_id')
            ->whereNull('p.ipc_id')->count();

        $this->assertSame(0, $huerfanos);
    }

    public function test_cada_lista_conserva_su_cantidad(): void
    {
        $this->escenario();

        $this->artisan('inventario:fusionar-catalogo', ['--ejecutar' => true])->assertSuccessful();

        // Las tres listas siguen pidiendo 2, cada una apuntando al global.
        $this->assertSame(3, DB::table('inv_lista_item')->count());

        foreach (DB::table('inv_lista_item')->get() as $i) {
            $this->assertSame(10, (int) $i->lia_producto_id);
            $this->assertEquals(2, (float) $i->lia_cantidad_default);
        }
    }

    /**
     * El caso que reventaría a mitad de la transacción.
     *
     * `inv_lista_item` tiene un único en (lista, producto). Si una lista tuviera
     * dos filas que se fusionan en el mismo producto, el remapeo lo violaría.
     * Hoy no pasa en producción, pero el comando tiene que aguantarlo.
     */
    public function test_dos_items_de_la_misma_lista_se_suman_en_vez_de_reventar(): void
    {
        $this->local(1);
        $this->producto(10, 1, 'Linterna táctica');
        // Mismo nombre con otro espaciado y otras mayúsculas: normaliza igual,
        // así que los dos items acaban en la misma lista apuntando al mismo
        // producto — que es lo que viola el único.
        $this->producto(20, 1, 'linterna   TÁCTICA ');

        $this->lista(100, 1);
        $this->item(100, 10, 2);
        $this->item(100, 20, 3);

        $this->artisan('inventario:fusionar-catalogo', ['--ejecutar' => true])->assertSuccessful();

        $items = DB::table('inv_lista_item')->get();

        $this->assertCount(1, $items, 'los dos items tienen que quedar en uno');
        $this->assertEquals(5, (float) $items->first()->lia_cantidad_default,
            'la cantidad esperada se suma: descartarla perdería inventario');
    }

    public function test_productos_distintos_no_se_mezclan(): void
    {
        $this->local(1);
        $this->local(2);
        $this->producto(10, 1, 'Linterna táctica');
        $this->producto(20, 2, 'Bastón retráctil');

        $this->artisan('inventario:fusionar-catalogo', ['--ejecutar' => true])->assertSuccessful();

        $this->assertSame(2, DB::table('inv_producto_catalogo')->count());
    }

    public function test_correrlo_dos_veces_no_cambia_nada(): void
    {
        $this->escenario();

        $this->artisan('inventario:fusionar-catalogo', ['--ejecutar' => true])->assertSuccessful();
        $estado = DB::table('inv_producto_catalogo')->orderBy('ipc_id')->get()->toJson();

        $this->artisan('inventario:fusionar-catalogo', ['--ejecutar' => true])->assertSuccessful();

        $this->assertSame($estado, DB::table('inv_producto_catalogo')->orderBy('ipc_id')->get()->toJson());
    }

    /**
     * ⚠️ El fallo que la fusión estuvo a punto de dejar suelto.
     *
     * El selector de productos del relation manager de Listas filtraba por
     * `ipc_ins_code`. Con el catálogo global esa columna es nula en todas las
     * filas, así que el selector quedaba **completamente vacío** — sin error y
     * sin aviso: simplemente no se podía agregar ningún producto a ninguna
     * lista. Lo que se ofrece ahora es el catálogo entero, que es el sentido de
     * la fusión.
     */
    public function test_el_selector_de_la_lista_ofrece_el_catalogo_global(): void
    {
        $this->local(1);
        $this->producto(10, 1, 'Linterna táctica');
        $this->producto(20, 2 === 2 ? 1 : 1, 'Bastón retráctil');

        $this->artisan('inventario:fusionar-catalogo', ['--ejecutar' => true])->assertSuccessful();

        $ofrecidos = \Modules\Administracion\Models\ProductoCatalogo::query()
            ->where('ipc_activo', true)
            ->orderBy('ipc_nombre')
            ->pluck('ipc_nombre', 'ipc_id');

        $this->assertCount(2, $ofrecidos,
            'con el filtro por local el selector salía vacío y no se podía armar ninguna lista');
    }

    public function test_la_simulacion_no_escribe(): void
    {
        $this->escenario();

        $this->artisan('inventario:fusionar-catalogo')->assertSuccessful();

        $this->assertSame(3, DB::table('inv_producto_catalogo')->count());
    }
}
