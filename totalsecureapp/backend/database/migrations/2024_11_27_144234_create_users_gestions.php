<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersGestions extends Migration{

    public function up(){
        Schema::create('user_has_gestions', function (Blueprint $table) {
            $table->id('ug_code');
            $table->unsignedBigInteger('ug_user_id');
            $table->timestamp('ug_ingreso')->nullable();
            $table->timestamp('ug_egreso')->nullable();
            $table->boolean('ug_finish')->default(false);
            $table->integer('ug_state')->default(1);
            $table->bigInteger('ug_created_user')->nullable();
            $table->bigInteger('ug_updated_user')->nullable();
            $table->timestamp('ug_created_at')->nullable();
            $table->timestamp('ug_updated_at')->nullable();
            $table->foreign('ug_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
    public function down(){
        Schema::dropIfExists('user_has_gestions');
    }
}
