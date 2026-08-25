<?php

namespace Tests\Unit;

use App\Support\PerfilPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Alcance del rol Lider Operativo, acotado por pais.
 *
 * El Supervisor se acota por local (user_has_institucion) y el Administrador ve
 * todo. El Lider gestiona una operacion entera, asi que su alcance es el pais:
 * ve los locales cuya ciudad pertenece a una provincia de su(s) pais(es).
 *
 * Lo que se fija aqui, sobre todo, es el caso peligroso: un lider SIN paises
 * asignados no debe ver todo. Sin esa distincion una configuracion incompleta se
 * convierte en acceso global.
 */
class AlcancePorPaisTest extends TestCase
{
    use RefreshDatabase;

    private int $ecuador;
    private int $colombia;
    private int $localGuayaquil;
    private int $localBogota;
    private int $localSinCiudad;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '9999999999', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('testing'),
            'usu_nmbcom' => 'Test', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 't@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Ecuador y sus provincias los siembra la migracion.
        $this->ecuador  = (int) DB::table('pais')->where('pa_iso2', 'EC')->value('pa_id');
        $this->colombia = (int) DB::table('pais')->where('pa_iso2', 'CO')->value('pa_id');

        $this->localGuayaquil = $this->crearLocal('Guayaquil', 'Guayas', $this->ecuador);
        $this->localBogota    = $this->crearLocal('Bogotá', 'Cundinamarca', $this->colombia);

