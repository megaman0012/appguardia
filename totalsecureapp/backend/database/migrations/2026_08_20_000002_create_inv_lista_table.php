<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvListaTable extends Migration
{
    public function up()
    {
        Schema::create('inv_lista', function (Blueprint $table) {
            $table->id('li_id');
            $table->bigInteger('li_ins_code');
            $table->string('li_nombre', 255);
            $table->text('li_descripcion')->nullable();
            $table->boolean('li_activo')->default(true);
            $table->timestamp('li_created_at')->nullable();
            $table->bigInteger('li_created_user')->nullable();
            $table->timestamp('li_updated_at')->nullable();
            $table->bigInteger('li_updated_user')->nullable();

            $table->foreign('li_ins_code')
                  ->references('ins_code')
                  ->on('organizacion_institucion')
                  ->onDelete('cascade');
            $table->foreign('li_created_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            $table->foreign('li_updated_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->index('li_ins_code', 'idx_lista_ins');
            $table->index('li_activo', 'idx_lista_activo');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inv_lista');
    }
}
