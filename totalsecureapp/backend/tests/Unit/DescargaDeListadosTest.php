<?php

namespace Tests\Unit;

use App\Filament\Resources\AlertasResource\Pages\ListAlertas;
use App\Filament\Resources\UsersResource\Pages\ListUsers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Session;
use Tests\TestCase;

/**
 * La descarga a Excel de los listados.
 *
 * **Lo que habia antes.** `pxlrbt/filament-excel` instalado, pero solo con
 * `ExportBulkAction` y solo en 6 de 27 pantallas. Una accion masiva exige
 * tildar filas y exporta unicamente lo tildado, con «seleccionar todo» limitado
 * a la pagina visible: para bajar las 37.617 filas de rondas habia que paginar
 * y tildar 1.505 veces. Ademas se llamaba sin configurar nada, asi que el Excel
 * salia con encabezados crudos (`al_ins_code`, `al_estado_alerta`).
 */
class DescargaDeListadosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Admin Uno', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'a@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Session::put('usuID', 1);
        Session::put('usuPF', 'Administrador');
    }

    public function test_todos_los_listados_ofrecen_descarga_en_la_cabecera(): void
    {
        // Se recorren los recursos de verdad: si aparece un listado nuevo sin
        // descarga, esto tiene que romperse.
        $sinDescarga = [];

        foreach (glob(app_path('Filament/Resources/*Resource.php')) as $ruta) {
            $clase = 'App\\Filament\\Resources\\' . basename($ruta, '.php');

            if (!class_exists($clase)) {
                continue;
            }

            $indice = $clase::getPages()['index'] ?? null;

            if ($indice === null) {
                continue;
            }

            // Filament 2 devolvia arreglos `['class' => ...]`; Filament 3
            // devuelve objetos `PageRegistration` con `getPage()`.
            $pagina = is_array($indice)
                ? ($indice['class'] ?? null)
                : (is_object($indice) && method_exists($indice, 'getPage') ? $indice->getPage() : null);

            if ($pagina === null) {
                continue;
            }

            if (!is_subclass_of($pagina, \App\Filament\Pages\ListadoBase::class)) {
                $sinDescarga[] = class_basename($clase) . ' (no hereda de ListadoBase)';
            }
        }

        $this->assertSame([], $sinDescarga);
    }

    public function test_el_boton_de_descarga_se_dibuja_sin_tildar_nada(): void
    {
        // El punto del cambio: antes habia que seleccionar filas para que
        // apareciera cualquier opcion de exportar.
        Livewire::test(ListUsers::class)
            ->assertSuccessful()
            ->assertSee('Descargar');
    }

    /** La hoja configurada en una accion, sin depender de metodos que el paquete no expone. */
    /*
     * ⚠️ `getCachedActions()` era el nombre en Filament 2; **la 3 lo renombro a
     * `getCachedHeaderActions()`**, en linea con el cambio de `getActions()` a
     * `getHeaderActions()` en las paginas.
     */
    private function hojaDe($pagina, string $accion)
    {
        $obj = collect($pagina->instance()->getCachedHeaderActions())
            ->first(fn ($a) => $a->getName() === $accion);

        $this->assertNotNull($obj, "El listado no ofrece la accion «{$accion}»");

        // `$exports` es protegida y el paquete no trae getter.
        $prop = new \ReflectionProperty($obj, 'exports');
        $prop->setAccessible(true);

        $hoja = $prop->getValue($obj)->first();
        $hoja->hydrate($pagina->instance());

        return $hoja;
    }

    public function test_la_descarga_usa_las_columnas_visibles_y_sus_etiquetas(): void
    {
        $pagina = Livewire::test(ListAlertas::class)->assertSuccessful();
        $encabezados = $this->hojaDe($pagina, 'descargar')->getHeadings();

        // Con `fromTable()` los encabezados son las etiquetas del listado, no
        // los nombres de columna de la base. Los exports que ya existian se
        // llamaban sin configurar nada y el cliente recibia un Excel con
        // encabezados `al_ins_code` / `al_estado_alerta`.
        $this->assertNotEmpty($encabezados);
        $this->assertNotContains('al_ins_code', $encabezados);
        $this->assertNotContains('al_estado_alerta', $encabezados);
    }

    public function test_el_nombre_del_archivo_lleva_la_fecha(): void
    {
        $pagina = Livewire::test(ListUsers::class)->assertSuccessful();
        $hoja = $this->hojaDe($pagina, 'descargar');

        $metodo = new \ReflectionMethod($hoja, 'getFilename');
        $metodo->setAccessible(true);
        $nombre = $metodo->invoke($hoja);

        // Evita el «descarga (3).xlsx» y deja claro a que corte corresponde.
        $this->assertStringContainsString(now()->format('Y-m-d'), $nombre);
        $this->assertStringContainsString('usuario', $nombre);
    }

    public function test_la_descarga_se_lleva_todo_lo_filtrado_sin_tildar_filas(): void
    {
        // Se crean mas usuarios que la pagina del listado (25) para que quede
        // claro que la descarga NO se limita a lo visible: es lo que la accion
        // masiva no podia hacer.
        for ($i = 2; $i <= 40; $i++) {
            DB::table('users')->insert([
                'id' => $i,
                'usu_cedula' => str_pad((string) $i, 10, '0', STR_PAD_LEFT),
                'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
                'usu_nmbcom' => "Guardia {$i}", 'usu_ape1' => 'T', 'usu_ape2' => 'T',
                'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => "g{$i}@e.com",
                'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $pagina = Livewire::test(ListUsers::class)->assertSuccessful();
        $hoja = $this->hojaDe($pagina, 'descargar');

        $this->assertSame(40, $hoja->getQuery()->count());
    }

    public function test_la_descarga_respeta_el_filtro_del_listado(): void
    {
        DB::table('users')->insert([
            'id' => 2, 'usu_cedula' => '2222222222', 'usu_tipdoc' => 'CC',
            'usu_password' => bcrypt('x'), 'usu_nmbcom' => 'Guardia Inactivo',
            'usu_ape1' => 'T', 'usu_ape2' => 'T', 'usu_nmb1' => 'T', 'usu_nmb2' => 'T',
            'usu_email' => 'i@e.com', 'usu_state' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // El listado abre filtrado por activos (FiltroDeEstado): la descarga
        // tiene que traer lo mismo que se ve, no la tabla entera.
        $pagina = Livewire::test(ListUsers::class)->assertSuccessful();
        $hoja = $this->hojaDe($pagina, 'descargar');

        $this->assertSame(1, $hoja->getQuery()->count());
    }
}
