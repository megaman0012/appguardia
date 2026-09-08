<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El Vigilante puede cerrar el inventario que el mismo recibio.
 *
 * **El bug.** `2026_08_21_200001_seed_mobile_permissions` le dio al Vigilante
 * `inventario.ver`, `inventario.ver_detalle` e `inventario.registrar`, pero
 * **no** `inventario.finalizar`. Con eso:
 *
 *  1. El guardia ve el modulo Inventario en el menu (`inventario.ver`).
 *  2. Recibe la lista al entrar al turno (`inventario.registrar`) -- funciona.
 *  3. Al salir, la app llama `POST /api/inventario/finishsave` para devolverla,
 *     y el servidor responde **403**.
 *  4. La recepcion queda abierta para siempre.
 *  5. En el turno siguiente, la recepcion es rechazada con «Ya existe una
 *     recepcion registrada para esta lista».
 *
 * O sea: cada guardia podia registrar inventario **una sola vez en su vida** y
 * despues quedaba bloqueado, sin ninguna forma de arreglarlo desde la app.
 *
 * Recibir y devolver son las dos mitades de una misma operacion, y la unica que
 * puede hacer ambas es la persona que tiene los articulos en la mano. Un
 * permiso separado para el cierre solo tendria sentido si el cierre lo hiciera
 * un supervisor, y la app no funciona asi: `InventarioDetalleScreen` llama a
 * los dos endpoints con el mismo token.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rol = DB::table('roles')->where('name', 'Vigilante')->value('id');
        $permiso = DB::table('permissions')->where('name', 'inventario.finalizar')->value('id');

        if ($rol === null || $permiso === null) {
            return;
        }

        DB::table('role_has_permissions')->updateOrInsert(
            ['permission_id' => $permiso, 'role_id' => $rol],
            []
        );
    }

    public function down(): void
    {
        $rol = DB::table('roles')->where('name', 'Vigilante')->value('id');
        $permiso = DB::table('permissions')->where('name', 'inventario.finalizar')->value('id');

        if ($rol === null || $permiso === null) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('permission_id', $permiso)
            ->where('role_id', $rol)
            ->delete();
    }
};
