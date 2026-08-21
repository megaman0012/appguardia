<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvMovimientoCabeceraTable extends Migration
{
    public function up()
    {
        Schema::create('inv_movimiento_cabecera', function (Blueprint $table) {
            $table->id('mc_id');
            $table->bigInteger('mc_ins_code');
            $table->bigInteger('mc_lista_id');
            $table->enum('mc_tipo', ['recepcion', 'devolucion', 'baja']);
            $table->bigInteger('mc_usuario_id');
            $table->timestamp('mc_fecha');
            $table->string('mc_lat', 50)->nullable();
            $table->string('mc_lng', 50)->nullable();
            $table->text('mc_observaciones')->nullable();
            $table->enum('mc_estado', ['pendiente', 'completado', 'cancelado'])->default('pendiente');
            $table->timestamp('mc_created_at')->nullable();
            $table->bigInteger('mc_created_user')->nullable();
            $table->timestamp('mc_updated_at')->nullable();
            $table->bigInteger('mc_updated_user')->nullable();

            $table->foreign('mc_ins_code')
                  ->references('ins_code')
                  ->on('organizacion_institucion')
                  ->onDelete('cascade');
            $table->foreign('mc_lista_id')
                  ->references('li_id')
                  ->on('inv_lista')
                  ->onDelete('cascade');
            $table->foreign('mc_usuario_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('mc_created_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            $table->foreign('mc_updated_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->index('mc_ins_code', 'idx_movimiento_ins');
            $table->index('mc_lista_id', 'idx_movimiento_lista');
            $table->index('mc_tipo', 'idx_movimiento_tipo');
            $table->index('mc_estado', 'idx_movimiento_estado');
            $table->index('mc_fecha', 'idx_movimiento_fecha');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inv_movimiento_cabecera');
    }
}
