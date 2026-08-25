<?php

namespace App\Services\Avisos;

use Modules\Administracion\Models\AvisoEnvio;

/**
 * Qué pasó al intentar entregar un aviso.
 *
 * Devolver solo `true`/`false` alcanzaba para el código, pero no para la
 * persona que después pregunta por qué un guardia no se enteró: no es lo mismo
 * "no se intentó porque no tiene número cargado" que "se intentó y el gateway
 * no respondió". Lo primero se arregla cargando un dato; lo segundo, levantando
 * un servicio.
 */
class ResultadoDeAviso
{
    private function __construct(
        public readonly string $resultado,
        public readonly ?string $detalle = null,
        public readonly ?string $destino = null
    ) {
    }

    public static function enviado(?string $destino = null): self
    {
        return new self(AvisoEnvio::ENVIADO, null, $destino);
    }

    /** No se intentó: sin número, sin consentimiento o canal apagado. */
    public static function omitido(string $motivo, ?string $destino = null): self
    {
        return new self(AvisoEnvio::OMITIDO, $motivo, $destino);
    }

    public static function fallido(string $detalle, ?string $destino = null): self
    {
        return new self(AvisoEnvio::FALLIDO, $detalle, $destino);
    }

    public function ok(): bool
    {
        return $this->resultado === AvisoEnvio::ENVIADO;
    }
}
