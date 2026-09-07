<?php

namespace Tests\Unit;

use App\Services\PresenceValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fija el comportamiento del fail-open de la geocerca.
 *
 * El hueco original: un local SIN marcador activo aceptaba marcajes desde
 * cualquier lugar y la fila quedaba identica a una comprobada de verdad. Se
 * verifico con un marcaje a 273 km que entro sin dejar rastro. Se decidio
 * ACEPTAR pero MARCAR, no bloquear -- un guardia no puede perder su asistencia
 * porque a alguien le falto configurar el local.
 *
 * Estos tests existen porque el comportamiento anterior no estaba cubierto por
 * ninguno, y por eso nadie lo noto.
 */
class VerificacionUbicacionTest extends TestCase
{
    use RefreshDatabase;

    private PresenceValidationService $service;
    private int $insCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PresenceValidationService::class);

        $this->insCode = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion'             => 'Local de prueba',
            'ins_estado'                  => true,
            'ins_radio_tolerancia_metros' => 100,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ], 'ins_code');

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

        DB::table('user_has_institucion')->insert([
            'ui_usu_id'   => 1,
            'ui_ins_code' => $this->insCode,
            'ui_state'      => 1,
            'ui_created_at' => now(),
            'ui_updated_at' => now(),
        ]);
    }

    private function crearMarcador(bool $activo = true): void
    {
        DB::table('institucion_marcadores')->insert([
            'im_ins_code'    => $this->insCode,
            'im_descripcion' => 'Garita',
            'im_lat'         => -2.1890,
            'im_lng'         => -79.8890,
            'im_estado'      => $activo,
            'im_created_at'  => now(),
            'im_updated_at'  => now(),
        ]);
    }

    /** @test */
    public function dentro_del_radio_queda_verificado()
    {
        $this->crearMarcador();

        // ~95 m del marcador, radio 100.
        $r = $this->service->validarUbicacion(-2.18986, -79.8890, $this->insCode);

        $this->assertTrue($r['valido']);
        $this->assertTrue($r['verificado'], 'una medicion real debe marcarse como verificada');
        $this->assertGreaterThan(0, $r['distancia_m']);
    }

    /** @test */
    public function fuera_del_radio_se_rechaza_pero_la_medicion_ocurrio()
    {
        $this->crearMarcador();

        // ~333 m del marcador.
        $r = $this->service->validarUbicacion(-2.1920, -79.8890, $this->insCode);

        $this->assertFalse($r['valido']);
        // Se rechaza JUSTAMENTE porque se pudo medir. Marcarlo como no
        // verificado confundiria "esta lejos" con "no se sabe donde esta".
        $this->assertTrue($r['verificado']);
        $this->assertStringContainsString('Fuera de geocerca', $r['motivo']);
    }

    /** @test */
    public function sin_marcador_activo_se_acepta_pero_sin_verificar()
    {
        $this->crearMarcador(activo: false);

        // Quito: 273 km del local. Antes esto entraba indistinguible de un
        // marcaje hecho en la garita.
        $r = $this->service->validarUbicacion(-0.1807, -78.4678, $this->insCode);

        $this->assertTrue($r['valido'], 'no se bloquea: el guardia no pierde su marcaje');
        $this->assertFalse($r['verificado'], 'pero queda escrito que nadie lo comprobo');
        $this->assertStringContainsString('no tiene marcador activo', $r['motivo']);
    }

    /** @test */
    public function sin_ningun_marcador_tampoco_se_verifica()
    {
        $r = $this->service->validarUbicacion(-0.1807, -78.4678, $this->insCode);

        $this->assertTrue($r['valido']);
        $this->assertFalse($r['verificado']);
    }

    /** @test */
    public function medir_no_bloquea_y_distingue_los_tres_estados()
    {
        $this->crearMarcador();

        $dentro = $this->service->medirUbicacion(-2.18986, -79.8890, $this->insCode);
        $this->assertTrue($dentro['verificada']);
        $this->assertNotNull($dentro['distancia_m']);

        // Fuera del radio: se midio, y la distancia lo prueba. Es lo que separa
        // "estaba lejos" de "no se pudo medir", que es el dato que sirve.
        $fuera = $this->service->medirUbicacion(-2.1920, -79.8890, $this->insCode);
        $this->assertFalse($fuera['verificada']);
        $this->assertGreaterThan(300, $fuera['distancia_m']);
    }

    /** @test */
    public function sin_gps_no_se_inventa_una_distancia()
    {
        $this->crearMarcador();

        // La app manda '0'/'0' cuando el dispositivo no dio ubicacion. Medir eso
        // contra el golfo de Guinea daria una distancia enorme y falsa.
        foreach ([['0', '0'], [null, null], ['abc', 'def']] as [$lat, $lng]) {
            $r = $this->service->medirUbicacion($lat, $lng, $this->insCode);

            $this->assertFalse($r['verificada']);
            $this->assertNull(
                $r['distancia_m'],
                'sin ubicacion no hay distancia que reportar'
            );
        }
    }

    /** @test */
    public function medir_sin_marcador_no_verifica_nada()
    {
        $r = $this->service->medirUbicacion(-2.18986, -79.8890, $this->insCode);

        $this->assertFalse($r['verificada']);
        $this->assertNull($r['distancia_m']);
    }
}
