<?php

namespace Tests\Unit;

use App\Services\Avisos\CanalDeAviso;
use App\Services\Avisos\ResultadoDeAviso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Alertas;
use Modules\MobileApp\Models\users;
use Tests\TestCase;

/**
 * Captura los avisos en memoria, en vez de mandarlos.
 */
class CanalDeAlertaDePrueba implements CanalDeAviso
{
    /** @var array<int, array{usuario: int, titulo: string, cuerpo: string, datos: array}> */
    public static array $enviados = [];

    public function nombre(): string
    {
        return 'prueba';
    }

    public function enviar(int $usuarioId, string $titulo, string $cuerpo, array $datos = []): ResultadoDeAviso
    {
        self::$enviados[] = compact('usuarioId', 'titulo', 'cuerpo', 'datos');

        return ResultadoDeAviso::enviado('prueba');
    }
}

/** Un canal que siempre revienta, para probar que el aviso no arrastra a la alerta. */
class CanalDeAlertaRoto implements CanalDeAviso
{
    public function nombre(): string
    {
        return 'roto';
    }

    public function enviar(int $usuarioId, string $titulo, string $cuerpo, array $datos = []): ResultadoDeAviso
    {
        throw new \RuntimeException('el gateway no responde');
    }
}

/**
 * Que la alerta de panico le LLEGUE a alguien.
 *
 * `BotonDePanicoTest` ya cubre que la alerta se cree, se asigne y aparezca en
 * «Alertas de hoy». **Ese era justamente el hueco**: se probaba que quedara
 * registrada y nunca que alguien se enterara. Y no se enteraba nadie: el unico
 * aviso era un evento `ShouldBroadcast` emitido con `BROADCAST_DRIVER=log`, o
 * sea una linea en un archivo.
 *
 * Un sistema que anota la emergencia y no la comunica pasa todas sus pruebas y
 * deja al guardia solo.
 */
class AvisoDeAlertaTest extends TestCase
{
    use RefreshDatabase;

    private int $insCode;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        CanalDeAlertaDePrueba::$enviados = [];
        config(['avisos.canales' => [CanalDeAlertaDePrueba::class]]);

