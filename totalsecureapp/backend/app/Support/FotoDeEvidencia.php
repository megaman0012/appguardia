<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Donde esta la foto de una evidencia, y por que a veces no estaba.
 *
 * Las fotos de la operacion --marcaciones, rondas, accesos, novedades, bitacora--
 * viven en `public/images/<modulo>/AAAA/MM/DD/<archivo>`. El nombre del archivo
 * se guarda en la fila, y la carpeta **se reconstruia** a partir de la fecha del
 * registro.
 *
 * ⚠️ **Ahi estaba el problema.** `generalTrait::storeFiles()` arma la carpeta con
 * `Carbon::now()` -- el momento en que el servidor recibe el archivo -- mientras
 * los accesores la reconstruian con la fecha del HECHO (`nv_fecha_hora`,
 * `rd_fecha_hora`, `bt_fecha_hora`), que la manda el dispositivo.
 *
 * Mientras se crea y se sincroniza el mismo dia, las dos fechas coinciden y todo
 * funciona. Pero la app guarda en cola lo que no pudo enviar: **una novedad
 * escrita a las 23:50 y sincronizada a las 00:10 queda con la foto en la carpeta
 * de un dia y el registro apuntando a la del otro.** La foto existe en el disco
 * y no se ve en ninguna parte. No hay error, no hay log: simplemente no aparece.
 *
 * Esta clase resuelve las dos formas de guardar:
 *
 * - **Ruta relativa** (`novedad/2026/09/15/foto.jpg`), que es lo que se guarda
 *   desde ahora: no hay nada que reconstruir, asi que el problema no puede
 *   repetirse.
 * - **Solo el nombre**, como estan los registros historicos. Se reconstruye con
 *   la fecha, y **si ahi no esta, se busca en los dias contiguos**, que es donde
 *   la deja el desfase de la sincronizacion.
 */
class FotoDeEvidencia
{
    /** Dias hacia atras y adelante donde buscar una foto que no esta donde dice. */
    private const MARGEN_DIAS = 2;

    /**
     * La URL publica de la foto, o null si no esta en el disco.
     *
     * @param string|null $guardado  lo que hay en la columna: ruta relativa o nombre
     * @param string      $modulo    'novedad', 'rondas', 'accesos', 'biometria', 'bitacora'
     * @param mixed       $fecha     fecha del registro, para reconstruir la carpeta
     */
    public static function url(?string $guardado, string $modulo, $fecha): ?string
    {
        $relativa = self::rutaRelativa($guardado, $modulo, $fecha);

        return $relativa === null ? null : asset('images/' . $relativa);
    }

    /**
     * La ruta relativa a `public/images` donde esta realmente la foto.
     */
    public static function rutaRelativa(?string $guardado, string $modulo, $fecha): ?string
    {
        $guardado = trim((string) $guardado);

        if ($guardado === '') {
            return null;
        }

        // Forma nueva: ya viene la ruta completa, no hay nada que adivinar.
        if (str_contains($guardado, '/')) {
            return file_exists(public_path('images/' . $guardado)) ? $guardado : null;
        }

        if (empty($fecha)) {
            return null;
        }

        try {
            $dia = Carbon::parse($fecha);
        } catch (\Throwable $e) {
            return null;
        }

        // Donde deberia estar segun la fecha del registro.
        $ruta = $modulo . '/' . $dia->format('Y/m/d') . '/' . $guardado;
        if (file_exists(public_path('images/' . $ruta))) {
            return $ruta;
        }

        /*
         * No esta donde dice: se busca en los dias contiguos.
         *
         * Es el caso de la sincronizacion diferida, y por eso el margen es
         * chico y simetrico. Ampliarlo mas seria empezar a adivinar: dos fotos
         * de dias distintos pueden llamarse igual, porque el nombre solo lleva
         * el usuario y la marca de tiempo del servidor.
         */
        for ($i = 1; $i <= self::MARGEN_DIAS; $i++) {
            foreach ([$dia->copy()->addDays($i), $dia->copy()->subDays($i)] as $vecino) {
                $ruta = $modulo . '/' . $vecino->format('Y/m/d') . '/' . $guardado;
                if (file_exists(public_path('images/' . $ruta))) {
                    return $ruta;
                }
            }
        }

        return null;
    }
}
