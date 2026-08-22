<?php

namespace Tests\Unit;

use App\Services\OfflineSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Novedad;
use Modules\MobileApp\Models\users;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    private OfflineSyncService $offlineSync;
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

        $this->offlineSync = app(OfflineSyncService::class);
    }

    private function crearNovedad(?string $clientUuid, string $obs = 'Novedad de prueba'): Novedad
    {
        $nv = new Novedad();
        $nv->nv_usu_id = 1;
        $nv->nv_ins_code = $this->insCode;
        $nv->nv_observacion = $obs;
        $nv->nv_fecha_hora = now();
        $nv->nv_lat = '-33.45';
        $nv->nv_lng = '-70.66';
        $nv->nv_estado = 1;
        $nv->nv_client_uuid = $clientUuid;
        $nv->nv_sincronizado_en = $this->offlineSync->sincronizadoEn();
        $nv->nv_created_user = 1;
        $nv->nv_updated_user = 1;
        $nv->save();

        return $nv;
    }

    // ── ocurridoEn: fecha real del evento en campo ──

    public function test_ocurrido_en_sin_valor_usa_el_momento_actual(): void
    {
        Carbon::setTestNow('2026-08-21 10:00:00');

        $this->assertSame('2026-08-21 10:00:00', $this->offlineSync->ocurridoEn(null));
        $this->assertSame('2026-08-21 10:00:00', $this->offlineSync->ocurridoEn(''));

        Carbon::setTestNow();
    }

    public function test_ocurrido_en_respeta_una_fecha_pasada_del_dispositivo(): void
    {
        Carbon::setTestNow('2026-08-21 10:00:00');

        // Registro hecho sin señal 3 horas antes de poder sincronizar.
        $this->assertSame(
            '2026-08-21 07:00:00',
            $this->offlineSync->ocurridoEn('2026-08-21 07:00:00')
        );

        Carbon::setTestNow();
    }

    public function test_ocurrido_en_recorta_una_fecha_futura(): void
    {
        Carbon::setTestNow('2026-08-21 10:00:00');

        // Reloj del dispositivo adelantado: no debe crear registros en el futuro.
        $this->assertSame(
            '2026-08-21 10:00:00',
            $this->offlineSync->ocurridoEn('2026-08-25 12:00:00')
        );

        Carbon::setTestNow();
    }

    public function test_ocurrido_en_con_valor_invalido_usa_el_momento_actual(): void
    {
        Carbon::setTestNow('2026-08-21 10:00:00');

        $this->assertSame('2026-08-21 10:00:00', $this->offlineSync->ocurridoEn('no-es-fecha'));

        Carbon::setTestNow();
    }

    // ── buscar ──

    public function test_buscar_sin_client_uuid_devuelve_null(): void
    {
        $this->crearNovedad(null);

        $this->assertNull($this->offlineSync->buscar(Novedad::class, 'nv_client_uuid', null));
        $this->assertNull($this->offlineSync->buscar(Novedad::class, 'nv_client_uuid', ''));
    }

    public function test_buscar_con_client_uuid_inexistente_devuelve_null(): void
    {
        $this->assertNull(
            $this->offlineSync->buscar(Novedad::class, 'nv_client_uuid', '11111111-1111-4111-8111-111111111111')
        );
    }

    public function test_buscar_encuentra_el_registro_ya_sincronizado(): void
    {
        $uuid = '22222222-2222-4222-8222-222222222222';
        $nv = $this->crearNovedad($uuid);

        $hallado = $this->offlineSync->buscar(Novedad::class, 'nv_client_uuid', $uuid);

        $this->assertNotNull($hallado);
        $this->assertSame($nv->nv_id, $hallado->nv_id);
    }

    // ── registrar: idempotencia ──

    public function test_registrar_sin_client_uuid_siempre_crea(): void
    {
        list($a, $dupA) = $this->offlineSync->registrar(
            Novedad::class, 'nv_client_uuid', null,
            fn () => $this->crearNovedad(null, 'primera')
        );
        list($b, $dupB) = $this->offlineSync->registrar(
            Novedad::class, 'nv_client_uuid', null,
            fn () => $this->crearNovedad(null, 'segunda')
        );

        $this->assertFalse($dupA);
        $this->assertFalse($dupB);
        $this->assertNotSame($a->nv_id, $b->nv_id);
        $this->assertSame(2, Novedad::count());
    }

    public function test_registrar_con_client_uuid_nuevo_crea_el_registro(): void
    {
        $uuid = '33333333-3333-4333-8333-333333333333';

        list($nv, $duplicado) = $this->offlineSync->registrar(
            Novedad::class, 'nv_client_uuid', $uuid,
            fn () => $this->crearNovedad($uuid)
        );

        $this->assertFalse($duplicado);
        $this->assertSame($uuid, $nv->nv_client_uuid);
        $this->assertSame(1, Novedad::count());
    }

    public function test_reintento_con_el_mismo_client_uuid_no_duplica(): void
    {
        $uuid = '44444444-4444-4444-8444-444444444444';

        list($primera, $dup1) = $this->offlineSync->registrar(
            Novedad::class, 'nv_client_uuid', $uuid,
            fn () => $this->crearNovedad($uuid, 'original')
        );

        list($segunda, $dup2) = $this->offlineSync->registrar(
            Novedad::class, 'nv_client_uuid', $uuid,
            function () {
                $this->fail('El reintento no debe volver a crear el registro');
            }
        );

        $this->assertFalse($dup1);
        $this->assertTrue($dup2);
        $this->assertSame($primera->nv_id, $segunda->nv_id);
        $this->assertSame('original', $segunda->nv_observacion);
        $this->assertSame(1, Novedad::count());
    }

    public function test_carrera_de_dos_reintentos_recupera_el_registro_ganador(): void
    {
        $uuid = '55555555-5555-4555-8555-555555555555';

        // El registro entra "por detras" (como el reintento que gana la carrera) y
        // el callback choca contra el indice unique.
        $ganador = $this->crearNovedad($uuid, 'ganador');

        list($nv, $duplicado) = $this->offlineSync->registrar(
            Novedad::class,
            'nv_client_uuid',
            $uuid,
            function () use ($uuid) {
                // Fuerza la violacion de unicidad, igual que el reintento perdedor.
                return $this->crearNovedad($uuid, 'perdedor');
            }
        );

        $this->assertTrue($duplicado);
        $this->assertSame($ganador->nv_id, $nv->nv_id);
        $this->assertSame('ganador', $nv->nv_observacion);
        $this->assertSame(1, Novedad::count());
    }

    public function test_client_uuid_distinto_crea_registros_distintos(): void
    {
        $a = '66666666-6666-4666-8666-666666666666';
        $b = '77777777-7777-4777-8777-777777777777';

        list($nvA, ) = $this->offlineSync->registrar(
            Novedad::class, 'nv_client_uuid', $a, fn () => $this->crearNovedad($a, 'A')
        );
        list($nvB, ) = $this->offlineSync->registrar(
            Novedad::class, 'nv_client_uuid', $b, fn () => $this->crearNovedad($b, 'B')
        );

        $this->assertNotSame($nvA->nv_id, $nvB->nv_id);
        $this->assertSame(2, Novedad::count());
    }

    public function test_sincronizado_en_queda_registrado(): void
    {
        $uuid = '88888888-8888-4888-8888-888888888888';
        $nv = $this->crearNovedad($uuid);

        $this->assertNotNull($nv->fresh()->nv_sincronizado_en);
    }

    // ── Contrato HTTP: el reintento no debe verse como error ──

    private function tokenVigilante(): string
    {
        $user = users::find(1);

        $roleId = DB::table('roles')->where('name', 'Vigilante')->value('id');
        DB::table('user_has_roles')->updateOrInsert(
            ['user_id' => 1, 'role_id' => $roleId],
            ['ru_code' => DB::table('user_has_roles')->max('ru_code') + 1]
        );

        DB::table('user_has_institucion')->updateOrInsert(
            ['ui_usu_id' => 1, 'ui_ins_code' => $this->insCode],
            ['ui_state' => 1]
        );

        return $user->createToken('offline-test')->plainTextToken;
    }

    public function test_reintento_del_endpoint_responde_200_y_no_duplica(): void
    {
        $token = $this->tokenVigilante();
        $uuid = '99999999-9999-4999-8999-999999999999';

        $payload = [
            'ins_code'       => $this->insCode,
            'nv_observacion' => 'Portón forzado',
            'nv_lat'         => '-33.45',
            'nv_lng'         => '-70.66',
            'client_uuid'    => $uuid,
            'ocurrido_en'    => now()->subHours(3)->format('Y-m-d H:i:s'),
        ];

        $primera = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/novedad_create', $payload);

        $primera->assertStatus(200);
        $primera->assertJsonPath('duplicado', false);

        // Mismo registro reenviado por la cola de sincronizacion.
        $segunda = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/novedad_create', $payload);

        $segunda->assertStatus(200);
        $segunda->assertJsonPath('duplicado', true);
        $segunda->assertJsonPath('result', 'success');
        $this->assertSame($primera->json('nv_id'), $segunda->json('nv_id'));
        $this->assertSame(1, Novedad::count());
    }

    public function test_endpoint_conserva_la_fecha_del_evento_no_la_de_llegada(): void
    {
        $token = $this->tokenVigilante();
        $ocurrido = now()->subHours(5)->format('Y-m-d H:i:s');

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/novedad_create', [
                'ins_code'       => $this->insCode,
                'nv_observacion' => 'Registrada sin señal',
                'nv_lat'         => '-33.45',
                'nv_lng'         => '-70.66',
                'client_uuid'    => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                'ocurrido_en'    => $ocurrido,
            ]);

        $respuesta->assertStatus(200);

        $nv = Novedad::first();
        // La novedad ocurrio 5 horas antes de llegar al servidor: esa distancia es
        // justamente lo que hace auditable la latencia de red en campo.
        $this->assertSame($ocurrido, Carbon::parse($nv->nv_fecha_hora)->format('Y-m-d H:i:s'));
        $this->assertTrue(
            Carbon::parse($nv->nv_sincronizado_en)->greaterThan(Carbon::parse($nv->nv_fecha_hora))
        );
    }

    public function test_endpoint_rechaza_un_client_uuid_mal_formado(): void
    {
        $token = $this->tokenVigilante();

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/novedad_create', [
                'ins_code'       => $this->insCode,
                'nv_observacion' => 'Test',
                'nv_lat'         => '-33.45',
                'nv_lng'         => '-70.66',
                'client_uuid'    => 'no-es-un-uuid',
            ]);

        $respuesta->assertJsonPath('success', false);
        $this->assertSame(0, Novedad::count());
    }
}
