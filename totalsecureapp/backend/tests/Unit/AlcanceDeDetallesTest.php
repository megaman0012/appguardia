<?php

namespace Tests\Unit;

use App\Filament\Resources\InstitucionMarcadoresResource;
use App\Filament\Resources\RondaDetalleResource;
use App\Support\PerfilPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Que un Supervisor no vea lo de otro cliente cambiando un numero en la URL.
 *
 * ⚠️ `RondaDetalleResource` e `InstitucionMarcadoresResource` filtraban **solo
 * por el parametro de la URL** (`?ronda=`, `?codigo=`), sin mirar el perfil. Los
 * dos ids son enteros consecutivos, asi que no habia nada que adivinar:
 *
 *  - En rondas, un Supervisor leia el recorrido de otro local: a que hora paso
 *    el guardia por cada punto, con foto y coordenadas.
 *  - En marcadores era peor, porque ese recurso **se puede editar**: podia mover
 *    las coordenadas del QR de otro cliente. Correr un marcador unos metros hace
 *    que las rondas de ese local empiecen a fallar la validacion de cercania,
 *    sin que quede registrado como un cambio de configuracion.
 *
 * Los listados padre si acotaban, y eso mantenia el agujero fuera de la vista:
 * por la navegacion normal nunca se llegaba a una ronda ajena.
 */
class AlcanceDeDetallesTest extends TestCase
{
    use RefreshDatabase;

    private int $localPropio;
    private int $localAjeno;
    private int $rondaPropia;
    private int $rondaAjena;
    private int $marcadorPropio;
    private int $marcadorAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Supervisor Uno', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 's@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->localPropio = $this->crearLocal('Local propio');
        $this->localAjeno  = $this->crearLocal('Local de otro cliente');

        $this->rondaPropia = $this->crearRonda($this->localPropio);
        $this->rondaAjena  = $this->crearRonda($this->localAjeno);

        $this->marcadorPropio = $this->crearMarcador($this->localPropio);
        $this->marcadorAjeno  = $this->crearMarcador($this->localAjeno);

        // El supervisor queda vinculado SOLO al local propio.
        DB::table('user_has_institucion')->insert([
            'ui_usu_id' => 1, 'ui_ins_code' => $this->localPropio, 'ui_state' => 1,
            'ui_created_at' => now(), 'ui_updated_at' => now(),
        ]);

