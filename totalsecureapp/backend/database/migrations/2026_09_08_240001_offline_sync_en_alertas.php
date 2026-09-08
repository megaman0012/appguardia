<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `client_uuid` en las alertas, para que el boton de panico no duplique.
 *
 * Es el octavo endpoint de campo que lo lleva, y el que mas lo necesita: se
 * toca **bajo estres**. Un guardia que ve algo raro aprieta, no ve respuesta
 * porque la red del sitio es mala, y aprieta otra vez. Sin idempotencia eso
 * son dos alertas para el mismo hecho, dos avisos al supervisor y dos entradas
 * en el historial.
 *
 * ⚠️ Aca el indice unique **no puede ser sobre `al_client_uuid` a secas**. La
 * tabla `alertas` usa clave compuesta con `al_anio`, y su PK no es un id
 * simple; el uuid igual es unico por si mismo, pero se declara con nombre
 * propio para no chocar con los indices que ya trae.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas', function (Blueprint $tabla) {
            $tabla->string('al_client_uuid', 36)->nullable();
            $tabla->timestamp('al_sincronizado_en')->nullable();

            // En Postgres varios NULL no colisionan, asi que las alertas
            // historicas y las que llegan sin el campo (el APK ya instalado)
            // siguen siendo validas.
            $tabla->unique('al_client_uuid', 'alertas_al_client_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('alertas', function (Blueprint $tabla) {
            $tabla->dropUnique('alertas_al_client_uuid_unique');
            $tabla->dropColumn(['al_client_uuid', 'al_sincronizado_en']);
        });
    }
};
