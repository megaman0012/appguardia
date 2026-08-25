<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cobertura de turnos descubiertos.
 *
 * Un turno se queda sin cubrir por tres motivos distintos —el guardia no llegó,
 * avisó que no viene, o el cliente pidió un refuerzo— pero el problema es el
 * mismo: hay un puesto vacío y hay que llenarlo. Por eso hay UN objeto,
 * `turno_vacante`, que se puede abrir de tres formas, y no tres flujos.
 *
 * Los datos del turno se copian dentro de la vacante en vez de leerse siempre
 * por la relación. Es a propósito: regenerar el cuadrante borra los turnos sin
 * marcaje, y una vacante abierta no puede desaparecer porque alguien republicó
 * la plantilla mientras tanto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turno_vacante', function (Blueprint $table) {
            $table->bigIncrements('tv_id');

            // El turno que quedó descubierto. Nullable porque un refuerzo pedido
            // por el cliente no nace de ningún turno previo.
            $table->unsignedBigInteger('tv_turno_id')->nullable();
            $table->unsignedBigInteger('tv_ins_code');
            $table->unsignedBigInteger('tv_puesto_id')->nullable();
            $table->unsignedBigInteger('tv_usu_id_ausente')->nullable();

            $table->date('tv_fecha');
            $table->time('tv_hora_inicio');
            $table->time('tv_hora_fin');

            $table->string('tv_motivo', 20)->default('falta');
            $table->string('tv_estado', 20)->default('detectada');

            // Ola 1 = solo el local; ola 2 = la ciudad. Se escala si nadie se
            // postula, salvo en locales que exigen acreditación propia.
            $table->string('tv_alcance', 20)->default('local');

            $table->unsignedBigInteger('tv_abierta_por')->nullable();
            $table->timestamp('tv_abierta_en')->nullable();
            $table->unsignedBigInteger('tv_turno_cobertura_id')->nullable();
            $table->unsignedBigInteger('tv_confirmada_por')->nullable();
            $table->timestamp('tv_confirmada_en')->nullable();
            $table->text('tv_observaciones')->nullable();

            $table->timestamps();

            // Si el turno se borra (republicación del cuadrante), la vacante
            // sigue en pie con sus propios datos.
            $table->foreign('tv_turno_id')->references('tu_id')->on('turno')->onDelete('set null');
            $table->foreign('tv_turno_cobertura_id')->references('tu_id')->on('turno')->onDelete('set null');
            $table->foreign('tv_ins_code')->references('ins_code')->on('organizacion_institucion');
            $table->foreign('tv_puesto_id')->references('pu_id')->on('puesto');

            $table->index(['tv_ins_code', 'tv_estado', 'tv_fecha']);
            $table->index(['tv_estado', 'tv_fecha']);
        });

        // El detector corre cada pocos minutos: sin esto crearía una vacante
        // nueva en cada pasada para el mismo turno.
        DB::statement("
            CREATE UNIQUE INDEX turno_vacante_turno_viva_unique
            ON turno_vacante (tv_turno_id)
            WHERE tv_turno_id IS NOT NULL AND tv_estado IN ('detectada', 'abierta', 'cubierta')
        ");

        Schema::create('turno_postulacion', function (Blueprint $table) {
            $table->bigIncrements('tp_id');
            $table->unsignedBigInteger('tp_tv_id');
            $table->unsignedBigInteger('tp_usu_id');
            $table->string('tp_estado', 20)->default('postulado');
            $table->text('tp_observaciones')->nullable();

            // Misma idempotencia que los cinco endpoints de campo: postularse
            // sin señal y sincronizar después no puede duplicar ni fallar.
            $table->string('tp_client_uuid')->nullable()->unique();
            $table->timestamp('tp_ocurrido_en')->nullable();
            $table->timestamp('tp_sincronizado_en')->nullable();

            $table->timestamps();

            $table->foreign('tp_tv_id')->references('tv_id')->on('turno_vacante')->onDelete('cascade');

            // Un guardia se postula una sola vez a la misma vacante.
            $table->unique(['tp_tv_id', 'tp_usu_id']);
            $table->index(['tp_usu_id', 'tp_estado']);
        });

        // Quién quiere trabajar de más. Sin esto habría que avisarle a todos, y
        // en dos semanas nadie miraría los avisos.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('usu_acepta_extras')->default(false);
        });

        // Locales donde no basta con ser guardia de la empresa: hace falta la
        // credencial del cliente o del aeropuerto. Esos nunca salen de la ola 1.
        Schema::table('organizacion_institucion', function (Blueprint $table) {
            $table->boolean('ins_requiere_acreditacion')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('organizacion_institucion', function (Blueprint $table) {
            $table->dropColumn('ins_requiere_acreditacion');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('usu_acepta_extras');
        });

        Schema::dropIfExists('turno_postulacion');
        Schema::dropIfExists('turno_vacante');
    }
};
