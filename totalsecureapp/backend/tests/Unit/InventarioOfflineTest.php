<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\MovimientoCabecera;
use Modules\Administracion\Models\MovimientoDetalle;
use Modules\MobileApp\Models\users;
use Tests\TestCase;

/**
 * Idempotencia del modulo de inventario.
 *
 * **Por que existe.** Los otros cinco endpoints de campo (novedad, ronda,
 * marcaje, acceso, alerta) ya aceptaban `client_uuid`: la tablet genera un id
 * por evento y el servidor reconoce el reintento. Inventario era el unico que
 * no, asi que un guardia sin señal que reintentaba la recepcion podia crear dos
 * movimientos -- o, peor, recibir «ya existe una recepcion registrada» por su
 * propio reintento y quedar bloqueado con el ciclo a medias.
 *
 * ⚠️ **El APK ya compilado no manda `client_uuid`.** Todo lo de aca es
 * opcional: la mitad de estos tests existe justamente para probar que sin el
 * campo el comportamiento es identico al anterior. Si alguno de esos falla, la
 * app que esta hoy en las tablets se rompe.
 */
class InventarioOfflineTest extends TestCase
{
    use RefreshDatabase;

    private int $insCode;
    private int $listaId;
    private int $productoId;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(
            ['id' => 1],
            [
                'usu_cedula'   => '9999999999',
                'usu_tipdoc'   => 'CC',
                'usu_password' => bcrypt('testing'),
                'usu_nmbcom'   => 'Vigilante Test',
                'usu_ape1'     => 'Test',
                'usu_ape2'     => 'Test',
                'usu_nmb1'     => 'Vigilante',
                'usu_nmb2'     => 'Test',
                'usu_email'    => 'inventario@example.com',
                'usu_state'    => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        $this->insCode = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Local Test',
            'ins_estado'      => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], 'ins_code');

        // Cada tabla de inventario prefija sus timestamps con su propia sigla
        // (`ipc_created_at`, `li_created_at`, `lia_created_at`): no son las
        // columnas `created_at` de Laravel.
        $this->productoId = DB::table('inv_producto_catalogo')->insertGetId([
            'ipc_ins_code'    => $this->insCode,
            'ipc_nombre'      => 'Linterna',
            'ipc_descripcion' => 'Linterna de mano',
            'ipc_activo'      => true,
            'ipc_stock_actual' => 10,
            'ipc_created_at'  => now(),
            'ipc_updated_at'  => now(),
        ], 'ipc_id');

        $this->listaId = DB::table('inv_lista')->insertGetId([
            'li_ins_code'   => $this->insCode,
            'li_nombre'     => 'Lista de turno',
            'li_activo'     => true,
            'li_created_at' => now(),
            'li_updated_at' => now(),
        ], 'li_id');

        DB::table('inv_lista_item')->insert([
            'lia_lista_id'         => $this->listaId,
            'lia_producto_id'      => $this->productoId,
            'lia_cantidad_default' => 1,
            'lia_activo'           => true,
            'lia_created_at'       => now(),
            'lia_updated_at'       => now(),
        ]);

        $roleId = DB::table('roles')->where('name', 'Vigilante')->value('id');
        DB::table('user_has_roles')->updateOrInsert(
            ['user_id' => 1, 'role_id' => $roleId],
            ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
        );

        DB::table('user_has_institucion')->updateOrInsert(
            ['ui_usu_id' => 1, 'ui_ins_code' => $this->insCode],
            ['ui_state' => 1]
        );

