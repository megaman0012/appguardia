<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El catalogo de productos deja de ser por local.
 *
 * **Lo que habia, medido en produccion:** 532 filas en `inv_producto_catalogo`
 * que son **4 productos distintos** --Baston retractil, Bodycam, Forros de
 * chaleco y Linterna tactica-- repetidos en **133 locales**. Un baston es el
 * mismo objeto en los 133 sitios; lo que cambia por local es **cuantos hay**, y
 * eso ya vive en `inv_lista_item.lia_cantidad_default`.
 *
 * **Lo que costaba:** agregar un quinto producto eran 133 inserciones, y
 * renombrar uno, 133 ediciones. La duplicacion ya se habia degradado sola --en
 * los nombres de lista hay `SEGURIDA FISICA` y `SEGURIDAD FISICA 1` junto a
 * `SEGURIDAD FISICA`--, que es exactamente lo que pasa cuando el mismo dato se
 * copia a mano cien veces.
 *
 * **La columna se deja, en nulo, en vez de borrarla.** `ipc_ins_code` pasa a
 * admitir nulos y **nulo significa «global»**. Borrarla obligaria a tocar de un
 * golpe la clave foranea, el indice, el ETL, los dos seeders, el modelo y el
 * recurso, y dejaria un `down()` que no puede devolver el dato. Asi la fusion se
 * puede verificar contra produccion antes de tocar el esquema de verdad.
 * Quitarla del todo queda como limpieza posterior.
 *
 * ⚠️ Esta migracion **no mueve datos**: solo abre la puerta. La fusion la hace
 * `inventario:fusionar-catalogo`, que simula por defecto y hay que revisar antes
 * de aplicar -- son 23.799 filas de `inv_movimiento_detalle` las que se
 * remapean.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inv_producto_catalogo', function (Blueprint $table) {
            /*
             * La clave foranea se cae y se vuelve a poner: PostgreSQL no deja
             * cambiar a nulable una columna referenciada sin rehacerla, y una
             * FK a nulos es valida (nulo no referencia nada).
             */
            $table->dropForeign(['ipc_ins_code']);
        });

        Schema::table('inv_producto_catalogo', function (Blueprint $table) {
            $table->bigInteger('ipc_ins_code')->nullable()->change();

            $table->foreign('ipc_ins_code')
                ->references('ins_code')
                ->on('organizacion_institucion')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        /*
         * Volver atras exige que no queden globales: una fila con `ipc_ins_code`
         * nulo no tiene local al que regresar, y poner cualquiera seria inventar
         * el dato. Si hay globales, la bajada falla a proposito en vez de
         * corromper el catalogo en silencio.
         */
        $globales = \Illuminate\Support\Facades\DB::table('inv_producto_catalogo')
            ->whereNull('ipc_ins_code')->count();

        if ($globales > 0) {
            throw new RuntimeException(
                "Hay {$globales} productos globales (ipc_ins_code nulo). Revertir "
                . 'exigiria inventarles un local. Reasignelos antes de bajar esta migracion.'
            );
        }

        Schema::table('inv_producto_catalogo', function (Blueprint $table) {
            $table->dropForeign(['ipc_ins_code']);
        });

        Schema::table('inv_producto_catalogo', function (Blueprint $table) {
            $table->bigInteger('ipc_ins_code')->nullable(false)->change();

            $table->foreign('ipc_ins_code')
                ->references('ins_code')
                ->on('organizacion_institucion')
                ->cascadeOnDelete();
        });
    }
};
