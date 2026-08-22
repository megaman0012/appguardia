<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indices compuestos para los filtros reales del codigo (Fase 9).
 *
 * Cada indice sale de contar los where/orderBy que el codigo ya hace, no de
 * suposiciones. Las tablas de alertas y turno ya traian los suyos de las fases
 * 3 y 4, asi que no se repiten aqui.
 *
 * El orden de las columnas importa: primero la de igualdad (institucion,
 * usuario, estado) y despues la de rango (fecha), que es como Postgres puede
 * usar el indice para el filtro y el ordenamiento a la vez.
 */
return new class extends Migration
{
    /** [tabla => [nombre corto => [columnas]]] */
    private array $indices = [
        // La consulta mas caliente del proyecto: 16 usos de ui_usu_id + ui_state
        // (cada request del portal y cada validacion de institucion de la app).
        'user_has_institucion' => [
            'uhi_usuario_estado'      => ['ui_usu_id', 'ui_state'],
            'uhi_institucion_estado'  => ['ui_ins_code', 'ui_state'],
        ],
        'acceso' => [
            'acceso_ins_fecha'   => ['ac_ins_code', 'ac_created_at'],
            'acceso_usu_fecha'   => ['ac_usu_id', 'ac_created_at'],
            'acceso_ins_estado'  => ['ac_ins_code', 'ac_estado_acceso'],
            'acceso_ins_tipo'    => ['ac_ins_code', 'ac_tipo'],
        ],
        'novedad' => [
            'novedad_ins_fecha' => ['nv_ins_code', 'nv_fecha_hora'],
            'novedad_usu_fecha' => ['nv_usu_id', 'nv_fecha_hora'],
        ],
        'ronda_cabecera' => [
            'ronda_cab_ins_fecha'  => ['rc_ins_code', 'rc_fecha_inicio'],
            'ronda_cab_usu_estado' => ['rc_usu_code', 'rc_estado_ronda'],
        ],
        'ronda_detalle' => [
            // rd_rc_id lo usa el withCount de puntos por ronda.
            'ronda_det_ronda_estado' => ['rd_rc_id', 'rd_estado'],
            'ronda_det_ins_fecha'    => ['rd_ins_code', 'rd_fecha_hora'],
            // Guard de "espere 5 minutos" al reescanear un marcador.
            'ronda_det_marcador_usu' => ['rd_im_code', 'rd_usu_id'],
        ],
        'user_has_biometria' => [
            'biometria_ins_fecha' => ['bio_ins_code', 'bio_created_at'],
            'biometria_usu_fecha' => ['bio_user_id', 'bio_created_at'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indices as $tabla => $indices) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) use ($tabla, $indices) {
                foreach ($indices as $nombre => $columnas) {
                    // Idempotente: re-ejecutar la migracion no debe fallar.
                    if ($this->existeIndice($nombre)) {
                        continue;
                    }
                    $table->index($columnas, $nombre);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indices as $tabla => $indices) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) use ($indices) {
                foreach (array_keys($indices) as $nombre) {
                    if ($this->existeIndice($nombre)) {
                        $table->dropIndex($nombre);
                    }
                }
            });
        }
    }

    private function existeIndice(string $nombre): bool
    {
        return \DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->where('indexname', $nombre)
            ->exists();
    }
};
