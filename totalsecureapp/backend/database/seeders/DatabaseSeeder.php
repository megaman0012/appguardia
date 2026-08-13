<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\MobileApp\Models\users;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->seedParametros();
        $this->seedRoles();
        $this->seedTestUser();
        $this->seedWebAccess();
        $this->seedCatalogTables();
        $this->seedDemoData();
    }

    private function seedParametros()
    {
        DB::table('parametros')->updateOrInsert(
            ['pr_descripcion' => 'access'],
            ['pr_value' => env('ACCESS_PARAM_VALUE', 'TS-2026-LOCAL')]
        );
    }

    private function seedRoles()
    {
        $roles = ['Supervisor', 'Vigilante'];
        foreach ($roles as $name) {
            DB::table('roles')->updateOrInsert(
                ['name' => $name],
                ['descripcion' => $name, 'estado' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function seedTestUser()
    {
        $user = users::firstOrCreate(
            ['usu_cedula' => '1234567890'],
            [
                'usu_tipdoc' => 'C',
                'usu_password' => '123456',
                'usu_nmbcom' => 'Guardia de Prueba',
                'usu_ape1' => 'Prueba',
                'usu_ape2' => 'TS',
                'usu_nmb1' => 'Guardia',
                'usu_nmb2' => 'Sistema',
                'usu_email' => 'guardia@totalsecure.local',
                'usu_state' => 1,
            ]
        );

        $vigilante = DB::table('roles')->where('name', 'Vigilante')->first();

        DB::table('user_has_roles')->updateOrInsert(
            ['user_id' => $user->id, 'role_id' => $vigilante->id],
            []
        );

        DB::table('user_has_gestions')->updateOrInsert(
            ['ug_user_id' => $user->id, 'ug_finish' => false],
            [
                'ug_ingreso' => now(),
                'ug_state' => 1,
                'ug_created_user' => $user->id,
                'ug_created_at' => now(),
                'ug_updated_at' => now(),
            ]
        );
    }

    private function seedWebAccess()
    {
        $sections = [
            ['ps_codigo' => 1, 'ps_nombre' => 'Administración', 'ps_posicion' => 1, 'ps_icono' => 'fa-user-cog'],
            ['ps_codigo' => 2, 'ps_nombre' => 'Formularios', 'ps_posicion' => 2, 'ps_icono' => 'fa-file-alt'],
        ];
        foreach ($sections as $section) {
            DB::table('permission_section')->updateOrInsert(
                ['ps_codigo' => $section['ps_codigo']],
                [
                    'ps_nombre' => $section['ps_nombre'],
                    'ps_posicion' => $section['ps_posicion'],
                    'ps_icono' => $section['ps_icono'],
                ]
            );
        }

        $permissions = [
            [
                'name' => 'administracion/persona.index',
                'ps_codigo' => 1,
                'pr_descripcion' => 'Personas',
                'pr_subdescripcion' => 'Gestión de personas',
                'pr_icono' => 'fas fa-users',
                'pr_posicion' => 1.1,
                'pr_state' => 1,
            ],
            [
                'name' => 'formularios/epicrisis.index',
                'ps_codigo' => 2,
                'pr_descripcion' => 'Epicrisis',
                'pr_subdescripcion' => 'Formulario 006',
                'pr_icono' => 'fas fa-file-medical',
                'pr_posicion' => 2.1,
                'pr_state' => 1,
            ],
            [
                'name' => 'formularios/referencia.index',
                'ps_codigo' => 2,
                'pr_descripcion' => 'Referencia',
                'pr_subdescripcion' => 'Formulario 053',
                'pr_icono' => 'fas fa-file-export',
                'pr_posicion' => 2.2,
                'pr_state' => 1,
            ],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission['name']],
                $permission
            );
        }

        $vigilante = DB::table('roles')->where('name', 'Vigilante')->first();
        $supervisor = DB::table('roles')->where('name', 'Supervisor')->first();

        $forSupervisor = ['administracion/persona.index', 'formularios/epicrisis.index', 'formularios/referencia.index'];
        $forVigilante = ['administracion/persona.index'];

        foreach (DB::table('permissions')->get() as $permission) {
            if (in_array($permission->name, $forSupervisor)) {
                DB::table('role_has_permissions')->updateOrInsert(
                    ['permission_id' => $permission->id, 'role_id' => $supervisor->id],
                    []
                );
            }
            if (in_array($permission->name, $forVigilante)) {
                DB::table('role_has_permissions')->updateOrInsert(
                    ['permission_id' => $permission->id, 'role_id' => $vigilante->id],
                    []
                );
            }
        }

        $user = users::where('usu_cedula', '1234567890')->first();
        if ($user && ! DB::table('user_has_roles')->where('user_id', $user->id)->where('role_id', $supervisor->id)->exists()) {
            DB::table('user_has_roles')->insert([
                'user_id' => $user->id,
                'role_id' => $supervisor->id,
            ]);
        }
    }

    private function seedCatalogTables()
    {
        $documentos = [
            ['td_code' => 1, 'td_sigla' => 'C', 'td_descripcion' => 'Cédula de Identidad', 'td_estado' => 1],
            ['td_code' => 2, 'td_sigla' => 'R', 'td_descripcion' => 'RUC', 'td_estado' => 1],
            ['td_code' => 3, 'td_sigla' => 'P', 'td_descripcion' => 'Pasaporte', 'td_estado' => 1],
        ];
        foreach ($documentos as $row) {
            DB::table('tipo_documento')->updateOrInsert(['td_code' => $row['td_code']], $row);
        }

        $generos = [
            ['tg_code' => 1, 'tg_sigla' => 'M', 'tg_descripcion' => 'Masculino', 'tg_estado' => 1],
            ['tg_code' => 2, 'tg_sigla' => 'F', 'tg_descripcion' => 'Femenino', 'tg_estado' => 1],
        ];
        foreach ($generos as $row) {
            DB::table('tipo_genero')->updateOrInsert(['tg_code' => $row['tg_code']], $row);
        }

        $especialidades = [
            ['te_code' => 1, 'te_descripcion' => 'Medicina General', 'te_estado' => 1],
            ['te_code' => 2, 'te_descripcion' => 'Traumatología', 'te_estado' => 1],
        ];
        foreach ($especialidades as $row) {
            DB::table('tipo_especialidad')->updateOrInsert(['te_code' => $row['te_code']], $row);
        }

        $servicios = [
            ['ts_code' => 1, 'tg_code' => 'EMER', 'tg_descripcion' => 'Emergencia', 'tg_estado' => 1],
            ['ts_code' => 2, 'tg_code' => 'HOS', 'tg_descripcion' => 'Hospitalización', 'tg_estado' => 1],
        ];
        foreach ($servicios as $row) {
            DB::table('tipo_servicio')->updateOrInsert(['ts_code' => $row['ts_code']], $row);
        }

        $motivos = [
            ['rm_code' => 1, 'rm_motivo' => 'Derivación a especialista', 'rm_estado' => 1],
            ['rm_code' => 2, 'rm_motivo' => 'Hospitalización', 'rm_estado' => 1],
        ];
        foreach ($motivos as $row) {
            DB::table('referencia_motivo')->updateOrInsert(['rm_code' => $row['rm_code']], $row);
        }

        DB::table('persona')->updateOrInsert(
            ['pt_documento' => '1234567890'],
            [
                'pt_tip_doc' => '1',
                'pt_nmb_comp' => 'Guardia de Prueba',
                'pt_ape1' => 'Prueba',
                'pt_ape2' => 'TS',
                'pt_nmb1' => 'Guardia',
                'pt_nmb2' => 'Sistema',
                'pt_fch_nac' => '1990-01-01',
                'pt_pais' => 'Ecuador',
                'pt_provincia' => 'Guayas',
                'pt_ciudad' => 'Guayaquil',
                'pt_parroquia' => 'Centro',
                'pt_direccion' => 'Av. Principal 100',
                'pt_estado' => 1,
            ]
        );
    }

    private function seedDemoData()
    {
        $user = users::where('usu_cedula', '1234567890')->first();

        $insId = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'INSTITUCIÓN DEMO S.A.',
            'ins_razon_social' => 'Institución Demo S.A.',
            'ins_direccion' => 'Av. Principal 100, Guayaquil',
            'ins_ciudad' => 'Guayaquil',
            'ins_telefono' => '04-0000000',
            'ins_email' => 'demo@totalsecure.local',
            'ins_tipo' => 'Cliente',
            'ins_estado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'ins_code');

        DB::table('user_has_institucion')->updateOrInsert(
            ['ui_usu_id' => $user->id, 'ui_ins_code' => $insId],
            ['ui_state' => 1, 'ui_created_at' => now(), 'ui_updated_at' => now()]
        );

        DB::table('institucion_marcadores')->insert([
            [
                'im_ins_code' => $insId,
                'im_numero' => 1,
                'im_tipo' => 'QR',
                'im_descripcion' => 'Punto de control 1 - Ingreso',
                'im_lat' => '-2.1890',
                'im_lng' => '-79.8890',
                'im_estado' => true,
                'im_created_at' => now(),
                'im_updated_at' => now(),
            ],
            [
                'im_ins_code' => $insId,
                'im_numero' => 2,
                'im_tipo' => 'QR',
                'im_descripcion' => 'Punto de control 2 - Bodega',
                'im_lat' => '-2.1900',
                'im_lng' => '-79.8900',
                'im_estado' => true,
                'im_created_at' => now(),
                'im_updated_at' => now(),
            ],
        ]);

        $p1 = DB::table('inv_productos')->insertGetId([
            'pr_nombre' => 'Extintor',
            'pr_descripcion' => 'Extintor PQS 10 lb',
            'pr_especificacion' => 'Polvo químico seco',
            'pr_stock_actual' => 10,
            'pr_estado' => 1,
            'pr_created_at' => now(),
            'pr_updated_at' => now(),
        ], 'pr_id');
        $p2 = DB::table('inv_productos')->insertGetId([
            'pr_nombre' => 'Botiquín',
            'pr_descripcion' => 'Botiquín primeros auxilios',
            'pr_especificacion' => 'Completo',
            'pr_stock_actual' => 5,
            'pr_estado' => 1,
            'pr_created_at' => now(),
            'pr_updated_at' => now(),
        ], 'pr_id');

        $lpId = DB::table('inv_listas_productos')->insertGetId([
            'lp_ins_code' => $insId,
            'lp_nombre' => 'Checklist Bodega',
            'lp_descripcion' => 'Revisión mensual de bodega',
            'lp_estado' => 1,
            'lp_created_at' => now(),
            'lp_updated_at' => now(),
        ], 'lp_id');

        DB::table('inv_lista_producto_items')->insert([
            ['lpi_lp_id' => $lpId, 'lpi_pr_id' => $p1, 'lpi_cantidad' => 1, 'lpi_estado' => 1, 'lpi_created_at' => now(), 'lpi_updated_at' => now()],
            ['lpi_lp_id' => $lpId, 'lpi_pr_id' => $p2, 'lpi_cantidad' => 2, 'lpi_estado' => 1, 'lpi_created_at' => now(), 'lpi_updated_at' => now()],
        ]);
    }
}
