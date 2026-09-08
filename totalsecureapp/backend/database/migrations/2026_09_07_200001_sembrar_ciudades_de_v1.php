<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las ciudades que hacen falta para poder cargar los 137 locales de v1.
 *
 * En v1 la ciudad de un local era TEXTO LIBRE (`ins_ciudad varchar(250)`),
 * escrito a mano y sin catalogo: 16 valores distintos con mayusculas
 * inconsistentes, abreviaturas y un dato sucio. En v2 es `ins_cd_id`, FK a
 * `ciudad`, y el catalogo solo traia Guayaquil.
 *
 * Esto importa mas de lo que parece: el alcance del Lider Operativo se calcula
 * subiendo ciudad -> provincia -> pais. **Un local sin ciudad no pertenece a
 * ningun pais, asi que ningun lider lo ve.** Sin estas filas, 42 de los 137
 * locales quedarian invisibles.
 *
 * Idempotente por (cd_pr_id, cd_nombre), igual que el sembrado de paises y
 * provincias de 2026_08_24_200001.
 */
return new class extends Migration
{
    /**
     * Ciudad normalizada => provincia, con el texto de v1 que la origina.
     *
     * Decisiones de normalizacion que conviene poder discutir:
     *  - `MANTENIMIENTO` NO es una ciudad: alguien escribio un area en el campo.
     *    Por decision del usuario (2026-09-07) ese local va a Guayaquil.
     *  - `VILLAMIL PLAYAS` es el canton Playas, en **Guayas**, no en Santa Elena.
     *    Se confunden porque Playas queda en la costa entre las dos provincias.
     *  - `SAN CRISTOBAL` y `BALTRA` son islas de Galapagos, no ciudades. Se
     *    cargan con el nombre que usa la operacion, no el oficial
     *    (Puerto Baquerizo Moreno / Santa Cruz), porque es el que reconoce quien
     *    mira el panel. Baltra es donde esta el aeropuerto.
     *  - `STO. DOMINGO` -> Santo Domingo, en Santo Domingo de los Tsachilas.
     */
    private const CIUDADES = [
        // [provincia, ciudad normalizada, texto original en v1, locales]
        ['Guayas',                         'Guayaquil',        'Guayaquil',       95],
        ['Pichincha',                      'Quito',            'QUITO',           16],
        ['Manabí',                         'Manta',            'MANTA',           11],
        ['Azuay',                          'Cuenca',           'CUENCA',           2],
        ['Tungurahua',                     'Ambato',           'AMBATO',           2],
        ['Manabí',                         'Portoviejo',       'PORTOVIEJO',       1],
        ['Imbabura',                       'Ibarra',           'IBARRA',           1],
        ['Santo Domingo de los Tsáchilas', 'Santo Domingo',    'STO. DOMINGO',     1],
        ['Guayas',                         'Durán',            'DURÁN',            1],
        ['Guayas',                         'Naranjal',         'NARANJAL',         1],
        ['Guayas',                         'El Triunfo',       'EL TRIUNFO',       1],
        ['Guayas',                         'Nobol',            'Nobol',            1],
        ['Guayas',                         'Playas',           'VILLAMIL PLAYAS',  1],
        ['Galápagos',                      'San Cristóbal',    'SAN CRISTOBAL',    1],
        ['Galápagos',                      'Baltra',           'BALTRA',           1],
    ];

    public function up(): void
    {
        $provincias = DB::table('provincia')->pluck('pr_id', 'pr_nombre');

        foreach (self::CIUDADES as [$provincia, $ciudad, , ]) {
            $prId = $provincias[$provincia] ?? null;

            // Si falta la provincia es que el sembrado de geografia no corrio o
            // cambio de nombre. Fallar aqui es mejor que crear la ciudad
            // colgando de la provincia equivocada.
            if ($prId === null) {
                throw new RuntimeException("No existe la provincia '{$provincia}'. ¿Corrio 2026_08_24_200001?");
            }

            DB::table('ciudad')->updateOrInsert(
                ['cd_pr_id' => $prId, 'cd_nombre' => $ciudad],
                ['cd_estado' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // "Por ahora solo Ecuador" (decision del 2026-09-07). Se DESACTIVA en vez
        // de borrarse: el README dice que se opera en Colombia, y "por ahora"
        // implica que puede volver. Borrarla obligaria a recrearla con otro id y
        // a rehacer cualquier vinculo.
        DB::table('pais')->where('pa_iso2', 'CO')->update([
            'pa_estado'  => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('pais')->where('pa_iso2', 'CO')->update([
            'pa_estado'  => true,
            'updated_at' => now(),
        ]);

        // Solo se borran las ciudades que ningun local este usando: si el ETL ya
        // corrio, revertir esto no puede dejar 137 locales sin ciudad.
        foreach (self::CIUDADES as [$provincia, $ciudad, , ]) {
            if ($ciudad === 'Guayaquil') {
                continue; // no la creo esta migracion
            }

            $prId = DB::table('provincia')->where('pr_nombre', $provincia)->value('pr_id');
            if ($prId === null) {
                continue;
            }

            $cdId = DB::table('ciudad')
                ->where('cd_pr_id', $prId)->where('cd_nombre', $ciudad)->value('cd_id');

            if ($cdId && !DB::table('organizacion_institucion')->where('ins_cd_id', $cdId)->exists()) {
                DB::table('ciudad')->where('cd_id', $cdId)->delete();
            }
        }
    }
};
