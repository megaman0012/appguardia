<?php

namespace Tests\Unit;

use App\Filament\Resources\RolesResource\Pages\EditRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Acceso\Models\roles;
use Session;
use Tests\TestCase;

/**
 * Configuracion › Perfiles › Editar abria una pagina VACIA.
 *
 * `RolesResource::form()` era literalmente `return $schema->schema([ ]);`. La
 * pantalla se llama «Perfiles y permisos», tiene su boton de editar y su pagina
 * registrada, y al abrirla no habia ni un campo. Los 111 vinculos de
 * `role_has_permissions` solo se podian tocar por SQL.
 *
 * Y habia un segundo fallo debajo, que no se veia porque el primero lo tapaba:
 * el modelo `roles` **no declaraba `$fillable`**. Eloquent trae
 * `$guarded = ['*']`, asi que aunque el formulario hubiera existido,
 * `$record->update($datos)` no habria escrito una sola columna -- y sin error.
 *
 * ⚠️ Lo que mas importa de este test es el ultimo caso: **el nombre del perfil
 * no se puede cambiar** en los cinco que `PerfilPanel` reconoce por cadena
 * literal. Renombrar «Administrador» deja fuera del panel a todos sus usuarios,
 * y el unico sintoma seria «no puedo entrar».
 */
class FormularioDePerfilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'C', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Admin', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'a@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Session::put('usuID', 1);
        Session::put('usuPF', 'Administrador');
    }

    /**
     * Las migraciones ya siembran los perfiles reales (`Supervisor`,
     * `Administrador`, `Consola`...), asi que se reutiliza el que exista en vez
     * de crearlo: `roles.name` es unico.
     */
    private function perfil(string $nombre = 'Consola'): roles
    {
        return roles::updateOrCreate(
            ['name' => $nombre],
            ['descripcion' => 'original', 'estado' => true, 'visible' => true],
        );
    }

    private function permiso(int $id, string $name, int $seccion = 10): int
    {
        DB::table('permission_section')->updateOrInsert(
            ['ps_codigo' => $seccion],
            ['ps_nombre' => 'Rondas', 'ps_posicion' => $seccion],
        );

        DB::table('permissions')->updateOrInsert(['id' => $id], [
            'name' => $name, 'ps_codigo' => $seccion,
            'pr_descripcion' => 'Ver ' . $name, 'pr_posicion' => $id, 'pr_state' => 1,
        ]);

        return $id;
    }

    public function test_el_formulario_ya_no_esta_vacio(): void
    {
        $perfil = $this->perfil();

        Livewire::test(EditRoles::class, ['record' => $perfil->getKey()])
            ->assertFormFieldExists('name')
            ->assertFormFieldExists('descripcion')
            ->assertFormFieldExists('estado')
            ->assertFormFieldExists('visible')
            ->assertFormFieldExists('permissions');
    }

    public function test_guardar_escribe_de_verdad(): void
    {
        // Un perfil que el codigo NO conoce por nombre: ese si se puede renombrar.
        $perfil = $this->perfil('Auditor');

        Livewire::test(EditRoles::class, ['record' => $perfil->getKey()])
            ->fillForm([
                'name'        => 'Auditor interno',
                'descripcion' => 'Solo lectura para auditoría',
                'estado'      => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $perfil->refresh();

        // Sin `$fillable` en el modelo esto pasaba sin escribir nada.
        $this->assertSame('Auditor interno', $perfil->name);
        $this->assertSame('Solo lectura para auditoría', $perfil->descripcion);
        $this->assertFalse((bool) $perfil->estado);
    }

    public function test_los_permisos_se_cargan_y_se_guardan(): void
    {
        // Un perfil propio: los sembrados ya traen permisos y el estado del
        // formulario no seria solo lo que pone este test.
        $perfil = $this->perfil('Auditor');
        $ver    = $this->permiso(1, 'rondas.ver');
        $crear  = $this->permiso(2, 'rondas.crear');

        DB::table('role_has_permissions')->insert([
            'role_id' => $perfil->getKey(), 'permission_id' => $ver,
        ]);

        Livewire::test(EditRoles::class, ['record' => $perfil->getKey()])
            // Llega marcado lo que ya tenia.
            ->assertFormSet(['permissions' => [$ver]])
            // Y se puede agregar otro.
            ->fillForm(['permissions' => [$ver, $crear]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            [$ver, $crear],
            DB::table('role_has_permissions')->where('role_id', $perfil->getKey())
                ->orderBy('permission_id')->pluck('permission_id')->all(),
        );
    }

    public function test_quitar_un_permiso_lo_borra_del_pivote(): void
    {
        $perfil = $this->perfil('Auditor');
        $ver    = $this->permiso(1, 'rondas.ver');

        DB::table('role_has_permissions')->insert([
            'role_id' => $perfil->getKey(), 'permission_id' => $ver,
        ]);

        Livewire::test(EditRoles::class, ['record' => $perfil->getKey()])
            ->fillForm(['permissions' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0,
            DB::table('role_has_permissions')->where('role_id', $perfil->getKey())->count());
    }

    public function test_no_se_puede_renombrar_un_perfil_que_el_codigo_conoce(): void
    {
        $perfil = $this->perfil('Administrador');

        Livewire::test(EditRoles::class, ['record' => $perfil->getKey()])
            ->fillForm(['name' => 'Admin'])
            ->call('save');

        $perfil->refresh();

        $this->assertSame(
            'Administrador',
            $perfil->name,
            'renombrarlo deja fuera del panel a todos sus usuarios: PerfilPanel compara la cadena literal',
        );
    }
}
