<?php

namespace Tests\Unit;

use App\Services\RecuperacionDeClave;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\MobileApp\Models\users;
use Tests\TestCase;

/**
 * Que recuperar la clave no sea la forma de entrar a la cuenta de otro.
 *
 * ⚠️ Lo que habia antes, y esta suite no detectaba: `POST /api/procesar_paswchg`
 * cambiaba la contrasena de cualquier usuario mandando solo su `user_id` -- un
 * entero secuencial -- sin token y sin autenticacion, sobre un endpoint
 * publicado en internet. `solicitud_paswchg` completaba el circuito devolviendo
 * el `user_id` y el token a cambio de una cedula.
 *
 * El primer test de aca es exactamente esa peticion. Antes de este cambio
 * pasaba, y se llevaba la cuenta.
 */
class RecuperacionDeClaveTest extends TestCase
{
    use RefreshDatabase;

    private users $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '0912345678', 'usu_tipdoc' => 'CC',
            'usu_password' => bcrypt('ClaveVieja1'), 'usu_nmbcom' => 'Guardia Uno',
            'usu_ape1' => 'T', 'usu_ape2' => 'T', 'usu_nmb1' => 'T', 'usu_nmb2' => 'T',
            'usu_email' => 'g@e.com', 'usu_state' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->usuario = users::find(1);
    }

    /**
     * Deja un codigo vigente y lo devuelve en claro.
     *
     * El codigo real no se puede leer de la base porque se guarda hasheado --
     * que es justamente lo que se quiere-- asi que el test emite uno de verdad,
     * para que queden bien la caducidad y el contador, y despues pisa el hash
     * con el de un codigo conocido.
     */
    private function codigoVigente(): string
    {
        app(RecuperacionDeClave::class)->emitir($this->usuario);

        $codigo = '12345678';
        $this->usuario->refresh();
        $this->usuario->usu_reset_token = hash('sha256', $codigo);
        $this->usuario->save();

        return $codigo;
    }

    /**
     * EL AGUJERO. Esta peticion es la que tomaba cualquier cuenta.
     */
    public function test_no_se_puede_cambiar_la_clave_solo_con_el_user_id(): void
    {
        $this->postJson('/api/procesar_paswchg', [
            'user_id'   => 1,
            'password'  => 'Nueva1234',
            'password2' => 'Nueva1234',
        ])->assertJsonPath('success', false);

        $this->assertTrue(
            Hash::check('ClaveVieja1', users::find(1)->usu_password),
            'La clave cambio sin codigo: el agujero sigue abierto.'
        );
    }

    public function test_sin_codigo_no_cambia_nada(): void
    {
        $this->postJson('/api/procesar_paswchg', [
            'usu_cedula' => '0912345678',
            'password'   => 'Nueva1234',
            'password2'  => 'Nueva1234',
        ])->assertJsonPath('success', false);

        $this->assertTrue(Hash::check('ClaveVieja1', users::find(1)->usu_password));
    }

    public function test_con_un_codigo_equivocado_no_cambia_nada(): void
    {
        $this->codigoVigente();

        $this->postJson('/api/procesar_paswchg', [
            'usu_cedula' => '0912345678',
            'codigo'     => '00000000',
            'password'   => 'Nueva1234',
            'password2'  => 'Nueva1234',
        ])->assertJsonPath('success', false);

        $this->assertTrue(Hash::check('ClaveVieja1', users::find(1)->usu_password));
    }

    public function test_con_el_codigo_correcto_si_cambia(): void
    {
        $codigo = $this->codigoVigente();

        $this->postJson('/api/procesar_paswchg', [
            'usu_cedula' => '0912345678',
            'codigo'     => $codigo,
            'password'   => 'Nueva1234',
            'password2'  => 'Nueva1234',
        ])->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('Nueva1234', users::find(1)->usu_password));
    }

    public function test_el_codigo_sirve_una_sola_vez(): void
    {
        $codigo = $this->codigoVigente();
        $datos = [
            'usu_cedula' => '0912345678',
            'codigo'     => $codigo,
            'password'   => 'Nueva1234',
            'password2'  => 'Nueva1234',
        ];

        $this->postJson('/api/procesar_paswchg', $datos)->assertJsonPath('success', true);

        // El segundo intento con el mismo codigo, para otra clave distinta.
        $this->postJson('/api/procesar_paswchg', array_merge($datos, [
            'password' => 'Otra12345', 'password2' => 'Otra12345',
        ]))->assertJsonPath('success', false);

        $this->assertTrue(Hash::check('Nueva1234', users::find(1)->usu_password));
    }

    public function test_un_codigo_vencido_no_sirve(): void
    {
        $codigo = $this->codigoVigente();

        $this->usuario->usu_reset_expira = now()->subMinute();
        $this->usuario->save();

        $this->postJson('/api/procesar_paswchg', [
            'usu_cedula' => '0912345678',
            'codigo'     => $codigo,
            'password'   => 'Nueva1234',
            'password2'  => 'Nueva1234',
        ])->assertJsonPath('success', false);

        $this->assertTrue(Hash::check('ClaveVieja1', users::find(1)->usu_password));
    }

    /** Un codigo numerico sin limite de intentos se adivina por fuerza bruta. */
    public function test_a_los_cinco_intentos_el_codigo_se_invalida(): void
    {
        $codigo = $this->codigoVigente();

        for ($i = 0; $i < RecuperacionDeClave::INTENTOS_MAXIMOS; $i++) {
            $this->postJson('/api/procesar_paswchg', [
                'usu_cedula' => '0912345678', 'codigo' => '00000000',
                'password' => 'Nueva1234', 'password2' => 'Nueva1234',
            ]);
        }

        // Ahora ni siquiera el codigo bueno sirve.
        $this->postJson('/api/procesar_paswchg', [
            'usu_cedula' => '0912345678', 'codigo' => $codigo,
            'password' => 'Nueva1234', 'password2' => 'Nueva1234',
        ])->assertJsonPath('success', false);

        $this->assertTrue(Hash::check('ClaveVieja1', users::find(1)->usu_password));
    }

    /**
     * El codigo no puede volver en la respuesta: si vuelve, no prueba nada.
     */
    public function test_la_solicitud_no_devuelve_el_codigo_ni_el_user_id(): void
    {
        $respuesta = $this->postJson('/api/solicitud_paswchg', ['usu_cedula' => '0912345678']);

        $respuesta->assertJsonPath('success', true)
            ->assertJsonMissingPath('token')
            ->assertJsonMissingPath('user_id');
    }

    /**
     * La respuesta no puede servir para averiguar que cedulas estan registradas.
     */
    public function test_una_cedula_que_no_existe_responde_igual(): void
    {
        $registrada = $this->postJson('/api/solicitud_paswchg', ['usu_cedula' => '0912345678']);
        $inventada  = $this->postJson('/api/solicitud_paswchg', ['usu_cedula' => '0000000000']);

        $this->assertSame($registrada->json(), $inventada->json());
    }

    /** En la base nunca queda el codigo legible. */
    public function test_el_codigo_se_guarda_hasheado(): void
    {
        app(RecuperacionDeClave::class)->emitir($this->usuario);

        $guardado = DB::table('users')->where('id', 1)->value('usu_reset_token');

        $this->assertSame(64, strlen($guardado), 'No parece un sha256.');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $guardado);
    }

    public function test_la_clave_nueva_no_puede_ser_la_cedula(): void
    {
        $codigo = $this->codigoVigente();

        $this->postJson('/api/procesar_paswchg', [
            'usu_cedula' => '0912345678',
            'codigo'     => $codigo,
            'password'   => '0912345678',
            'password2'  => '0912345678',
        ])->assertJsonPath('success', false);
    }

    /*
     * ---------------------------------------------------------------
     * El mismo agujero, por la puerta del portal web.
     *
     * `POST /acceso/procesar_cambiopass` tomaba el `user_id` de un campo del
     * formulario y no comprobaba ningun token. Cerrar solo la API habria dejado
     * esta abierta, y es la misma puerta.
     * ---------------------------------------------------------------
     */

    public function test_web_no_se_puede_cambiar_la_clave_mandando_un_user_id(): void
    {
        $this->post('/acceso/procesar_cambiopass', [
            'user_id'   => 1,
            'password'  => 'Nueva1234',
            'password2' => 'Nueva1234',
        ]);

        $this->assertTrue(
            Hash::check('ClaveVieja1', users::find(1)->usu_password),
            'La clave cambio desde el portal sin enlace valido.'
        );
    }

    public function test_web_con_la_sesion_del_enlace_valido_si_cambia(): void
    {
        $this->codigoVigente();

        $this->withSession(['reset_usuario_id' => 1])
            ->post('/acceso/procesar_cambiopass', [
                'password'  => 'Nueva1234',
                'password2' => 'Nueva1234',
            ]);

        $this->assertTrue(Hash::check('Nueva1234', users::find(1)->usu_password));
    }

    /** Un enlace con un codigo inventado no abre el formulario. */
    public function test_web_un_enlace_invalido_no_autoriza_nada(): void
    {
        $this->get('/acceso/cambiar_password/00000000')
            ->assertRedirect('/acceso/login');

        $this->assertNull(session('reset_usuario_id'));
    }

    public function test_web_el_enlace_correcto_abre_el_formulario(): void
    {
        $codigo = $this->codigoVigente();

        $this->get('/acceso/cambiar_password/' . $codigo)->assertOk();

        $this->assertSame(1, session('reset_usuario_id'));
    }
}
