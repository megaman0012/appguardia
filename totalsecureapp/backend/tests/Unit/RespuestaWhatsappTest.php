<?php

namespace Tests\Unit;

use App\Services\VacanteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Administracion\Models\AvisoEnvio;
use Modules\Administracion\Models\Puesto;
use Modules\Administracion\Models\Turno;
use Modules\Administracion\Models\TurnoPostulacion;
use Modules\Administracion\Models\TurnoVacante;
use Tests\TestCase;

/**
 * El guardia contesta por WhatsApp y su respuesta entra sola al sistema.
 *
 * Esta es la mitad que faltaba. La app vive en las tablets de los puestos, así
 * que el guardia que puede cubrir —que está franco, en su casa— no la tiene.
 * WhatsApp es el único camino hasta él, y si alguien tuviera que leer los
 * mensajes y cargarlos a mano, a las tres de la mañana no pasaría.
 */
class RespuestaWhatsappTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-de-prueba-largo';

    private int $local;
    private int $guardia = 1;
    private int $ausente = 2;
    private int $consola = 3;
    private Puesto $puesto;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'avisos.whatsapp.url'           => 'http://gateway.test',
            'avisos.whatsapp.instancia'     => 'totalsecure',
            'avisos.whatsapp.api_key'       => 'clave',
            'avisos.whatsapp.codigo_pais'   => '593',
            'avisos.whatsapp.webhook_token' => self::TOKEN,
            // Solo WhatsApp: es el canal que importa para este flujo.
            'avisos.canales'                => [\App\Services\Avisos\CanalWhatsApp::class],
        ]);

        Http::fake(['*' => Http::response(['key' => ['id' => 'x']], 201)]);

        $this->local = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Terminal', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        $this->crearUsuario($this->guardia, 'Vigilante', '0987654321', true);
        $this->crearUsuario($this->ausente, 'Vigilante', null, false);
        $this->crearUsuario($this->consola, 'Consola', '0999111222', true);

        $this->puesto = Puesto::create([
            'pu_ins_code' => $this->local, 'pu_nombre' => 'Garita', 'pu_estado' => true,
        ]);
    }

    private function crearUsuario(int $id, string $rol, ?string $whatsapp, bool $extras): void
    {
        DB::table('users')->updateOrInsert(['id' => $id], [
            'usu_cedula' => str_pad((string) $id, 10, '0', STR_PAD_LEFT),
            'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => $rol . ' ' . $id, 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => $id . '@e.com',
            'usu_state' => 1,
            'usu_acepta_extras' => $extras,
            'usu_whatsapp' => $whatsapp,
            'usu_acepta_whatsapp' => $whatsapp !== null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $rolId = DB::table('roles')->where('name', $rol)->value('id');
        DB::table('user_has_roles')->updateOrInsert(
            ['user_id' => $id, 'role_id' => $rolId],
            ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
        );

        if ($rol === 'Vigilante') {
            DB::table('user_has_institucion')->updateOrInsert(
                ['ui_usu_id' => $id, 'ui_ins_code' => $this->local],
                ['ui_state' => 1]
            );
        }
    }

    /** Crea la vacante y la ofrece, que es lo que manda el WhatsApp. */
    private function ofrecer(string $inicio = '06:00', string $fin = '14:00'): TurnoVacante
    {
        $turno = Turno::create([
            'tu_ins_code' => $this->local, 'tu_usu_id' => $this->ausente,
            'tu_puesto_id' => $this->puesto->pu_id,
            'tu_fecha' => Carbon::today()->toDateString(),
            'tu_hora_inicio_prevista' => $inicio, 'tu_hora_fin_prevista' => $fin,
            'tu_estado' => 'programado', 'tu_state' => true,
        ]);

        $servicio = app(VacanteService::class);

        return $servicio->abrir($servicio->crearDesdeTurno($turno), 99);
    }

    private function entra(string $texto, ?string $numero = null, bool $propio = false)
    {
        return $this->postJson('/api/whatsapp/webhook/' . self::TOKEN, [
            'event' => 'messages.upsert',
            'data'  => [
                'key'     => [
                    'remoteJid' => ($numero ?? '593987654321') . '@s.whatsapp.net',
                    'fromMe'    => $propio,
                ],
                'message' => ['conversation' => $texto],
            ],
        ]);
    }

    // ── La puerta ──

    public function test_sin_el_token_correcto_la_ruta_no_existe(): void
    {
        // Quien tenga el token puede simular que un guardia aceptó un turno.
        $r = $this->postJson('/api/whatsapp/webhook/otro-token', ['event' => 'messages.upsert']);

        $r->assertStatus(404);
    }

    public function test_ignora_los_mensajes_que_manda_el_propio_sistema(): void
    {
        // Los mensajes salientes vuelven por el webhook: sin esto, el sistema se
        // contestaría a sí mismo.
        $this->ofrecer();

        $this->entra('SI', null, true)->assertJsonPath('ignorado', 'propio');

        $this->assertSame(0, TurnoPostulacion::count());
    }

    public function test_ignora_los_grupos(): void
    {
        $r = $this->postJson('/api/whatsapp/webhook/' . self::TOKEN, [
            'event' => 'messages.upsert',
            'data'  => [
                'key'     => ['remoteJid' => '123456@g.us', 'fromMe' => false],
                'message' => ['conversation' => 'SI'],
            ],
        ]);

        $r->assertJsonPath('ignorado', 'grupo o sin origen');
    }

    public function test_un_numero_desconocido_no_hace_nada(): void
    {
        $this->ofrecer();

        $this->entra('SI', '593000000000')->assertJsonPath('estado', 'desconocido');

        $this->assertSame(0, TurnoPostulacion::count());
    }

    // ── La respuesta que importa ──

    public function test_un_si_con_una_sola_oferta_registra_la_postulacion(): void
    {
        $vacante = $this->ofrecer();

        $this->entra('si')->assertJsonPath('estado', 'aceptada');

        $this->assertSame(1, TurnoPostulacion::count());
        $this->assertSame($this->guardia, (int) TurnoPostulacion::first()->tp_usu_id);
        $this->assertSame($vacante->tv_id, (int) TurnoPostulacion::first()->tp_tv_id);
    }

    public function test_acepta_el_formato_con_codigo(): void
    {
        $vacante = $this->ofrecer();

        $this->entra("SI {$vacante->tv_id}")->assertJsonPath('estado', 'aceptada');

        $this->assertSame(1, TurnoPostulacion::count());
    }

    public function test_al_aceptar_se_le_avisa_a_la_consola(): void
    {
        // Sin esto, la respuesta del guardia quedaría esperando a que alguien
        // entre al panel a mirar, que es justo lo que no pasa de madrugada.
        $this->ofrecer();

        $this->entra('si');

        $aviso = AvisoEnvio::where('ae_usu_id', $this->consola)
            ->where('ae_tipo', 'postulacion_recibida')
            ->first();

        $this->assertNotNull($aviso, 'La central 24/7 tiene que enterarse en el momento');
    }

    public function test_con_dos_ofertas_pide_el_codigo_en_vez_de_adivinar(): void
    {
        // Adivinar cuál aceptó mandaría a alguien al puesto equivocado.
        $this->ofrecer('06:00', '14:00');
        $this->ofrecer('16:00', '23:00');

        $this->entra('si')->assertJsonPath('estado', 'ambigua');

        $this->assertSame(0, TurnoPostulacion::count());
    }

    public function test_con_dos_ofertas_el_codigo_desempata(): void
    {
        $this->ofrecer('06:00', '14:00');
        $segunda = $this->ofrecer('16:00', '23:00');

        $this->entra("si {$segunda->tv_id}")->assertJsonPath('estado', 'aceptada');

        $this->assertSame($segunda->tv_id, (int) TurnoPostulacion::first()->tp_tv_id);
    }

    public function test_un_no_no_postula_pero_queda_registrado(): void
    {
        // Que avise que no puede también es información: la central deja de
        // esperar esa respuesta.
        $vacante = $this->ofrecer();

        $this->entra('no')->assertJsonPath('estado', 'negativa');

        $this->assertSame(0, TurnoPostulacion::count());
        $this->assertSame(
            1,
            AvisoEnvio::where('ae_tipo', 'respuesta_negativa')->where('ae_tv_id', $vacante->tv_id)->count()
        );
    }

    public function test_una_respuesta_que_no_se_entiende_pide_aclaracion(): void
    {
        $this->ofrecer();

        $this->entra('buenas, quien habla?')->assertJsonPath('estado', 'no_entendida');

        $this->assertSame(0, TurnoPostulacion::count());
    }

    public function test_sin_convocatorias_abiertas_lo_dice(): void
    {
        $this->entra('si')->assertJsonPath('estado', 'sin_ofertas');
    }

    public function test_si_el_turno_ya_fue_cubierto_se_lo_avisa(): void
    {
        $vacante = $this->ofrecer();
        app(VacanteService::class)->cancelar($vacante, 99, 'El guardia apareció');

        $this->entra('si')->assertJsonPath('estado', 'sin_ofertas');

        $this->assertSame(0, TurnoPostulacion::count());
    }

    public function test_si_ya_tiene_un_turno_a_esa_hora_se_le_explica(): void
    {
        // "Ya tiene un turno a esa hora" es información útil; "no se pudo" no.
        $vacante = $this->ofrecer();

        Turno::create([
            'tu_ins_code' => $this->local, 'tu_usu_id' => $this->guardia,
            'tu_fecha' => Carbon::today()->toDateString(),
            'tu_hora_inicio_prevista' => '06:00', 'tu_hora_fin_prevista' => '14:00',
            'tu_estado' => 'programado', 'tu_state' => true,
        ]);

        $r = $this->entra('si');

        $r->assertJsonPath('estado', 'no_elegible');
        $this->assertStringContainsString('turno a esa hora', $r->json('detalle'));
        $this->assertSame(0, TurnoPostulacion::count());
    }

    public function test_responde_al_guardia_por_el_mismo_whatsapp(): void
    {
        $this->ofrecer();

        $this->entra('si');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/message/sendText/')
                && $request['number'] === '593987654321'
                && str_contains($request['text'], 'central le confirmará');
        });
    }
}
