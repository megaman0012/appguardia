<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp del guardia y registro de los avisos enviados.
 *
 * El registro no es auditoría por gusto: cuando un puesto queda vacío, la
 * primera pregunta es "¿le avisamos a alguien?". Sin esta tabla la única
 * respuesta posible es "debería haber salido".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Con código de país. Ver ConversorDeNumero: un número mal formado
            // no da error, simplemente no llega.
            $table->string('usu_whatsapp', 20)->nullable();

            // Aparte de usu_acepta_extras: querer turnos extra no es lo mismo
            // que aceptar que le escriban al WhatsApp personal.
            $table->boolean('usu_acepta_whatsapp')->default(false);
        });

        Schema::create('aviso_envio', function (Blueprint $table) {
            $table->bigIncrements('ae_id');
            $table->unsignedBigInteger('ae_usu_id');
            $table->string('ae_canal', 20);
            $table->string('ae_tipo', 40);
            $table->string('ae_titulo');
            $table->text('ae_cuerpo')->nullable();

            // A dónde salió: el número de WhatsApp, o vacío en push.
            $table->string('ae_destino', 60)->nullable();

            $table->string('ae_resultado', 20);
            $table->text('ae_detalle')->nullable();
            $table->unsignedBigInteger('ae_tv_id')->nullable();

            $table->timestamps();

            $table->index(['ae_usu_id', 'created_at']);
            $table->index(['ae_canal', 'ae_resultado']);
            $table->index('ae_tv_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aviso_envio');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['usu_whatsapp', 'usu_acepta_whatsapp']);
        });
    }
};
