<?php

namespace App\Services;

use App\generalTrait;
use Modules\Administracion\Models\InstitucionMarcadores;
use Modules\Administracion\Models\OrganizacionInstitucion;
use Modules\Administracion\Models\UserHasInstitucion;

class PresenceValidationService
{
    use generalTrait;

    /**
     * Resultado de una validación de presencia
     */
    public array $resultado = [
        'valido'       => false,
        'distancia_m'  => 0.0,
        'motivo'       => '',
        'marcador'     => null,
        // 'verificado' separa "comprobe la geocerca y estaba dentro" de "no
        // pude comprobar nada": las dos devuelven valido=true. Sin esta marca
        // un marcaje de un local sin marcadores quedaba indistinguible de uno
        // comprobado de verdad. Ver 2026_09_07_100001_add_verificacion_ubicacion.
        'verificado'   => false,
    ];

    /**
     * Ecuador continental e insular, con margen. Fuera de esto, una coordenada
     * de marcador no es una ubicacion: es un dato mal cargado.
     */
    private const LAT_MIN = -5.0;
    private const LAT_MAX = 1.5;
    private const LNG_MIN = -81.2;
    private const LNG_MAX = -75.2;

    /**
     * ¿La coordenada de un marcador es creible?
     *
     * ⚠️ **Esta comprobacion existe por un apagon real.** 64 marcadores venian
     * de v1 con latitud y longitud positivas -- Ecuador esta al oeste y al sur,
     * asi que ambas deben ser negativas (salvo la latitud en el norte del
     * pais). Con el signo invertido, `calcularDistancia` devolvia **17.764 km** y
     * la geocerca rechazaba todo: en **61 de 110 locales el marcaje biometrico
     * era imposible** y el guardia solo veia «Fuera de geocerca (17764506m)».
     *
     * Los signos ya estan corregidos (`2026_09_08_220001`), pero el formulario
     * del panel permite escribir cualquier numero. Si vuelve a entrar una
     * coordenada imposible, el marcaje se ACEPTA sin verificar -- como cuando el
     * local no tiene marcador -- en vez de dejar a un guardia sin poder marcar
     * su turno. Un dato mal cargado es un problema de configuracion, y la
     * asistencia de una persona no es el lugar donde pagarlo.
     */
    private function coordenadaCreible($lat, $lng): bool
    {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return false;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat === 0.0 && $lng === 0.0) {
            return false;
        }

