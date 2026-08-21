<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvMovimientoDetalleTable extends Migration
{
    public function up()
    {
        Schema::create('inv_movimiento_detalle', function (Blueprint $table) {
            $table->id('md_id');
            $table->bigInteger('md_movimiento_id');
            $table->bigInteger('md_producto_id');
            $table->decimal('md_cantidad_default', 10, 2)->default(0);
            $table->decimal('md_cantidad_real', 10, 2)->default(0);
            $table->boolean('md_recibido')->default(false);
            $table->text('md_observacion')->nullable();
            $table->enum('md_estado', ['ok', 'falta', 'danado'])->default('ok');
            $table->timestamp('md_created_at')->nullable();
            $table->bigInteger('md_created_user')->nullable();
            $table->timestamp('md_updated_at')->nullable();
            $table->bigInteger('md_updated_user')->nullable();

            $table->foreign('md_movimiento_id')
                  ->references('mc_id')
                  ->on('inv_movimiento_cabecera')
                  ->onDelete('cascade');
            $table->foreign('md_producto_id')
                  ->references('ipc_id')
                  ->on('inv_producto_catalogo')
                  ->onDelete('cascade');
            $table->foreign('md_created_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            $table->foreign('md_updated_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->index('md_movimiento_id', 'idx_detalle_movimiento');
            $table->index('md_producto_id', 'idx_detalle_producto');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inv_movimiento_detalle');
    }
}
