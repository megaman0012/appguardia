<?php

namespace Tests\Unit;

use App\Filament\Resources\AvisoResource\Pages\ListAvisos;
use App\Filament\Resources\TurnoResource\Pages\ListTurnos;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Modules\Administracion\Models\AvisoEnvio;
use Modules\Administracion\Models\Turno;
use Tests\TestCase;

/**
 * Las pantallas del panel, renderizadas CON datos.
 *
 * Una tabla vacía se dibuja bien casi siempre; los errores aparecen con la
 * primera fila. Pasó de verdad: `TurnoResource` formateaba la marcación de
 * entrada como fecha con un guión de relleno cuando era null, y la pantalla
 * entera respondía 500 en cuanto existía un turno sin marcar — que es el estado
 * normal de todo turno futuro.
 */
class PantallasConDatosTest extends TestCase
{
    use RefreshDatabase;

    private int $local;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-01 07:00:00'));

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Guardia Uno', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'g@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->local = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Terminal', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        Session::put('usuID', 1);
        Session::put('usuPF', 'Administrador');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_la_tabla_de_turnos_se_dibuja_con_un_turno_sin_marcar(): void
    {
        // Un turno futuro SIEMPRE está sin marcar: si esto revienta, la pantalla
        // de turnos no sirve para lo único para lo que existe.
        Turno::create([
            'tu_ins_code' => $this->local, 'tu_usu_id' => 1,
            'tu_fecha' => Carbon::tomorrow()->toDateString(),
            'tu_hora_inicio_prevista' => '06:00', 'tu_hora_fin_prevista' => '14:00',
            'tu_estado' => 'programado', 'tu_state' => true,
        ]);

        Livewire::test(ListTurnos::class)->assertSuccessful()->assertSee('Terminal');
    }

    public function test_la_tabla_de_turnos_se_dibuja_con_un_turno_ya_marcado(): void
    {
        Turno::create([
            'tu_ins_code' => $this->local, 'tu_usu_id' => 1,
            'tu_fecha' => Carbon::today()->toDateString(),
            'tu_hora_inicio_prevista' => '06:00', 'tu_hora_fin_prevista' => '14:00',
            'tu_marcada_entrada' => Carbon::today()->setTime(6, 3),
            'tu_minutos_tardanza' => 3,
            'tu_estado' => 'en_curso', 'tu_state' => true,
        ]);

        Livewire::test(ListTurnos::class)->assertSuccessful();
    }

    public function test_la_tabla_de_avisos_se_dibuja_con_envios_y_respuestas(): void
    {
        AvisoEnvio::create([
            'ae_usu_id' => 1, 'ae_canal' => 'whatsapp', 'ae_tipo' => 'vacante_abierta',
            'ae_titulo' => 'Turno por cubrir', 'ae_destino' => '593987654321',
            'ae_direccion' => AvisoEnvio::SALIENTE, 'ae_resultado' => AvisoEnvio::ENVIADO,
        ]);

        AvisoEnvio::create([
            'ae_usu_id' => 1, 'ae_canal' => 'whatsapp', 'ae_tipo' => 'respuesta_negativa',
            'ae_titulo' => 'Respuesta del guardia',
            'ae_direccion' => AvisoEnvio::ENTRANTE, 'ae_resultado' => AvisoEnvio::ENVIADO,
            'ae_detalle' => 'Respondió que no puede cubrirlo',
        ]);

        Livewire::test(ListAvisos::class)
            ->assertSuccessful()
            ->assertSee('Respuesta')
            ->assertSee('Enviado');
    }
}
