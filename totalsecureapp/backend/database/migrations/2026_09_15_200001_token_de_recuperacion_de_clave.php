<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que hace falta para que recuperar la clave exija algo mas que una cedula.
 *
 * ⚠️ **Hasta hoy `POST /api/procesar_paswchg` cambiaba la contrasena de
 * cualquier usuario sabiendo solo su `user_id`**, un entero secuencial, sin
 * autenticacion y sin comprobar ningun token -- y el endpoint esta publicado en
 * internet. `solicitud_paswchg`, tambien publico, devolvia el `user_id` y el
 * token en la respuesta a cambio de una cedula. En Ecuador la cedula no es un
 * secreto, asi que cualquiera podia tomar cualquier cuenta, incluidas las de
 * Administrador, que ven los datos de 879 personas y 12.664 biometrias.
 *
 * Tres columnas nuevas:
 *
 * - `usu_reset_token`: el codigo **hasheado**, nunca en claro. Si alguien lee la
 *   base no puede usar los codigos vigentes, que es justamente lo que pasaba
 *   antes con `remember_token`.
 * - `usu_reset_expira`: un codigo sin caducidad sirve para siempre. El anterior
 *   se guardaba y no vencia nunca.
 * - `usu_reset_intentos`: un codigo numerico sin limite de intentos se adivina a
 *   fuerza bruta. Con esto se invalida al quinto fallo.
 *
 * `remember_token` deja de usarse para esto: es el "recordarme" de Laravel, no
 * un token de recuperacion, y mezclarlos hacia que iniciar sesion y recuperar la
 * clave se pisaran entre si.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('usu_reset_token', 64)->nullable();
            $table->timestamp('usu_reset_expira')->nullable();
            $table->unsignedSmallInteger('usu_reset_intentos')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['usu_reset_token', 'usu_reset_expira', 'usu_reset_intentos']);
        });
    }
};
