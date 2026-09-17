<?php

namespace Tests\Unit;

use App\Filament\Widgets\AlertasEnVivo;
use App\Support\PerfilPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * El aviso de emergencia en el panel.
 *
 * ⚠️ La notificacion del boton de panico funcionaba --quedaba registrada y le
 * llegaba a supervisores, Consola y Administradores-- pero el unico aviso
 * visible era la campanita de Filament: **un numero pequeño en un icono, que no
 * suena ni interrumpe**. Con el panel abierto en una pantalla que nadie mira, una
 * emergencia podia pasar inadvertida indefinidamente.
 */
class AlertasEnVivoTest extends TestCase
{
    use RefreshDatabase;

    private int $localPropio;
    private int $localAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Guardia Uno', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'g@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->localPropio = $this->local('Garita Norte');
        $this->localAjeno  = $this->local('Local ajeno');

        DB::table('user_has_institucion')->insert([
            'ui_usu_id' => 1, 'ui_ins_code' => $this->localPropio, 'ui_state' => 1,
            'ui_created_at' => now(), 'ui_updated_at' => now(),
        ]);

        Session::put('usuID', 1);
        Session::put('usuPF', PerfilPanel::ADMINISTRADOR);
    }

    private function local(string $n): int
    {
        return DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => $n, 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');
    }

    private function alerta(array $extra = []): int
    {
        return DB::table('alertas')->insertGetId(array_merge([
            'al_ins_code' => $this->localPropio,
            'al_usu_id' => 1,
            'al_lat' => '-2.189', 'al_lng' => '-79.889',
            'al_anio' => date('Y'),
            'al_estado_alerta' => 'pendiente',
            'al_estado' => 1,
            'al_prioridad' => 'critica',
            'al_observacion' => 'Botón de emergencia',
            'al_fecha' => now(),
        ], $extra), 'al_code');
    }

    private function widget(): AlertasEnVivo
    {
        return new AlertasEnVivo();
    }

    public function test_muestra_una_emergencia_sin_atender(): void
    {
        $code = $this->alerta();

        $this->assertContains($code, array_column($this->widget()->getAlertas(), 'code'));
    }

    public function test_no_muestra_las_ya_resueltas(): void
    {
        $code = $this->alerta(['al_estado_alerta' => 'finalizada']);

        $this->assertNotContains($code, array_column($this->widget()->getAlertas(), 'code'));
    }

    /**
     * Sin este corte, una alerta vieja que nadie cerro seguiria sonando para
     * siempre y el aviso se volveria ruido que todos aprenden a ignorar.
     */
    public function test_no_muestra_alertas_de_hace_mas_de_un_dia(): void
    {
        $code = $this->alerta(['al_fecha' => now()->subDays(2)]);

        $this->assertNotContains($code, array_column($this->widget()->getAlertas(), 'code'));
    }

    public function test_un_supervisor_no_ve_emergencias_de_otro_local(): void
    {
        Session::put('usuPF', PerfilPanel::SUPERVISOR);

        $propia = $this->alerta();
        $ajena  = $this->alerta(['al_ins_code' => $this->localAjeno]);

        $codes = array_column($this->widget()->getAlertas(), 'code');

        $this->assertContains($propia, $codes);
        $this->assertNotContains($ajena, $codes);
    }

    /** La app manda 0/0 cuando no hubo lectura de GPS. */
    public function test_sin_gps_no_ofrece_un_mapa_que_apunta_a_ninguna_parte(): void
    {
        $this->alerta(['al_lat' => '0', 'al_lng' => '0']);

        $this->assertNull($this->widget()->getAlertas()[0]['mapa']);
    }

    public function test_con_gps_ofrece_el_mapa(): void
    {
        $this->alerta();

        $this->assertStringContainsString('maps.google.com', $this->widget()->getAlertas()[0]['mapa']);
    }

    /** Lleva el local y quien la mando: una alerta anonima no sirve para actuar. */
    public function test_dice_de_que_local_y_de_quien_es(): void
    {
        $this->alerta();

        $a = $this->widget()->getAlertas()[0];

        $this->assertSame('Garita Norte', $a['local']);
        $this->assertSame('Guardia Uno', $a['guardia']);
    }
}
