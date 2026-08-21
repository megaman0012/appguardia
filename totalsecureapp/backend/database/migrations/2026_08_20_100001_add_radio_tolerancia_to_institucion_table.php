<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRadioToleranciaToInstitucionTable extends Migration
{
    public function up(): void
    {
        Schema::table('organizacion_institucion', function (Blueprint $table) {
            $table->integer('ins_radio_tolerancia_metros')->default(100)->after('ins_estado');
        });
    }

    public function down(): void
    {
        Schema::table('organizacion_institucion', function (Blueprint $table) {
            $table->dropColumn('ins_radio_tolerancia_metros');
        });
    }
}