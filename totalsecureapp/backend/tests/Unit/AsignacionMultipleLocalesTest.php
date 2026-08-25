<?php

namespace Tests\Unit;

use App\Filament\Resources\UserHasInstitucionResource\Pages\CreateUserHasInstitucion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\UserHasInstitucion;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Asignar varios locales a un usuario en un solo paso.
 *
 * Antes el formulario aceptaba un local por alta: un guardia que rota por cuatro
 * locales exigia cuatro pasadas. Ahora el selector es multiple al crear y se
 * genera un vinculo por cada uno.
 */
class AsignacionMultipleLocalesTest extends TestCase
{
    use RefreshDatabase;

    private array $locales = [];

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '9999999999', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('testing'),
            'usu_nmbcom' => 'Test', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 't@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['Local A', 'Local B', 'Local C'] as $nombre) {
            $this->locales[] = DB::table('organizacion_institucion')->insertGetId([
                'ins_descripcion' => $nombre, 'ins_estado' => true,
                'created_at' => now(), 'updated_at' => now(),
            ], 'ins_code');
        }
    }

    /** Invoca el metodo protegido que Filament usa al guardar el formulario. */
    private function crear(array $data)
    {
        $pagina = new CreateUserHasInstitucion();
        $m = new ReflectionMethod(CreateUserHasInstitucion::class, 'handleRecordCreation');
        $m->setAccessible(true);

        return $m->invoke($pagina, $data);
    }

    public function test_asigna_varios_locales_en_un_solo_paso(): void
    {
        $this->crear([
            'ui_usu_id'   => 1,
            'ui_ins_code' => $this->locales,
        ]);

        $this->assertSame(3, UserHasInstitucion::where('ui_usu_id', 1)->count());

        foreach ($this->locales as $insCode) {
            $this->assertTrue(
                UserHasInstitucion::where('ui_usu_id', 1)->where('ui_ins_code', $insCode)->exists(),
                "Falta el vinculo con el local {$insCode}"
            );
        }
    }

    public function test_asignar_uno_solo_sigue_funcionando(): void
    {
        $this->crear([
            'ui_usu_id'   => 1,
            'ui_ins_code' => [$this->locales[0]],
        ]);

        $this->assertSame(1, UserHasInstitucion::where('ui_usu_id', 1)->count());
    }

    public function test_los_locales_ya_asignados_se_omiten_sin_fallar(): void
    {
        // Primer alta: A y B.
        $this->crear([
            'ui_usu_id'   => 1,
            'ui_ins_code' => [$this->locales[0], $this->locales[1]],
        ]);

        // Segunda: A (repetido) y C. Reasignar no deberia obligar a recordar
        // cuales ya tenia.
        $this->crear([
            'ui_usu_id'   => 1,
            'ui_ins_code' => [$this->locales[0], $this->locales[2]],
        ]);

        $this->assertSame(3, UserHasInstitucion::where('ui_usu_id', 1)->count());
        $this->assertSame(
            1,
            UserHasInstitucion::where('ui_usu_id', 1)->where('ui_ins_code', $this->locales[0])->count(),
            'El local repetido no debe duplicarse'
        );
    }

    public function test_los_vinculos_quedan_activos(): void
    {
        $this->crear([
            'ui_usu_id'   => 1,
            'ui_ins_code' => $this->locales,
        ]);

        $this->assertSame(
            3,
            UserHasInstitucion::where('ui_usu_id', 1)->where('ui_state', 1)->count(),
            'Un vinculo inactivo no lo veria ni el portal ni la app'
        );
    }
}
