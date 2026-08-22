<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Modules\MobileApp\Models\users;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

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
    }

    // ── Helpers ──

    private function userConRol(string $rol): users
    {
        $user = users::find(1);
        $roleId = DB::table('roles')->where('name', $rol)->value('id');
        DB::table('user_has_roles')->updateOrInsert(
            ['user_id' => 1, 'role_id' => $roleId],
            ['ru_code' => DB::table('user_has_roles')->max('ru_code') + 1]
        );
        return $user;
    }

    private function tokenPara(users $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function crearAlertaPayload(): array
    {
        return [
            'ins_code'     => $this->insCode,
            'al_tipo'      => 'intruso',
            'al_descripcion' => 'Alerta de prueba',
        ];
    }

    // ── Seed de permisos ──

    /** @test */
    public function seed_asigna_21_permisos_a_vigilante_y_31_a_supervisor()
    {
        $vigilanteId = DB::table('roles')->where('name', 'Vigilante')->value('id');
        $supervisorId = DB::table('roles')->where('name', 'Supervisor')->value('id');

        $vig = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_id', $vigilanteId)
            ->whereBetween('ps_codigo', [10, 18])
            ->count();
        $sup = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_id', $supervisorId)
            ->whereBetween('ps_codigo', [10, 18])
            ->count();

        $this->assertEquals(21, $vig);
        $this->assertEquals(31, $sup);
    }

    /** @test */
    public function can_verifica_permiso_por_rol()
    {
        $vigilante = $this->userConRol('Vigilante');
        $this->assertTrue($vigilante->can('acceso.registrar'));
        $this->assertFalse($vigilante->can('alertas.crear'));

        $supervisor = $this->userConRol('Supervisor');
        $this->assertTrue($supervisor->can('alertas.crear'));
        $this->assertTrue($supervisor->can('inventario.finalizar'));
    }

    // ── Middleware en rutas ──

    /** @test */
    public function sin_token_responde_401()
    {
        $response = $this->postJson('/api/alert/crear', $this->crearAlertaPayload());
        $response->assertStatus(401);
    }

    /** @test */
    public function vigilante_no_puede_crear_alerta_responde_403()
    {
        $user = $this->userConRol('Vigilante');
        $token = $this->tokenPara($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/alert/crear', $this->crearAlertaPayload());

        $response->assertStatus(403);
        $response->assertJsonPath('required_permission', 'alertas.crear');
    }

    /** @test */
    public function supervisor_puede_crear_alerta_no_recibe_403()
    {
        $user = $this->userConRol('Supervisor');
        $token = $this->tokenPara($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/alert/crear', $this->crearAlertaPayload());

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    /** @test */
    public function vigilante_puede_listar_accesos()
    {
        $user = $this->userConRol('Vigilante');
        $token = $this->tokenPara($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/accesosbyinst', [
                'ins_code' => $this->insCode,
                'date'     => now()->toDateString(),
            ]);

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    // ── PerfilController ──

    /** @test */
    public function seleccionar_perfil_retorna_roles_del_usuario()
    {
        $user = $this->userConRol('Vigilante');
        $token = $this->tokenPara($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/seleccionar_perfil');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'perfiles');
        $response->assertJsonPath('perfiles.0.nombre', 'Vigilante');
    }

    /** @test */
    public function procesar_perfil_retorna_permisos_del_rol()
    {
        $user = $this->userConRol('Vigilante');
        $token = $this->tokenPara($user);
        $roleId = DB::table('roles')->where('name', 'Vigilante')->value('id');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/procesar_perfil', ['id' => $roleId]);

        $response->assertStatus(200);
        $permisos = $response->json('permisos');
        $this->assertCount(21, $permisos);
        $this->assertContains('acceso.registrar', $permisos);
        $this->assertNotContains('alertas.crear', $permisos);
    }

    /** @test */
    public function procesar_perfil_de_otro_usuario_responde_403()
    {
        $user = $this->userConRol('Vigilante');
        $token = $this->tokenPara($user);
        $supervisorId = DB::table('roles')->where('name', 'Supervisor')->value('id');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/procesar_perfil', ['id' => $supervisorId]);

        $response->assertStatus(403);
    }
}
