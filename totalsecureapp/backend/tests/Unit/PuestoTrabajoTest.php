<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\OrganizacionInstitucion;
use Modules\Administracion\Models\Puesto;
use Modules\Administracion\Models\Turno;
use Modules\MobileApp\Models\users;
use Tests\TestCase;

/**
 * Puesto de trabajo: la posicion concreta dentro de un local.
 *
 * Distinto del marcador QR, que es un punto de paso de una ronda. Un turno se
 * cubre en un puesto.
 */
class PuestoTrabajoTest extends TestCase
{
    use RefreshDatabase;

    private int $local;
    private int $otroLocal;
    private int $guardia;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '9999999999', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('testing'),
            'usu_nmbcom' => 'Guardia Test', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 't@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->guardia = 1;

        $this->local = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Terminal de carga', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        $this->otroLocal = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Otro local', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');
    }

    private function crearPuesto(string $nombre, ?int $local = null): Puesto
    {
        return Puesto::create([
            'pu_ins_code' => $local ?? $this->local,
            'pu_nombre'   => $nombre,
            'pu_estado'   => true,
        ]);
    }

    private function programarTurno(?int $puestoId): Turno
    {
        return Turno::create([
            'tu_ins_code'             => $this->local,
            'tu_usu_id'               => $this->guardia,
            'tu_puesto_id'            => $puestoId,
            'tu_fecha'                => now()->toDateString(),
            'tu_hora_inicio_prevista' => '08:00',
            'tu_hora_fin_prevista'    => '16:00',
            'tu_estado'               => 'programado',
            'tu_state'                => true,
        ]);
    }

    // ── El puesto ──

    public function test_un_local_puede_tener_varios_puestos(): void
    {
        $this->crearPuesto('Garita de ingreso');
        $this->crearPuesto('Andén de carga');
        $this->crearPuesto('Sala de monitoreo');

        $this->assertSame(3, OrganizacionInstitucion::find($this->local)->puestos()->count());
    }

    public function test_no_admite_dos_puestos_con_el_mismo_nombre_en_el_local(): void
    {
        $this->crearPuesto('Garita de ingreso');

        // Serian indistinguibles al programar un turno.
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->crearPuesto('Garita de ingreso');
    }

    public function test_el_mismo_nombre_si_vale_en_otro_local(): void
    {
        $this->crearPuesto('Garita de ingreso');
        $otro = $this->crearPuesto('Garita de ingreso', $this->otroLocal);

        $this->assertSame($this->otroLocal, (int) $otro->pu_ins_code);
    }

    public function test_el_puesto_se_acota_por_institucion(): void
    {
        $this->crearPuesto('Garita', $this->local);
        $this->crearPuesto('Garita', $this->otroLocal);

        // Usa el trait BelongsToInstitution, igual que el resto del dominio.
        $this->assertSame(1, Puesto::forInstitution($this->local)->count());
        $this->assertSame(2, Puesto::forInstitutions([$this->local, $this->otroLocal])->count());
    }

    // ── El turno ──

    public function test_un_turno_se_cubre_en_un_puesto(): void
    {
        $puesto = $this->crearPuesto('Garita de ingreso');
        $turno = $this->programarTurno($puesto->pu_id);

        $this->assertSame('Garita de ingreso', $turno->puesto->pu_nombre);
        $this->assertSame(1, $puesto->turnos()->count());
    }

    public function test_el_puesto_es_opcional(): void
    {
        // No todos los locales dividen el trabajo en puestos.
        $turno = $this->programarTurno(null);

        $this->assertNull($turno->tu_puesto_id);
        $this->assertNull($turno->puesto);
    }

    public function test_borrar_un_puesto_con_turnos_no_borra_el_historial(): void
    {
        $puesto = $this->crearPuesto('Garita de ingreso');
        $this->programarTurno($puesto->pu_id);

        // La FK es restrict: el historial de turnos cumplidos no se pierde
        // porque alguien reorganice los puestos.
        $this->expectException(\Illuminate\Database\QueryException::class);
        $puesto->delete();
    }

    // ── El bug que impedia ver los turnos ──

    public function test_los_turnos_activos_se_filtran_por_tu_state_no_por_tu_estado(): void
    {
        $this->programarTurno(null);

        // tu_estado es varchar ('programado', 'en_curso'...); el booleano es
        // tu_state. Comparar tu_estado con true no falla: devuelve 0 filas en
        // silencio, que es lo que dejaba al guardia sin ver su turno en la app.
        $conBug = Turno::where('tu_ins_code', $this->local)->where('tu_estado', true)->count();
        $correcto = Turno::where('tu_ins_code', $this->local)->where('tu_state', true)->count();

        $this->assertSame(0, $conBug, 'Comparar tu_estado con true no matchea nunca');
        $this->assertSame(1, $correcto);
    }

    public function test_el_controller_ya_no_usa_la_comparacion_rota(): void
    {
        $archivo = base_path('Modules/MobileApp/Http/Controllers/TurnoController.php');
        $codigo = file_get_contents($archivo);

        $this->assertStringNotContainsString(
            "where('tu_estado', true)",
            $codigo,
            'tu_estado es varchar: filtrar los activos va por tu_state'
        );
    }
}
