<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateParametrosTable extends Migration{

    public function up(){
        Schema::create('parametros', function (Blueprint $table) {
            $table->id('pr_code');
            $table->string('pr_descripcion', 50);
            $table->string('pr_value');
        });
    }

    public function down(){
        Schema::dropIfExists('parametros');
    }
}
