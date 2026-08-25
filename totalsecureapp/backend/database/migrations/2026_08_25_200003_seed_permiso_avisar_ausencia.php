<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso para que el guardia avise con tiempo que no va a cubrir su turno.
 *
 * Va en la seccion 21 (cobertura de turnos), junto a ver y postularse.
 */
return new class extends Migration
{
    private const SECCION = 21;
    private const PERMISO = 'vacantes.avisar_ausencia';

    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => self::PERMISO],
            [
                'ps_codigo'         => self::SECCION,
                'pr_descripcion'    => 'Avisar ausencia',
                'pr_subdescripcion' => 'Reportar con tiempo que no podra cubrir un turno',
                'pr_icono'          => 'calendar',
                'pr_posicion'       => 3,
                'pr_state'          => 1,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        $permisoId = DB::table('permissions')->where('name', self::PERMISO)->value('id');
        $vigilanteId = DB::table('roles')->where('name', 'Vigilante')->value('id');

        if ($permisoId && $vigilanteId) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['permission_id' => $permisoId, 'role_id' => $vigilanteId],
                []
            );
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', self::PERMISO)->delete();
    }
};
