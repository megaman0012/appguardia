<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBioTuCodeToBiometriaTable extends Migration
{
    public function up(): void
    {
        Schema::table('user_has_biometria', function (Blueprint $table) {
            $table->bigInteger('bio_tu_code')->nullable()->after('bio_state');
            $table->index('bio_tu_code');
        });
    }

    public function down(): void
    {
        Schema::table('user_has_biometria', function (Blueprint $table) {
            $table->dropIndex(['bio_tu_code']);
            $table->dropColumn('bio_tu_code');
        });
    }
}
