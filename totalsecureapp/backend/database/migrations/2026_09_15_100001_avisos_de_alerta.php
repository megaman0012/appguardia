<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que hacia falta para que una alerta de panico le llegue a alguien.
 *
 * Hasta ahora la alerta se guardaba bien y **el aviso no existia**:
 * `AlertaService` emitia un evento `ShouldBroadcast` con `BROADCAST_DRIVER=log`,
 * o sea que el «aviso» era una linea en un archivo que nadie lee. El boton de
 * EMERGENCIA de los guardias no despertaba a nadie.
 *
 * Dos cosas se agregan aca:
 *
 * 1. `notifications`, la tabla estandar de Laravel. Filament la usa para la
 *    campanita del panel, que es «la web» donde el usuario espera ver la alerta.
 *    No estaba publicada: el proyecto venia de Laravel 8 y nunca se uso.
 *
 * 2. `aviso_envio.ae_al_code`, para poder responder «a quien se le aviso de esta
 *    alerta, y llego?». Es el mismo papel que cumple `ae_tv_id` para una
 *    vacante. Sin FK, igual que `ae_tv_id`: borrar una alerta no debe llevarse
 *    la constancia de que se aviso.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');

                /*
                 * ⚠️ `json`, no `text`.
                 *
                 * La migracion estandar de Laravel usa `text` porque esta
                 * pensada para MySQL. En PostgreSQL el operador `->>` --que
                 * Filament usa para contar las notificaciones no leidas de la
                 * campanita-- **solo existe para `json` y `jsonb`**, asi que con
                 * `text` cualquier pagina del panel devuelve 500 en cuanto hay
                 * sesion iniciada. Lo corrige tambien la migracion
                 * `2026_09_15_300001`, para las instalaciones donde esta ya
                 * habia corrido.
                 */
                $table->json('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('aviso_envio', 'ae_al_code')) {
            Schema::table('aviso_envio', function (Blueprint $table) {
                $table->unsignedBigInteger('ae_al_code')->nullable()->after('ae_tv_id');
                $table->index('ae_al_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('aviso_envio', 'ae_al_code')) {
            Schema::table('aviso_envio', function (Blueprint $table) {
                $table->dropIndex(['ae_al_code']);
                $table->dropColumn('ae_al_code');
            });
        }

        // `notifications` no se borra: si se publico, puede tener avisos
        // pendientes de leer que no son de esta migracion.
    }
};
