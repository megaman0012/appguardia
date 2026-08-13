<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration{

    public function up(){
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('usu_cedula');
            $table->string('usu_tipdoc');
            $table->string('usu_password');
            $table->string('usu_nmbcom');
            $table->string('usu_ape1');
            $table->string('usu_ape2');
            $table->string('usu_nmb1');
            $table->string('usu_nmb2');
            $table->string('usu_email');
            $table->timestamp('usu_email_verified_at')->nullable();

            $table->integer('usu_state')->default(1);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(){
        Schema::dropIfExists('users');
    }

}
