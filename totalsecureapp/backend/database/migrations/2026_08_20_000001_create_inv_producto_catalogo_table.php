<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvProductoCatalogoTable extends Migration
{
    public function up()
    {
        Schema::create('inv_producto_catalogo', function (Blueprint $table) {
            $table->id('ipc_id');
            $table->bigInteger('ipc_ins_code');
            $table->string('ipc_nombre', 255);
            $table->text('ipc_descripcion')->nullable();
            $table->text('ipc_especificacion')->nullable();
            $table->boolean('ipc_activo')->default(true);
            $table->timestamp('ipc_created_at')->nullable();
            $table->bigInteger('ipc_created_user')->nullable();
            $table->timestamp('ipc_updated_at')->nullable();
            $table->bigInteger('ipc_updated_user')->nullable();

            $table->foreign('ipc_ins_code')
                  ->references('ins_code')
                  ->on('organizacion_institucion')
                  ->onDelete('cascade');
            $table->foreign('ipc_created_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            $table->foreign('ipc_updated_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->index('ipc_ins_code', 'idx_producto_catalogo_ins');
            $table->index('ipc_nombre', 'idx_producto_catalogo_nombre');
            $table->index('ipc_activo', 'idx_producto_catalogo_activo');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inv_producto_catalogo');
    }
}
