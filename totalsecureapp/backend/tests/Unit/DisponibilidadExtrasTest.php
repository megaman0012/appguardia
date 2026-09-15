<?php

namespace Tests\Unit;

use App\Filament\Resources\DisponibilidadExtrasResource;
use App\Support\PerfilPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * El interruptor de «quiero cubrir turnos extra», ahora tambien desde el panel.
 *
 * ⚠️ `usu_acepta_extras` decide si un guardia ve las vacantes abiertas y si
 * entra en la convocatoria por WhatsApp, y **no se podia tocar desde ningun
 * lado del panel**: solo desde el Perfil dentro de la app. Como la app vive en
 * las tablets de los puestos y no en el telefono del guardia, ofrecerse para un
 * turno extra obligaba a ir hasta un puesto.
 */
class DisponibilidadExtrasTest extends TestCase
{
    use RefreshDatabase;

    private int $localPropio;
    private int $localAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->localPropio = $this->crearLocal('Local propio');
        $this->localAjeno  = $this->crearLocal('Local ajeno');

        $this->crearGuardia(1, 'Guardia Propio', $this->localPropio);
        $this->crearGuardia(2, 'Guardia Ajeno', $this->localAjeno);
        $this->crearGuardia(3, 'Guardia Inactivo', $this->localPropio, 0);

        // El supervisor de sesion solo esta vinculado al local propio.
        DB::table('users')->updateOrInsert(['id' => 9], [
            'usu_cedula' => '9999999999', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Supervisor', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'sup@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('user_has_institucion')->insert([
            'ui_usu_id' => 9, 'ui_ins_code' => $this->localPropio, 'ui_state' => 1,
            'ui_created_at' => now(), 'ui_updated_at' => now(),
        ]);

        Session::put('usuID', 9);
        Session::put('usuPF', PerfilPanel::SUPERVISOR);
    }

    private function crearLocal(string $nombre): int
    {
        return DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => $nombre, 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');
    }

    private function crearGuardia(int $id, string $nombre, int $insCode, int $estado = 1): void
    {
        DB::table('users')->updateOrInsert(['id' => $id], [
            'usu_cedula' => str_repeat((string) $id, 10), 'usu_tipdoc' => 'CC',
            'usu_password' => bcrypt('x'), 'usu_nmbcom' => $nombre,
            'usu_ape1' => 'T', 'usu_ape2' => 'T', 'usu_nmb1' => 'T', 'usu_nmb2' => 'T',
            'usu_email' => "g{$id}@e.com", 'usu_state' => $estado,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('user_has_institucion')->insert([
            'ui_usu_id' => $id, 'ui_ins_code' => $insCode, 'ui_state' => 1,
            'ui_created_at' => now(), 'ui_updated_at' => now(),
        ]);
    }

    /** @return string[] */
    private function nombresListados(): array
    {
        return DisponibilidadExtrasResource::getEloquentQuery()
            ->pluck('usu_nmbcom')->all();
    }

    public function test_el_supervisor_ve_a_su_gente(): void
    {
        $this->assertContains('Guardia Propio', $this->nombresListados());
    }

    public function test_el_supervisor_no_ve_a_la_gente_de_otro_local(): void
    {
        $this->assertNotContains('Guardia Ajeno', $this->nombresListados());
    }

    public function test_no_se_lista_personal_inactivo(): void
    {
        $this->assertNotContains('Guardia Inactivo', $this->nombresListados());
    }

    public function test_el_administrador_ve_a_todos(): void
    {
        Session::put('usuPF', PerfilPanel::ADMINISTRADOR);

        $nombres = $this->nombresListados();

        $this->assertContains('Guardia Propio', $nombres);
        $this->assertContains('Guardia Ajeno', $nombres);
    }

    /** Aca no se dan de alta personas: eso es Usuarios. */
    public function test_no_se_pueden_crear_usuarios_desde_esta_pantalla(): void
    {
        $this->assertFalse(DisponibilidadExtrasResource::canCreate());
    }

    /**
     * Lo que el panel cambia tiene que ser lo que la API lee: es el mismo campo,
     * y de eso depende que activarlo en la web se note en la tablet.
     */
    public function test_el_campo_que_activa_el_panel_es_el_que_lee_la_api(): void
    {
        DB::table('users')->where('id', 1)->update(['usu_acepta_extras' => true]);

        $this->assertTrue(
            (bool) \Modules\MobileApp\Models\users::find(1)->usu_acepta_extras
        );
    }
}
