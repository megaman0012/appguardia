<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Secciones y permisos de la app móvil (Fase 6 - RBAC granular).
     * ps_codigo 10-18 reservados para módulos móviles (1-2 los usa el admin web).
     */
    public function up(): void
    {
        $secciones = [
            ['ps_codigo' => 10, 'ps_nombre' => 'Rondas',        'ps_posicion' => 10],
            ['ps_codigo' => 11, 'ps_nombre' => 'Acceso',        'ps_posicion' => 11],
            ['ps_codigo' => 12, 'ps_nombre' => 'Novedades',     'ps_posicion' => 12],
            ['ps_codigo' => 13, 'ps_nombre' => 'Inventario',    'ps_posicion' => 13],
            ['ps_codigo' => 14, 'ps_nombre' => 'Alertas',       'ps_posicion' => 14],
            ['ps_codigo' => 15, 'ps_nombre' => 'Biometría',     'ps_posicion' => 15],
            ['ps_codigo' => 16, 'ps_nombre' => 'Perfil',        'ps_posicion' => 16],
            ['ps_codigo' => 17, 'ps_nombre' => 'Instituciones', 'ps_posicion' => 17],
            ['ps_codigo' => 18, 'ps_nombre' => 'Notificaciones','ps_posicion' => 18],
        ];

        foreach ($secciones as $s) {
            DB::table('permission_section')->updateOrInsert(
                ['ps_codigo' => $s['ps_codigo']],
                $s + ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $permisos = [
            // Rondas
            ['rondas.ver',            10, 'Ver rondas',              'Listado de rondas del día'],
            ['rondas.crear',          10, 'Crear ronda',             'Alta de rondas'],
            ['rondas.gestionar',      10, 'Gestionar ronda',         'Iniciar/finalizar rondas'],
            ['rondas.scannear_qr',    10, 'Escanear QR',             'Lectura de puntos QR'],
            ['rondas.ver_detalle',    10, 'Detalle de ronda',        'Ver detalle de ronda'],
            ['rondas.ver_historial',  10, 'Historial de rondas',     'Consultar historial'],
            // Acceso
            ['acceso.ver',                11, 'Ver accesos',           'Listado de accesos'],
            ['acceso.registrar',          11, 'Registrar acceso',      'Entrada/salida de personas'],
            ['acceso.ver_historial',      11, 'Historial de accesos',  'Consultar historial'],
            ['acceso.registrar_vehiculo', 11, 'Acceso vehicular',      'Registro con vehículo'],
            ['acceso.registrar_visitante',11, 'Acceso visitante',      'Pre-registro de visitantes'],
            // Novedades
            ['novedades.ver',         12, 'Ver novedades',           'Listado de novedades'],
            ['novedades.crear',       12, 'Crear novedad',           'Alta de novedades'],
            ['novedades.ver_detalle', 12, 'Detalle de novedad',      'Ver detalle'],
            // Inventario
            ['inventario.ver',         13, 'Ver inventario',         'Listas por institución'],
            ['inventario.ver_detalle', 13, 'Detalle de lista',       'Ver detalle de lista'],
            ['inventario.registrar',   13, 'Registrar movimiento',   'Movimientos de inventario'],
            ['inventario.finalizar',   13, 'Finalizar inventario',   'Cierre de inventario'],
            // Alertas
            ['alertas.ver',              14, 'Ver alertas',           'Alertas del día'],
            ['alertas.atender',          14, 'Atender alerta',        'Atención/cierre de alertas'],
            ['alertas.crear',            14, 'Crear alerta',          'Alerta manual'],
            ['alertas.ver_historial',    14, 'Historial de alertas',  'Consultar historial'],
            ['alertas.ver_estadisticas', 14, 'Estadísticas',          'Estadísticas de alertas'],
            // Biometría
            ['biometria.marcar',        15, 'Marcar biometría',       'Registro de marcaje'],
            ['biometria.ver_historial', 15, 'Historial biométrico',   'Consultar historial'],
            // Perfil
            ['perfil.ver',   16, 'Ver perfil',    'Datos propios'],
            ['perfil.editar',16, 'Editar perfil', 'Modificar datos propios'],
            // Instituciones
            ['instituciones.seleccionar', 17, 'Seleccionar institución', 'Elegir institución activa'],
            ['instituciones.ver',         17, 'Ver instituciones',       'Datos de la institución'],
            // Notificaciones
            ['notificaciones.ver',      18, 'Ver notificaciones',    'Bandeja de notificaciones'],
            ['notificaciones.registrar',18, 'Registrar token push',  'Alta/baja de tokens'],
        ];

        $ids = [];
        $pos = 0;
        foreach ($permisos as [$name, $sec, $desc, $sub]) {
            $pos += 1;
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'ps_codigo'         => $sec,
                    'pr_descripcion'    => $desc,
                    'pr_subdescripcion' => $sub,
                    'pr_icono'          => 'shield',
                    'pr_posicion'       => $pos,
                    'pr_state'          => 1,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]
            );
            $ids[$name] = DB::table('permissions')->where('name', $name)->value('id');
        }

        // Los roles base los crea DatabaseSeeder, que corre DESPUES de las migraciones:
        // en una BD nueva todavia no existen. Se crean aqui con los mismos valores que
        // usa el seeder (updateOrInsert, asi que el seeder no duplica) para que la
        // asignacion de permisos no se salte en silencio.
        // Mismo orden que DatabaseSeeder::seedRoles() para que los ids coincidan.
        $supervisor = $this->roleId('Supervisor');
        $vigilante = $this->roleId('Vigilante');

        $permVigilante = [
            'rondas.ver', 'rondas.gestionar', 'rondas.scannear_qr', 'rondas.ver_detalle',
            'acceso.ver', 'acceso.registrar', 'acceso.ver_historial',
            'novedades.ver', 'novedades.crear',
            'inventario.ver', 'inventario.ver_detalle', 'inventario.registrar',
            'alertas.ver', 'alertas.atender',
            'biometria.marcar', 'biometria.ver_historial',
            'perfil.ver',
            'instituciones.seleccionar', 'instituciones.ver',
            'notificaciones.ver', 'notificaciones.registrar',
        ];

        foreach ($permVigilante as $name) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['permission_id' => $ids[$name], 'role_id' => $vigilante],
                []
            );
        }

        foreach ($ids as $pid) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['permission_id' => $pid, 'role_id' => $supervisor],
                []
            );
        }
    }

    private function roleId(string $name): int
    {
        DB::table('roles')->updateOrInsert(
            ['name' => $name],
            ['descripcion' => $name, 'estado' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        return (int) DB::table('roles')->where('name', $name)->value('id');
    }

    public function down(): void
    {
        $names = [
            'rondas.ver', 'rondas.crear', 'rondas.gestionar', 'rondas.scannear_qr',
            'rondas.ver_detalle', 'rondas.ver_historial',
            'acceso.ver', 'acceso.registrar', 'acceso.ver_historial',
            'acceso.registrar_vehiculo', 'acceso.registrar_visitante',
            'novedades.ver', 'novedades.crear', 'novedades.ver_detalle',
            'inventario.ver', 'inventario.ver_detalle', 'inventario.registrar', 'inventario.finalizar',
            'alertas.ver', 'alertas.atender', 'alertas.crear', 'alertas.ver_historial', 'alertas.ver_estadisticas',
            'biometria.marcar', 'biometria.ver_historial',
            'perfil.ver', 'perfil.editar',
            'instituciones.seleccionar', 'instituciones.ver',
            'notificaciones.ver', 'notificaciones.registrar',
        ];

        // role_has_permissions limpia en cascada por FK ON DELETE CASCADE
        DB::table('permissions')->whereIn('name', $names)->delete();
        DB::table('permission_section')->whereBetween('ps_codigo', [10, 18])->delete();
    }
};
