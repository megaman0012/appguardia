<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRuCodeToUserHasRolesTable extends Migration
{
    public function up()
    {
        Schema::table('user_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('ru_code')->nullable();
        });

        DB::statement('CREATE SEQUENCE IF NOT EXISTS user_has_roles_ru_code_seq');
        DB::statement("UPDATE user_has_roles SET ru_code = nextval('user_has_roles_ru_code_seq') WHERE ru_code IS NULL");
        DB::statement("SELECT setval('user_has_roles_ru_code_seq', (SELECT COALESCE(MAX(ru_code), 1) FROM user_has_roles))");
        DB::statement('ALTER TABLE user_has_roles ALTER COLUMN ru_code SET DEFAULT nextval(\'user_has_roles_ru_code_seq\')');
        DB::statement('ALTER TABLE user_has_roles ALTER COLUMN ru_code SET NOT NULL');
        DB::statement('CREATE UNIQUE INDEX user_has_roles_ru_code_unique ON user_has_roles (ru_code)');
    }

    public function down()
    {
        Schema::table('user_has_roles', function (Blueprint $table) {
            $table->dropColumn('ru_code');
        });
        DB::statement('DROP SEQUENCE IF EXISTS user_has_roles_ru_code_seq');
    }
}
