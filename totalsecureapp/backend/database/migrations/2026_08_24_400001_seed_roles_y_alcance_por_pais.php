<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa el modelo de cinco roles y el alcance por pais del Lider Operativo.
 *
 * Roles del sistema:
 *   Vigilante        app movil
 *   Supervisor       observa guardias y turnos, atiende alertas. Ve sus locales
 *   Lider Operativo  da de alta guardias y los asigna. Ve su(s) pais(es)
 *   Administrador    Sistemas: todo, sin filtro
 *   Cliente          portal de solo lectura
 *
 * Los tres primeros y Cliente ya existian. Aqui se crean los dos que faltaban.
 *
 * 'Administrador' es el nombre que las 11 pantallas administrativas ya exigian
 * desde antes; crearlo las habilita sin tocar codigo. 'Lider Operativo' es nuevo
 * y se agrego a App\Support\PerfilPanel.
 *
 * user_has_pais existe porque el alcance del Lider Operativo NO se puede
 * expresar con user_has_institucion: esa es a nivel de local, demasiado granular
 * para alguien que gestiona una operacion entera. Y admite varias filas por
 * usuario porque un lider puede llevar mas de un pais.
 */
return new class extends Migration
{
    private const SECCION_PERSONAL = 20;

    private const PERMISOS = [
        ['usuarios.ver',                 'Ver usuarios',            'Listado del personal'],
        ['usuarios.crear',               'Crear usuario',           'Alta de guardias y personal'],
        ['usuarios.editar',              'Editar usuario',          'Modificar datos del personal'],
        ['usuarios.asignar_rol',         'Asignar rol',             'Definir el perfil de un usuario'],
        ['usuarios.asignar_institucion', 'Asignar local',           'Vincular un usuario a un local'],
        ['usuarios.asignar_puesto',      'Asignar puesto',          'Vincular un usuario a un puesto'],
    ];

    public function up(): void
    {
        $this->crearTablaAlcancePorPais();

        $administrador = $this->rolId('Administrador', 'Departamento de Sistemas: acceso total');
        $lider         = $this->rolId('Lider Operativo', 'Da de alta y asigna personal en su(s) pais(es)');

        $permisos = $this->crearPermisos();

        // El permiso del panel ya existe desde la limpieza del modulo de salud.
        $panel = DB::table('permissions')->where('name', 'admin')->value('id');
        if ($panel) {
            $permisos[] = $panel;
        }

        foreach ([$administrador, $lider] as $rol) {
            foreach ($permisos as $permisoId) {
                DB::table('role_has_permissions')->updateOrInsert(
                    ['permission_id' => $permisoId, 'role_id' => $rol],
                    []
                );
            }
        }

        // El Administrador ademas hereda todo lo operativo de la app y el panel,
        // para poder reproducir cualquier problema que le reporten.
        $operativos = DB::table('permissions')
            ->whereBetween('ps_codigo', [10, 18])
            ->pluck('id');

        foreach ($operativos as $permisoId) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['permission_id' => $permisoId, 'role_id' => $administrador],
                []
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_has_pais');

        DB::table('permissions')
            ->whereIn('name', array_column(self::PERMISOS, 0))
            ->delete();

        DB::table('permission_section')->where('ps_codigo', self::SECCION_PERSONAL)->delete();

        // role_has_permissions se limpia en cascada por la FK.
        DB::table('roles')->whereIn('name', ['Administrador', 'Lider Operativo'])->delete();
    }

    private function crearTablaAlcancePorPais(): void
    {
        if (Schema::hasTable('user_has_pais')) {
            return;
        }

        Schema::create('user_has_pais', function (Blueprint $table) {
            $table->bigIncrements('up_id');
            $table->unsignedBigInteger('up_usu_id');
            $table->unsignedBigInteger('up_pa_id');
            $table->boolean('up_estado')->default(true);
            $table->timestamps();

            $table->foreign('up_pa_id')->references('pa_id')->on('pais')->onDelete('cascade');
            $table->unique(['up_usu_id', 'up_pa_id']);
            $table->index('up_usu_id');
        });
    }

    /** @return int[] */
    private function crearPermisos(): array
    {
        DB::table('permission_section')->updateOrInsert(
            ['ps_codigo' => self::SECCION_PERSONAL],
            [
                'ps_nombre'   => 'Gestión de personal',
                'ps_posicion' => self::SECCION_PERSONAL,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        $ids = [];
        foreach (self::PERMISOS as $pos => [$name, $desc, $sub]) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'ps_codigo'         => self::SECCION_PERSONAL,
                    'pr_descripcion'    => $desc,
                    'pr_subdescripcion' => $sub,
                    'pr_icono'          => 'users',
                    'pr_posicion'       => $pos + 1,
                    'pr_state'          => 1,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]
            );
            $ids[] = DB::table('permissions')->where('name', $name)->value('id');
        }

        return $ids;
    }

    private function rolId(string $nombre, string $descripcion): int
    {
        DB::table('roles')->updateOrInsert(
            ['name' => $nombre],
            ['descripcion' => $descripcion, 'estado' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        return (int) DB::table('roles')->where('name', $nombre)->value('id');
    }
};
