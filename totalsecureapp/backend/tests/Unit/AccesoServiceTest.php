<?php

namespace Tests\Unit;

use App\Services\AccesoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Administracion\Models\Acceso;
use Modules\Administracion\Models\AccesoHistorial;
use Modules\Administracion\Models\AccesoPersona;
use Modules\Administracion\Models\AccesoPreregistro;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AccesoServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccesoService $service;
    private int $insCode;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(
            ['id' => 1],
            [
                'usu_cedula'   => '9999999999',
                'usu_tipdoc'   => 'CC',
                'usu_password' => bcrypt('testing'),
                'usu_nmbcom'   => 'Usuario Test',
                'usu_ape1'     => 'Test',
                'usu_ape2'     => 'Test',
                'usu_nmb1'     => 'Usuario',
                'usu_nmb2'     => 'Test',
                'usu_email'    => 'test@example.com',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        $this->insCode = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Institucion Test',
            'ins_estado'      => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], 'ins_code');

        $this->service = app(AccesoService::class);
    }

    // ── Helpers ──

    private function datosBase(array $overrides = []): array
    {
        return array_merge([
            'tipoAc'         => 'peatonal',
            'identificacion' => '1712345678',
            'nombres'        => 'Juan',
            'apellidos'      => 'Perez',
            'latitud'        => '-1.8312',
            'longitud'       => '-79.5341',
            'institucion'    => $this->insCode,
            'isEntrada'      => true,
        ], $overrides);
    }

    // ── Validacion ──

    /** @test */
    public function tipo_de_acceso_invalido_lanza_validacion(): void
    {
        try {
            $this->service->registrar($this->datosBase(['tipoAc' => 'invalido']), 1);
            $this->fail('Debio lanzar ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tipoAc', $e->errors());
        }
    }

    /** @test */
    public function acceso_vehicular_requiere_patente(): void
    {
        try {
            $this->service->registrar($this->datosBase(['tipoAc' => 'vehicular']), 1);
            $this->fail('Debio lanzar ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('patente', $e->errors());
        }
    }

    /** @test */
    public function acceso_visitante_requiere_motivo(): void
    {
        try {
            $this->service->registrar($this->datosBase(['tipoAc' => 'visitante']), 1);
            $this->fail('Debio lanzar ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('motivo', $e->errors());
        }
    }

    /** @test */
    public function acceso_proveedor_requiere_motivo(): void
    {
        try {
            $this->service->registrar($this->datosBase(['tipoAc' => 'proveedor']), 1);
            $this->fail('Debio lanzar ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('motivo', $e->errors());
        }
    }

    // ── Registro de accesos ──

    /** @test */
    public function registrar_acceso_peatonal_crea_persona_acceso_e_historial(): void
    {
        $acc = $this->service->registrar($this->datosBase(), 1);

        $this->assertEquals(Acceso::TIPO_PEATONAL, $acc->ac_tipo);
        $this->assertEquals(Acceso::ESTADO_EN_CURSO, $acc->ac_estado_acceso);
        $this->assertEquals(1, $acc->ac_is_entrada);

        $this->assertDatabaseHas('acceso_persona', [
            'ap_documento' => '1712345678',
            'ap_nombres'   => 'Juan',
        ]);

        $this->assertDatabaseHas('acceso_historial', [
            'ah_ac_code'    => $acc->ac_code,
            'ah_tipo_marca' => 'entrada',
        ]);
    }

    /** @test */
    public function reutiliza_persona_existente_por_documento(): void
    {
        $this->service->registrar($this->datosBase(), 1);
        $this->service->registrar($this->datosBase(['nombres' => 'Otro Nombre']), 1);

        $this->assertEquals(1, AccesoPersona::where('ap_documento', '1712345678')->count());
    }

    /** @test */
    public function registrar_salida_directa_completa_el_acceso(): void
    {
        $acc = $this->service->registrar($this->datosBase(['isEntrada' => false]), 1);

        $this->assertEquals(Acceso::ESTADO_COMPLETADA, $acc->ac_estado_acceso);
        $this->assertEquals(0, $acc->ac_is_entrada);

        $this->assertDatabaseHas('acceso_historial', [
            'ah_ac_code'    => $acc->ac_code,
            'ah_tipo_marca' => 'salida',
        ]);
    }

    /** @test */
    public function registrar_acceso_vehicular_crea_detalle_vehiculo(): void
    {
        $acc = $this->service->registrar($this->datosBase([
            'tipoAc'  => 'vehicular',
            'patente' => 'ABC-1234',
            'marca'   => 'Toyota',
            'isSello' => true,
        ]), 1);

        $this->assertDatabaseHas('acceso_vehiculo', [
            'av_ac_code'  => $acc->ac_code,
            'av_patente'  => 'ABC-1234',
            'av_marca'    => 'Toyota',
            'av_is_sello' => true,
        ]);
    }

    /** @test */
    public function registrar_acceso_visitante_crea_detalle_visita(): void
    {
        $acc = $this->service->registrar($this->datosBase([
            'tipoAc'       => 'visitante',
            'motivo'       => 'Reunion comercial',
            'areaVisita'   => 'Administracion',
            'personaVisita'=> 'Gerente General',
        ]), 1);

        $this->assertDatabaseHas('acceso_visitante', [
            'avi_ac_code'      => $acc->ac_code,
            'avi_motivo'       => 'Reunion comercial',
            'avi_area_visita'  => 'Administracion',
            'avi_persona_visita' => 'Gerente General',
        ]);

        $this->assertDatabaseMissing('acceso_vehiculo', ['av_ac_code' => $acc->ac_code]);
    }

    /** @test */
    public function proveedor_con_patente_genera_detalle_visita_y_vehiculo(): void
    {
        $acc = $this->service->registrar($this->datosBase([
            'tipoAc'        => 'proveedor',
            'motivo'        => 'Entrega de mercaderia',
            'patente'       => 'XYZ-9876',
            'empresaOrigen' => 'Distribuidora SA',
        ]), 1);

        $this->assertDatabaseHas('acceso_visitante', [
            'avi_ac_code'       => $acc->ac_code,
            'avi_empresa_origen'=> 'Distribuidora SA',
        ]);

        $this->assertDatabaseHas('acceso_vehiculo', [
            'av_ac_code' => $acc->ac_code,
            'av_patente' => 'XYZ-9876',
        ]);
    }

    // ── Salidas ──

    /** @test */
    public function salida_actualiza_estado_y_registra_historial(): void
    {
        $acc = $this->service->registrar($this->datosBase(), 1);

        $actualizado = $this->service->registrarSalida($acc->ac_code, '-1.83', '-79.53');

        $this->assertEquals(Acceso::ESTADO_COMPLETADA, $actualizado->ac_estado_acceso);
        $this->assertEquals(0, $actualizado->ac_is_entrada);
        $this->assertNotNull($actualizado->ac_is_salida_fecha);

        $marcas = AccesoHistorial::where('ah_ac_code', $acc->ac_code)
            ->orderBy('ah_code')
            ->pluck('ah_tipo_marca')
            ->toArray();

        $this->assertEquals(['entrada', 'salida'], $marcas);
    }

    /** @test */
    public function no_permite_doble_salida(): void
    {
        $acc = $this->service->registrar($this->datosBase(), 1);
        $this->service->registrarSalida($acc->ac_code);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Al acceso se le registro como salida previamente');

        $this->service->registrarSalida($acc->ac_code);
    }

    /** @test */
    public function salida_de_codigo_inexistente_falla(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se encontro informacion del codigo provisto');

        $this->service->registrarSalida(99999);
    }

    // ── Accessors ──

    /** @test */
    public function tiempo_de_permanencia_se_calcula_tras_salida(): void
    {
        $acc = $this->service->registrar($this->datosBase(), 1);

        $this->assertNull($acc->tiempo_permanencia);

        $acc->ac_created_at = now()->subMinutes(90);
        $acc->save();

        $salida = $this->service->registrarSalida($acc->ac_code);

        $this->assertMatchesRegularExpression('/^\d+h \d+m$/', $salida->tiempo_permanencia);
    }

    // ── Pre-registro ──

    /** @test */
    public function crear_preregistro_genera_token_y_queda_pendiente(): void
    {
        $preregistro = $this->service->crearPreregistro([
            'institucion'    => $this->insCode,
            'fechaEstimada'  => now()->addDay()->toDateString(),
            'horaEstimada'   => '10:00',
            'identificacion' => '0987654321',
            'nombres'        => 'Maria',
            'apellidos'      => 'Garcia',
            'motivo'         => 'Mantenimiento',
        ], 1);

        $this->assertEquals(AccesoPreregistro::ESTADO_PENDIENTE, $preregistro->apr_estado);
        $this->assertNotEmpty($preregistro->apr_token);
        $this->assertTrue($preregistro->estaPendiente());

        $this->assertDatabaseHas('acceso_preregistro', [
            'apr_ins_code' => $this->insCode,
            'apr_estado'   => 'pendiente',
        ]);
    }

    /** @test */
    public function listar_preregistros_filtra_por_fecha(): void
    {
        $this->service->crearPreregistro([
            'institucion'    => $this->insCode,
            'fechaEstimada'  => now()->toDateString(),
            'identificacion' => '1111111111',
            'nombres'        => 'A',
            'apellidos'      => 'B',
        ], 1);

        $this->service->crearPreregistro([
            'institucion'    => $this->insCode,
            'fechaEstimada'  => now()->addDays(5)->toDateString(),
            'identificacion' => '2222222222',
            'nombres'        => 'C',
            'apellidos'      => 'D',
        ], 1);

        $hoy = $this->service->listarPreregistros($this->insCode, now()->toDateString());
        $todos = $this->service->listarPreregistros($this->insCode);

        $this->assertCount(1, $hoy);
        $this->assertCount(2, $todos);
    }

    /** @test */
    public function cancelar_preregistro_pendiente(): void
    {
        $preregistro = $this->service->crearPreregistro([
            'institucion'    => $this->insCode,
            'fechaEstimada'  => now()->toDateString(),
            'identificacion' => '3333333333',
            'nombres'        => 'E',
            'apellidos'      => 'F',
        ], 1);

        $cancelado = $this->service->cancelarPreregistro($preregistro->apr_code);

        $this->assertEquals(AccesoPreregistro::ESTADO_CANCELADO, $cancelado->apr_estado);
    }

    /** @test */
    public function no_cancela_preregistro_ya_procesado(): void
    {
        $preregistro = $this->service->crearPreregistro([
            'institucion'    => $this->insCode,
            'fechaEstimada'  => now()->toDateString(),
            'identificacion' => '4444444444',
            'nombres'        => 'G',
            'apellidos'      => 'H',
        ], 1);

        $this->service->cancelarPreregistro($preregistro->apr_code);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Solo se pueden cancelar pre-registros pendientes');

        $this->service->cancelarPreregistro($preregistro->apr_code);
    }

    /** @test */
    public function entrada_confirma_preregistro_pendiente_de_hoy(): void
    {
        $preregistro = $this->service->crearPreregistro([
            'institucion'    => $this->insCode,
            'fechaEstimada'  => now()->toDateString(),
            'identificacion' => '1712345678',
            'nombres'        => 'Juan',
            'apellidos'      => 'Perez',
        ], 1);

        $this->service->registrar($this->datosBase(), 1);

        $this->assertDatabaseHas('acceso_preregistro', [
            'apr_code'   => $preregistro->apr_code,
            'apr_estado' => AccesoPreregistro::ESTADO_LLEGO,
        ]);
    }

    /** @test */
    public function entrada_no_confirma_preregistro_de_otra_institucion(): void
    {
        $otraIns = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Otra Institucion',
            'ins_estado'      => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], 'ins_code');

        $preregistro = $this->service->crearPreregistro([
            'institucion'    => $otraIns,
            'fechaEstimada'  => now()->toDateString(),
            'identificacion' => '1712345678',
            'nombres'        => 'Juan',
            'apellidos'      => 'Perez',
        ], 1);

        $this->service->registrar($this->datosBase(), 1);

        $this->assertDatabaseHas('acceso_preregistro', [
            'apr_code'   => $preregistro->apr_code,
            'apr_estado' => AccesoPreregistro::ESTADO_PENDIENTE,
        ]);
    }

    // ── Relaciones ──

    /** @test */
    public function relacion_persona_accesos_devuelve_sus_accesos(): void
    {
        $this->service->registrar($this->datosBase(), 1);
        $this->service->registrar($this->datosBase(), 1);

        $persona = AccesoPersona::where('ap_documento', '1712345678')->first();

        $this->assertCount(2, $persona->accesos);
    }
}
