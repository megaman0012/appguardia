<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deja por escrito si la ubicacion de un marcaje/acceso se pudo comprobar.
 *
 * El problema: un local SIN marcador activo aceptaba marcajes desde cualquier
 * lugar y el registro quedaba identico a uno comprobado de verdad. Un marcaje
 * hecho a 300 km se veia igual que uno hecho en la garita, asi que nadie podia
 * auditar la asistencia sin revisar a mano si el local tenia marcadores.
 *
 * Se elige NULLABLE a proposito: colapsar los estados en un booleano volveria
 * a esconder el problema. La columna se lee JUNTO con la distancia:
 *   verificada=true                estaba dentro del radio
 *   verificada=false + distancia   se midio y estaba FUERA del radio
 *   verificada=false + null        no se pudo medir: el local no tiene marcador
 *                                  activo, o el dispositivo no dio ubicacion
 *   verificada=null                fila anterior a esta migracion, no se sabe
 *
 * En biometria el caso "fuera del radio" no llega a guardarse porque el marcaje
 * se rechaza antes; en acceso si, porque ahi la medicion no bloquea.
 *
 * No se rechaza nada que antes se aceptara: un guardia no pierde su marcaje
 * porque a alguien le falto configurar el local, y un visitante no se queda
 * afuera por un GPS con mala señal. Bloquear es una decision de negocio aparte
 * y hay que tomarla a la vista de cuantas filas salen sin verificar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_has_biometria', function (Blueprint $table) {
            $table->boolean('bio_ubicacion_verificada')->nullable()->after('bio_lng');
            $table->decimal('bio_distancia_m', 10, 2)->nullable()->after('bio_ubicacion_verificada');
        });

        Schema::table('acceso', function (Blueprint $table) {
            $table->boolean('ac_ubicacion_verificada')->nullable()->after('ac_lng');
            $table->decimal('ac_distancia_m', 10, 2)->nullable()->after('ac_ubicacion_verificada');
        });

        // Para el panel: lo que se consulta es "traeme los no verificados", y sin
        // indice eso recorre la tabla entera de marcajes.
        Schema::table('user_has_biometria', function (Blueprint $table) {
            $table->index(['bio_ins_code', 'bio_ubicacion_verificada'], 'biometria_ins_verificada_idx');
        });
        Schema::table('acceso', function (Blueprint $table) {
            $table->index(['ac_ins_code', 'ac_ubicacion_verificada'], 'acceso_ins_verificada_idx');
        });
    }

    public function down(): void
    {
        Schema::table('user_has_biometria', function (Blueprint $table) {
            $table->dropIndex('biometria_ins_verificada_idx');
            $table->dropColumn(['bio_ubicacion_verificada', 'bio_distancia_m']);
        });
        Schema::table('acceso', function (Blueprint $table) {
            $table->dropIndex('acceso_ins_verificada_idx');
            $table->dropColumn(['ac_ubicacion_verificada', 'ac_distancia_m']);
        });
    }
};
