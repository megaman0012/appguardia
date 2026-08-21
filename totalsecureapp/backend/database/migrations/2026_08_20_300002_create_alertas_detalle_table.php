<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas_detalle', function (Blueprint $table) {
            $table->id('ad_id');
            $table->unsignedBigInteger('ad_al_code');
            $table->unsignedBigInteger('ad_usuario_asignado')->nullable();
            $table->enum('ad_prioridad', ['baja', 'media', 'alta', 'critica'])->default('media');
            $table->enum('ad_estado', ['asignada', 'en_revision', 'resuelta', 'escalada'])->default('asignada');
            $table->timestamp('ad_fecha_asignacion')->nullable();
            $table->timestamp('ad_fecha_atencion')->nullable();
            $table->integer('ad_tiempo_respuesta_seg')->default(0);
            $table->text('ad_observacion_atencion')->nullable();
            $table->integer('ad_created_user');
            $table->timestamps();

            $table->foreign('ad_al_code')->references('al_code')->on('alertas')->onDelete('cascade');
            $table->foreign('ad_usuario_asignado')->references('id')->on('users')->onDelete('set null');

            $table->index(['ad_al_code', 'ad_estado']);
            $table->index(['ad_usuario_asignado', 'ad_estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_detalle');
    }
};
