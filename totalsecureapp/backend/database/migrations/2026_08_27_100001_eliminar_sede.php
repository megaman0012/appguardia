<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina el nivel "sede", herencia de coredt360.
 *
 * Era un nivel intermedio de otra jerarquia (organizacion -> sede -> institucion)
 * que hace lo mismo que hoy hacen el cliente (`ins_cliente_id`) y la geografia
 * (pais -> provincia -> ciudad -> local). Nunca se uso: las tres tablas estaban
 * vacias, ningun local tenia sede, y su unico efecto era una columna en blanco en
 * media docena de pantallas y tres menus que no llevaban a nada.
 *
 * ANTES DE BORRAR se rescata el vinculo con el cliente: si algun local llegara a
 * tener sede pero no cliente, se copia el cliente que colgaba de esa sede. Es la
 * unica informacion util que la cadena podia contener.
 *
 * El `down()` recrea la estructura pero **no los datos**: el vinculo local->sede
 * solo vuelve desde un backup. Mismo criterio que la migracion 2026_08_21_100002.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rescatarClientes();

        if (Schema::hasColumn('organizacion_institucion', 'ins_so_code')) {
            Schema::table('organizacion_institucion', function (Blueprint $table) {
                $table->dropColumn('ins_so_code');
            });
        }

        Schema::dropIfExists('organizacion_sede');
        Schema::dropIfExists('sede');
    }

    /**
     * Pasa el cliente que colgaba de la sede al local, si el local no lo tiene.
     *
     * En la base de desarrollo no hay nada que rescatar, pero en produccion no se
     * puede asumir lo mismo: perder el vinculo con el cliente dejaria locales sin
     * dueño y sin forma de reconstruirlo.
     */
    private function rescatarClientes(): void
    {
        if (!Schema::hasTable('organizacion_sede') || !Schema::hasColumn('organizacion_institucion', 'ins_so_code')) {
            return;
        }

        DB::table('organizacion_institucion as oi')
            ->join('organizacion_sede as os', 'os.so_code', '=', 'oi.ins_so_code')
            ->whereNull('oi.ins_cliente_id')
            ->whereNotNull('os.so_org_code')
            ->select('oi.ins_code', 'os.so_org_code')
            ->get()
            ->each(function ($fila) {
                DB::table('organizacion_institucion')
                    ->where('ins_code', $fila->ins_code)
                    ->update(['ins_cliente_id' => $fila->so_org_code]);
            });
    }

    public function down(): void
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

        Schema::create('organizacion_sede', function (Blueprint $table) {
            $table->bigIncrements('so_code');
            $table->unsignedBigInteger('so_ps_code')->nullable();
            $table->unsignedBigInteger('so_org_code')->nullable();
            $table->boolean('so_estado')->default(true);
            $table->unsignedBigInteger('so_created_user')->nullable();
            $table->unsignedBigInteger('so_updated_user')->nullable();
            $table->timestamps();
        });

        Schema::table('organizacion_institucion', function (Blueprint $table) {
            $table->unsignedBigInteger('ins_so_code')->nullable();
        });
    }
};
