<?php

namespace App\Support;

/**
 * El nombre de una persona, en un solo lugar.
 *
 * **Por que existe.** `users` guarda el nombre CINCO veces: `usu_nmbcom` con el
 * nombre completo, mas `usu_nmb1`, `usu_nmb2`, `usu_ape1` y `usu_ape2` con las
 * piezas. El formulario del panel pedia las cinco, todas obligatorias, mientras
 * que `usuario:crear` y la carga masiva pedian solo dos («nombres» y
 * «apellidos») y derivaban el resto -- cada uno con su propia copia de la misma
 * logica. Esta clase es esa logica, escrita una vez.
 *
 * **Que campo importa de verdad.** `usu_nmbcom` se lee en 52 lugares del
 * codigo; las cuatro piezas, en uno solo y como respaldo. Y **749 de 880
 * usuarios (85%) las tienen vacias**. Son herencia de coredt360: se completan
 * porque la base las exige, no porque alguien las use.
 *
 * **El orden es APELLIDOS y despues NOMBRES.** Decidido el 2026-09-09. Asi el
 * listado de 880 personas queda ordenado por apellido, que es como se busca a
 * alguien en una nomina y como figura en la credencial del guardia. Hasta
 * entonces no habia convencion: de los 131 usuarios con desglose, 45 estaban en
 * un orden y 54 en el otro.
 */
class NombreDePersona
{
    /**
     * De «nombres» + «apellidos» a las cinco columnas.
     *
     * @return array{usu_nmbcom: string, usu_nmb1: string, usu_nmb2: string, usu_ape1: string, usu_ape2: string}
     */
    public static function componer(?string $nombres, ?string $apellidos): array
    {
        $nombres   = self::normalizar($nombres);
        $apellidos = self::normalizar($apellidos);

        $pn = self::palabras($nombres);
        $pa = self::palabras($apellidos);

        return [
            // Apellidos primero. `trim` porque uno de los dos puede venir vacio
            // y si no quedaria un espacio al principio o al final.
            'usu_nmbcom' => trim($apellidos . ' ' . $nombres),

            // ⚠️ Las cuatro son NOT NULL. Cuando la persona tiene un solo
            // nombre o un solo apellido se repite el primero: no es un descuido,
            // es lo que hizo el ETL con los 665 que vinieron sin desglosar, y
            // cambiarlo ahora dejaria dos convenciones conviviendo.
            'usu_nmb1' => $pn[0] ?? '',
            'usu_nmb2' => $pn[1] ?? ($pn[0] ?? ''),
            'usu_ape1' => $pa[0] ?? '',
            'usu_ape2' => $pa[1] ?? ($pa[0] ?? ''),
        ];
    }

    /**
     * El camino inverso, para poder EDITAR un usuario ya cargado.
     *
     * @param  object|array $usuario  El registro, con las columnas `usu_*`.
     * @return array{nombres: string, apellidos: string, adivinado: bool}
     */
    public static function descomponer($usuario): array
    {
        $c = fn (string $campo) => trim((string) (is_array($usuario) ? ($usuario[$campo] ?? '') : ($usuario->$campo ?? '')));

        $nmb1 = $c('usu_nmb1');
        $nmb2 = $c('usu_nmb2');
        $ape1 = $c('usu_ape1');
        $ape2 = $c('usu_ape2');

        // El caso bueno: la fila trae el desglose. Son 131 de 880.
        if ($nmb1 !== '' && $ape1 !== '') {
            return [
                // Si el segundo repite al primero es la marca del ETL: significa
                // «no tiene segundo», no que se llame igual dos veces.
                'nombres'   => self::unir($nmb1, $nmb2),
                'apellidos' => self::unir($ape1, $ape2),
                'adivinado' => false,
            ];
        }

        // El caso feo: solo hay nombre completo, y **no hay forma de saber donde
        // terminan los apellidos**. Se asume el patron dominante en Ecuador y en
        // esta base -- dos apellidos primero -- pero es una SUPOSICION, y quien
        // edite tiene que verla. De ahi el `adivinado`, que el formulario usa
        // para avisar.
        //
        // Rompe con los apellidos compuestos: «DE LA TORRE PEREZ JUAN» sale mal
        // y hay que corregirlo a mano. No hay heuristica que lo resuelva bien.
        $palabras = self::palabras($c('usu_nmbcom'));

        if (count($palabras) <= 2) {
            // Con dos palabras o menos, partir por la mitad es tan arbitrario
            // como cualquier cosa: se deja todo como apellido, que es lo que
            // menos informacion inventa.
            return ['nombres' => '', 'apellidos' => implode(' ', $palabras), 'adivinado' => count($palabras) > 0];
        }

        return [
            'apellidos' => implode(' ', array_slice($palabras, 0, 2)),
            'nombres'   => implode(' ', array_slice($palabras, 2)),
            'adivinado' => true,
        ];
    }

    /** Une la primera y la segunda pieza, salteando la repeticion del ETL. */
    private static function unir(string $primera, string $segunda): string
    {
        if ($segunda === '' || mb_strtoupper($segunda) === mb_strtoupper($primera)) {
            return $primera;
        }

        return $primera . ' ' . $segunda;
    }

    /** @return list<string> */
    private static function palabras(string $texto): array
    {
        return preg_split('/\s+/', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private static function normalizar(?string $texto): string
    {
        // Los espacios de mas llegan solos al copiar y pegar de una planilla, y
        // un nombre con dos espacios en el medio rompe la busqueda por texto.
        return trim(preg_replace('/\s+/', ' ', (string) $texto) ?? '');
    }
}
