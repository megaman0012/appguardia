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
        $this->asignarSupervisorAlUsuarioDemo();
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

    /**
     * El usuario demo tambien recibe el rol Supervisor, para poder entrar al
     * panel Filament. Vive aqui porque en produccion no existe ese usuario.
     */
    private function asignarSupervisorAlUsuarioDemo(): void
    {
        $user = users::where('usu_cedula', '1234567890')->first();
        $supervisor = DB::table('roles')->where('name', 'Supervisor')->first();

        if (!$user || !$supervisor) {
            return;
        }

        DB::table('user_has_roles')->updateOrInsert(
            ['user_id' => $user->id, 'role_id' => $supervisor->id],
            ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
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

        // Inventario: se siembra el juego de tablas de FASE1
        // (inv_producto_catalogo / inv_lista / inv_lista_item /
        // inv_movimiento_cabecera / inv_movimiento_detalle), que es el que usan
        // la app movil Y el panel.
        //
        // Antes sembraba las tablas viejas (inv_productos, inv_listas_productos,
        // inv_lista_producto_items). Como la app lee las nuevas, el inventario de
        // demostracion **no existia para la tablet**: la pantalla salia vacia y
        // parecia que el modulo no funcionaba.
        //
        // Los productos ahora son POR LOCAL (`ipc_ins_code`); en el modelo viejo
        // eran globales.
        $p1 = DB::table('inv_producto_catalogo')->insertGetId([
            'ipc_ins_code' => $insId,
            'ipc_nombre' => 'Extintor',
            'ipc_descripcion' => 'Extintor PQS 10 lb',
            'ipc_especificacion' => 'Polvo químico seco',
            'ipc_stock_actual' => 10,
            'ipc_activo' => true,
            'ipc_created_at' => now(),
            'ipc_updated_at' => now(),
        ], 'ipc_id');
        $p2 = DB::table('inv_producto_catalogo')->insertGetId([
            'ipc_ins_code' => $insId,
            'ipc_nombre' => 'Botiquín',
            'ipc_descripcion' => 'Botiquín primeros auxilios',
            'ipc_especificacion' => 'Completo',
            'ipc_stock_actual' => 5,
            'ipc_activo' => true,
            'ipc_created_at' => now(),
            'ipc_updated_at' => now(),
        ], 'ipc_id');

        $listaId = DB::table('inv_lista')->insertGetId([
            'li_ins_code' => $insId,
            'li_nombre' => 'Checklist Bodega',
            'li_descripcion' => 'Revisión mensual de bodega',
            'li_activo' => true,
            'li_created_at' => now(),
            'li_updated_at' => now(),
        ], 'li_id');

        DB::table('inv_lista_item')->insert([
            ['lia_lista_id' => $listaId, 'lia_producto_id' => $p1, 'lia_cantidad_default' => 1, 'lia_activo' => true, 'lia_created_at' => now(), 'lia_updated_at' => now()],
            ['lia_lista_id' => $listaId, 'lia_producto_id' => $p2, 'lia_cantidad_default' => 2, 'lia_activo' => true, 'lia_created_at' => now(), 'lia_updated_at' => now()],
        ]);

        // Un movimiento con su detalle, para que las pantallas del panel se
        // puedan probar con filas de verdad. Una tabla vacia se renderiza
        // siempre: los errores de formato aparecen con el primer registro (ver
        // PantallasConDatosTest).
        //
        // Se deja una diferencia a proposito -- 2 esperados, 1 contado, estado
        // `falta` -- porque el caso interesante del inventario es justamente el
        // que no cuadra.
        $movId = DB::table('inv_movimiento_cabecera')->insertGetId([
            'mc_ins_code' => $insId,
            'mc_lista_id' => $listaId,
            'mc_tipo' => 'recepcion',
            'mc_usuario_id' => $user->id,
            'mc_fecha' => now(),
            'mc_lat' => '-2.1890',
            'mc_lng' => '-79.8890',
            'mc_observaciones' => 'Recepción de turno, revisión completa',
            'mc_estado' => 'completado',
            'mc_created_at' => now(),
            'mc_updated_at' => now(),
        ], 'mc_id');

        DB::table('inv_movimiento_detalle')->insert([
            ['md_movimiento_id' => $movId, 'md_producto_id' => $p1, 'md_cantidad_default' => 1, 'md_cantidad_real' => 1, 'md_recibido' => true, 'md_observacion' => null, 'md_estado' => 'ok', 'md_created_at' => now(), 'md_updated_at' => now()],
            ['md_movimiento_id' => $movId, 'md_producto_id' => $p2, 'md_cantidad_default' => 2, 'md_cantidad_real' => 1, 'md_recibido' => true, 'md_observacion' => 'Falta un botiquín', 'md_estado' => 'falta', 'md_created_at' => now(), 'md_updated_at' => now()],
        ]);
    }
}
