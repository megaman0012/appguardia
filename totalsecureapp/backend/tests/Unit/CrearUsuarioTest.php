<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\MobileApp\Models\users;
use Tests\TestCase;

/**
 * Comando usuario:crear (Fase dominio).
 *
 * En una base de produccion nueva las migraciones dejan roles y permisos pero
 * ningun usuario. Este comando crea el primero real, y lo que se verifica aqui
 * son las tres piezas que el login exige por separado y es facil olvidar:
 * usuario activo, rol asignado y una gestion ABIERTA.
 */
class CrearUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private int $insCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insCode = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Cliente Real',
            'ins_estado'      => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], 'ins_code');
    }

    private function crear(array $opciones = []): int
    {
        return $this->artisan('usuario:crear', array_merge([
            '--cedula'        => '0102030405',
            '--nombres'       => 'Ana Maria',
            '--apellidos'     => 'Perez Lopez',
            '--email'         => 'ana@empresa.com',
            '--rol'           => 'Supervisor',
            '--instituciones' => (string) $this->insCode,
            '--password'      => 'Segura2026x',
        ], $opciones))->run();
    }

    public function test_crea_las_tres_piezas_que_el_login_exige(): void
    {
        $this->assertSame(0, $this->crear());

        $usuario = users::where('usu_cedula', '0102030405')->first();
        $this->assertNotNull($usuario);
        $this->assertSame(1, (int) $usuario->usu_state);

        $rol = DB::table('user_has_roles')
            ->join('roles', 'roles.id', '=', 'user_has_roles.role_id')
            ->where('user_id', $usuario->id)
            ->value('roles.name');
        $this->assertSame('Supervisor', $rol);

        // ug_finish = false: sin esta fila el login responde que no hay gestion activa.
        $this->assertTrue(
            DB::table('user_has_gestions')
                ->where('ug_user_id', $usuario->id)
                ->where('ug_finish', false)
                ->exists(),
            'Deberia quedar una gestion abierta'
        );

        $this->assertTrue(
            DB::table('user_has_institucion')
                ->where('ui_usu_id', $usuario->id)
                ->where('ui_ins_code', $this->insCode)
                ->where('ui_state', 1)
                ->exists()
        );
    }

    public function test_la_contrasena_queda_hasheada_y_validable(): void
    {
        $this->crear();

        $usuario = users::where('usu_cedula', '0102030405')->first();
        $hash = DB::table('users')->where('id', $usuario->id)->value('usu_password');

        // usu_password no esta en $fillable del modelo, asi que un create() la
        // descartaria en silencio y el insert violaria el NOT NULL. El comando la
        // asigna directo; esto lo fija.
        $this->assertNotNull($hash, 'La contraseña no se guardo');
        $this->assertNotSame('Segura2026x', $hash, 'Se guardo en texto plano');
        $this->assertTrue(Hash::check('Segura2026x', $hash));
        $this->assertFalse(Hash::check('otra-clave', $hash));
    }

    public function test_rechaza_una_contrasena_debil(): void
    {
        $this->assertSame(1, $this->crear(['--password' => 'corta']));
        $this->assertSame(0, users::where('usu_cedula', '0102030405')->count());
    }

    public function test_rechaza_un_rol_inexistente(): void
    {
        $this->assertSame(1, $this->crear(['--rol' => 'NoExiste']));
        $this->assertSame(0, users::where('usu_cedula', '0102030405')->count());
    }

    public function test_rechaza_una_institucion_inexistente(): void
    {
        $this->assertSame(1, $this->crear(['--instituciones' => '99999']));
        $this->assertSame(0, users::where('usu_cedula', '0102030405')->count());
    }

    public function test_rechaza_una_cedula_repetida(): void
    {
        $this->assertSame(0, $this->crear());
        $this->assertSame(1, $this->crear(['--email' => 'otra@empresa.com']));
        $this->assertSame(1, users::where('usu_cedula', '0102030405')->count());
    }

    public function test_permite_crear_sin_instituciones(): void
    {
        // Valido: el vinculo se puede agregar despues desde el panel.
        $this->assertSame(0, $this->crear(['--instituciones' => '']));

        $usuario = users::where('usu_cedula', '0102030405')->first();
        $this->assertNotNull($usuario);
        $this->assertSame(0, DB::table('user_has_institucion')->where('ui_usu_id', $usuario->id)->count());
    }

    public function test_el_rol_creado_da_permisos_de_la_api(): void
    {
        $this->crear();

        $usuario = users::where('usu_cedula', '0102030405')->first();

        // Supervisor: los 31 permisos moviles, sin los del panel web.
        $this->assertTrue($usuario->can('alertas.crear'));
        $this->assertTrue($usuario->can('inventario.finalizar'));
    }
}
