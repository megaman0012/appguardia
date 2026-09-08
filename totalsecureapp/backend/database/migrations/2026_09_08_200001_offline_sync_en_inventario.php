<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotencia por `client_uuid` en los movimientos de inventario.
 *
 * Inventario era el unico endpoint que **crea registros en campo** sin esta
 * proteccion: los otros cinco (biometria, los dos de rondas, acceso y novedad)
 * la tienen desde la Fase 7. Sin ella, un reintento de la tablet sin señal crea
 * un movimiento duplicado.
 *
 * Lo que habia en su lugar era un chequeo en PHP («¿ya hay una recepcion
 * abierta?»), que es una **carrera**: dos toques seguidos pueden pasar los dos
 * por el `if` antes de que el primero inserte. Y no se podia cerrar con un
 * indice unico sobre las claves, porque un guardia recibe la misma lista una vez
 * **por turno** (los datos migrados tienen 346 grupos que lo violarian): lo que
 * distingue una recepcion abierta de una cerrada es si tiene devolucion
 * posterior, y eso no cabe en un indice unico.
 *
 * **Nullable a proposito.** En Postgres varios NULL no colisionan en un indice
 * unico, asi que:
 *  - Las 11.879 filas migradas siguen validas.
 *  - **El APK ya compilado, que no envia `client_uuid`, sigue funcionando igual.**
 *    La idempotencia entra en juego recien cuando una app lo mande.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inv_movimiento_cabecera', function (Blueprint $table) {
            $table->string('mc_client_uuid', 36)->nullable()->unique();
            $table->timestamp('mc_sincronizado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inv_movimiento_cabecera', function (Blueprint $table) {
            $table->dropUnique(['mc_client_uuid']);
            $table->dropColumn(['mc_client_uuid', 'mc_sincronizado_en']);
        });
    }
};
