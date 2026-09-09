<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Saca los espacios de sobra de `usu_nmbcom`.
 *
 * Aparecieron al revisar el listado ya reordenado: dos filas encabezaban la
 * lista de 880 personas porque empiezan con un espacio, y el espacio ordena
 * antes que cualquier letra. Son **179 filas** con espacio al principio, al
 * final, o dos espacios en el medio:
 *
 *     [ JOSE DAVID  TROYA MAQUILON]
 *     [GUISEPPE DONATO AYERVE MERINO ]
 *     [MOLINA TULMO ROSA ]
 *
 * Llegan al copiar y pegar de una planilla. Ademas de descolocar el orden,
 * hacen que una busqueda por texto exacto no encuentre a la persona.
 *
 * **Esto no reordena ni reinterpreta nada**: colapsa espacios. Ninguna palabra
 * cambia, aparece ni desaparece, asi que no necesita la comprobacion cautelosa
 * de la migracion anterior -- aunque igual se verifica, porque una expresion
 * regular mal escrita podria comerse algo y es barato asegurarse.
 *
 * `NombreDePersona::componer()` ya normaliza los espacios, asi que los nombres
 * que se carguen o editen de ahora en adelante nacen limpios: esto es solo para
 * los que ya estaban.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sucios = DB::table('users')
            ->whereRaw("usu_nmbcom <> trim(usu_nmbcom) OR usu_nmbcom ~ '\\s{2,}'")
            ->select('id', 'usu_nmbcom')
            ->get();

        $limpiados = 0;
        $rechazados = [];

        foreach ($sucios as $u) {
            $limpio = trim(preg_replace('/\s+/', ' ', (string) $u->usu_nmbcom) ?? '');

            // Si al limpiar cambiara la cantidad de palabras, algo esta mal en
            // la expresion regular y no en el dato: mejor no tocar esa fila.
            if (count(preg_split('/\s+/', trim((string) $u->usu_nmbcom), -1, PREG_SPLIT_NO_EMPTY) ?: [])
                !== count(preg_split('/\s+/', $limpio, -1, PREG_SPLIT_NO_EMPTY) ?: [])) {
                $rechazados[] = $u->id;
                continue;
            }

            DB::table('users')->where('id', $u->id)->update(['usu_nmbcom' => $limpio]);
            $limpiados++;
        }

        echo "Nombres con espacios limpiados: {$limpiados}\n";

        if ($rechazados !== []) {
            echo 'Rechazados: ' . implode(', ', $rechazados) . "\n";
        }
    }

    /** Sin vuelta atras: no se guarda de que lado estaba cada espacio sobrante. */
    public function down(): void
    {
    }
};
