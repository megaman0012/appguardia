<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Devuelve el signo a las coordenadas de los marcadores QR.
 *
 * **El bug, y era grave.** 64 de 118 marcadores tenian latitud y longitud
 * POSITIVAS. Ecuador esta al oeste del meridiano de Greenwich y casi todo al
 * sur del ecuador: la longitud es **siempre** negativa, y la latitud solo es
 * positiva en el norte del pais (Ibarra, +0.34). Un marcador de Guayaquil
 * guardado como `2.048401 / 79.888372` en vez de `-2.048401 / -79.888372`
 * queda del otro lado del planeta.
 *
 * `PresenceValidationService::validarUbicacion()` **bloquea** cuando la
 * distancia supera el radio del local (100 m por defecto). Con el signo
 * invertido la distancia calculada era de **17.764 km**, asi que:
 *
 *  - En **61 de 110 locales con marcador activo, el marcaje biometrico era
 *    imposible.** El guardia recibia «Fuera de geocerca (17764506m)» y no habia
 *    forma de registrar entrada ni salida.
 *  - Las rondas por QR usan `validarPresencia()`, que bloquea igual.
 *
 * Los signos **ya venian mal de v1** -- la misma cuenta ahi: 65 latitudes
 * positivas de 118 -- y el ETL los copio fielmente. No es un fallo de la
 * migracion: es un dato que nunca estuvo bien y que en v1 no se notaba porque
 * v1 no comparaba la ubicacion contra nada.
 *
 * ⚠️ **No se puede hacer `-abs()` a ciegas.** `LA FABRIL IBARRA` tiene latitud
 * +0.338139 y **es correcta**: Ibarra esta al norte del ecuador. La regla es
 * por rango: se corrige solo si al invertir el punto cae dentro de Ecuador
 * continental e insular (lat -5.0 a 1.5, lng -81.2 a -75.2). Con eso Ibarra
 * queda intacta.
 *
 * Queda uno sin tocar a proposito: `im_code = 3` («CCO», del local Oficina
 * Garzota) esta en 46.0/78.0, que es relleno, y esta **inactivo**. Invertirlo
 * daria otro punto igual de falso; se deja como esta y se corrige a mano si
 * alguna vez se activa.
 */
return new class extends Migration
{
    /** Ecuador continental e insular, con margen. */
    private const LAT_MIN = -5.0;
    private const LAT_MAX = 1.5;
    private const LNG_MIN = -81.2;
    private const LNG_MAX = -75.2;

    public function up(): void
    {
        $marcadores = DB::table('institucion_marcadores')
            ->select('im_code', 'im_lat', 'im_lng')
            ->whereRaw("im_lat ~ '^-?[0-9.]+$'")
            ->whereRaw("im_lng ~ '^-?[0-9.]+$'")
            ->get();

        $corregidos = 0;

        foreach ($marcadores as $m) {
            $lat = (float) $m->im_lat;
            $lng = (float) $m->im_lng;

            if ($this->dentroDeEcuador($lat, $lng)) {
                continue;
            }

            $nLat = $lat > self::LAT_MAX ? -$lat : $lat;
            $nLng = $lng > 0 ? -$lng : $lng;

            // Solo se toca si el resultado es creible. Si al invertir sigue
            // fuera del pais, el dato esta mal por otra razon y cambiarlo solo
            // esconderia el problema.
            if (!$this->dentroDeEcuador($nLat, $nLng)) {
                continue;
            }

            DB::table('institucion_marcadores')
                ->where('im_code', $m->im_code)
                ->update([
                    'im_lat' => number_format($nLat, 6, '.', ''),
                    'im_lng' => number_format($nLng, 6, '.', ''),
                ]);

            $corregidos++;
        }

        echo "  Marcadores con el signo corregido: {$corregidos}\n";
    }

    /**
     * No hay `down()` que valga: volver a poner los signos al reves seria
     * reintroducir el bug. Si hiciera falta revertir, el volcado de v1 tiene
     * los valores originales.
     */
    public function down(): void
    {
        // Intencionalmente vacio.
    }

    private function dentroDeEcuador(float $lat, float $lng): bool
    {
        return $lat >= self::LAT_MIN && $lat <= self::LAT_MAX
            && $lng >= self::LNG_MIN && $lng <= self::LNG_MAX;
    }
};
