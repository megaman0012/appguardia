<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rol Consola: la central que atiende 24/7.
 *
 * Una falta a las tres de la mañana no espera a que el Lider Operativo
 * despierte. La Consola es quien busca al guardia disponible y confirma la
 * cobertura a cualquier hora.
 *
 * Recibe el permiso del panel (seccion 3) y nada mas: no configura el sistema ni
 * da de alta personal. Lo que puede hacer lo resuelve PerfilPanel, no una lista
 * de permisos granulares.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['name' => 'Consola'],
            [
                'descripcion' => 'Central de operaciones 24/7',
                'estado'      => 1,
                'visible'     => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        $rolId = (int) DB::table('roles')->where('name', 'Consola')->value('id');
        $permisoPanel = DB::table('permissions')->where('name', 'admin')->value('id');

        if ($rolId && $permisoPanel) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['permission_id' => $permisoPanel, 'role_id' => $rolId],
                []
            );
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'Consola')->delete();
    }
};
