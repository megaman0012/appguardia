<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina los restos del sistema de salud del que se migro este backend.
 *
 * El backend viene de coredt360 / HagpAsist, un sistema hospitalario, y arrastro
 * el modulo Formularios (Epicrisis = formulario 006 del MSP, Referencia = 053) y
 * la pantalla de Personas. Nada de eso pertenece a un sistema de guardias, y
 * ademas no funcionaba: las consultas apuntan a tablas de un HIS (capbas, epiman,
 * ingresos, maedia...) que no existen aqui, sobre una conexion 'hagphosv' que no
 * esta definida. Buscar un paciente respondia
 * "Database connection [hagphosv] not configured".
 *
 * OJO con el orden: el JS de seleccion de perfil hace
 *     location.href = urlbase + '/' + data.link
 * donde data.link es el NOMBRE del primer permiso del rol. Ese primer permiso era
 * 'administracion/persona.index'. Si se borrara sin reemplazo, el rol quedaria sin
 * permisos web y el login responderia "No tiene los permisos necesarios", dejando
 * el panel inaccesible. Por eso aqui se crea primero el permiso 'admin' (que
 * apunta al panel Filament) y recien despues se borran los viejos.
 *
 * Efecto secundario buscado: al perfil Vigilante no se le asigna permiso web. Un
 * vigilante usa la app movil, no el panel; si selecciona ese perfil en la web
 * ahora recibe "No tiene los permisos necesarios" en vez de aterrizar en una
 * pantalla que no le sirve.
 *
 * El down() restaura la estructura y los permisos, pero NO el contenido de la
 * tabla 'persona' ni de los catalogos: eso solo esta en el respaldo previo.
 */
