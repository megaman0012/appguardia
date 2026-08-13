<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWebLogTables extends Migration
{
    public function up()
    {
        Schema::create('log_trafico', function (Blueprint $table) {
            $table->bigIncrements('lt_code');
            $table->timestamp('lt_fecha')->nullable();
            $table->bigInteger('lt_user_id')->nullable();
            $table->bigInteger('lt_user_id_gs')->nullable();
            $table->string('lt_address', 50)->nullable();
            $table->string('lt_perfil', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('log', function (Blueprint $table) {
            $table->bigIncrements('lg_code');
            $table->string('lg_year', 4)->nullable();
            $table->timestamp('lg_date')->nullable();
            $table->string('lg_ctrl', 150)->nullable();
            $table->string('lg_func', 150)->nullable();
            $table->bigInteger('lg_user_id')->nullable();
            $table->bigInteger('lg_user_id_gs')->nullable();
            $table->text('lg_reqs')->nullable();
            $table->text('lg_urls')->nullable();
            $table->string('lg_mthd', 10)->nullable();
            $table->string('lg_type', 20)->nullable();
            $table->text('lg_obsr')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('log_trafico');
        Schema::dropIfExists('log');
    }
}
