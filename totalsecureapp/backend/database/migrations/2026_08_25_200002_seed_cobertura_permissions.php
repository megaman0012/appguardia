<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos de cobertura de turnos (seccion 21).
 *
 * Van al rol Vigilante: es el guardia quien mira los turnos disponibles y se
 * postula. La confirmacion la hace el supervisor desde el panel, que no pasa por
 * estos permisos sino por PerfilPanel.
 *
 * El rol se crea aqui si no existe, por el mismo motivo que en las migraciones
 * anteriores: los seeders corren despues de las migraciones y en una base nueva
 * la asignacion se saltaria en silencio.
 */
return new class extends Migration
{
    private const SECCION = 21;

    private array $permisos = [
        ['vacantes.ver',      'Turnos disponibles',  'Ver los turnos que quedaron sin cubrir'],
        ['vacantes.postular', 'Postularse a turnos', 'Ofrecerse para cubrir un turno'],
    ];

    public function up(): void
    {
        DB::table('permission_section')->updateOrInsert(
            ['ps_codigo' => self::SECCION],
            [
                'ps_codigo'   => self::SECCION,
                'ps_nombre'   => 'Cobertura de turnos',
                'ps_posicion' => self::SECCION,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        $ids = [];
        $pos = 0;
        foreach ($this->permisos as [$name, $desc, $sub]) {
            $pos += 1;
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'ps_codigo'         => self::SECCION,
                    'pr_descripcion'    => $desc,
                    'pr_subdescripcion' => $sub,
                    'pr_icono'          => 'calendar',
                    'pr_posicion'       => $pos,
                    'pr_state'          => 1,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]
            );
            $ids[] = DB::table('permissions')->where('name', $name)->value('id');
        }

        $vigilanteId = $this->roleId('Vigilante', 'Guardia de seguridad');

        foreach ($ids as $permissionId) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['permission_id' => $permissionId, 'role_id' => $vigilanteId],
                []
            );
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', array_column($this->permisos, 0))
            ->delete();

        DB::table('permission_section')->where('ps_codigo', self::SECCION)->delete();
    }

    private function roleId(string $name, string $descripcion): int
    {
        DB::table('roles')->updateOrInsert(
            ['name' => $name],
            ['descripcion' => $descripcion, 'estado' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        return (int) DB::table('roles')->where('name', $name)->value('id');
    }
};
