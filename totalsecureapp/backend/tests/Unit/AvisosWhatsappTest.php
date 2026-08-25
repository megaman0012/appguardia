<?php

namespace Tests\Unit;

use App\Services\Avisos\CanalWhatsApp;
use App\Services\Avisos\EvolutionApi;
use App\Services\Avisos\NumeroWhatsapp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Administracion\Models\AvisoEnvio;
use Tests\TestCase;

/**
 * Aviso por WhatsApp a través del gateway Evolution.
 *
 * Lo que se fija acá es que el sistema no se engañe a sí mismo. Un número mal
 * formado, un guardia que no autorizó, o el gateway apagado **no producen un
 * error**: el envío simplemente no llega. Si eso se registrara como "enviado",
 * nadie se enteraría de que el aviso nunca salió hasta que un puesto amanezca
 * vacío.
 */
class AvisosWhatsappTest extends TestCase
{
    use RefreshDatabase;

    private int $guardia = 1;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'avisos.whatsapp.url'         => 'http://gateway.test',
            'avisos.whatsapp.instancia'   => 'totalsecure',
            'avisos.whatsapp.api_key'     => 'clave-de-prueba',
            'avisos.whatsapp.codigo_pais' => '593',
        ]);

        $this->crearGuardia($this->guardia, '0987654321', true);
    }

    private function crearGuardia(int $id, ?string $whatsapp, bool $acepta): void
    {
        DB::table('users')->updateOrInsert(['id' => $id], [
            'usu_cedula' => str_pad((string) $id, 10, '0', STR_PAD_LEFT),
            'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Guardia ' . $id, 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => $id . '@e.com',
            'usu_state' => 1,
            'usu_whatsapp' => $whatsapp,
            'usu_acepta_whatsapp' => $acepta,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function canal(): CanalWhatsApp
    {
        return new CanalWhatsApp(new EvolutionApi());
    }

    // ── El número ──

    public function test_completa_el_codigo_de_pais_de_un_numero_local(): void
    {
        $this->assertSame('593987654321', NumeroWhatsapp::normalizar('0987654321'));
        $this->assertSame('593987654321', NumeroWhatsapp::normalizar('987654321'));
    }

    public function test_limpia_el_formato_con_el_que_la_gente_escribe(): void
    {
        $this->assertSame('593987654321', NumeroWhatsapp::normalizar('+593 98 765 4321'));
        $this->assertSame('593987654321', NumeroWhatsapp::normalizar('(593) 98-765-4321'));
    }

    public function test_respeta_un_numero_que_ya_trae_codigo_de_pais(): void
    {
        $this->assertSame('573001234567', NumeroWhatsapp::normalizar('573001234567'));
        $this->assertSame('593987654321', NumeroWhatsapp::normalizar('593987654321'));
    }

    public function test_usa_el_codigo_de_colombia_cuando_se_le_indica(): void
    {
        $this->assertSame('573001234567', NumeroWhatsapp::normalizar('3001234567', '57'));
    }

    public function test_descarta_lo_que_no_es_un_numero_de_la_region(): void
    {
        $this->assertFalse(NumeroWhatsapp::valido('123'));
        $this->assertFalse(NumeroWhatsapp::valido(''));
        $this->assertFalse(NumeroWhatsapp::valido(null));
    }

    // ── Cuándo NO se intenta ──

    public function test_no_se_le_escribe_a_quien_no_autorizo(): void
    {
        // Aceptar turnos extra no es aceptar que le escriban al teléfono
        // personal: son dos permisos distintos.
        Http::fake();
        $this->crearGuardia(2, '0987654322', false);

        $r = $this->canal()->enviar(2, 'Turno disponible', 'Hay un turno');

        $this->assertSame(AvisoEnvio::OMITIDO, $r->resultado);
        $this->assertStringContainsString('No autorizó', $r->detalle);
        Http::assertNothingSent();
    }

    public function test_no_se_intenta_sin_un_numero_valido(): void
    {
        Http::fake();
        $this->crearGuardia(3, null, true);

        $r = $this->canal()->enviar(3, 'Turno disponible', 'Hay un turno');

        $this->assertSame(AvisoEnvio::OMITIDO, $r->resultado);
        Http::assertNothingSent();
    }

    public function test_sin_gateway_configurado_no_se_intenta_nada(): void
    {
        // El canal puede quedar declarado aunque todavía no exista el gateway.
        Http::fake();
        config(['avisos.whatsapp.url' => '']);

        $r = (new CanalWhatsApp(new EvolutionApi()))->enviar($this->guardia, 'Hola', 'Prueba');

        $this->assertSame(AvisoEnvio::OMITIDO, $r->resultado);
        Http::assertNothingSent();
    }

    // ── Cuándo sí ──

    public function test_envia_al_numero_normalizado_y_con_la_clave(): void
    {
        Http::fake(['*' => Http::response(['key' => ['id' => 'abc']], 201)]);

        $r = $this->canal()->enviar($this->guardia, 'Turno disponible', 'Garita, 06:00 a 14:00');

        $this->assertTrue($r->ok());
        $this->assertSame('593987654321', $r->destino);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/message/sendText/totalsecure')
                && $request['number'] === '593987654321'
                && str_contains($request['text'], 'Garita')
                && $request->hasHeader('apikey', 'clave-de-prueba');
        });
    }

    public function test_un_error_del_gateway_queda_como_fallido_con_su_motivo(): void
    {
        // "Falló" y "no se intentó" son problemas distintos: uno se arregla
        // levantando un servicio, el otro cargando un dato.
        Http::fake(['*' => Http::response('instance not found', 404)]);

        $r = $this->canal()->enviar($this->guardia, 'Turno disponible', 'Hay un turno');

        $this->assertSame(AvisoEnvio::FALLIDO, $r->resultado);
        $this->assertStringContainsString('404', $r->detalle);
    }

    public function test_el_gateway_apagado_no_lanza_excepcion(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $r = $this->canal()->enviar($this->guardia, 'Turno disponible', 'Hay un turno');

        $this->assertSame(AvisoEnvio::FALLIDO, $r->resultado);
        $this->assertStringContainsString('refused', $r->detalle);
    }

    // ── Estado del canal, que es lo que muestra el panel ──

    public function test_reporta_la_sesion_conectada_y_su_numero(): void
    {
        Http::fake([
            '*connectionState*' => Http::response(['instance' => ['state' => 'open']], 200),
            '*fetchInstances*'  => Http::response([['ownerJid' => '593999888777@s.whatsapp.net']], 200),
        ]);

        $estado = (new EvolutionApi())->estado();

        $this->assertSame('abierta', $estado['estado']);
        $this->assertSame('593999888777', $estado['numero']);
    }

    public function test_reporta_la_sesion_caida(): void
    {
        // La sesión se cae sola cada tanto y hay que volver a escanear el QR. Si
        // nadie lo ve, los avisos dejan de salir en silencio.
        Http::fake(['*' => Http::response(['instance' => ['state' => 'close']], 200)]);

        $this->assertSame('cerrada', (new EvolutionApi())->estado()['estado']);
    }

    public function test_reporta_el_gateway_sin_respuesta(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $estado = (new EvolutionApi())->estado();

        $this->assertSame('sin_respuesta', $estado['estado']);
        $this->assertStringContainsString('refused', $estado['detalle']);
    }

    public function test_sin_configurar_lo_dice_en_vez_de_intentar(): void
    {
        Http::fake();
        config(['avisos.whatsapp.api_key' => '']);

        $estado = (new EvolutionApi())->estado();

        $this->assertFalse($estado['configurado']);
        $this->assertStringContainsString('WHATSAPP_', $estado['detalle']);
        Http::assertNothingSent();
    }
}
