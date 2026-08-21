<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTurnoTable extends Migration
{
    public function up(): void
    {
        Schema::create('turno', function (Blueprint $table) {
            $table->id('tu_id');
            $table->bigInteger('tu_ins_code');
            $table->bigInteger('tu_usu_id');
            $table->bigInteger('tu_marcador_code')->nullable();
            $table->date('tu_fecha');
            $table->time('tu_hora_inicio_prevista');
            $table->time('tu_hora_fin_prevista');

            // Vinculacion con marcaciones reales
            $table->bigInteger('tu_bio_entrada_code')->nullable();
            $table->bigInteger('tu_bio_salida_code')->nullable();
            $table->timestamp('tu_marcada_entrada')->nullable();
            $table->timestamp('tu_marcada_salida')->nullable();

            // Calculos
            $table->integer('tu_minutos_tardanza')->nullable();
            $table->integer('tu_minutos_extras')->nullable();
            $table->text('tu_observaciones')->nullable();

            // Estado del ciclo de vida
            $table->string('tu_estado')->default('programado');
            $table->boolean('tu_state')->default(true);

            // Auditoria
            $table->bigInteger('tu_created_user')->nullable();
            $table->bigInteger('tu_updated_user')->nullable();
            $table->timestamp('tu_created_at')->nullable();
            $table->timestamp('tu_updated_at')->nullable();

            // Indices
            $table->index(['tu_ins_code', 'tu_fecha']);
            $table->index(['tu_usu_id', 'tu_fecha']);
            $table->index(['tu_estado', 'tu_fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turno');
    }
}
