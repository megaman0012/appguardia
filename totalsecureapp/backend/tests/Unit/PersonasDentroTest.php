<?php

namespace Tests\Unit;

use App\Filament\Resources\PersonasDentroResource;
use App\Services\AccesoService;
use App\Support\PerfilPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Modules\Administracion\Models\Acceso;
use Tests\TestCase;

/**
 * Quien esta adentro, y cerrar su acceso desde la web.
 *
 * ⚠️ Faltaban las dos cosas: el listado de Accesos mezcla todo el historico, asi
 * que saber quien seguia dentro era leerlo fila por fila, y **la salida solo se
 * podia registrar desde la tablet**. Si el visitante se iba por otra puerta, el
 * acceso quedaba abierto indefinidamente.
 */
class PersonasDentroTest extends TestCase
{
    use RefreshDatabase;

    private int $localPropio;
    private int $localAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Supervisor', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 's@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->localPropio = $this->crearLocal('Local propio');
        $this->localAjeno  = $this->crearLocal('Local ajeno');

        DB::table('user_has_institucion')->insert([
            'ui_usu_id' => 1, 'ui_ins_code' => $this->localPropio, 'ui_state' => 1,
            'ui_created_at' => now(), 'ui_updated_at' => now(),
        ]);

        Session::put('usuID', 1);
        Session::put('usuPF', PerfilPanel::ADMINISTRADOR);
    }

    private function crearLocal(string $nombre): int
    {
        return DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => $nombre, 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');
    }

    private function crearAcceso(int $insCode, string $estado = Acceso::ESTADO_EN_CURSO): Acceso
    {
        return Acceso::create([
            'ac_usu_id' => 1,
            'ac_ins_code' => $insCode,
            'ac_is_entrada' => 1,
            'ac_estado_acceso' => $estado,
            'ac_estado' => 1,
            'ac_created_at' => now(),
            'ac_updated_at' => now(),
        ]);
    }

    public function test_lista_a_quien_sigue_adentro(): void
    {
        $acceso = $this->crearAcceso($this->localPropio);

        $this->assertTrue(
            PersonasDentroResource::getEloquentQuery()
                ->where('ac_code', $acceso->ac_code)->exists()
        );
    }

    public function test_no_lista_a_quien_ya_salio(): void
    {
        $acceso = $this->crearAcceso($this->localPropio, Acceso::ESTADO_COMPLETADA);

        $this->assertFalse(
            PersonasDentroResource::getEloquentQuery()
                ->where('ac_code', $acceso->ac_code)->exists()
        );
    }

    public function test_registrar_la_salida_lo_saca_de_la_lista(): void
    {
        $acceso = $this->crearAcceso($this->localPropio);

        app(AccesoService::class)->registrarSalida($acceso->ac_code);

        $this->assertFalse(
            PersonasDentroResource::getEloquentQuery()
                ->where('ac_code', $acceso->ac_code)->exists()
        );

        $fresco = Acceso::find($acceso->ac_code);
        $this->assertNotNull($fresco->ac_is_salida_fecha);
        $this->assertSame(Acceso::ESTADO_COMPLETADA, $fresco->ac_estado_acceso);
    }

    /** Una salida registrada dos veces no puede pisar la hora de la primera. */
    public function test_no_se_puede_registrar_la_salida_dos_veces(): void
    {
        $acceso = $this->crearAcceso($this->localPropio);
        app(AccesoService::class)->registrarSalida($acceso->ac_code);

        $this->expectException(\RuntimeException::class);
        app(AccesoService::class)->registrarSalida($acceso->ac_code);
    }

    /**
     * La salida desde el panel no lleva coordenadas: la registra una persona
     * desde una oficina, no el dispositivo del punto. Inventar la ubicacion del
     * servidor seria peor que no tener ninguna.
     */
    public function test_la_salida_desde_el_panel_no_inventa_coordenadas(): void
    {
        $acceso = $this->crearAcceso($this->localPropio);

        app(AccesoService::class)->registrarSalida($acceso->ac_code);

        $fresco = Acceso::find($acceso->ac_code);
        $this->assertNull($fresco->ac_lat_sal);
        $this->assertNull($fresco->ac_lng_sal);
    }

    public function test_un_supervisor_no_ve_a_los_de_otro_local(): void
    {
        Session::put('usuPF', PerfilPanel::SUPERVISOR);

        $propio = $this->crearAcceso($this->localPropio);
        $ajeno  = $this->crearAcceso($this->localAjeno);

        $codigos = PersonasDentroResource::getEloquentQuery()->pluck('ac_code')->all();

        $this->assertContains($propio->ac_code, $codigos);
        $this->assertNotContains($ajeno->ac_code, $codigos);
    }

    public function test_no_se_registran_ingresos_desde_esta_pantalla(): void
    {
        // Los ingresos se toman en el punto, con la tablet.
        $this->assertFalse(PersonasDentroResource::canCreate());
    }
}
