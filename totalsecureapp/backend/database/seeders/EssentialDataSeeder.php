<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos base compartidos por desarrollo y produccion.
 *
 * Extraido de DatabaseSeeder (que ademas crea el usuario y la institucion de
 * demo) para que ProductionSeeder pueda sembrar solo lo esencial sin arrastrar
 * datos de mentira. El contenido de los dos metodos es el mismo de antes.
 *
 * Idempotente: usa updateOrInsert en todo.
 */
class EssentialDataSeeder extends Seeder
{
    public function run()
    {
        $this->seedWebAccess();
        $this->seedCatalogTables();
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

    }

}
