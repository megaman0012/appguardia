<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Puesto;
use Tests\TestCase;

/**
 * La carga de puestos de trabajo.
 *
 * ⚠️ La tabla `puesto` estaba vacia, y sin puestos **Turnos, el cuadrante y las
 * vacantes no tenian nada que mostrar**: un turno se asigna a un puesto.
 *
 * La v1 no tenia puestos, asi que quien cargo los datos creo un «local» por cada
 * posicion fisica -- «Cementerio Puerta 10», «Garita 3» --, o sea que lo que hoy
 * figura como local **ya es** el puesto. El comando le da a cada local el suyo,
 * sin mover ningun registro historico.
 */
class CargarPuestosTest extends TestCase
{
    use RefreshDatabase;

    private function crearLocal(string $nombre, bool $activo = true): int
    {
        return DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => $nombre, 'ins_estado' => $activo,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');
    }

    public function test_la_simulacion_no_escribe_nada(): void
    {
        $this->crearLocal('Garita 1');

        $this->artisan('puestos:cargar')->assertSuccessful();

        $this->assertSame(0, Puesto::count());
    }

    public function test_crea_un_puesto_por_local_activo(): void
    {
        $a = $this->crearLocal('Garita 1');
        $b = $this->crearLocal('Garita 2');

        $this->artisan('puestos:cargar --ejecutar')->assertSuccessful();

        $this->assertSame(2, Puesto::count());
        $this->assertTrue(Puesto::where('pu_ins_code', $a)->where('pu_nombre', 'Garita 1')->exists());
        $this->assertTrue(Puesto::where('pu_ins_code', $b)->where('pu_nombre', 'Garita 2')->exists());
    }

    public function test_no_crea_puestos_para_locales_inactivos(): void
    {
        $this->crearLocal('Local cerrado', false);

        $this->artisan('puestos:cargar --ejecutar')->assertSuccessful();

        $this->assertSame(0, Puesto::count());
    }

    /** Correrlo dos veces no puede duplicar: la tabla tiene unico por local+nombre. */
    public function test_es_idempotente(): void
    {
        $this->crearLocal('Garita 1');

        $this->artisan('puestos:cargar --ejecutar')->assertSuccessful();
        $this->artisan('puestos:cargar --ejecutar')->assertSuccessful();

        $this->assertSame(1, Puesto::count());
    }

    public function test_toma_el_nombre_del_sitio_desde_el_csv(): void
    {
        $code = $this->crearLocal('Cementerio Puerta 10');

        $csv = tempnam(sys_get_temp_dir(), 'puestos') . '.csv';
        file_put_contents($csv, "sitio_propuesto,ins_code\n\"CEMENTERIO PUERTA\",{$code}\n");

        $this->artisan("puestos:cargar --csv={$csv} --ejecutar")->assertSuccessful();

        $this->assertSame(
            'CEMENTERIO PUERTA',
            Puesto::where('pu_ins_code', $code)->value('pu_descripcion')
        );

        unlink($csv);
    }

    /** Un CSV que no existe no puede frenar la carga. */
    public function test_sin_csv_valido_igual_carga(): void
    {
        $this->crearLocal('Garita 1');

        $this->artisan('puestos:cargar --csv=/no/existe.csv --ejecutar')->assertSuccessful();

        $this->assertSame(1, Puesto::count());
    }

    public function test_un_local_sin_descripcion_se_omite(): void
    {
        DB::table('organizacion_institucion')->insert([
            'ins_descripcion' => '', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('puestos:cargar --ejecutar')->assertSuccessful();

        $this->assertSame(0, Puesto::count());
    }
}
