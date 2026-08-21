<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvListaItemTable extends Migration
{
    public function up()
    {
        Schema::create('inv_lista_item', function (Blueprint $table) {
            $table->id('lia_id');
            $table->bigInteger('lia_lista_id');
            $table->bigInteger('lia_producto_id');
            $table->decimal('lia_cantidad_default', 10, 2)->default(0);
            $table->boolean('lia_activo')->default(true);
            $table->timestamp('lia_created_at')->nullable();
            $table->bigInteger('lia_created_user')->nullable();
            $table->timestamp('lia_updated_at')->nullable();
            $table->bigInteger('lia_updated_user')->nullable();

            $table->foreign('lia_lista_id')
                  ->references('li_id')
                  ->on('inv_lista')
                  ->onDelete('cascade');
            $table->foreign('lia_producto_id')
                  ->references('ipc_id')
                  ->on('inv_producto_catalogo')
                  ->onDelete('cascade');
            $table->foreign('lia_created_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            $table->foreign('lia_updated_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->index('lia_lista_id', 'idx_lista_item_lista');
            $table->index('lia_producto_id', 'idx_lista_item_producto');
            $table->unique(['lia_lista_id', 'lia_producto_id'], 'uk_lista_producto');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inv_lista_item');
    }
}
