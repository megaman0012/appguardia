<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->enum('al_prioridad', ['baja', 'media', 'alta', 'critica'])
                  ->default('media')
                  ->after('al_estado');

            $table->enum('al_estado_nuevo', ['pendiente', 'en_atencion', 'finalizada', 'cancelada'])
                  ->default('pendiente')
                  ->after('al_prioridad');
        });

        DB::statement("
            UPDATE alertas 
            SET al_estado_nuevo = CASE 
                WHEN al_estado_alerta = 'resuelta' THEN 'finalizada'
                WHEN al_estado = 1 THEN 'pendiente'
                ELSE 'cancelada'
            END
        ");

        Schema::table('alertas', function (Blueprint $table) {
            $table->dropColumn('al_estado_alerta');
        });

        DB::statement('ALTER TABLE alertas RENAME COLUMN al_estado_nuevo TO al_estado_alerta');

        Schema::table('alertas', function (Blueprint $table) {
            $table->index(['al_ins_code', 'al_estado_alerta', 'al_fecha']);
            $table->index(['al_usu_id', 'al_estado_alerta']);
        });
    }

    public function down(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->dropIndex(['al_ins_code', 'al_estado_alerta', 'al_fecha']);
            $table->dropIndex(['al_usu_id', 'al_estado_alerta']);
            $table->dropColumn('al_prioridad');
            $table->dropColumn('al_estado_alerta');
        });
    }
};
