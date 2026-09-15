<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `notifications.data` tiene que ser JSON de verdad en PostgreSQL.
 *
 * ⚠️ **Esto tumbaba el panel entero con un 500 al entrar.** La migracion estandar
 * de Laravel declara `data` como `text`, porque esta pensada para MySQL, donde
 * las funciones JSON aceptan texto. En PostgreSQL el operador `->>` **solo existe
 * para `json` y `jsonb`**, y Filament lo usa para contar las notificaciones no
 * leidas de la campanita:
 *
 *     SQLSTATE[42883]: operator does not exist: text ->> unknown
 *
 * Como ese conteo se hace al dibujar el layout del panel, fallaba **cualquier**
 * pagina de `/admin` una vez iniciada la sesion -- no la campanita sola. El login
 * y la seleccion de perfil funcionaban porque son vistas Blade del modulo Acceso,
 * fuera del panel; el 500 aparecia justo despues, al entrar a Filament.
 *
 * `USING data::jsonb` convierte lo que ya hubiera guardado, que es JSON valido
 * aunque la columna dijera `text`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        // Solo PostgreSQL necesita esto; en MySQL/SQLite `text` funciona.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
    }

    public function down(): void
    {
        if (!Schema::hasTable('notifications') || DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
    }
};
