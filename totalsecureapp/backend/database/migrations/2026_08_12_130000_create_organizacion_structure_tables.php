<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrganizacionStructureTables extends Migration
{
    public function up()
    {
        Schema::create('sede', function (Blueprint $table) {
            $table->bigIncrements('ps_code');
            $table->string('ps_sigla', 20)->nullable();
            $table->string('ps_descripcion', 150)->nullable();
            $table->boolean('ps_estado')->default(true);
            $table->unsignedBigInteger('ps_created_user')->nullable();
            $table->unsignedBigInteger('ps_updated_user')->nullable();
            $table->timestamps();
        });

        Schema::create('organizacion', function (Blueprint $table) {
            $table->bigIncrements('org_code');
            $table->string('org_descripcion', 150)->nullable();
            $table->string('org_razon_social', 200)->nullable();
            $table->string('org_direccion', 255)->nullable();
            $table->string('org_ciudad', 100)->nullable();
            $table->string('org_pais', 100)->nullable();
            $table->string('org_telefono', 50)->nullable();
            $table->string('org_email', 150)->nullable();
            $table->string('org_tipo', 50)->nullable();
            $table->string('org_website', 255)->nullable();
            $table->string('org_numero_registro', 100)->nullable();
            $table->boolean('org_estado')->default(true);
            $table->unsignedBigInteger('org_created_user')->nullable();
            $table->unsignedBigInteger('org_updated_user')->nullable();
            $table->timestamps();
        });

        Schema::create('organizacion_sede', function (Blueprint $table) {
            $table->bigIncrements('so_code');
            $table->unsignedBigInteger('so_ps_code')->nullable();
            $table->unsignedBigInteger('so_org_code')->nullable();
            $table->boolean('so_estado')->default(true);
            $table->unsignedBigInteger('so_created_user')->nullable();
            $table->unsignedBigInteger('so_updated_user')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('organizacion_sede');
        Schema::dropIfExists('organizacion');
        Schema::dropIfExists('sede');
    }
}
