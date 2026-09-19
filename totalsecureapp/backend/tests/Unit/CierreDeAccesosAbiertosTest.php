<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Acceso;
use Tests\TestCase;

/**
 * «Personas dentro» decia 9.769 de 9.776 accesos que existen.
 *
 * Eran dos problemas distintos mezclados, y por eso el test los separa:
 *
 *  - **6.436 ya habian salido.** El ETL de la v1 importo los accesos sin tocar
 *    `ac_estado_acceso`, que tiene `default('en_curso')`, asi que entraron como
 *    «en curso» aunque `ac_is_entrada` dijera 0. La migracion
 *    `2026_08_21_100002_acceso_generalizado_migrate_data` ya traia la regla
 *    correcta, pero corrio tres semanas antes que el ETL.
 *  - **3.333 nunca tuvieron salida registrada.**
 *
 * Lo que este test fija, y es lo que importa que no se pierda: al cerrar los del
 * segundo grupo **no se inventa una hora de salida**. Poner `now()` dejaria un
 * acceso de abril de 2025 con diecisiete meses adentro, y ese numero despues se
 * lee como real en los reportes de permanencia.
 */
class CierreDeAccesosAbiertosTest extends TestCase
{
    use RefreshDatabase;

    private function acceso(array $extra = []): int
    {
        return (int) DB::table('acceso')->insertGetId(array_merge([
            'ac_usu_id'        => 1,
            'ac_ins_code'      => 1,
            'ac_is_entrada'    => 1,
            'ac_bicicleta'     => false,
            'ac_is_acomp'      => false,
            'ac_estado'        => true,
            'ac_estado_acceso' => Acceso::ESTADO_EN_CURSO,
            'ac_created_at'    => Carbon::parse('2025-04-16 22:34:44'),
        ], $extra), 'ac_code');
    }

    public function test_el_que_ya_salio_solo_recibe_el_estado_que_le_faltaba(): void
    {
        $salida = Carbon::parse('2025-04-17 06:00:00');

        $id = $this->acceso([
            'ac_is_entrada'      => 0,
            'ac_is_salida_fecha' => $salida,
        ]);

        $this->artisan('accesos:cerrar-abiertos', ['--ejecutar' => true])->assertSuccessful();

        $acc = DB::table('acceso')->where('ac_code', $id)->first();

        $this->assertSame(Acceso::ESTADO_COMPLETADA, $acc->ac_estado_acceso);

        // Su salida fue real: no se toca la hora ni se anota nada encima.
        $this->assertSame($salida->toDateTimeString(), Carbon::parse($acc->ac_is_salida_fecha)->toDateTimeString());
        $this->assertNull($acc->ac_observaciones);

        // Y NO se inventa una marca de salida hoy para un hecho de 2025.
        $this->assertSame(0, DB::table('acceso_historial')->where('ah_ac_code', $id)->count());
    }

    public function test_el_abierto_de_verdad_se_cierra_sin_fabricar_la_hora(): void
    {
        $ingreso = Carbon::parse('2025-04-16 22:34:44');
        $id      = $this->acceso(['ac_created_at' => $ingreso]);

        $this->artisan('accesos:cerrar-abiertos', ['--ejecutar' => true])->assertSuccessful();

        $acc = DB::table('acceso')->where('ac_code', $id)->first();

        $this->assertSame(Acceso::ESTADO_COMPLETADA, $acc->ac_estado_acceso);
        $this->assertSame(0, (int) $acc->ac_is_entrada);

        // Lo que no debe pasar: que quede con la fecha de hoy.
        $this->assertSame(
            $ingreso->toDateTimeString(),
            Carbon::parse($acc->ac_is_salida_fecha)->toDateTimeString(),
            'la salida se fabrico con la hora de ahora en vez de igualarse al ingreso',
        );

        // Y que se sepa por que esta cerrado.
        $this->assertStringContainsString('Cierre administrativo', $acc->ac_observaciones);
        $this->assertSame(1, DB::table('acceso_historial')->where('ah_ac_code', $id)->count());
    }

    public function test_sin_fecha_de_ingreso_no_se_le_pone_ninguna_salida(): void
    {
        $id = $this->acceso(['ac_created_at' => null]);

        $this->artisan('accesos:cerrar-abiertos', ['--ejecutar' => true])->assertSuccessful();

        $acc = DB::table('acceso')->where('ac_code', $id)->first();

        $this->assertSame(Acceso::ESTADO_COMPLETADA, $acc->ac_estado_acceso);
        $this->assertNull($acc->ac_is_salida_fecha, 'no hay de donde sacar la hora: debe quedar nula');
    }

    public function test_dias_de_gracia_deja_abiertos_los_recientes(): void
    {
        $viejo    = $this->acceso(['ac_created_at' => Carbon::now()->subDays(30)]);
        $reciente = $this->acceso(['ac_created_at' => Carbon::now()->subDays(2)]);

        $this->artisan('accesos:cerrar-abiertos', ['--ejecutar' => true, '--dias-gracia' => 7])
            ->assertSuccessful();

        $this->assertSame(Acceso::ESTADO_COMPLETADA,
            DB::table('acceso')->where('ac_code', $viejo)->value('ac_estado_acceso'));

        $this->assertSame(Acceso::ESTADO_EN_CURSO,
            DB::table('acceso')->where('ac_code', $reciente)->value('ac_estado_acceso'),
            'un acceso reciente puede ser gente realmente adentro: lo resuelve la operacion');
    }

    public function test_correrlo_dos_veces_no_vuelve_a_escribir(): void
    {
        $id = $this->acceso();

        $this->artisan('accesos:cerrar-abiertos', ['--ejecutar' => true])->assertSuccessful();
        $this->artisan('accesos:cerrar-abiertos', ['--ejecutar' => true])->assertSuccessful();

        // Una sola marca y una sola nota: si no fuera idempotente, la
        // observacion se iria acumulando en cada corrida.
        $this->assertSame(1, DB::table('acceso_historial')->where('ah_ac_code', $id)->count());
        $this->assertSame(1, substr_count(
            (string) DB::table('acceso')->where('ac_code', $id)->value('ac_observaciones'),
            'Cierre administrativo',
        ));
    }

    public function test_la_simulacion_no_escribe_nada(): void
    {
        $id = $this->acceso();

        $this->artisan('accesos:cerrar-abiertos')->assertSuccessful();

        $this->assertSame(Acceso::ESTADO_EN_CURSO,
            DB::table('acceso')->where('ac_code', $id)->value('ac_estado_acceso'));
    }
}