return new class extends Migration
{
    private const SECCION_PANEL = 3;

    /** Secciones del sistema de salud que se eliminan. */
    private const SECCIONES_LEGACY = [1, 2];

    private const PERMISOS_LEGACY = [
        'administracion/persona.index',
        'formularios/epicrisis.index',
        'formularios/referencia.index',
    ];

    /** Tablas del dominio de salud, sin ningun uso en el sistema de guardias. */
    private const TABLAS_LEGACY = [
        'persona',
        'tipo_documento',
        'tipo_genero',
        'tipo_pais',
        'tipo_especialidad',
        'tipo_servicio',
        'referencia_motivo',
    ];

    /** Roles que pueden entrar al panel (ver canAccessFilament). */
    private const ROLES_PANEL = ['Administrador', 'Administrador General', 'Supervisor'];

    public function up(): void
    {
        $permisoPanel = $this->crearPermisoDelPanel();
        $this->asignarPanelALosRolesQueCorresponden($permisoPanel);
        $this->borrarPermisosLegacy();
        $this->borrarTablasLegacy();
    }

    public function down(): void
    {
        $this->recrearTablasLegacy();
        $this->recrearPermisosLegacy();

        DB::table('permissions')->where('name', 'admin')->delete();
        DB::table('permission_section')->where('ps_codigo', self::SECCION_PANEL)->delete();
    }

    // ── up ──

    private function crearPermisoDelPanel(): int
    {
        DB::table('permission_section')->updateOrInsert(
            ['ps_codigo' => self::SECCION_PANEL],
            [
                'ps_nombre'   => 'Panel',
                'ps_posicion' => 1,
                'ps_icono'    => 'fa-shield-alt',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        // El nombre del permiso es la ruta a la que redirige el login web.
        DB::table('permissions')->updateOrInsert(
            ['name' => 'admin'],
            [
                'ps_codigo'         => self::SECCION_PANEL,
                'pr_descripcion'    => 'Panel de administracion',
                'pr_subdescripcion' => 'Rondas, accesos, alertas, novedades e inventario',
                'pr_icono'          => 'fa-shield-alt',
                'pr_posicion'       => 1,
                'pr_state'          => 1,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        return (int) DB::table('permissions')->where('name', 'admin')->value('id');
    }

    private function asignarPanelALosRolesQueCorresponden(int $permisoId): void
    {
        $roles = DB::table('roles')->whereIn('name', self::ROLES_PANEL)->pluck('id');

        foreach ($roles as $roleId) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['permission_id' => $permisoId, 'role_id' => $roleId],
                []
            );
        }
    }

    private function borrarPermisosLegacy(): void
    {
        // role_has_permissions se limpia en cascada por la FK.
        DB::table('permissions')->whereIn('name', self::PERMISOS_LEGACY)->delete();
        DB::table('permission_section')->whereIn('ps_codigo', self::SECCIONES_LEGACY)->delete();
    }

    private function borrarTablasLegacy(): void
    {
        foreach (self::TABLAS_LEGACY as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }

    // ── down ──

    private function recrearTablasLegacy(): void
    {
        if (!Schema::hasTable('persona')) {
            Schema::create('persona', function (Blueprint $table) {
                $table->bigIncrements('pt_code');
                $table->string('pt_documento')->nullable();
                $table->string('pt_tip_doc')->nullable();
                $table->string('pt_nmb_comp')->nullable();
                $table->string('pt_ape1')->nullable();
                $table->string('pt_ape2')->nullable();
                $table->string('pt_nmb1')->nullable();
                $table->string('pt_nmb2')->nullable();
                $table->date('pt_fch_nac')->nullable();
                $table->string('pt_pais')->nullable();
                $table->string('pt_provincia')->nullable();
                $table->string('pt_ciudad')->nullable();
                $table->string('pt_parroquia')->nullable();
                $table->string('pt_direccion')->nullable();
                $table->integer('pt_estado')->default(1);
                $table->timestamps();
            });
        }

        $catalogos = [
            'tipo_documento'    => ['td_code', 'td_sigla', 'td_descripcion', 'td_estado'],
            'tipo_genero'       => ['tg_code', 'tg_sigla', 'tg_descripcion', 'tg_estado'],
            'tipo_pais'         => ['tp_code', 'tp_sigla', 'tp_descripcion', 'tp_estado'],
            'tipo_especialidad' => ['te_code', 'te_sigla', 'te_descripcion', 'te_estado'],
            'tipo_servicio'     => ['ts_code', 'ts_sigla', 'ts_descripcion', 'ts_estado'],
        ];

        foreach ($catalogos as $tabla => $columnas) {
            if (Schema::hasTable($tabla)) {
                continue;
            }
            Schema::create($tabla, function (Blueprint $table) use ($columnas) {
                [$code, $sigla, $descripcion, $estado] = $columnas;
                $table->bigIncrements($code);
                $table->string($sigla)->nullable();
                $table->string($descripcion)->nullable();
                $table->integer($estado)->default(1);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('referencia_motivo')) {
            Schema::create('referencia_motivo', function (Blueprint $table) {
                $table->bigIncrements('rm_code');
                $table->string('rm_motivo')->nullable();
                $table->integer('rm_estado')->default(1);
                $table->timestamps();
            });
        }
    }

    private function recrearPermisosLegacy(): void
    {
        $secciones = [
            ['ps_codigo' => 1, 'ps_nombre' => 'Administración', 'ps_posicion' => 1, 'ps_icono' => 'fa-user-cog'],
            ['ps_codigo' => 2, 'ps_nombre' => 'Formularios',    'ps_posicion' => 2, 'ps_icono' => 'fa-file-alt'],
        ];

        foreach ($secciones as $seccion) {
            DB::table('permission_section')->updateOrInsert(
                ['ps_codigo' => $seccion['ps_codigo']],
                $seccion + ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $permisos = [
            ['administracion/persona.index', 1, 'Personas',   'Registro de personas'],
            ['formularios/epicrisis.index',  2, 'Epicrisis',  'Formulario 006'],
            ['formularios/referencia.index', 2, 'Referencia', 'Formulario 053'],
        ];

        $supervisor = DB::table('roles')->where('name', 'Supervisor')->value('id');

        foreach ($permisos as $pos => [$name, $seccion, $desc, $sub]) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'ps_codigo'         => $seccion,
                    'pr_descripcion'    => $desc,
                    'pr_subdescripcion' => $sub,
                    'pr_icono'          => 'fa-file',
                    'pr_posicion'       => $pos + 1,
                    'pr_state'          => 1,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]
            );

            if ($supervisor) {
                DB::table('role_has_permissions')->updateOrInsert(
                    [
                        'permission_id' => DB::table('permissions')->where('name', $name)->value('id'),
                        'role_id'       => $supervisor,
                    ],
                    []
                );
            }
        }
    }
};
