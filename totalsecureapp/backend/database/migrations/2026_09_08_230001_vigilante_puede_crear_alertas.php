<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El Vigilante puede levantar una alerta.
 *
 * **Es el boton de panico, y no lo tenia.** El seed le dio `alertas.ver` y
 * `alertas.atender`, pero **no `alertas.crear`**: podia mirar las alertas del
 * dia y cerrarlas, no generar una. En una aplicacion para guardias de
 * seguridad, avisar de una emergencia es la funcion mas importante que hay, y
 * la unica persona que esta en el sitio cuando pasa algo es el vigilante.
 *
 * El endpoint `POST /api/alert/crear` ya existia y funciona; lo que faltaba era
 * el permiso. (La app tampoco tenia el boton: `constants.ts` solo declaraba
 * `/alert/today`. Eso se agrego para la proxima version del APK, pero el
 * permiso hay que darlo igual, y este cambio no necesita recompilar nada.)
 *
 * `alertas.crear` sigue fuera de Consola y Lider Operativo: ellos no estan en
 * el sitio.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rol = DB::table('roles')->where('name', 'Vigilante')->value('id');
        $permiso = DB::table('permissions')->where('name', 'alertas.crear')->value('id');

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
        $permiso = DB::table('permissions')->where('name', 'alertas.crear')->value('id');

        if ($rol === null || $permiso === null) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('permission_id', $permiso)
            ->where('role_id', $rol)
            ->delete();
    }
};
