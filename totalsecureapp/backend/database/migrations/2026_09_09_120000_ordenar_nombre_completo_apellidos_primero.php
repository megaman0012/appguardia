<?php

use App\Support\NombreDePersona;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deja `usu_nmbcom` con los apellidos primero, pero SOLO donde es seguro.
 *
 * **El problema.** No habia convencion: de los 131 usuarios con el desglose
 * cargado, 45 tenian el nombre completo como «apellidos nombres» y 54 al reves.
 * El listado de 880 personas quedaba ordenado por una mezcla de apellido y
 * nombre de pila. El 2026-09-09 se decidio: **apellidos primero**, que es como
 * se busca a alguien en una nomina y como figura en la credencial.
 *
 * **Por que no se normaliza todo.** Una corrida en seco mostro que reordenar a
 * ciegas usando el desglose DESTRUYE nombres reales, porque en algunas filas el
 * desglose tiene basura mientras el nombre completo esta bien:
 *
 *     387  DAZA GONZALEZ JUAN JOSE          ->  duplicado duplicado
 *     587  ALCIVAR ALMEIDA ORLANDO ALADINO  ->  duplicado duplicado
 *     431  00000000000ANDRES UBALDO ...     ->  00000000000LAJE 00000000000CARDENAS ...
 *
 * Alguien escribio «duplicado» en `usu_ape1`/`usu_nmb1` y el ETL arrastro un
 * prefijo de ceros. Son datos de personas: perderlos no se arregla despues.
 *
 * **La regla, entonces:** se reordena unicamente cuando el resultado tiene
 * EXACTAMENTE las mismas palabras que el original. Si aparece, desaparece o
 * cambia una sola, la fila queda intacta y se informa. Es una comprobacion que
 * no depende de confiar en el desglose: compara el antes con el despues.
 *
 * Los 749 usuarios sin desglose NO se tocan: ahi no hay forma de saber donde
 * terminan los apellidos, y adivinar sobre nombres de personas reales no vale
 * el riesgo. Se corrigen solos a medida que alguien los edite, que es cuando el
 * formulario muestra la suposicion y la puede confirmar.
 */
return new class extends Migration
{
    public function up(): void
    {
        $candidatos = DB::table('users')
            ->whereNotNull('usu_ape1')->where('usu_ape1', '<>', '')
            ->whereNotNull('usu_nmb1')->where('usu_nmb1', '<>', '')
            ->select('id', 'usu_nmbcom', 'usu_nmb1', 'usu_nmb2', 'usu_ape1', 'usu_ape2')
            ->get();

        $normalizados = 0;
        $yaEstaban    = 0;
        $omitidos     = [];

        foreach ($candidatos as $u) {
            $actual = trim((string) $u->usu_nmbcom);
            $partes = NombreDePersona::descomponer($u);
            $nuevo  = NombreDePersona::componer($partes['nombres'], $partes['apellidos'])['usu_nmbcom'];

            if ($actual === $nuevo) {
                $yaEstaban++;
                continue;
            }

            if (!$this->mismasPalabras($actual, $nuevo)) {
                $omitidos[] = "  id {$u->id}: «{$actual}» daria «{$nuevo}»";
                continue;
            }

            DB::table('users')->where('id', $u->id)->update(['usu_nmbcom' => $nuevo]);
            $normalizados++;
        }

        echo "Nombres reordenados: {$normalizados}\n";
        echo "Ya estaban bien: {$yaEstaban}\n";
        echo 'Omitidos por no ser reordenamientos limpios: ' . count($omitidos) . "\n";

        foreach ($omitidos as $linea) {
            echo $linea . "\n";
        }

        if ($omitidos !== []) {
            echo "\n⚠️  Esos quedaron INTACTOS a proposito: el desglose no coincide con el\n";
            echo "    nombre completo, asi que hay que revisarlos a mano desde el panel.\n";
        }
    }

    /**
     * ¿El reordenamiento conserva exactamente las mismas palabras?
     *
     * Comparacion **sensible a mayusculas y tildes** a proposito. Una fila que
     * pasaria de «Daniel Andrés» a «DANIEL ANDRES» tiene las mismas palabras
     * pero pierde la tilde y el formato: no es un reordenamiento, es una
     * reescritura, y no es lo que esta migracion vino a hacer.
     */
    private function mismasPalabras(string $antes, string $despues): bool
    {
        $partir = static function (string $t): array {
            $p = preg_split('/\s+/', $t, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            sort($p);

            return $p;
        };

        return $partir($antes) === $partir($despues);
    }

    /**
     * Sin vuelta atras, y es deliberado.
     *
     * Reordenar palabras no se puede deshacer sin haber guardado el original, y
     * guardar una copia de 880 nombres para poder revertir un cambio que esta
     * demostrado que no pierde ninguna palabra es mas riesgo que beneficio: una
     * tabla mas para olvidarse de borrar, con datos personales adentro.
     *
     * La migracion es idempotente: correrla de nuevo no cambia nada, porque las
     * filas ya quedaron en el orden que ella misma produce.
     */
    public function down(): void
    {
    }
};
