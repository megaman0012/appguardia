<?php

namespace Tests\Unit;

use App\Services\Inventario\ResumenDeInventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cuánto equipo tiene repartido cada cliente, sumando las listas de sus locales.
 *
 * ⚠️ Hubo aquí una segunda cifra, «asignado», que salía de `inv_stock_cliente`.
 * Se quitó el 2026-09-21: modelaba algo que este departamento no hace --no
 * maneja stock ni bodega, solo tiene o no tiene-- y la tabla nunca llegó a tener
 * una fila.
 */
class ResumenDeInventarioTest extends TestCase
{
    use RefreshDatabase;

    private ResumenDeInventario $resumen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resumen = app(ResumenDeInventario::class);
    }

    private function cliente(int $org, string $nombre): void
    {
        DB::table('organizacion')->updateOrInsert(['org_code' => $org], [
            'org_descripcion' => $nombre, 'org_estado' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function local(int $ins, ?int $org, bool $activo = true): void
    {
        DB::table('organizacion_institucion')->updateOrInsert(['ins_code' => $ins], [
            'ins_descripcion' => 'Local ' . $ins, 'ins_cliente_id' => $org,
            'ins_estado' => $activo, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function producto(int $id, string $nombre): void
    {
        DB::table('inv_producto_catalogo')->updateOrInsert(['ipc_id' => $id], [
            'ipc_nombre' => $nombre, 'ipc_ins_code' => null,
            'ipc_activo' => true, 'ipc_created_at' => now(),
        ]);
    }

    /** Una lista en el local con N unidades del producto. */
    private function reparte(int $ins, int $producto, float $cant): void
    {
        $lista = DB::table('inv_lista')->where('li_ins_code', $ins)->value('li_id');

        if (! $lista) {
            $lista = DB::table('inv_lista')->insertGetId([
                'li_ins_code' => $ins, 'li_nombre' => 'Seguridad física',
                'li_activo' => true, 'li_created_at' => now(),
            ], 'li_id');
        }

        DB::table('inv_lista_item')->insert([
            'lia_lista_id' => $lista, 'lia_producto_id' => $producto,
            'lia_cantidad_default' => $cant, 'lia_activo' => true, 'lia_created_at' => now(),
        ]);
    }

    private function escenario(): void
    {
        $this->cliente(1, 'JBGYE');
        $this->producto(10, 'Cámara corporal');

        $this->local(101, 1);
        $this->local(102, 1);

        $this->reparte(101, 10, 2);
        $this->reparte(102, 10, 3);
    }

    public function test_suma_lo_repartido_en_todos_los_locales_del_cliente(): void
    {
        $this->escenario();

        $f = $this->resumen->porClienteYProducto()->firstWhere('org_code', 1);

        $this->assertEquals(5.0, $f->distribuido, '2 + 3 de los dos locales');
    }

    public function test_un_cliente_no_ve_lo_de_otro(): void
    {
        $this->escenario();

        $this->cliente(2, 'DHL');
        $this->local(201, 2);
        $this->reparte(201, 10, 3);

        $filas = $this->resumen->porClienteYProducto();

        $this->assertEquals(5.0, $filas->firstWhere('org_code', 1)->distribuido);
        $this->assertEquals(3.0, $filas->firstWhere('org_code', 2)->distribuido);
    }

    /**
     * ⚠️ 51 de los 188 locales de producción no tienen cliente.
     *
     * Descartarlos haría que el resumen cuadrara en pantalla y estuviera
     * mintiendo: ese equipo existe y está repartido en algún sitio.
     */
    public function test_los_locales_sin_cliente_no_se_pierden(): void
    {
        $this->producto(10, 'Cámara corporal');
        $this->local(999, null);
        $this->reparte(999, 10, 7);

        $f = $this->resumen->porClienteYProducto()
            ->firstWhere('org_code', ResumenDeInventario::SIN_CLIENTE);

        $this->assertNotNull($f, 'los locales sin cliente tienen que aparecer, agrupados');
        $this->assertEquals(7.0, $f->distribuido);
    }

    /**
     * ⚠️ Un local retirado no cuenta.
     *
     * Al retirar 91 locales contra la lista final del cliente quedaron 59
     * listas con 236 items colgando de puestos desactivados. Sin filtrar, el
     * reporte seguiría contando equipo donde ya no se opera — y nadie lo
     * notaría, porque el número sale mayor, no roto.
     */
    public function test_un_local_desactivado_no_suma(): void
    {
        $this->escenario();

        $this->local(103, 1, activo: false);
        $this->reparte(103, 10, 9);

        $f = $this->resumen->porClienteYProducto()->firstWhere('org_code', 1);

        $this->assertEquals(5.0, $f->distribuido, 'las 9 del local retirado no deben contar');
        $this->assertCount(2, $this->resumen->porLocalYProducto(1),
            'el desglose tampoco debe mostrar el puesto retirado');
    }

    public function test_el_desglose_baja_al_local(): void
    {
        $this->escenario();

        $filas = $this->resumen->porLocalYProducto(1);

        $this->assertCount(2, $filas);
        $this->assertEquals(2.0, $filas->firstWhere('ins_code', 101)->cantidad);
        $this->assertEquals(3.0, $filas->firstWhere('ins_code', 102)->cantidad);
    }

    /**
     * `null` = ve todo; `[]` = **no ve nada**.
     *
     * Confundirlos convierte a un supervisor sin locales vinculados en acceso
     * global. Es la misma distinción de `PerfilPanel::localesVisibles()`.
     */
    public function test_el_alcance_vacio_no_devuelve_todo(): void
    {
        $this->escenario();

        $this->assertCount(1, $this->resumen->porClienteYProducto(null));
        $this->assertCount(0, $this->resumen->porClienteYProducto([]));
        $this->assertEquals(2.0,
            $this->resumen->porClienteYProducto([101])->firstWhere('org_code', 1)->distribuido);
    }
}
