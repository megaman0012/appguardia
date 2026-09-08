<?php

namespace Tests\Unit;

use App\Filament\Resources\InstitucionMarcadoresResource\Pages\CreateInstitucionMarcadores;
use App\Filament\Resources\OrganizacionInstitucionResource\Pages\CreateOrganizacionInstitucion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Session;
use Tests\TestCase;

/**
 * El formulario de Local y el de Marcador QR, tal como se dibujan.
 *
 * Cubren dos cosas que el usuario reporto y una que casi dejo la operacion
 * parada:
 *
 *  - **Aparecian dos campos «Ciudad»** seguidos: el selector del catalogo y un
 *    texto libre heredado de v1. Habia que leer la letra chica para saber cual
 *    contaba.
 *  - **«Razon Social» en un local** no tiene sentido: la razon social es del
 *    cliente. Se renombro a «Sitio», que es lo que de verdad guarda.
 *  - **La latitud y la longitud aceptaban cualquier numero.** Asi entraron 64
 *    marcadores con el signo invertido, y con eso el marcaje biometrico era
 *    imposible en 61 de 110 locales.
 */
class FormularioLocalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Admin', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'a@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Session::put('usuID', 1);
        Session::put('usuPF', 'Administrador');
    }

    public function test_el_formulario_de_local_ya_no_muestra_dos_ciudades(): void
    {
        $pagina = Livewire::test(CreateOrganizacionInstitucion::class);

        $pagina->assertSuccessful();
        // El selector del catalogo se queda...
        $pagina->assertSee('Ciudad');
        // ...y el texto libre heredado ya no esta.
        $pagina->assertDontSee('texto libre');
    }

    public function test_el_local_pide_sitio_y_no_razon_social(): void
    {
        $pagina = Livewire::test(CreateOrganizacionInstitucion::class);

        $pagina->assertSuccessful();
        $pagina->assertSee('Sitio');
        $pagina->assertDontSee('Razon Social');
    }

    public function test_el_sitio_no_es_obligatorio(): void
    {
        // Obligarlo es lo que lo lleno de relleno: 73 de 137 locales repetian el
        // nombre del cliente y varios decian «Total Security Company».
        $ciudad = DB::table('ciudad')->value('cd_id');

        Livewire::test(CreateOrganizacionInstitucion::class)
            ->set('data.ins_descripcion', 'Local sin sitio')
            ->set('data.ins_direccion', 'Calle 1')
            ->set('data.ins_cd_id', $ciudad)
            ->set('data.ins_telefono', '042000000')
            ->set('data.ins_email', 'local@example.com')
            ->set('data.ins_tipo', 'Local')
            ->call('create')
            ->assertHasNoFormErrors(['ins_razon_social']);
    }

    public function test_el_marcador_rechaza_una_latitud_positiva_de_guayaquil(): void
    {
        $ins = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Local', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        // 2.19 en vez de -2.19: es el error exacto que traian 64 marcadores.
        Livewire::test(CreateInstitucionMarcadores::class)
            ->set('data.im_ins_code', $ins)
            ->set('data.im_tipo', 'Entrada')
            ->set('data.im_descripcion', 'Garita')
            ->set('data.im_lat', '2.1890')
            ->set('data.im_lng', '79.8890')
            ->call('create')
            ->assertHasFormErrors(['im_lat', 'im_lng']);
    }

    public function test_el_marcador_acepta_una_coordenada_correcta(): void
    {
        $ins = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Local', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        Livewire::test(CreateInstitucionMarcadores::class)
            ->set('data.im_ins_code', $ins)
            ->set('data.im_tipo', 'Entrada')
            ->set('data.im_descripcion', 'Garita')
            ->set('data.im_lat', '-2.1890')
            ->set('data.im_lng', '-79.8890')
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_el_marcador_acepta_la_latitud_positiva_de_ibarra(): void
    {
        // Ibarra esta al norte del ecuador: +0.34 es correcto y el rango tiene
        // que dejarlo pasar.
        $ins = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'La Fabril Ibarra', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        Livewire::test(CreateInstitucionMarcadores::class)
            ->set('data.im_ins_code', $ins)
            ->set('data.im_tipo', 'Entrada')
            ->set('data.im_descripcion', 'Garita')
            ->set('data.im_lat', '0.338139')
            ->set('data.im_lng', '-78.186917')
            ->call('create')
            ->assertHasNoFormErrors();
    }
}
