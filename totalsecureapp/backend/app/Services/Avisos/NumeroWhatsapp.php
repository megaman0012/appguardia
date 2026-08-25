<?php

namespace App\Services\Avisos;

/**
 * Normaliza un número para WhatsApp.
 *
 * WhatsApp quiere el número con código de país y sin nada más. Un número mal
 * formado **no da error**: el gateway lo acepta y el mensaje nunca llega. Por
 * eso se normaliza acá y se valida antes de intentar el envío.
 *
 * Los dos casos del negocio:
 *   Ecuador  0987654321  -> 593987654321
 *   Colombia 3001234567  -> 573001234567
 */
class NumeroWhatsapp
{
    /** Códigos de los países donde opera la empresa. */
    private const CODIGOS_CONOCIDOS = ['593', '57'];

    public static function normalizar(?string $numero, ?string $codigoPais = null): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $numero);

        if ($digitos === '') {
            return null;
        }

        // Ya viene con código de país conocido.
        foreach (self::CODIGOS_CONOCIDOS as $codigo) {
            if (str_starts_with($digitos, $codigo) && strlen($digitos) >= strlen($codigo) + 9) {
                return $digitos;
            }
        }

        $codigoPais = $codigoPais ?: (string) config('avisos.whatsapp.codigo_pais', '593');

        // Formato local con el 0 inicial: 0987654321.
        if (str_starts_with($digitos, '0')) {
            return $codigoPais . substr($digitos, 1);
        }

        // Nueve o diez dígitos sin cero: se asume local del país por defecto.
        if (strlen($digitos) >= 9 && strlen($digitos) <= 10) {
            return $codigoPais . $digitos;
        }

        return $digitos;
    }

    /** Un número que no llega a esto no es un móvil válido de la región. */
    public static function valido(?string $numero): bool
    {
        $normalizado = self::normalizar($numero);

        return $normalizado !== null
            && strlen($normalizado) >= 11
            && strlen($normalizado) <= 15;
    }
}