        $this->token = users::find(1)->createToken('inventario-test')->plainTextToken;
    }

    /** @param array<string,mixed> $extra */
    private function recepcion(array $extra = [])
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/inventario/listsave', array_merge([
                'ins_code'  => $this->insCode,
                'list_code' => $this->listaId,
                'latitud'   => '-2.189',
                'longitud'  => '-79.889',
                'productos' => json_encode([[
                    'id_producto' => $this->productoId,
                    'cantidaddf'  => 1,
                    'cantidad'    => 1,
                ]]),
            ], $extra));
    }

    /** @param array<string,mixed> $extra */
    private function baja(array $extra = [])
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/inventario/registrar-baja', array_merge([
                'ins_code'  => $this->insCode,
                'list_code' => $this->listaId,
                'latitud'   => '-2.189',
                'longitud'  => '-79.889',
                'motivo'    => 'Se rompio en la ronda',
                'productos' => json_encode([[
                    'id_producto' => $this->productoId,
                    'cantidad'    => 1,
                ]]),
            ], $extra));
    }

    // ── Compatibilidad con el APK que ya esta instalado ──

    public function test_el_apk_actual_sin_client_uuid_registra_igual_que_antes(): void
    {
        $respuesta = $this->recepcion();

        $respuesta->assertStatus(200);
        $respuesta->assertJsonPath('message', 'Recepción registrada con éxito');
        // Sin client_uuid no hay nada que deduplicar: la clave del contrato
        // viejo es que la respuesta NO traiga la marca de duplicado.
        $this->assertNull($respuesta->json('duplicado'));
        $this->assertSame(1, MovimientoCabecera::count());
        $this->assertNull(MovimientoCabecera::first()->mc_client_uuid);
    }

    public function test_sin_client_uuid_la_segunda_recepcion_sigue_bloqueada(): void
    {
        $this->recepcion()->assertStatus(200);

        // Comportamiento de siempre: con el ciclo abierto no se puede recibir
        // otra vez la misma lista. Esto NO debe cambiar.
        $segunda = $this->recepcion();

        $segunda->assertJsonPath('success', false);
        $this->assertSame(1, MovimientoCabecera::count());
    }

    // ── Idempotencia con client_uuid ──

    public function test_reintento_con_el_mismo_client_uuid_devuelve_el_mismo_id(): void
    {
        $uuid = '11111111-1111-4111-8111-111111111111';

        $primera = $this->recepcion(['client_uuid' => $uuid]);
        $primera->assertStatus(200);
        $this->assertNull($primera->json('duplicado'));

        $segunda = $this->recepcion(['client_uuid' => $uuid]);

        $segunda->assertStatus(200);
        $segunda->assertJsonPath('duplicado', true);
        $this->assertSame($primera->json('id'), $segunda->json('id'));
        $this->assertSame(1, MovimientoCabecera::count());
    }

    public function test_el_reintento_no_choca_contra_su_propia_recepcion_abierta(): void
    {
        // Este es el caso que motivo el cambio. El primer envio deja una
        // recepcion ABIERTA; el reintento, si el chequeo de «ya existe una
        // recepcion» corriera antes de mirar el client_uuid, seria rechazado
        // por la fila que el mismo intento anterior creo. El guardia veria un
        // error y su inventario quedaria a medias.
        $uuid = '22222222-2222-4222-8222-222222222222';

        $this->recepcion(['client_uuid' => $uuid])->assertStatus(200);

        $reintento = $this->recepcion(['client_uuid' => $uuid]);

        $this->assertNull($reintento->json('success'));
        $reintento->assertJsonPath('duplicado', true);
    }

    public function test_el_reintento_no_duplica_los_detalles(): void
    {
        $uuid = '33333333-3333-4333-8333-333333333333';

        $this->recepcion(['client_uuid' => $uuid]);
        $this->recepcion(['client_uuid' => $uuid]);

        $this->assertSame(1, MovimientoDetalle::count());
    }

    public function test_los_detalles_llevan_timestamps(): void
    {
        // Se insertan con insert() masivo, que no llena created_at/updated_at
        // solo: si no se ponen a mano quedan nulos y el detalle no se puede
        // ordenar ni auditar.
        $this->recepcion()->assertStatus(200);

        $detalle = MovimientoDetalle::first();
        $this->assertNotNull($detalle->md_created_at);
        $this->assertNotNull($detalle->md_updated_at);
    }

    public function test_conserva_la_hora_real_del_movimiento_no_la_de_llegada(): void
    {
        $ocurrido = now()->subHours(4)->format('Y-m-d H:i:s');

        $this->recepcion([
            'client_uuid' => '44444444-4444-4444-8444-444444444444',
            'ocurrido_en' => $ocurrido,
        ])->assertStatus(200);

        $mov = MovimientoCabecera::first();
        $this->assertSame($ocurrido, \Carbon\Carbon::parse($mov->mc_fecha)->format('Y-m-d H:i:s'));
        $this->assertNotNull($mov->mc_sincronizado_en);
    }

    public function test_rechaza_un_client_uuid_mal_formado(): void
    {
        $respuesta = $this->recepcion(['client_uuid' => 'no-es-un-uuid']);

        $respuesta->assertJsonPath('success', false);
        $this->assertSame(0, MovimientoCabecera::count());
    }

    public function test_client_uuid_distinto_en_otra_lista_crea_otro_movimiento(): void
    {
        $otraLista = DB::table('inv_lista')->insertGetId([
            'li_ins_code'   => $this->insCode,
            'li_nombre'     => 'Segunda lista',
            'li_activo'     => true,
            'li_created_at' => now(),
            'li_updated_at' => now(),
        ], 'li_id');

        $this->recepcion(['client_uuid' => '55555555-5555-4555-8555-555555555555'])
            ->assertStatus(200);

        $this->recepcion([
            'client_uuid' => '66666666-6666-4666-8666-666666666666',
            'list_code'   => $otraLista,
        ])->assertStatus(200);

        $this->assertSame(2, MovimientoCabecera::count());
    }

    // ── Devolucion ──

    public function test_la_devolucion_repetida_no_repisa_la_fecha(): void
    {
        $id = $this->recepcion()->json('id');
        $ocurrido = now()->subHours(2)->format('Y-m-d H:i:s');

        $primera = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/inventario/finishsave', [
                'ins_code'    => $this->insCode,
                'code_mov'    => $id,
                'latitud'     => '-2.189',
                'longitud'    => '-79.889',
                'ocurrido_en' => $ocurrido,
            ]);

        $primera->assertStatus(200);
        $this->assertNull($primera->json('duplicado'));
        $this->assertSame(
            $ocurrido,
            \Carbon\Carbon::parse(MovimientoCabecera::find($id)->mc_fecha)->format('Y-m-d H:i:s')
        );

        // El reintento no debe mover la fecha a la hora en que llego la red.
        $segunda = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/inventario/finishsave', [
                'ins_code' => $this->insCode,
                'code_mov' => $id,
                'latitud'  => '-2.189',
                'longitud' => '-79.889',
            ]);

        $segunda->assertStatus(200);
        $segunda->assertJsonPath('duplicado', true);
        $this->assertSame(
            $ocurrido,
            \Carbon\Carbon::parse(MovimientoCabecera::find($id)->mc_fecha)->format('Y-m-d H:i:s')
        );
    }

    public function test_cerrado_el_ciclo_se_puede_volver_a_recibir(): void
    {
        $id = $this->recepcion()->json('id');

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/inventario/finishsave', [
                'ins_code' => $this->insCode,
                'code_mov' => $id,
                'latitud'  => '-2.189',
                'longitud' => '-79.889',
            ])->assertStatus(200);

        // Turno siguiente: el mismo guardia recibe la misma lista otra vez.
        $this->recepcion()->assertJsonPath('message', 'Recepción registrada con éxito');
        $this->assertSame(2, MovimientoCabecera::count());
    }

    // ── Baja ──

    public function test_la_baja_sin_client_uuid_funciona_como_antes(): void
    {
        $respuesta = $this->baja();

        $respuesta->assertStatus(200);
        $respuesta->assertJsonPath('message', 'Baja registrada con éxito');
        $this->assertNull($respuesta->json('duplicado'));
        $this->assertSame(1, MovimientoCabecera::count());
    }

    public function test_el_reintento_de_una_baja_no_la_duplica(): void
    {
        $uuid = '77777777-7777-4777-8777-777777777777';

        $primera = $this->baja(['client_uuid' => $uuid]);
        $segunda = $this->baja(['client_uuid' => $uuid]);

        $primera->assertStatus(200);
        $segunda->assertStatus(200);
        $segunda->assertJsonPath('duplicado', true);
        $this->assertSame($primera->json('id'), $segunda->json('id'));
        $this->assertSame(1, MovimientoCabecera::count());
        $this->assertSame(1, MovimientoDetalle::count());
    }

    public function test_dos_bajas_distintas_si_se_registran(): void
    {
        // Un guardia puede dar de baja dos cosas en el mismo turno: la
        // deduplicacion es por client_uuid, no por lista.
        $this->baja(['client_uuid' => '88888888-8888-4888-8888-888888888888']);
        $this->baja(['client_uuid' => '99999999-9999-4999-8999-999999999999']);

        $this->assertSame(2, MovimientoCabecera::count());
    }
}
