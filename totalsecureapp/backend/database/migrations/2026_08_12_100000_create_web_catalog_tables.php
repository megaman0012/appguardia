<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWebCatalogTables extends Migration
{
    public function up()
    {
        Schema::create('tipo_documento', function (Blueprint $table) {
            $table->integer('td_code')->primary();
            $table->string('td_sigla', 10);
            $table->string('td_descripcion', 100);
            $table->integer('td_estado')->default(1);
        });

        Schema::create('tipo_genero', function (Blueprint $table) {
            $table->integer('tg_code')->primary();
            $table->string('tg_sigla', 10);
            $table->string('tg_descripcion', 50);
            $table->integer('tg_estado')->default(1);
        });

        Schema::create('tipo_especialidad', function (Blueprint $table) {
            $table->integer('te_code')->primary();
            $table->string('te_descripcion', 100);
            $table->integer('te_estado')->default(1);
        });

        Schema::create('tipo_servicio', function (Blueprint $table) {
            $table->integer('ts_code')->primary();
            $table->string('tg_code', 20);
            $table->string('tg_descripcion', 100);
            $table->integer('tg_estado')->default(1);
        });

        Schema::create('referencia_motivo', function (Blueprint $table) {
            $table->integer('rm_code')->primary();
            $table->string('rm_motivo', 255);
            $table->integer('rm_estado')->default(1);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tipo_documento');
        Schema::dropIfExists('tipo_genero');
        Schema::dropIfExists('tipo_especialidad');
        Schema::dropIfExists('tipo_servicio');
        Schema::dropIfExists('referencia_motivo');
    }
}