        Session::put('usuID', 1);
        Session::put('usuPF', PerfilPanel::SUPERVISOR);
    }

    private function crearLocal(string $nombre): int
    {
        return DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => $nombre, 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');
    }

    private function crearRonda(int $insCode): int
    {
        $rcId = DB::table('ronda_cabecera')->insertGetId([
            'rc_usu_code' => 1, 'rc_ins_code' => $insCode, 'rc_estado' => 1,
            'rc_fecha_inicio' => now(), 'rc_created_at' => now(), 'rc_updated_at' => now(),
        ], 'rc_id');

        DB::table('ronda_detalle')->insert([
            'rd_usu_id' => 1, 'rd_ins_code' => $insCode, 'rd_rc_id' => $rcId,
            'rd_observacion' => 'Punto revisado', 'rd_fecha_hora' => now(),
            'rd_estado' => 1, 'rd_created_at' => now(), 'rd_updated_at' => now(),
        ]);

        return $rcId;
    }

    private function crearMarcador(int $insCode): int
    {
        return DB::table('institucion_marcadores')->insertGetId([
            'im_ins_code' => $insCode, 'im_numero' => 1, 'im_tipo' => 'QR',
            'im_descripcion' => 'Puerta', 'im_lat' => '-2.18', 'im_lng' => '-79.88',
            'im_estado' => true, 'im_created_at' => now(), 'im_updated_at' => now(),
        ], 'im_code');
    }

    /** Pone el parametro en la URL, que es de donde lo lee el recurso. */
    private function conUrl(string $parametro, int $valor): void
    {
        app()->instance('request', Request::create("/admin?{$parametro}={$valor}", 'GET'));
    }

    // ── Detalle de rondas ────────────────────────────────────────────────

    /** EL AGUJERO: cambiar el `?ronda=` a mano. */
    public function test_un_supervisor_no_ve_el_detalle_de_la_ronda_de_otro_local(): void
    {
        $this->conUrl('ronda', $this->rondaAjena);

        $this->assertSame(0, RondaDetalleResource::getEloquentQuery()->count());
    }

    public function test_un_supervisor_si_ve_el_detalle_de_su_propia_ronda(): void
    {
        $this->conUrl('ronda', $this->rondaPropia);

        $this->assertSame(1, RondaDetalleResource::getEloquentQuery()->count());
    }

    public function test_un_administrador_ve_el_detalle_de_cualquier_ronda(): void
    {
        Session::put('usuPF', PerfilPanel::ADMINISTRADOR);
        $this->conUrl('ronda', $this->rondaAjena);

        $this->assertSame(1, RondaDetalleResource::getEloquentQuery()->count());
    }

    /**
     * Un supervisor sin locales vinculados no ve NADA, no ve todo. Es la
     * distincion que convierte una configuracion incompleta en acceso global.
     */
    public function test_un_supervisor_sin_locales_no_ve_ningun_detalle(): void
    {
        DB::table('user_has_institucion')->where('ui_usu_id', 1)->delete();
        $this->conUrl('ronda', $this->rondaPropia);

        $this->assertSame(0, RondaDetalleResource::getEloquentQuery()->count());
    }

    // ── Marcadores del local ─────────────────────────────────────────────

    /** EL AGUJERO, y este ademas se puede editar. */
    public function test_un_supervisor_no_ve_los_marcadores_de_otro_local(): void
    {
        $this->conUrl('codigo', $this->localAjeno);

        $this->assertSame(0, InstitucionMarcadoresResource::getEloquentQuery()->count());
    }

    public function test_un_supervisor_si_ve_los_marcadores_de_su_local(): void
    {
        $this->conUrl('codigo', $this->localPropio);

        $this->assertSame(1, InstitucionMarcadoresResource::getEloquentQuery()->count());
    }

    public function test_un_administrador_ve_los_marcadores_de_cualquier_local(): void
    {
        Session::put('usuPF', PerfilPanel::ADMINISTRADOR);
        $this->conUrl('codigo', $this->localAjeno);

        $this->assertSame(1, InstitucionMarcadoresResource::getEloquentQuery()->count());
    }

    /**
     * El registro ajeno no se puede ni traer por id: si se pudiera, la pagina
     * de edicion lo cargaria igual aunque el listado no lo muestre.
     */
    public function test_el_marcador_ajeno_no_se_puede_traer_por_id(): void
    {
        $this->conUrl('codigo', $this->localAjeno);

        $this->assertNull(
            InstitucionMarcadoresResource::getEloquentQuery()
                ->where('im_code', $this->marcadorAjeno)
                ->first()
        );
    }

    public function test_la_ronda_ajena_no_se_puede_traer_por_id(): void
    {
        $this->conUrl('ronda', $this->rondaAjena);

        $this->assertNull(RondaDetalleResource::getEloquentQuery()->first());
    }

    // ── El helper que resuelve los dos alcances ──────────────────────────

    public function test_localesVisibles_acota_al_supervisor_a_los_suyos(): void
    {
        $this->assertSame([$this->localPropio], PerfilPanel::localesVisibles());
    }

    public function test_localesVisibles_devuelve_null_para_el_administrador(): void
    {
        Session::put('usuPF', PerfilPanel::ADMINISTRADOR);

        $this->assertNull(PerfilPanel::localesVisibles());
    }

    public function test_localesVisibles_devuelve_vacio_y_no_null_sin_locales(): void
    {
        DB::table('user_has_institucion')->where('ui_usu_id', 1)->delete();

        // `[]` es "no ve nada"; `null` seria "ve todo". La diferencia es el bug.
        $this->assertSame([], PerfilPanel::localesVisibles());
    }
}
