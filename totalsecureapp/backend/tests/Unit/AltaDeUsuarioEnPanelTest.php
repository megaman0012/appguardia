<?php

namespace Tests\Unit;

use App\Filament\Resources\UsersResource\Pages\CreateUsers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Modules\MobileApp\Models\users;
use Session;
use Tests\TestCase;

/**
 * Alta de un usuario desde el panel.
 *
 * **Estaba roto de dos formas.**
 *
 *  - `mutateFormDataBeforeCreate()` ponia `Hash::make('123456')`: **la misma
 *    contraseña para todos** los usuarios creados desde el panel, y sin
 *    mostrarla en ningun lado, asi que nadie se enteraba de cual era.
 *  - Creaba **solo la fila de `users`**. Un usuario necesita cuatro piezas: la
 *    fila, el rol, una gestion abierta y el vinculo al local. Sin gestion
 *    abierta el login responde «El usuario no tiene una gestion activa», asi
 *    que el usuario quedaba dado de alta y **sin poder entrar**.
 */
class AltaDeUsuarioEnPanelTest extends TestCase
{
    use RefreshDatabase;

    private int $local;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'C', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Admin', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'a@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->local = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Garita Norte', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        // Insertar el id 1 a mano deja la secuencia atrasada y el siguiente
        // insert choca con la PK: es un artefacto del test, no del codigo.
        DB::statement("SELECT setval(pg_get_serial_sequence('users','id'), (SELECT max(id) FROM users))");

        Session::put('usuID', 1);
        Session::put('usuPF', 'Administrador');
    }

    private function crear(array $extra = [])
    {
        return Livewire::test(CreateUsers::class)
            ->fillForm(array_merge([
                'usu_cedula' => '0912345678',
                'usu_tipdoc' => 'C',
                'usu_nmbcom' => 'JUAN PEREZ',
                'usu_ape1'   => 'PEREZ',
                'usu_ape2'   => 'GOMEZ',
                'usu_nmb1'   => 'JUAN',
                'usu_nmb2'   => 'CARLOS',
                'usu_email'  => 'juan@example.com',
                'usu_state'  => true,
                'rol'        => 'Vigilante',
                'locales'    => [$this->local],
            ], $extra))
            ->call('create');
    }

    public function test_crea_las_cuatro_piezas_que_el_login_exige(): void
    {
        $this->crear()->assertHasNoFormErrors();

        $u = users::where('usu_cedula', '0912345678')->first();
        $this->assertNotNull($u);

        $rol = DB::table('user_has_roles')->join('roles', 'roles.id', '=', 'user_has_roles.role_id')
            ->where('user_id', $u->id)->value('roles.name');
        $this->assertSame('Vigilante', $rol);

        $this->assertTrue(
            DB::table('user_has_gestions')->where('ug_user_id', $u->id)->where('ug_finish', false)->exists(),
            'Sin gestion abierta el login responde «no tiene una gestion activa»'
        );

        $this->assertTrue(
            DB::table('user_has_institucion')
                ->where('ui_usu_id', $u->id)->where('ui_ins_code', $this->local)->where('ui_state', 1)->exists()
        );
    }

    public function test_la_clave_ya_no_es_123456_para_todos(): void
    {
        $this->crear()->assertHasNoFormErrors();
        $primera = users::where('usu_cedula', '0912345678')->value('usu_password');

        $this->crear(['usu_cedula' => '0912345679', 'usu_email' => 'otro@example.com'])
            ->assertHasNoFormErrors();
        $segunda = users::where('usu_cedula', '0912345679')->value('usu_password');

        $this->assertFalse(Hash::check('123456', $primera), 'Sigue usando la clave fija 123456');
        $this->assertFalse(Hash::check('123456', $segunda));
        $this->assertNotSame($primera, $segunda);
    }

    public function test_la_clave_queda_hasheada_y_no_en_texto_plano(): void
    {
        // Este recurso usa `Modules\Acceso\Models\users`, que NO hashea en su
        // evento `saving` -- el que lo hace es el modelo de `Modules\MobileApp`.
        // Asignar la clave en claro la guardaba en texto plano.
        $pagina = Livewire::test(CreateUsers::class)
            ->fillForm([
                'usu_cedula' => '0912345678', 'usu_tipdoc' => 'C',
                'usu_nmbcom' => 'JUAN PEREZ', 'usu_ape1' => 'PEREZ', 'usu_ape2' => 'GOMEZ',
                'usu_nmb1' => 'JUAN', 'usu_nmb2' => 'CARLOS',
                'usu_email' => 'juan@example.com', 'usu_state' => true,
                'rol' => 'Vigilante', 'locales' => [$this->local],
            ])
            ->call('create');

        $pagina->assertHasNoFormErrors();

        $hash = users::where('usu_cedula', '0912345678')->value('usu_password');

        $this->assertNotEmpty($hash);
        $this->assertStringStartsWith('$2y$', $hash, 'La contraseña se guardó en texto plano');
    }

    public function test_el_perfil_es_obligatorio(): void
    {
        // Sin rol, el usuario no ve ningun modulo: no tiene sentido crearlo asi.
        $this->crear(['rol' => null])->assertHasFormErrors(['rol']);
    }
}
