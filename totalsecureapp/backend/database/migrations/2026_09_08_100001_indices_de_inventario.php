<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dos indices para el camino de escritura del inventario.
 *
 * Ninguno cambia el contrato de la API, asi que **no obliga a recompilar el
 * APK**.
 *
 * **1. El chequeo de duplicado no tenia indice que lo cubriera.**
 * `saveListMov` pregunta, antes de crear una recepcion, si ya existe una para
 * (local, lista, usuario, tipo) sin cancelar. Con los indices de una sola
 * columna, Postgres entraba por `idx_movimiento_lista` y **filtraba las otras
 * cuatro condiciones a mano**: unas 90 filas por lista hoy, y creciendo con una
 * recepcion por turno por local (137 locales). El indice compuesto lo convierte
 * en un acierto directo.
 *
 * **2. NO se agrega un indice unico, y vale saber por que se descarto.** La idea
 * era impedir dos recepciones vivas de la misma lista desde la base. Pero un
 * guardia recibe la misma lista **una vez por turno**, asi que la unicidad por
 * (local, lista, guardia) es falsa: los datos migrados tienen 346 grupos que la
 * violarian. Lo que distingue una recepcion abierta de una cerrada no es la
 * combinacion de claves, es si tiene una devolucion posterior -- y eso no se
 * puede expresar en un indice unico.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE INDEX idx_movimiento_ciclo
            ON inv_movimiento_cabecera (mc_ins_code, mc_lista_id, mc_usuario_id, mc_tipo, mc_fecha)
        ');

    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_movimiento_ciclo');
    }
};
