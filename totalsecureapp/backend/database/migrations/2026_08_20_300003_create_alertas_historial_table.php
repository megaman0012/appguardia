<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas_historial', function (Blueprint $table) {
            $table->id('ah_id');
            $table->unsignedBigInteger('ah_al_code');
            $table->enum('ah_accion', ['creada', 'asignada', 'escalada', 'atendida', 'cancelada']);
            $table->unsignedBigInteger('ah_usuario_id');
            $table->text('ah_descripcion')->nullable();
            $table->timestamps();

            $table->foreign('ah_al_code')->references('al_code')->on('alertas')->onDelete('cascade');
            $table->foreign('ah_usuario_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['ah_al_code', 'ah_accion']);
            $table->index('ah_usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_historial');
    }
};