        $this->insCode = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Garita Norte',
            'ins_estado'      => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], 'ins_code');

        $this->usuario(1, 'Guardia Uno', 'Vigilante', $this->insCode);
        $this->token = users::find(1)->createToken('aviso-test')->plainTextToken;
    }

    /** Crea un usuario con su rol y, si se le pasa local, lo vincula. */
    private function usuario(int $id, string $nombre, string $rol, ?int $insCode = null): void
    {
        DB::table('users')->updateOrInsert(['id' => $id], [
            'usu_cedula' => str_repeat((string) $id, 10), 'usu_tipdoc' => 'CC',
            'usu_password' => bcrypt('x'), 'usu_nmbcom' => $nombre,
            'usu_ape1' => 'T', 'usu_ape2' => 'T', 'usu_nmb1' => 'T', 'usu_nmb2' => 'T',
            'usu_email' => "u{$id}@e.com", 'usu_state' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $rolId = DB::table('roles')->where('name', $rol)->value('id');
        DB::table('user_has_roles')->updateOrInsert(
            ['user_id' => $id, 'role_id' => $rolId],
            ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
        );

        if ($insCode !== null) {
            DB::table('user_has_institucion')->updateOrInsert(
                ['ui_usu_id' => $id, 'ui_ins_code' => $insCode],
                ['ui_state' => 1, 'ui_created_at' => now(), 'ui_updated_at' => now()]
            );
        }
    }

    /** @param array<string,mixed> $extra */
    private function crear(array $extra = [])
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/alert/crear', array_merge([
                'ins'         => $this->insCode,
                'lat'         => '-2.1890',
                'lng'         => '-79.8890',
                'observacion' => 'Intento de ingreso forzado en la puerta 3',
                'prioridad'   => 'critica',
            ], $extra));
    }

    /** @return int[] */
    private function avisados(): array
    {
        return array_values(array_unique(array_column(CanalDeAlertaDePrueba::$enviados, 'usuarioId')));
    }

    public function test_el_supervisor_del_local_recibe_el_aviso(): void
    {
        $this->usuario(2, 'Supervisor Dos', 'Supervisor', $this->insCode);

        $this->crear()->assertStatus(201);

        $this->assertContains(2, $this->avisados());
    }

    /**
     * La Consola no se acota por local: de madrugada es la unica que mira.
     */
    public function test_la_consola_recibe_el_aviso_aunque_no_este_vinculada_al_local(): void
    {
        $this->usuario(4, 'Consola Central', 'Consola');

        $this->crear()->assertStatus(201);

        $this->assertContains(4, $this->avisados());
    }

    public function test_un_supervisor_de_otro_local_no_recibe_el_aviso(): void
    {
        $otro = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Otro local', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        $this->usuario(3, 'Supervisor Ajeno', 'Supervisor', $otro);

        $this->crear()->assertStatus(201);

        $this->assertNotContains(3, $this->avisados());
    }

    /** Avisarle al propio guardia que el pidio auxilio no aporta nada. */
    public function test_el_guardia_que_la_emitio_no_se_avisa_a_si_mismo(): void
    {
        $this->crear()->assertStatus(201);

        $this->assertNotContains(1, $this->avisados());
    }

    public function test_queda_constancia_del_aviso_vinculada_a_la_alerta(): void
    {
        $this->usuario(2, 'Supervisor Dos', 'Supervisor', $this->insCode);

        $this->crear()->assertStatus(201);

        $alerta = Alertas::first();

        $this->assertDatabaseHas('aviso_envio', [
            'ae_usu_id'  => 2,
            'ae_tipo'    => 'alerta_creada',
            'ae_al_code' => $alerta->al_code,
            'ae_canal'   => 'prueba',
        ]);
    }

    public function test_el_aviso_lleva_la_ubicacion_cuando_el_gps_respondio(): void
    {
        $this->usuario(2, 'Supervisor Dos', 'Supervisor', $this->insCode);

        $this->crear()->assertStatus(201);

        $this->assertStringContainsString(
            'maps.google.com/?q=-2.189,-79.889',
            CanalDeAlertaDePrueba::$enviados[0]['cuerpo']
        );
    }

    /**
     * La app manda 0/0 a proposito cuando el GPS no respondio, para que una
     * emergencia no se pierda por estar bajo techo. Mandar a alguien a las
     * coordenadas 0,0 en una emergencia seria peor que no mandarlo.
     */
    public function test_sin_gps_el_aviso_lo_dice_en_vez_de_mandar_a_cero_cero(): void
    {
        $this->usuario(2, 'Supervisor Dos', 'Supervisor', $this->insCode);

        $this->crear(['lat' => '0', 'lng' => '0'])->assertStatus(201);

        $cuerpo = CanalDeAlertaDePrueba::$enviados[0]['cuerpo'];

        $this->assertStringNotContainsString('maps.google.com', $cuerpo);
        $this->assertStringContainsString('Sin ubicación', $cuerpo);
    }

    public function test_una_prioridad_critica_se_anuncia_como_emergencia(): void
    {
        $this->usuario(2, 'Supervisor Dos', 'Supervisor', $this->insCode);

        $this->crear(['prioridad' => 'critica'])->assertStatus(201);

        $this->assertStringContainsString('EMERGENCIA', CanalDeAlertaDePrueba::$enviados[0]['titulo']);
        $this->assertStringContainsString('Garita Norte', CanalDeAlertaDePrueba::$enviados[0]['titulo']);
    }

    /**
     * El aviso acelera, no habilita: la alerta ya esta en «Alertas de hoy».
     */
    public function test_si_el_canal_revienta_la_alerta_igual_se_crea(): void
    {
        config(['avisos.canales' => [CanalDeAlertaRoto::class]]);
        $this->usuario(2, 'Supervisor Dos', 'Supervisor', $this->insCode);

        $this->crear()->assertStatus(201);

        $this->assertSame(1, Alertas::count());
    }

    /**
     * El caso de un `config/avisos.php` con una clase mal escrita: revienta al
     * construirse, antes de que el notificador pueda protegerse por canal.
     */
    public function test_un_canal_mal_configurado_tampoco_frena_la_alerta(): void
    {
        config(['avisos.canales' => ['App\\Services\\Avisos\\CanalQueNoExiste']]);

        $this->crear()->assertStatus(201);

        $this->assertSame(1, Alertas::count());
    }

    /**
     * El canal real del panel, no el de prueba: es el unico que no depende de
     * Firebase ni del gateway de WhatsApp, y por eso es el que tiene que
     * funcionar siempre.
     */
    public function test_el_canal_del_panel_deja_la_notificacion_para_la_campanita(): void
    {
        config(['avisos.canales' => [\App\Services\Avisos\CanalPanel::class]]);
        $this->usuario(2, 'Supervisor Dos', 'Supervisor', $this->insCode);

        $this->crear()->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id'   => 2,
            'notifiable_type' => \Modules\Acceso\Models\users::class,
        ]);

        /*
         * La notificacion tiene que llegar CON el enlace a la alerta y marcada
         * como urgente. El enlace se arma con `getUrl()` dentro de una peticion
         * de la API, donde el panel no es el contexto actual: si eso dejara de
         * resolver, la campanita seguiria apareciendo y el supervisor tendria
         * que buscar la alerta a mano, que es medio minuto en una emergencia.
         */
        $datos = json_decode(DB::table('notifications')->value('data'), true);

        $this->assertSame('danger', $datos['status']);
        $this->assertStringContainsString('/admin/alertas', $datos['actions'][0]['url']);
    }
}
