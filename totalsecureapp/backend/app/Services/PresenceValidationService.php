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
    ];

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
            ];
        }

        // 3. Obtener radio de tolerancia de la institución
        $institucion = OrganizacionInstitucion::where('ins_code', $institucionId)->first();
        if ($institucion && $institucion->ins_radio_tolerancia_metros) {
            $radioTolerancia = $institucion->ins_radio_tolerancia_metros;
        }

        // 4. Calcular distancia
        $distancia = $this->calcularDistancia(
            $latitud, $longitud,
            $marcador->im_lat, $marcador->im_lng
        );

        // 5. Validar geocerca
        if ($distancia > $radioTolerancia) {
            return [
                'valido'      => false,
                'distancia_m' => round($distancia, 2),
                'motivo'      => "Fuera de geocerca ({$distancia}m, radio: {$radioTolerancia}m)",
                'marcador'    => null,
            ];
        }

        return [
            'valido'      => true,
            'distancia_m' => round($distancia, 2),
            'motivo'      => '',
            'marcador'    => $marcador,
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
                    'motivo'      => "Fuera de geocerca ({$distancia}m, radio: {$radioTolerancia}m)",
                    'marcador'    => null,
                ];
            }

            return [
                'valido'      => true,
                'distancia_m' => round($distancia, 2),
                'motivo'      => '',
                'marcador'    => $marcador,
            ];
        }

        // Sin marcador: validación básica de institución
        return [
            'valido'      => true,
            'distancia_m' => 0.0,
            'motivo'      => '',
            'marcador'    => null,
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
            ];
        }

        return [
            'valido'      => true,
            'distancia_m' => 0.0,
            'motivo'      => '',
            'marcador'    => null,
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