<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionSectionTable extends Migration{

    protected $connection = 'mysql';
    public function up(){
        Schema::create('permission_section', function (Blueprint $table) {
            $table->id('ps_codigo')->autoIncrement();
            $table->string('ps_nombre', 50)->nullable();
            $table->timestamps();
            $table->integer('ps_posicion')->nullable();
            $table->string('ps_icono', 50)->nullable();
        });
    }
    public function down(){
        Schema::dropIfExists('permission_section');
    }
}
