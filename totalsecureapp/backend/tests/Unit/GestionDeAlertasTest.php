<?php

namespace Tests\Unit;

use App\Services\AlertaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Alertas;
use Tests\TestCase;

/**
 * Cerrar una emergencia desde el panel.
 *
 * ⚠️ **No se podia.** El panel listaba las alertas y mostraba su estado, pero no
 * tenia ninguna accion para cambiarlo: se quedaban en «en atencion» para
 * siempre. `AlertaService::atenderAlerta()` y `cancelarAlerta()` existian desde
 * el principio y **nadie los llamaba**.
 *
 * El circuito real es: el guardia aprieta el boton, Consola llama al punto y
 * averigua que paso, y cierra dejando escrito el resultado.
 */
class GestionDeAlertasTest extends TestCase
{
    use RefreshDatabase;

    private int $insCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insCode = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Garita', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        foreach ([1 => 'Guardia', 2 => 'Consola'] as $id => $nombre) {
            DB::table('users')->updateOrInsert(['id' => $id], [
                'usu_cedula' => str_repeat((string) $id, 10), 'usu_tipdoc' => 'CC',
                'usu_password' => bcrypt('x'), 'usu_nmbcom' => $nombre,
                'usu_ape1' => 'T', 'usu_ape2' => 'T', 'usu_nmb1' => 'T', 'usu_nmb2' => 'T',
                'usu_email' => "u{$id}@e.com", 'usu_state' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function alerta(): Alertas
    {
        return app(AlertaService::class)->crearAlerta([
            'institucion_id' => $this->insCode,
            'usuario_id' => 1,
            'lat' => '-2.189', 'lng' => '-79.889',
            'observacion' => 'Botón de emergencia',
            'prioridad' => 'critica',
        ]);
    }

    public function test_finalizar_deja_la_alerta_cerrada(): void
    {
        $a = $this->alerta();

        app(AlertaService::class)->atenderAlerta($a, 2, 'Se llamó al punto, fue un roce sin consecuencias.');

        $this->assertNotSame('en_atencion', Alertas::find($a->al_code)->al_estado_alerta);
    }

    /** Lo que se escribe al cerrar tiene que quedar, o cerrar no informa nada. */
    public function test_el_comentario_queda_en_el_historial(): void
    {
        $a = $this->alerta();

        app(AlertaService::class)->atenderAlerta($a, 2, 'Todo en orden tras verificar.');

        $this->assertTrue(
            DB::table('alertas_historial')->where('ah_al_code', $a->al_code)->exists(),
            'No quedo rastro de quien cerro la alerta ni de cuando.'
        );
    }

    /**
     * Una falsa alarma y una emergencia atendida no pueden contar como lo
     * mismo: si se mezclan, el tiempo medio de respuesta deja de significar
     * nada.
     */
    public function test_una_falsa_alarma_se_cancela_y_no_se_finaliza(): void
    {
        $a = $this->alerta();

        app(AlertaService::class)->cancelarAlerta($a, 2, 'Se apretó sin querer al limpiar.');

        $this->assertSame('cancelada', Alertas::find($a->al_code)->al_estado_alerta);
    }

    public function test_una_alerta_cancelada_sale_del_aviso_en_vivo(): void
    {
        $a = $this->alerta();
        app(AlertaService::class)->cancelarAlerta($a, 2, 'Falsa alarma.');

        \Illuminate\Support\Facades\Session::put('usuID', 2);
        \Illuminate\Support\Facades\Session::put('usuPF', \App\Support\PerfilPanel::ADMINISTRADOR);

        $codigos = array_column((new \App\Filament\Widgets\AlertasEnVivo())->getAlertas(), 'code');

        $this->assertNotContains($a->al_code, $codigos);
    }

    /**
     * ⚠️ Sin supervisor vinculado al local, la alerta queda «pendiente» y sin
     * asignacion. Antes cerrarla lanzaba «La alerta no tiene asignación activa»,
     * asi que **esas emergencias eran imposibles de cerrar para siempre**. Que
     * la configuracion este incompleta no puede dejar una alerta abierta
     * eternamente: es justo cuando Consola tiene que poder cerrarla a mano.
     */
    public function test_se_puede_cerrar_una_alerta_sin_supervisor_asignado(): void
    {
        $a = $this->alerta();
        DB::table('alertas_detalle')->where('ad_al_code', $a->al_code)->delete();

        app(AlertaService::class)->atenderAlerta($a->fresh(), 2, 'Cierre desde Consola');

        $this->assertTrue(
            DB::table('alertas_detalle')->where('ad_al_code', $a->al_code)->exists(),
            'No quedo registro de quien se hizo cargo.'
        );
    }
}
