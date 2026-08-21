<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Detalle vehicular (ac_tipo = vehicular, o proveedor con vehiculo)
        Schema::create('acceso_vehiculo', function (Blueprint $table) {
            $table->id('av_code');
            $table->bigInteger('av_ac_code');
            $table->string('av_patente', 20)->nullable();
            $table->string('av_empresa', 100)->nullable();
            $table->boolean('av_is_sello')->default(false);
            $table->boolean('av_is_neumatico')->default(false);
            $table->boolean('av_is_carro')->default(false);
            $table->boolean('av_pta_llave')->default(false);
            $table->string('av_kms', 20)->nullable();
            $table->string('av_color', 50)->nullable();
            $table->string('av_marca', 50)->nullable();
            $table->string('av_modelo', 50)->nullable();
            $table->integer('av_anio')->nullable();
            $table->timestamps();

            $table->foreign('av_ac_code')->references('ac_code')->on('acceso')->onDelete('cascade');
            $table->index('av_ac_code');
        });

        // Detalle visitante / proveedor
        Schema::create('acceso_visitante', function (Blueprint $table) {
            $table->id('avi_code');
            $table->bigInteger('avi_ac_code');
            $table->string('avi_motivo', 200)->nullable();
            $table->string('avi_area_visita', 100)->nullable();
            $table->string('avi_persona_visita', 150)->nullable();
            $table->string('avi_empresa_origen', 100)->nullable();
            $table->integer('avi_personas_grupo')->default(1);
            $table->string('avi_duracion_estimada', 50)->nullable();
            $table->timestamps();

            $table->foreign('avi_ac_code')->references('ac_code')->on('acceso')->onDelete('cascade');
            $table->index('avi_ac_code');
        });

        // Historial de marcas entrada/salida (multiples entradas y salidas)
        Schema::create('acceso_historial', function (Blueprint $table) {
            $table->id('ah_code');
            $table->bigInteger('ah_ac_code');
            $table->string('ah_tipo_marca', 10)->comment('entrada|salida');
            $table->timestamp('ah_fecha_hora');
            $table->string('ah_lat', 30)->nullable();
            $table->string('ah_lng', 30)->nullable();
            $table->text('ah_observaciones')->nullable();
            $table->timestamps();

            $table->foreign('ah_ac_code')->references('ac_code')->on('acceso')->onDelete('cascade');
            $table->index(['ah_ac_code', 'ah_tipo_marca']);
        });

        // Pre-registro de visitantes esperados
        Schema::create('acceso_preregistro', function (Blueprint $table) {
            $table->id('apr_code');
            $table->bigInteger('apr_ins_code');
            $table->bigInteger('apr_ap_code')->nullable();
            $table->date('apr_fecha_estimada');
            $table->time('apr_hora_estimada')->nullable();
            $table->string('apr_motivo', 200)->nullable();
            $table->string('apr_area_visita', 100)->nullable();
            $table->string('apr_estado', 20)->default('pendiente');
            $table->string('apr_token', 64)->nullable();
            $table->bigInteger('apr_created_user')->nullable();
            $table->timestamps();

            $table->foreign('apr_ins_code')->references('ins_code')->on('organizacion_institucion')->onDelete('cascade');
            $table->foreign('apr_ap_code')->references('ap_code')->on('acceso_persona')->onDelete('set null');
            $table->index(['apr_ins_code', 'apr_fecha_estimada', 'apr_estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acceso_preregistro');
        Schema::dropIfExists('acceso_historial');
        Schema::dropIfExists('acceso_visitante');
        Schema::dropIfExists('acceso_vehiculo');
    }
};
