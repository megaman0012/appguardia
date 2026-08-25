<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Puesto de trabajo: la posicion concreta dentro de un Local.
 *
 * Un local grande (una terminal de aeropuerto, un centro de distribucion) tiene
 * varias posiciones que se cubren por separado: garita de ingreso, anden de
 * carga, sala de monitoreo. El turno de un guardia se cubre en UN puesto.
 *
 * No confundir con institucion_marcadores: un marcador es un punto QR que el
 * guardia escanea al pasar en una ronda; un puesto es donde se queda durante su
 * turno. Por eso son tablas distintas y el turno referencia a las dos
 * (tu_marcador_code ya existia).
 *
 * pu_lat/pu_lng son opcionales: sirven si en el futuro se quiere validar la
 * presencia contra el puesto y no solo contra el local, que es lo que hoy hace
 * PresenceValidationService con el radio de tolerancia de la institucion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puesto', function (Blueprint $table) {
            $table->bigIncrements('pu_id');
            $table->unsignedBigInteger('pu_ins_code');
            $table->string('pu_nombre');
            $table->string('pu_descripcion')->nullable();
            $table->string('pu_lat')->nullable();
            $table->string('pu_lng')->nullable();
            $table->boolean('pu_estado')->default(true);
            $table->unsignedBigInteger('pu_created_user')->nullable();
            $table->unsignedBigInteger('pu_updated_user')->nullable();
            $table->timestamps();

            $table->foreign('pu_ins_code')
                ->references('ins_code')->on('organizacion_institucion')
                ->onDelete('cascade');

            // Dos puestos con el mismo nombre en el mismo local serian
            // indistinguibles al programar un turno.
            $table->unique(['pu_ins_code', 'pu_nombre']);
            $table->index('pu_ins_code');
        });

        Schema::table('turno', function (Blueprint $table) {
            $table->unsignedBigInteger('tu_puesto_id')->nullable();

            // restrict y no cascade: borrar un puesto no debe llevarse el
            // historial de turnos ya cumplidos.
            $table->foreign('tu_puesto_id')
                ->references('pu_id')->on('puesto')
                ->onDelete('restrict');

            $table->index('tu_puesto_id');
        });
    }

    public function down(): void
    {
        Schema::table('turno', function (Blueprint $table) {
            $table->dropForeign(['tu_puesto_id']);
            $table->dropColumn('tu_puesto_id');
        });

        Schema::dropIfExists('puesto');
    }
};
