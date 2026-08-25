<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantilla de turnos: el cuadrante de cobertura de un local.
 *
 * Programar turnos de a uno no escala: 50 guardias por 30 dias son 1.500 turnos
 * al mes. La plantilla los genera a partir de un patron SEMANAL, que es como
 * funciona un cuadrante real ("Juan cubre Garita, lunes a viernes, 06-14").
 * Modelarlo fecha por fecha obligaria a rehacer todo cada mes.
 *
 * Tres niveles:
 *   plantilla             el cuadrante de un local, con su vigencia y estado
 *   plantilla_franja      QUE hay que cubrir: puesto + dia de la semana + horario
 *   plantilla_asignacion  QUIEN lo cubre, con vigencia propia para rotaciones
 *
 * La salida son filas en `turno`, que no se toca. Aditivo, como el resto.
 *
 * turno.tu_plantilla_id marca cuales genero una plantilla. Es lo que permite
 * regenerar sin pisar los turnos cargados a mano en el panel, que quedan con
 * ese campo en null y nunca se tocan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantilla', function (Blueprint $table) {
            $table->bigIncrements('pl_id');
            $table->unsignedBigInteger('pl_ins_code');
            $table->string('pl_nombre');
            // borrador: se puede editar libremente. publicada: ya genero turnos.
            // archivada: se conserva por historial pero no genera mas.
            $table->string('pl_estado', 20)->default('borrador');
            $table->date('pl_vigencia_desde')->nullable();
            $table->date('pl_vigencia_hasta')->nullable();
            $table->text('pl_observaciones')->nullable();
            $table->unsignedBigInteger('pl_created_user')->nullable();
            $table->unsignedBigInteger('pl_updated_user')->nullable();
            $table->timestamps();

            $table->foreign('pl_ins_code')
                ->references('ins_code')->on('organizacion_institucion')
                ->onDelete('cascade');

            $table->index(['pl_ins_code', 'pl_estado']);
        });

        Schema::create('plantilla_franja', function (Blueprint $table) {
            $table->bigIncrements('pf_id');
            $table->unsignedBigInteger('pf_pl_id');
            $table->unsignedBigInteger('pf_puesto_id');
            // ISO-8601: 1 = lunes ... 7 = domingo, igual que Carbon::dayOfWeekIso.
            $table->unsignedTinyInteger('pf_dia_semana');
            $table->time('pf_hora_inicio');
            $table->time('pf_hora_fin');
            $table->boolean('pf_estado')->default(true);
            $table->timestamps();

            $table->foreign('pf_pl_id')->references('pl_id')->on('plantilla')->onDelete('cascade');
            $table->foreign('pf_puesto_id')->references('pu_id')->on('puesto')->onDelete('cascade');

            // El mismo puesto, el mismo dia y a la misma hora es la misma franja.
            $table->unique(['pf_pl_id', 'pf_puesto_id', 'pf_dia_semana', 'pf_hora_inicio'], 'plantilla_franja_unica');
            $table->index('pf_pl_id');
        });

        Schema::create('plantilla_asignacion', function (Blueprint $table) {
            $table->bigIncrements('pa_id');
            $table->unsignedBigInteger('pa_pf_id');
            $table->unsignedBigInteger('pa_usu_id');
            // Vigencia propia: un reemplazo de dos semanas no obliga a rehacer la
            // plantilla, se acota la asignacion.
            $table->date('pa_desde')->nullable();
            $table->date('pa_hasta')->nullable();
            $table->boolean('pa_estado')->default(true);
            $table->timestamps();

            $table->foreign('pa_pf_id')->references('pf_id')->on('plantilla_franja')->onDelete('cascade');

            $table->unique(['pa_pf_id', 'pa_usu_id'], 'plantilla_asignacion_unica');
            $table->index('pa_usu_id');
        });

        Schema::table('turno', function (Blueprint $table) {
            $table->unsignedBigInteger('tu_plantilla_id')->nullable();

            // set null y no cascade: borrar una plantilla no debe llevarse el
            // historial de turnos ya cumplidos, solo desvincularlos.
            $table->foreign('tu_plantilla_id')
                ->references('pl_id')->on('plantilla')
                ->onDelete('set null');

            $table->index(['tu_plantilla_id', 'tu_fecha']);
        });
    }

    public function down(): void
    {
        Schema::table('turno', function (Blueprint $table) {
            $table->dropForeign(['tu_plantilla_id']);
            $table->dropColumn('tu_plantilla_id');
        });

        Schema::dropIfExists('plantilla_asignacion');
        Schema::dropIfExists('plantilla_franja');
        Schema::dropIfExists('plantilla');
    }
};
