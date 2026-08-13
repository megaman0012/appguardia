<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixTipoServicioColumns extends Migration
{
    public function up()
    {
        Schema::table('tipo_servicio', function (Blueprint $table) {
            $table->string('ts_descripcion', 100)->nullable()->after('ts_code');
            $table->integer('ts_estado')->default(1)->after('ts_descripcion');
        });
    }

    public function down()
    {
        Schema::table('tipo_servicio', function (Blueprint $table) {
            $table->dropColumn(['ts_descripcion', 'ts_estado']);
        });
    }
}
