<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersonaTable extends Migration{

    public function up(){
        Schema::create('persona', function (Blueprint $table) {
            $table->id('pt_code')->autoIncrement();
            $table->string('pt_documento', 255)->nullable();
            $table->string('pt_tip_doc', 255)->nullable();
            $table->string('pt_nmb_comp', 255)->nullable();
            $table->string('pt_ape1', 255)->nullable();
            $table->string('pt_ape2', 255)->nullable();
            $table->string('pt_nmb1', 255)->nullable();
            $table->string('pt_nmb2', 255)->nullable();
            $table->dateTime('pt_fch_nac')->nullable();
            $table->string('pt_pais', 255)->nullable();
            $table->string('pt_provincia', 255)->nullable();
            $table->string('pt_ciudad', 255)->nullable();
            $table->string('pt_parroquia', 255)->nullable();
            $table->string('pt_direccion', 255)->nullable();
            $table->integer('pt_estado')->default(1);
            $table->bigInteger('pt_created_user')->nullable();
            $table->bigInteger('pt_updated_user')->nullable();
            $table->timestamps();
        });
    }
    public function down(){
        Schema::dropIfExists('persona');
    }
}
