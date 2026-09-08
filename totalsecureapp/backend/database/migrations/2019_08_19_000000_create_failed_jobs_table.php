<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFailedJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    /*
     * ⚠️ Aca decia `protected $connection = 'mysql';`, herencia de cuando el
     * proyecto corria sobre MySQL (v1). Este proyecto es PostgreSQL: la
     * conexion `mysql` de `config/database.php` toma `DB_HOST` y `DB_PORT` del
     * .env, o sea que apuntaba el **driver de MySQL contra el puerto 5432 de
     * Postgres**. El TCP conecta, el cliente manda el saludo de MySQL y se
     * queda esperando una respuesta que nunca llega: **cuelgue indefinido**, sin
     * error ni traza.
     *
     * Laravel 8.75 no lo respetaba y la migracion corria sobre la conexion por
     * defecto -- por eso esta tabla existe en Postgres en produccion y la
     * migracion figura aplicada en el lote 1. Al subir a 8.83.29 empezo a
     * respetarlo, y con eso `migrate:fresh` (y con el toda la suite de tests)
     * se colgaba en esta migracion sin decir nada.
     *
     * Sin la linea, la migracion usa la conexion por defecto, que es lo que
     * produccion ya tiene.
     */
    public function up()
    {
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('failed_jobs');
    }
}
