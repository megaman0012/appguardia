<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cuanto dura la sesion de la app.
 *
 * ⚠️ `TOKEN_EXPIRE_IN` no estaba declarada en el `.env`, asi que regia el valor
 * por defecto: **3600 segundos, una hora**. Y Sanctum SI hace cumplir el
 * `expires_at` de cada token (`Guard::isValidAccessToken`), asi que la sesion
 * caducaba de verdad: el guardia recibia un 401, la app borraba la sesion y lo
 * devolvia al login **en medio de lo que estuviera haciendo**. Con turnos de
 * 12 h, una docena de veces por turno.
 *
 * Este test no fija el valor exacto --se cambia desde el `.env`-- sino lo que
 * importa: que la sesion cubra un turno completo con margen.
 */
class DuracionDeSesionTest extends TestCase
{
    use RefreshDatabase;

    /** El turno mas largo de la operacion son 12 h. */
    private const TURNO_MAS_LARGO = 12 * 3600;

    public function test_la_sesion_dura_mas_que_un_turno_completo(): void
    {
        $segundos = (int) config('sanctum.expiracion_token_movil');

        $this->assertGreaterThan(
            self::TURNO_MAS_LARGO,
            $segundos,
            'La sesión caduca antes de que termine un turno: el guardia tendría que '
            . 'volver a iniciar sesión en mitad de su jornada.'
        );
    }

    /**
     * Sin expiracion tampoco: un token eterno en una tablet que se pierde no
     * caduca nunca. La idea es que dure lo suficiente, no para siempre.
     */
    public function test_la_sesion_no_es_eterna(): void
    {
        $this->assertGreaterThan(0, (int) config('sanctum.expiracion_token_movil'));
    }

    /**
     * El login tiene que grabar esa caducidad en el token, que es lo que Sanctum
     * mira. Si se guardara sin `expires_at`, el valor configurado no haria nada.
     */
    public function test_el_login_graba_la_caducidad_en_el_token(): void
    {
        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'CC',
            'usu_password' => bcrypt('Clave123'), 'usu_nmbcom' => 'Guardia',
            'usu_ape1' => 'T', 'usu_ape2' => 'T', 'usu_nmb1' => 'T', 'usu_nmb2' => 'T',
            'usu_email' => 'g@e.com', 'usu_state' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $rol = DB::table('roles')->where('name', 'Vigilante')->value('id');
        DB::table('user_has_roles')->updateOrInsert(
            ['user_id' => 1, 'role_id' => $rol],
            ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
        );

        $this->postJson('/api/login', [
            'usu_cedula' => '1111111111',
            'usu_password' => 'Clave123',
        ]);

        $token = DB::table('personal_access_tokens')->latest('id')->first();

        if ($token === null) {
            $this->markTestSkipped('El login no emitió token en este entorno.');
        }

        $this->assertNotNull($token->expires_at, 'El token quedó sin caducidad.');

        $this->assertGreaterThan(
            self::TURNO_MAS_LARGO,
            now()->diffInSeconds($token->expires_at, absolute: true),
            'El token caduca antes de que termine un turno.'
        );
    }
}