        // Un local sin ciudad: no pertenece a ningun pais.
        $this->localSinCiudad = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Local sin ubicar', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        Session::put('usuID', 1);
    }

    private function crearLocal(string $ciudad, string $provincia, int $paisId): int
    {
        $prId = DB::table('provincia')->where('pr_pa_id', $paisId)->where('pr_nombre', $provincia)->value('pr_id');
        if (!$prId) {
            $prId = DB::table('provincia')->insertGetId([
                'pr_pa_id' => $paisId, 'pr_nombre' => $provincia, 'pr_estado' => true,
                'created_at' => now(), 'updated_at' => now(),
            ], 'pr_id');
        }

        // La migracion de backfill ya crea Guayaquil: se reutiliza si existe.
        $cdId = DB::table('ciudad')->where('cd_pr_id', $prId)->where('cd_nombre', $ciudad)->value('cd_id');
        if (!$cdId) {
            $cdId = DB::table('ciudad')->insertGetId([
                'cd_pr_id' => $prId, 'cd_nombre' => $ciudad, 'cd_estado' => true,
                'created_at' => now(), 'updated_at' => now(),
            ], 'cd_id');
        }

        return DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => "Local {$ciudad}", 'ins_estado' => true, 'ins_cd_id' => $cdId,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');
    }

    private function asignarPais(int $paisId): void
    {
        DB::table('user_has_pais')->updateOrInsert(
            ['up_usu_id' => 1, 'up_pa_id' => $paisId],
            ['up_estado' => true, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    // ── El caso peligroso ──

    public function test_un_lider_sin_paises_no_ve_nada(): void
    {
        Session::put('usuPF', PerfilPanel::LIDER_OPERATIVO);

        // Vacio, NO null: null significaria "sin filtro" y le daria acceso global.
        $this->assertSame([], PerfilPanel::localesDelUsuario());
    }

    public function test_el_administrador_no_se_acota(): void
    {
        Session::put('usuPF', PerfilPanel::ADMINISTRADOR);

        $this->assertNull(
            PerfilPanel::localesDelUsuario(),
            'null significa sin filtro: Sistemas ve el total'
        );
    }

    public function test_el_supervisor_no_usa_el_alcance_por_pais(): void
    {
        Session::put('usuPF', PerfilPanel::SUPERVISOR);

        // Su alcance es por local, resuelto aparte con user_has_institucion.
        $this->assertNull(PerfilPanel::localesDelUsuario());
        $this->assertTrue(PerfilPanel::alcanceEsPorInstitucion());
        $this->assertFalse(PerfilPanel::alcanceEsPorPais());
    }

    // ── El acotado ──

    public function test_ve_solo_los_locales_de_su_pais(): void
    {
        Session::put('usuPF', PerfilPanel::LIDER_OPERATIVO);
        $this->asignarPais($this->ecuador);

        $locales = PerfilPanel::localesDelUsuario();

        $this->assertContains($this->localGuayaquil, $locales);
        $this->assertNotContains($this->localBogota, $locales, 'No debe ver locales de otro pais');
    }

    public function test_un_lider_puede_tener_varios_paises(): void
    {
        Session::put('usuPF', PerfilPanel::LIDER_OPERATIVO);
        $this->asignarPais($this->ecuador);
        $this->asignarPais($this->colombia);

        $locales = PerfilPanel::localesDelUsuario();

        $this->assertContains($this->localGuayaquil, $locales);
        $this->assertContains($this->localBogota, $locales);
    }

    public function test_un_local_sin_ciudad_no_aparece_en_ningun_pais(): void
    {
        Session::put('usuPF', PerfilPanel::LIDER_OPERATIVO);
        $this->asignarPais($this->ecuador);
        $this->asignarPais($this->colombia);

        // Preferible que falte a que se cuele en el alcance de un pais ajeno.
        $this->assertNotContains($this->localSinCiudad, PerfilPanel::localesDelUsuario());
    }

    public function test_un_pais_desactivado_para_el_usuario_deja_de_contar(): void
    {
        Session::put('usuPF', PerfilPanel::LIDER_OPERATIVO);
        $this->asignarPais($this->ecuador);
        $this->assertContains($this->localGuayaquil, PerfilPanel::localesDelUsuario());

        DB::table('user_has_pais')->where('up_usu_id', 1)->update(['up_estado' => false]);

        $this->assertSame([], PerfilPanel::localesDelUsuario());
    }

    // ── Capacidades por perfil ──

    public function test_reparto_de_capacidades_entre_los_perfiles(): void
    {
        $esperado = [
            //                          entra   sistema personal locales operar
            PerfilPanel::ADMINISTRADOR   => [true,  true,  true,  true,  true],
            PerfilPanel::LIDER_OPERATIVO => [true,  false, true,  true,  true],
            PerfilPanel::SUPERVISOR      => [true,  false, false, false, true],
            'Vigilante'                  => [false, false, false, false, false],
            'Cliente'                    => [false, false, false, false, false],
        ];

        foreach ($esperado as $perfil => [$entra, $sistema, $personal, $locales, $opera]) {
            Session::put('usuPF', $perfil);

            $this->assertSame($entra,    PerfilPanel::puedeEntrarAlPanel(),      "{$perfil}: entrar al panel");
            $this->assertSame($sistema,  PerfilPanel::puedeConfigurarSistema(),  "{$perfil}: configurar sistema");
            $this->assertSame($personal, PerfilPanel::puedeGestionarPersonal(),  "{$perfil}: gestionar personal");
            $this->assertSame($locales,  PerfilPanel::puedeAdministrarLocales(), "{$perfil}: administrar locales");
            $this->assertSame($opera,    PerfilPanel::puedeOperar(),             "{$perfil}: operar");
        }
    }

    public function test_los_cinco_roles_existen_con_sus_permisos(): void
    {
        $roles = DB::table('roles')->pluck('name')->all();

        foreach (['Administrador', 'Lider Operativo', 'Supervisor', 'Vigilante', 'Cliente'] as $rol) {
            $this->assertContains($rol, $roles, "Falta el rol {$rol}");
        }

        // El Lider gestiona personal; el Supervisor no.
        $lider = DB::table('roles')->where('name', 'Lider Operativo')->value('id');
        $sup   = DB::table('roles')->where('name', 'Supervisor')->value('id');

        $tiene = fn ($rolId, $permiso) => DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_id', $rolId)->where('permissions.name', $permiso)->exists();

        $this->assertTrue($tiene($lider, 'usuarios.crear'));
        $this->assertFalse($tiene($sup, 'usuarios.crear'), 'El Supervisor no debe poder crear usuarios');
    }
}
