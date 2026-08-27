<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Alertas;
use ReflectionClass;
use Tests\TestCase;

/**
 * N+1 en las tablas del panel (Fase 9).
 *
 * Las columnas de Filament del tipo 'institucion.cliente.org_descripcion'
 * disparan una consulta por relacion y por fila si el resource no hace eager
 * loading. Medido antes del arreglo: 5N+1 consultas, o sea 126 para las 25 filas
 * por pagina de Filament, contra 6 constantes.
 */
class EagerLoadingTest extends TestCase
{
    use RefreshDatabase;

    private const DIR_RESOURCES = 'app/Filament/Resources';

    private int $insCode;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '9999999999', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('testing'),
            'usu_nmbcom' => 'Usuario Test', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 't@example.com',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Local con cliente y con ciudad: son las dos cadenas de relaciones que
        // recorren hoy las columnas del panel.
        $cliente = DB::table('organizacion')->insertGetId([
            'org_descripcion' => 'Cliente Test', 'org_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'org_code');

        $ciudad = DB::table('ciudad')->value('cd_id');

        $this->insCode = DB::table('organizacion_institucion')->insertGetId([
            'ins_cliente_id' => $cliente, 'ins_cd_id' => $ciudad,
            'ins_descripcion' => 'Inst Test', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');
    }

    // ── Helpers ──

    private function contarConsultas(callable $fn): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    /** Recorre la cadena de relaciones igual que una columna 'a.b.c'. */
    private function tocar($registro, string $ruta): void
    {
        $actual = $registro;
        foreach (explode('.', $ruta) as $segmento) {
            if ($actual === null) {
                return;
            }
            $actual = $actual->{$segmento};
            if ($actual instanceof \Illuminate\Database\Eloquent\Collection) {
                $actual = $actual->first();
            }
        }
    }

    /** @return string[] */
    private function relacionesDe(string $resource): array
    {
        $constantes = (new ReflectionClass($resource))->getConstants();

        return $constantes['RELACIONES_TABLA'] ?? [];
    }

    private function sembrarAlertas(int $cantidad): void
    {
        Alertas::query()->delete();

        for ($i = 0; $i < $cantidad; $i++) {
            Alertas::create([
                'al_ins_code' => $this->insCode, 'al_usu_id' => 1, 'al_anio' => 2026,
                'al_estado' => 1, 'al_prioridad' => 'media', 'al_estado_alerta' => 'pendiente',
                'al_observacion' => "Alerta {$i}", 'al_fecha' => now(), 'al_created_user' => 1,
            ]);
        }
    }

    /** Consultas que cuesta renderizar una pagina de la tabla de alertas. */
    private function consultasDeLaTablaDeAlertas(int $filas): int
    {
        $this->sembrarAlertas($filas);
        $resource = \App\Filament\Resources\AlertasResource::class;
        $rutas = $this->relacionesDe($resource);

        return $this->contarConsultas(function () use ($resource, $rutas, $filas) {
            foreach ($resource::getEloquentQuery()->limit($filas)->get() as $registro) {
                foreach ($rutas as $ruta) {
                    $this->tocar($registro, $ruta);
                }
            }
        });
    }

    // ── Conteo de consultas ──

    public function test_la_tabla_de_alertas_no_gasta_mas_consultas_al_crecer(): void
    {
        $con10 = $this->consultasDeLaTablaDeAlertas(10);
        $con30 = $this->consultasDeLaTablaDeAlertas(30);

        // La comparacion es entre volumenes, no contra un numero magico: si el
        // conteo crece con las filas, hay N+1 aunque el absoluto parezca bajo.
        $this->assertSame(
            $con10,
            $con30,
            "El costo crecio con las filas ({$con10} con 10, {$con30} con 30): falta eager loading"
        );
    }

    public function test_el_resource_de_alertas_declara_sus_relaciones(): void
    {
        $rutas = $this->relacionesDe(\App\Filament\Resources\AlertasResource::class);

        $this->assertNotEmpty($rutas);
        $this->assertContains('institucion.cliente', $rutas);
    }

    public function test_el_eager_loading_queda_registrado_en_la_query(): void
    {
        $query = \App\Filament\Resources\AlertasResource::getEloquentQuery();

        $this->assertNotEmpty(
            $query->getEagerLoads(),
            'getEloquentQuery deberia traer las relaciones ya declaradas'
        );
    }

    // ── Red: ningun resource nuevo puede olvidar su eager loading ──

    /**
     * Columnas con relacion (make('a.b')) declaradas en un resource, ignorando
     * las comentadas.
     *
     * @return string[]  prefijos de relacion requeridos
     */
    private function relacionesQueUsanLasColumnas(string $archivo): array
    {
        $codigo = file_get_contents($archivo);
        $codigo = preg_replace('#/\*.*?\*/#s', '', $codigo);
        $codigo = preg_replace('#^\s*//.*$#m', '', $codigo);

        preg_match_all("#make\('([a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)+)'\)#", $codigo, $m);

        $prefijos = [];
        foreach ($m[1] ?? [] as $ruta) {
            $partes = explode('.', $ruta);
            array_pop($partes);
            if ($partes) {
                $prefijos[implode('.', $partes)] = true;
            }
        }

        return array_keys($prefijos);
    }

    public function test_todo_resource_con_columnas_de_relacion_declara_eager_loading(): void
    {
        $archivos = glob(base_path(self::DIR_RESOURCES . '/*.php'));

        // Guarda contra un test que pase por vacio si cambia la ruta del directorio.
        $this->assertGreaterThan(10, count($archivos), 'No se encontraron los resources');

        $revisados = 0;

        foreach ($archivos as $archivo) {
            $requeridas = $this->relacionesQueUsanLasColumnas($archivo);
            if (empty($requeridas)) {
                continue;
            }

            $resource = 'App\\Filament\\Resources\\' . basename($archivo, '.php');
            $declaradas = $this->relacionesDe($resource);
            $nombre = basename($archivo);

            $this->assertNotEmpty(
                $declaradas,
                "{$nombre} usa columnas de relacion pero no declara RELACIONES_TABLA: cada fila costaria una consulta por relacion"
            );

            foreach ($requeridas as $ruta) {
                $cubierta = false;
                foreach ($declaradas as $declarada) {
                    if ($declarada === $ruta || str_starts_with($declarada, $ruta . '.')) {
                        $cubierta = true;
                        break;
                    }
                }
                $this->assertTrue(
                    $cubierta,
                    "{$nombre}: la columna usa '{$ruta}' pero RELACIONES_TABLA no la incluye"
                );
            }

            $revisados++;
        }

        $this->assertGreaterThanOrEqual(16, $revisados, 'Se esperaban al menos 16 resources con relaciones');
    }
}