        return $lat >= self::LAT_MIN && $lat <= self::LAT_MAX
            && $lng >= self::LNG_MIN && $lng <= self::LNG_MAX;
    }

    /** Metros redondeados, para un mensaje que lea una persona. */
    private function aMetros(float $distancia): string
    {
        return $distancia >= 1000
            ? number_format($distancia / 1000, 1, ',', '.') . ' km'
            : ((int) round($distancia)) . ' m';
    }

    /**
     * Validar presencia completa: QR + GPS + Geocerca
     */
    public function validarPresencia(
        string $qrCode,
        float $latitud,
        float $longitud,
        int $institucionId,
        int $radioTolerancia = 100
    ): array {
        // 1. Validar institución
        $validarInst = $this->validarInstitucion($institucionId);
        if (!$validarInst['valido']) {
            return $validarInst;
        }

        // 2. Descifrar y validar QR
        $marcador = $this->descifrarQR($qrCode, $institucionId);
        if (!$marcador) {
            return [
                'valido'      => false,
                'distancia_m' => 0.0,
                'motivo'      => 'QR inválido, de otra institución o marcador inactivo',
                'marcador'    => null,
                'verificado'  => false,
            ];
        }

        // 3. Obtener radio de tolerancia de la institución
        $institucion = OrganizacionInstitucion::where('ins_code', $institucionId)->first();
        if ($institucion && $institucion->ins_radio_tolerancia_metros) {
            $radioTolerancia = $institucion->ins_radio_tolerancia_metros;
        }

        // 4. Si la coordenada del marcador no es creible, el QR ya probo que el
        //    guardia estaba ahi: se acepta sin medir. Ver coordenadaCreible().
        if (!$this->coordenadaCreible($marcador->im_lat, $marcador->im_lng)) {
            return [
                'valido'      => true,
                'distancia_m' => 0.0,
                'motivo'      => 'El marcador tiene una coordenada inválida: ubicación no verificada',
                'marcador'    => $marcador,
                'verificado'  => false,
            ];
        }

        // 5. Calcular distancia
        $distancia = $this->calcularDistancia(
            $latitud, $longitud,
            $marcador->im_lat, $marcador->im_lng
        );

        // 6. Validar geocerca
        if ($distancia > $radioTolerancia) {
            return [
                'valido'      => false,
                'distancia_m' => round($distancia, 2),
                'motivo'      => sprintf(
                    'Está a %s del punto de marcación (se permite hasta %d m). Acérquese al punto.',
                    $this->aMetros($distancia), $radioTolerancia
                ),
                'marcador'    => null,
                'verificado'  => true,
            ];
        }

        return [
            'valido'      => true,
            'distancia_m' => round($distancia, 2),
            'motivo'      => '',
            'marcador'    => $marcador,
            'verificado'  => true,
        ];
    }

    /**
     * Solo validar GPS sin QR (para biometría)
     */
    public function validarUbicacion(
        float $latitud,
        float $longitud,
        int $institucionId,
        int $radioTolerancia = 100
    ): array {
        // 1. Validar institución
        $validarInst = $this->validarInstitucion($institucionId);
        if (!$validarInst['valido']) {
            return $validarInst;
        }

        // 2. Obtener institución y radio
        $institucion = OrganizacionInstitucion::where('ins_code', $institucionId)->first();
        if ($institucion && $institucion->ins_radio_tolerancia_metros) {
            $radioTolerancia = $institucion->ins_radio_tolerancia_metros;
        }

        // 3. Obtener marcador principal de la institución (si existe)
        $marcador = InstitucionMarcadores::where('im_ins_code', $institucionId)
            ->where('im_estado', true)
            ->first();

        if ($marcador && !$this->coordenadaCreible($marcador->im_lat, $marcador->im_lng)) {
            // Marcador con coordenada imposible: se trata como si no hubiera
            // marcador. Ver coordenadaCreible().
            return [
                'valido'      => true,
                'distancia_m' => 0.0,
                'motivo'      => 'El marcador del local tiene una coordenada inválida: ubicación no verificada',
                'marcador'    => null,
                'verificado'  => false,
            ];
        }

        if ($marcador) {
            // Calcular distancia contra el marcador
            $distancia = $this->calcularDistancia(
                $latitud, $longitud,
                $marcador->im_lat, $marcador->im_lng
            );

            if ($distancia > $radioTolerancia) {
                return [
                    'valido'      => false,
                    'distancia_m' => round($distancia, 2),
                    'motivo'      => sprintf(
                        'Está a %s del punto de marcación (se permite hasta %d m). Acérquese al local.',
                        $this->aMetros($distancia), $radioTolerancia
                    ),
                    'marcador'    => null,
                    'verificado'  => true,
                ];
            }

            return [
                'valido'      => true,
                'distancia_m' => round($distancia, 2),
                'motivo'      => '',
                'marcador'    => $marcador,
                'verificado'  => true,
            ];
        }

        // Sin marcador activo no hay contra que medir, asi que el marcaje se
        // ACEPTA: un guardia no puede perder su asistencia porque a alguien le
        // falto configurar el local. Pero sale con verificado=false, y eso se
        // guarda en la fila y se ve en el panel. Antes esta rama devolvia
        // exactamente lo mismo que una comprobacion real y el hueco era
        // invisible: se aceptaba un marcaje a 273 km sin dejar rastro.
        return [
            'valido'      => true,
            'distancia_m' => 0.0,
            'motivo'      => 'El local no tiene marcador activo: ubicación no verificada',
            'marcador'    => null,
            'verificado'  => false,
        ];
    }

    /**
     * Medir la ubicación SIN bloquear nada.
     *
     * Para Accesos: el modulo guardaba ac_lat/ac_lng y nunca los comparaba con
     * nada -- inyectaba este servicio y no lo llamaba -- asi que no habia forma
     * de saber si un acceso se registro en la garita o desde una casa. Medir y
     * dejarlo escrito cierra ese hueco sin cambiar quien puede registrar: un
     * visitante legitimo no se puede quedar afuera porque el GPS del dispositivo
     * ande mal. Rechazar es una decision de negocio aparte.
     *
     * Devuelve ['verificada' => ?bool, 'distancia_m' => ?float]:
     *   verificada=true            estaba dentro del radio
     *   verificada=false + numero  se midio y estaba FUERA
     *   verificada=false + null    no se pudo medir (sin marcador, o sin GPS)
     */
    public function medirUbicacion(
        $latitud,
        $longitud,
        int $institucionId,
        int $radioTolerancia = 100
    ): array {
        $sinMedir = ['verificada' => false, 'distancia_m' => null];

        // La app manda '0'/'0' cuando el dispositivo no entrego ubicacion. Eso
        // no es el golfo de Guinea, es "no se sabe": medirlo daria una distancia
        // enorme y perfectamente falsa.
        if ($latitud === null || $longitud === null
            || !is_numeric($latitud) || !is_numeric($longitud)
            || ((float) $latitud === 0.0 && (float) $longitud === 0.0)) {
            return $sinMedir;
        }

        $institucion = OrganizacionInstitucion::where('ins_code', $institucionId)->first();
        if ($institucion && $institucion->ins_radio_tolerancia_metros) {
            $radioTolerancia = $institucion->ins_radio_tolerancia_metros;
        }

        $marcador = InstitucionMarcadores::where('im_ins_code', $institucionId)
            ->where('im_estado', true)
            ->first();

        if (!$marcador || !$this->coordenadaCreible($marcador->im_lat, $marcador->im_lng)) {
            return $sinMedir;
        }

        $distancia = $this->calcularDistancia(
            (float) $latitud, (float) $longitud,
            $marcador->im_lat, $marcador->im_lng
        );

        return [
            'verificada'  => $distancia <= $radioTolerancia,
            'distancia_m' => round($distancia, 2),
        ];
    }

    /**
     * Validar que el usuario esté vinculado a la institución
     */
    public function validarInstitucion(int $institucionId): array
    {
        $ins = UserHasInstitucion::where('ui_ins_code', $institucionId)
            ->where('ui_state', 1)
            ->first();

        if (!$ins) {
            return [
                'valido'      => false,
                'distancia_m' => 0.0,
                'motivo'      => 'Usuario no vinculado a institución',
                'marcador'    => null,
                'verificado'  => false,
            ];
        }

        return [
            'valido'      => true,
            'distancia_m' => 0.0,
            'motivo'      => '',
            'marcador'    => null,
            'verificado'  => false,
        ];
    }

    /**
     * Descifrar y validar código QR
     */
    public function descifrarQR(string $qrCode, int $institucionId): ?InstitucionMarcadores
    {
        try {
            $dcTxt = $this->aesCypher($qrCode, 2);
            $partes = explode('_', $dcTxt);

            if (!isset($partes[1]) || $partes[1] !== "TS") {
                return null;
            }

            $codMark = $partes[0];

            $marcador = InstitucionMarcadores::where('im_code', $codMark)
                ->where('im_ins_code', $institucionId)
                ->where('im_estado', true)
                ->first();

            return $marcador;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Calcular distancia entre dos puntos GPS en metros (Haversine)
     */
    public function calcularDistancia(
        float $lat1, float $lng1,
        float $lat2, float $lng2
    ): float {
        $radioTierra = 6371000; // metros

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $radioTierra * $c;
    }

    /**
     * Verificar si punto está dentro de geocerca
     */
    public function dentroDeGeocerca(
        float $latitud, float $longitud,
        float $marcaLat, float $marcaLng,
        int $radioMetros
    ): bool {
        $distancia = $this->calcularDistancia($latitud, $longitud, $marcaLat, $marcaLng);
        return $distancia <= $radioMetros;
    }
}