<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Alertas;
use Modules\MobileApp\Models\users;
use Tests\TestCase;

/**
 * Crear una alerta desde el sitio: el boton de panico.
 *
 * **Estaba roto por tres lados a la vez**, y eso es lo que lo hacia invisible:
 *
 *  1. La app no llamaba nunca a `/alert/crear` (`constants.ts` solo declaraba
 *     `/alert/today`), asi que la pantalla de Alertas era de solo lectura.
 *  2. El rol Vigilante no tenia `alertas.crear`: con el boton puesto habria
 *     recibido 403.
 *  3. `AlertaService::asignarASupervisor()` llamaba a `users::instituciones()`,
 *     una relacion **que no existe en ninguno de los dos modelos `users`**.
 *     Eloquent lanzaba BadMethodCallException dentro de la transaccion de
 *     `crearAlerta`, y el controlador lo convertia en **500 «Error al crear
 *     alerta»**. El endpoint no podia funcionar aunque se lo llamara con
 *     permiso.
 *
 * Y una cuarta, que habria dejado la alerta creada pero invisible:
 * `al_fecha` no se escribia y no tiene default, mientras `scopeDelDia()` -- lo
 * que alimenta «Alertas de hoy» -- filtra por esa columna.
 */
class BotonDePanicoTest extends TestCase
{
    use RefreshDatabase;

    private int $insCode;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insCode = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Garita Norte',
            'ins_estado'      => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], 'ins_code');

        $this->token = $this->vigilante();
    }

    /** Un vigilante vinculado al local, con su token. */
    private function vigilante(): string
    {
        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Guardia Uno', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'g@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $rol = DB::table('roles')->where('name', 'Vigilante')->value('id');
        DB::table('user_has_roles')->updateOrInsert(
            ['user_id' => 1, 'role_id' => $rol],
            ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
        );

        DB::table('user_has_institucion')->updateOrInsert(
            ['ui_usu_id' => 1, 'ui_ins_code' => $this->insCode],
            ['ui_state' => 1]
        );

        return users::find(1)->createToken('panico-test')->plainTextToken;
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

    public function test_un_vigilante_puede_crear_una_alerta(): void
    {
        $respuesta = $this->crear();

        $respuesta->assertStatus(201);
        $respuesta->assertJsonPath('success', true);
        $this->assertSame(1, Alertas::count());
    }

    public function test_la_alerta_creada_aparece_en_las_de_hoy(): void
    {
        // Si `al_fecha` queda nula la alerta existe pero no la ve nadie.
        $this->crear()->assertStatus(201);

        $this->assertNotNull(Alertas::first()->al_fecha);
        $this->assertSame(1, Alertas::delDia()->count());

        $hoy = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/alert/today', ['ins' => $this->insCode]);

        $hoy->assertStatus(200);
        $hoy->assertJsonCount(1, 'alerts');
    }

    public function test_sin_supervisor_en_el_local_la_alerta_no_se_pierde(): void
    {
        // asignarASupervisor() devuelve null y la alerta queda pendiente. Antes
        // esta rama reventaba con BadMethodCallException y el endpoint
        // respondia 500.
        $this->crear()->assertStatus(201);

        $alerta = Alertas::first();
        $this->assertSame('pendiente', $alerta->al_estado_alerta);
    }

    public function test_con_supervisor_del_local_la_alerta_queda_en_atencion(): void
    {
        DB::table('users')->updateOrInsert(['id' => 2], [
            'usu_cedula' => '2222222222', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Supervisor Dos', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 's@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $rol = DB::table('roles')->where('name', 'Supervisor')->value('id');
        DB::table('user_has_roles')->insert([
            'user_id' => 2, 'role_id' => $rol,
            'ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1,
        ]);
        DB::table('user_has_institucion')->insert([
            'ui_usu_id' => 2, 'ui_ins_code' => $this->insCode, 'ui_state' => 1,
            'ui_created_at' => now(), 'ui_updated_at' => now(),
        ]);

        $this->crear()->assertStatus(201);

        $this->assertSame('en_atencion', Alertas::first()->al_estado_alerta);
    }

    public function test_un_supervisor_de_OTRO_local_no_recibe_la_alerta(): void
    {
        $otro = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Otro local', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        DB::table('users')->updateOrInsert(['id' => 3], [
            'usu_cedula' => '3333333333', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Supervisor Ajeno', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'sa@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $rol = DB::table('roles')->where('name', 'Supervisor')->value('id');
        DB::table('user_has_roles')->insert([
            'user_id' => 3, 'role_id' => $rol,
            'ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1,
        ]);
        DB::table('user_has_institucion')->insert([
            'ui_usu_id' => 3, 'ui_ins_code' => $otro, 'ui_state' => 1,
            'ui_created_at' => now(), 'ui_updated_at' => now(),
        ]);

        $this->crear()->assertStatus(201);

        // El vinculo es por local: un supervisor de otro sitio no se entera.
        $this->assertSame('pendiente', Alertas::first()->al_estado_alerta);
    }

    public function test_el_doble_toque_con_el_mismo_uuid_no_duplica(): void
    {
        // El boton se aprieta bajo estres y sobre una red mala: si no hubo
        // respuesta, el guardia vuelve a apretar.
        $uuid = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';

        $primera = $this->crear(['client_uuid' => $uuid]);
        $segunda = $this->crear(['client_uuid' => $uuid]);

        $primera->assertStatus(201);
        $primera->assertJsonPath('duplicado', false);

        $segunda->assertStatus(200);
        $segunda->assertJsonPath('duplicado', true);
        $this->assertSame(1, Alertas::count());
    }

    public function test_sin_client_uuid_se_crean_las_dos(): void
    {
        // El APK ya instalado no manda el campo: dos avisos distintos del mismo
        // guardia tienen que entrar los dos.
        $this->crear()->assertStatus(201);
        $this->crear(['observacion' => 'Otro hecho, mas tarde'])->assertStatus(201);

        $this->assertSame(2, Alertas::count());
    }

    public function test_conserva_la_hora_real_del_hecho(): void
    {
        $ocurrido = now()->subHours(2);

        $this->crear([
            'client_uuid' => 'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff',
            'ocurrido_en' => $ocurrido->format('c'),
        ])->assertStatus(201);

        $this->assertSame(
            $ocurrido->format('Y-m-d H:i:s'),
            Alertas::first()->al_fecha->format('Y-m-d H:i:s')
        );
    }

    public function test_una_alerta_sin_gps_se_acepta(): void
    {
        // La app manda 0/0 cuando el dispositivo no entrego ubicacion. Una
        // emergencia no se puede perder porque el GPS tarde o el guardia este
        // bajo techo.
        $this->crear(['lat' => '0', 'lng' => '0'])->assertStatus(201);

        $this->assertSame(1, Alertas::count());
    }

    public function test_una_alerta_sin_motivo_se_rechaza(): void
    {
        $respuesta = $this->crear(['observacion' => '']);

        $respuesta->assertJsonPath('success', false);
        $this->assertSame(0, Alertas::count());
    }
}
