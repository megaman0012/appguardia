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

    /*
     * ---------------------------------------------------------------
     * Que el widget SE DIBUJE, no solo que la consulta devuelva datos.
     *
     * ⚠️ Los tests de arriba pasaban mientras el panel mostraba el widget roto:
     * comprobaban `getAlertas()`, que es PHP puro, y nunca renderizaban la
     * vista. El boton de sonido no aparecia porque la plantilla usaba
     * `<x-filament::section>`, **que no existe en esta version de Filament** --
     * el componente fallaba y se llevaba por delante su contenido.
     *
     * Dos veces se dio por arreglado sin comprobarlo. Esto lo comprueba.
     * ---------------------------------------------------------------
     */

    public function test_el_widget_se_dibuja_sin_reventar(): void
    {
        \Livewire\Livewire::test(AlertasEnVivo::class)->assertSuccessful();
    }

    /** El boton tiene que estar aunque NO haya emergencias: si no, no hay forma
     *  de dejar el sonido listo de antemano ni de comprobar que se oye. */
    public function test_el_boton_de_sonido_esta_aunque_no_haya_alertas(): void
    {
        $html = \Livewire\Livewire::test(AlertasEnVivo::class)->html();

        $this->assertStringContainsString('Activar sonido', $html);
    }

    /**
     * El widget se engancha al motor compartido, y sondea sin dormirse.
     *
     * ⚠️ Antes esto comprobaba que el propio widget registrara
     * `Livewire.on('emergencia-nueva')`. **Ya no lo hace, y es a proposito:** el
     * oyente estaba copiado aca y en el componente global, asi que Livewire
     * --que redibuja el widget en cada sondeo-- iba acumulando oyentes y la
     * alarma sonaba encima de si misma. Ahora hay uno solo, en
     * `partials/alarma-de-emergencia-js`, y `PanelConSesionTest` lo verifica
     * sobre la pagina entera, que es donde de verdad vive.
     *
     * Lo que si tiene que estar aca es el enganche al motor y el `keep-alive`:
     * sin ese modificador Livewire descarta el 95% de los sondeos con la pestaña
     * en segundo plano.
     */
    public function test_el_widget_se_engancha_al_motor_compartido(): void
    {
        $html = \Livewire\Livewire::test(AlertasEnVivo::class)->html();

        $this->assertStringContainsString('alarmaDeEmergencia()', $html);
        $this->assertStringContainsString('wire:poll.15s.keep-alive', $html);
    }

    public function test_una_emergencia_aparece_en_el_html(): void
    {
        $this->alerta();

        $html = \Livewire\Livewire::test(AlertasEnVivo::class)->html();

        $this->assertStringContainsString('Garita Norte', $html);
        $this->assertStringContainsString('Guardia Uno', $html);
    }
}
