<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos del portal cliente (Fase 8).
 *
 * Seccion ps_codigo 19 (1-2 el admin web, 10-18 la app movil). El rol Cliente
 * solo recibe estos permisos de lectura: no hereda ninguno de la app movil, asi
 * que un token del portal no puede escribir en los endpoints de la app.
 */
return new class extends Migration
{
    private const SECCION = 19;

    private array $permisos = [
        ['portal.instituciones', 'Instituciones del portal', 'Instituciones visibles para el cliente'],
        ['portal.resumen',       'Resumen del portal',       'Totales del rango para el tablero'],
        ['portal.biometria',     'Biometria del portal',     'Marcajes del personal'],
        ['portal.rondas',        'Rondas del portal',        'Rondas y puntos recorridos'],
        ['portal.novedades',     'Novedades del portal',     'Bitacora de novedades'],
        ['portal.accesos',       'Accesos del portal',       'Entradas y salidas'],
        ['portal.alertas',       'Alertas del portal',       'Alertas y su atencion'],
    ];

    public function up(): void
    {
        DB::table('permission_section')->updateOrInsert(
            ['ps_codigo' => self::SECCION],
            [
                'ps_codigo'   => self::SECCION,
                'ps_nombre'   => 'Portal Cliente',
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
                    'pr_icono'          => 'chart-bar',
                    'pr_posicion'       => $pos,
                    'pr_state'          => 1,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]
            );
            $ids[] = DB::table('permissions')->where('name', $name)->value('id');
        }

        // Mismo criterio que la migracion de Fase 6: el rol se crea aqui si no
        // existe, porque los seeders corren despues de las migraciones y en una BD
        // nueva la asignacion se saltaria en silencio.
        $clienteId = $this->roleId('Cliente', 'Cliente del portal (solo lectura)');

        foreach ($ids as $permissionId) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['permission_id' => $permissionId, 'role_id' => $clienteId],
                []
            );
        }
    }

    public function down(): void
    {
        // role_has_permissions se limpia en cascada por la FK.
        DB::table('permissions')
            ->whereIn('name', array_column($this->permisos, 0))
            ->delete();

        DB::table('permission_section')->where('ps_codigo', self::SECCION)->delete();
        DB::table('roles')->where('name', 'Cliente')->delete();
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
