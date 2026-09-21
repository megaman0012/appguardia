<?php

namespace Tests\Unit;

use App\Services\Inventario\AplicadorDeKit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Kit;
use Modules\Administracion\Models\Lista;
use Tests\TestCase;

/**
 * El kit de puesto: una plantilla en vez de 132 listas copiadas.
 *
 * Había 132 listas y **130 idénticas**. Dar inventario a un local nuevo eran
 * ~22 interacciones; agregar un producto a todos, 132 ediciones.
 *
 * ⚠️ Lo que más importa acá es que **`inv_lista` e `inv_lista_item` conservan su
 * forma**: son lo que lee la app del guardia y contra lo que se registran los
 * 23.799 movimientos. El kit gobierna cómo se escriben, no cómo se leen.
 */
class KitDePuestoTest extends TestCase
{
    use RefreshDatabase;

    private AplicadorDeKit $aplicador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aplicador = app(AplicadorDeKit::class);
    }

    private function local(int $ins): void
    {
        DB::table('organizacion_institucion')->updateOrInsert(['ins_code' => $ins], [
            'ins_descripcion' => 'Local ' . $ins, 'ins_estado' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function producto(int $id, string $nombre): void
    {
        DB::table('inv_producto_catalogo')->updateOrInsert(['ipc_id' => $id], [
            'ipc_nombre' => $nombre, 'ipc_ins_code' => null,
            'ipc_activo' => true, 'ipc_created_at' => now(),
        ]);
    }

    /** @param array<int,float> $items */
    private function kit(string $nombre, array $items): Kit
    {
        $kit = Kit::create(['ki_nombre' => $nombre, 'ki_activo' => true]);

        foreach ($items as $producto => $cantidad) {
            DB::table('inv_kit_item')->insert([
                'kii_ki_id' => $kit->ki_id, 'kii_producto_id' => $producto,
                'kii_cantidad' => $cantidad, 'kii_created_at' => now(),
            ]);
        }

        return $kit;
    }

    /** @return array<int,float> */
    private function itemsDe(int $insCode): array
    {
        $lista = Lista::where('li_ins_code', $insCode)->firstOrFail();

        $items = DB::table('inv_lista_item')
            ->where('lia_lista_id', $lista->li_id)
            ->where('lia_activo', true)
            ->pluck('lia_cantidad_default', 'lia_producto_id')
            ->map(fn ($v) => (float) $v)
            ->all();

        ksort($items);

        return $items;
    }

    private function escenario(): Kit
    {
        $this->producto(10, 'Bastón');
        $this->producto(20, 'Bodycam');
        $this->local(101);
        $this->local(102);

        return $this->kit('Seguridad física', [10 => 1.0, 20 => 1.0]);
    }

    public function test_aplicar_el_kit_crea_la_lista_del_local(): void
    {
        $kit = $this->escenario();

        $r = $this->aplicador->aplicar($kit, [101, 102]);

        $this->assertSame(['creadas' => 2, 'actualizadas' => 0, 'respetadas' => 0], $r);
        $this->assertSame([10 => 1.0, 20 => 1.0], $this->itemsDe(101));
        $this->assertSame([10 => 1.0, 20 => 1.0], $this->itemsDe(102));
    }

    /** El punto de todo: cambiar el kit cambia los 132 locales de una vez. */
    public function test_cambiar_el_kit_se_propaga_a_los_locales(): void
    {
        $kit = $this->escenario();
        $this->aplicador->aplicar($kit, [101, 102]);

        $this->producto(30, 'Linterna');
        DB::table('inv_kit_item')->insert([
            'kii_ki_id' => $kit->ki_id, 'kii_producto_id' => 30,
            'kii_cantidad' => 2, 'kii_created_at' => now(),
        ]);

        $this->aplicador->aplicar($kit->fresh(), [101, 102]);

        $this->assertSame([10 => 1.0, 20 => 1.0, 30 => 2.0], $this->itemsDe(101));
    }

    /** Y lo que pediste: un local puede apartarse, y se nota. */
    public function test_un_local_que_se_aparta_queda_marcado_y_se_respeta(): void
    {
        $kit = $this->escenario();
        $this->aplicador->aplicar($kit, [101, 102]);

        // El 101 necesita tres bastones en vez de uno.
        $lista = Lista::where('li_ins_code', 101)->first();
        DB::table('inv_lista_item')
            ->where('lia_lista_id', $lista->li_id)
            ->where('lia_producto_id', 10)
            ->update(['lia_cantidad_default' => 3]);

        $this->assertTrue($this->aplicador->refrescarBandera($lista->fresh()));

        // Volver a aplicar el kit NO lo pisa.
        $r = $this->aplicador->aplicar($kit, [101, 102]);

        $this->assertSame(1, $r['respetadas']);
        $this->assertSame([10 => 3.0, 20 => 1.0], $this->itemsDe(101),
            'pisar la excepción borraría en silencio la razón por la que ese puesto es distinto');
        $this->assertSame([10 => 1.0, 20 => 1.0], $this->itemsDe(102));
    }

    public function test_forzar_devuelve_el_local_al_kit(): void
    {
        $kit = $this->escenario();
        $this->aplicador->aplicar($kit, [101]);

        $lista = Lista::where('li_ins_code', 101)->first();
        DB::table('inv_lista_item')->where('lia_lista_id', $lista->li_id)
            ->where('lia_producto_id', 10)->update(['lia_cantidad_default' => 3]);
        $this->aplicador->refrescarBandera($lista->fresh());

        $this->aplicador->aplicar($kit, [101], forzar: true);

        $this->assertSame([10 => 1.0, 20 => 1.0], $this->itemsDe(101));
        $this->assertFalse((bool) Lista::where('li_ins_code', 101)->value('li_modificada'));
    }

    /**
     * La bandera se **calcula**, no se confía en que alguien la levante.
     *
     * Una bandera que hay que acordarse de poner se olvida, y entonces una
     * sincronización del kit pisaría en silencio la excepción de un puesto.
     */
    public function test_la_bandera_se_calcula_comparando_contenido(): void
    {
        $kit = $this->escenario();
        $this->aplicador->aplicar($kit, [101]);

        $lista = Lista::where('li_ins_code', 101)->first();

        // Cambiar el NOMBRE de la lista no la aparta del kit: el guardia cuenta
        // productos, no títulos.
        $lista->update(['li_nombre' => 'Otro nombre']);
        $this->assertFalse($this->aplicador->refrescarBandera($lista->fresh()));

        // Quitar un producto sí.
        DB::table('inv_lista_item')->where('lia_lista_id', $lista->li_id)
            ->where('lia_producto_id', 20)->update(['lia_activo' => false]);

        $this->assertTrue($this->aplicador->refrescarBandera($lista->fresh()));
    }

    /**
     * ⚠️ Los items se actualizan, no se borran y reinsertan.
     *
     * Borrarlos cambiaría sus `lia_id`. Hoy no los referencia nada, pero es la
     * clase de cambio que rompe un informe guardado meses después.
     */
    public function test_reaplicar_no_cambia_los_ids_de_los_items(): void
    {
        $kit = $this->escenario();
        $this->aplicador->aplicar($kit, [101]);

        $antes = DB::table('inv_lista_item')->orderBy('lia_id')->pluck('lia_id')->all();

        $this->aplicador->aplicar($kit, [101]);

        $this->assertSame($antes, DB::table('inv_lista_item')->orderBy('lia_id')->pluck('lia_id')->all());
    }

    public function test_aplicar_dos_veces_no_cambia_nada(): void
    {
        $kit = $this->escenario();

        $this->aplicador->aplicar($kit, [101]);
        $estado = $this->itemsDe(101);

        $r = $this->aplicador->aplicar($kit, [101]);

        $this->assertSame($estado, $this->itemsDe(101));
        $this->assertSame(0, $r['creadas']);
    }

    // ── El comando de migración ──────────────────────────────────────────────

    private function listaSuelta(int $ins, string $nombre, array $items): void
    {
        $this->local($ins);

        $id = DB::table('inv_lista')->insertGetId([
            'li_ins_code' => $ins, 'li_nombre' => $nombre,
            'li_activo' => true, 'li_created_at' => now(),
        ], 'li_id');

        foreach ($items as $producto => $cantidad) {
            DB::table('inv_lista_item')->insert([
                'lia_lista_id' => $id, 'lia_producto_id' => $producto,
                'lia_cantidad_default' => $cantidad, 'lia_activo' => true,
                'lia_created_at' => now(),
            ]);
        }
    }

    /**
     * El contenido del kit sale de la variante **más frecuente**.
     *
     * Con 122 listas iguales y 1 distinta, tomar la primera por id podría fijar
     * como estándar justo la excepción.
     */
    public function test_el_kit_toma_el_contenido_mayoritario_no_el_primero(): void
    {
        $this->producto(10, 'Bastón');
        $this->producto(20, 'Bodycam');

        // La PRIMERA por id es la rara: solo un producto.
        $this->listaSuelta(101, 'Seguridad física', [10 => 1]);
        $this->listaSuelta(102, 'Seguridad física', [10 => 1, 20 => 1]);
        $this->listaSuelta(103, 'Seguridad física', [10 => 1, 20 => 1]);

        $this->artisan('inventario:crear-kits', ['--ejecutar' => true])->assertSuccessful();

        $kit = Kit::where('ki_nombre', 'SEGURIDAD FÍSICA')->first();
        $this->assertNotNull($kit);
        $this->assertSame(2, $kit->items()->count(), 'el kit debe salir de las dos iguales');

        // Y la rara queda señalada, no perdida.
        $this->assertTrue((bool) Lista::where('li_ins_code', 101)->value('li_modificada'));
        $this->assertFalse((bool) Lista::where('li_ins_code', 102)->value('li_modificada'));
    }

    /** Las erratas NO se unen solas: eso lo decide quien mira, con --alias. */
    public function test_las_erratas_no_se_funden_sin_que_se_lo_pidan(): void
    {
        $this->producto(10, 'Bastón');
        $this->listaSuelta(101, 'SEGURIDAD FISICA', [10 => 1]);
        $this->listaSuelta(102, 'SEGURIDA FISICA', [10 => 1]);

        $this->artisan('inventario:crear-kits', ['--ejecutar' => true])->assertSuccessful();
        $this->assertSame(2, Kit::count(), 'unirlas solas sería adivinar');

        // Con el alias sí.
        Kit::query()->delete();
        DB::table('inv_lista')->update(['li_kit_id' => null, 'li_modificada' => false]);

        $this->artisan('inventario:crear-kits', [
            '--alias' => ['SEGURIDA FISICA=SEGURIDAD FISICA'],
            '--ejecutar' => true,
        ])->assertSuccessful();

        $this->assertSame(1, Kit::count());
        $this->assertSame(2, Lista::whereNotNull('li_kit_id')->count());
    }

    /**
     * ⚠️ La lista que ya difería no se pisa en la primera sincronización.
     *
     * Al migrar, cada lista guarda la huella de **su propio contenido**, no la
     * del kit. Si se guardara la del kit, la lista rara quedaría con una huella
     * que coincide, la primera sincronización la daría por intacta y la
     * reescribiría — borrando justo la excepción que se acababa de detectar.
     */
    public function test_la_lista_rara_sobrevive_a_la_primera_sincronizacion(): void
    {
        $this->producto(10, 'Bastón');
        $this->producto(20, 'Bodycam');

        $this->listaSuelta(101, 'Seguridad física', [10 => 1]);          // la rara
        $this->listaSuelta(102, 'Seguridad física', [10 => 1, 20 => 1]);
        $this->listaSuelta(103, 'Seguridad física', [10 => 1, 20 => 1]);

        $this->artisan('inventario:crear-kits', ['--ejecutar' => true])->assertSuccessful();

        $kit = Kit::firstOrFail();
        $this->aplicador->aplicar($kit, [101, 102, 103]);

        $this->assertSame([10 => 1.0], $this->itemsDe(101),
            'la excepción detectada al migrar se borró en la primera sincronización');
        $this->assertSame([10 => 1.0, 20 => 1.0], $this->itemsDe(102));
    }

    public function test_la_simulacion_no_escribe(): void
    {
        $this->producto(10, 'Bastón');
        $this->listaSuelta(101, 'Seguridad física', [10 => 1]);

        $this->artisan('inventario:crear-kits')->assertSuccessful();

        $this->assertSame(0, Kit::count());
    }
}
