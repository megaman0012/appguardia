<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue lo que el sistema manda de lo que el guardia contesta.
 *
 * Las respuestas por WhatsApp se guardaban en la misma tabla que los avisos
 * salientes, asi que en el panel un "no puedo cubrirlo" del guardia se veia como
 * si fuera un mensaje que la empresa habia enviado. Es el mismo registro, pero no
 * es lo mismo, y quien revisa por que un puesto quedo vacio necesita separarlos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aviso_envio', function (Blueprint $table) {
            $table->string('ae_direccion', 10)->default('saliente');
            $table->index(['ae_direccion', 'created_at']);
        });

        // Lo ya registrado eran avisos salientes, salvo las respuestas que se
        // guardaban con estos dos tipos.
        DB::table('aviso_envio')
            ->whereIn('ae_tipo', ['respuesta_negativa', 'respuesta_afirmativa'])
            ->update(['ae_direccion' => 'entrante']);
    }

    public function down(): void
    {
        Schema::table('aviso_envio', function (Blueprint $table) {
            $table->dropIndex(['ae_direccion', 'created_at']);
            $table->dropColumn('ae_direccion');
        });
    }
};
