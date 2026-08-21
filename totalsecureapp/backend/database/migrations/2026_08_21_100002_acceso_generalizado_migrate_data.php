<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Migrar datos vehiculares a acceso_vehiculo (antes de convertir ac_tipo)
        DB::statement("
            INSERT INTO acceso_vehiculo
                (av_ac_code, av_patente, av_empresa, av_is_sello, av_is_neumatico,
                 av_is_carro, av_pta_llave, av_kms, created_at, updated_at)
            SELECT a.ac_code, a.ac_patente, a.ac_empresa, a.ac_is_sello, a.ac_is_neumatico,
                   a.ac_is_carro, a.ac_pta_llave, a.ac_kms,
                   COALESCE(a.ac_created_at, NOW()), NOW()
            FROM acceso a
            WHERE (
                a.ac_patente IS NOT NULL
                OR a.ac_empresa IS NOT NULL
                OR a.ac_kms IS NOT NULL
                OR a.ac_is_sello
                OR a.ac_is_neumatico
                OR a.ac_is_carro
                OR a.ac_pta_llave
            )
            AND NOT EXISTS (SELECT 1 FROM acceso_vehiculo v WHERE v.av_ac_code = a.ac_code)
        ");

        // 2. Convertir ac_tipo de integer a string (sin DBAL, SQL nativo)
        DB::statement("
            ALTER TABLE acceso ALTER COLUMN ac_tipo TYPE varchar(20)
            USING (
                CASE ac_tipo
                    WHEN 1 THEN 'peatonal'
                    WHEN 2 THEN 'empleado'
                    WHEN 3 THEN 'visitante'
                    WHEN 4 THEN 'vehicular'
                    ELSE NULL
                END
            )
        ");

        // 3. Nuevas columnas de estado y token QR
        Schema::table('acceso', function (Blueprint $table) {
            $table->string('ac_estado_acceso', 20)->default('en_curso');
            $table->string('ac_token', 64)->nullable();
        });

        // 4. Backfill del estado segun entrada/salida
        DB::statement("
            UPDATE acceso
            SET ac_estado_acceso = CASE WHEN ac_is_entrada = 1 THEN 'en_curso' ELSE 'completada' END
        ");

        // 5. Eliminar columnas vehiculares (ya viven en acceso_vehiculo)
        //    Se conservan: ac_temperatura, ac_bicicleta, ac_is_acomp, ac_nomb_acomp, ac_rut_acomp
        Schema::table('acceso', function (Blueprint $table) {
            $table->dropColumn([
                'ac_patente',
                'ac_empresa',
                'ac_is_sello',
                'ac_is_neumatico',
                'ac_is_carro',
                'ac_pta_llave',
                'ac_kms',
                'ac_nombre_contrato',
            ]);
        });
    }

    public function down(): void
    {
        // 1. Restaurar columnas vehiculares
        Schema::table('acceso', function (Blueprint $table) {
            $table->string('ac_patente')->nullable();
            $table->string('ac_empresa')->nullable();
            $table->boolean('ac_is_sello')->default(false);
            $table->boolean('ac_is_neumatico')->default(false);
            $table->boolean('ac_is_carro')->default(false);
            $table->boolean('ac_pta_llave')->default(false);
            $table->string('ac_kms')->nullable();
            $table->string('ac_nombre_contrato')->nullable();
        });

        // 2. Copiar datos de vuelta
        DB::statement("
            UPDATE acceso a
            SET ac_patente      = v.av_patente,
                ac_empresa      = v.av_empresa,
                ac_is_sello     = v.av_is_sello,
                ac_is_neumatico = v.av_is_neumatico,
                ac_is_carro     = v.av_is_carro,
                ac_pta_llave    = v.av_pta_llave,
                ac_kms          = v.av_kms
            FROM acceso_vehiculo v
            WHERE v.av_ac_code = a.ac_code
        ");

        // 3. Convertir ac_tipo de string a integer
        DB::statement("
            ALTER TABLE acceso ALTER COLUMN ac_tipo TYPE integer
            USING (
                CASE ac_tipo
                    WHEN 'peatonal'  THEN 1
                    WHEN 'empleado'  THEN 2
                    WHEN 'visitante' THEN 3
                    WHEN 'vehicular' THEN 4
                    ELSE NULL
                END
            )
        ");

        // 4. Eliminar columnas nuevas
        Schema::table('acceso', function (Blueprint $table) {
            $table->dropColumn(['ac_estado_acceso', 'ac_token']);
        });
    }
};
