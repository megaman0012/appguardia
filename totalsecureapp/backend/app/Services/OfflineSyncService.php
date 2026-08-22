<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Idempotencia para los registros que la APK crea sin señal (Fase 7).
 *
 * El dispositivo genera un client_uuid por registro y lo reenvia en cada
 * reintento. El backend lo usa como idempotency key: el primer envio crea, los
 * siguientes devuelven el registro ya escrito sin error visible al usuario.
 */
class OfflineSyncService
{
    /** SQLSTATE de Postgres para unique_violation. */
    private const VIOLACION_UNICA = '23505';

    /**
     * Busca un registro ya sincronizado con ese client_uuid.
     *
     * @param  class-string<Model>  $modelo
     */
    public function buscar(string $modelo, string $columnaUuid, ?string $clientUuid): ?Model
    {
        if ($clientUuid === null || $clientUuid === '') {
            return null;
        }

        return $modelo::where($columnaUuid, $clientUuid)->first();
    }

    /**
     * Ejecuta $crear solo si el client_uuid no llego antes.
     *
     * @param  class-string<Model>  $modelo
     * @param  callable():Model  $crear
     * @return array{0: Model, 1: bool}  [registro, era_duplicado]
     */
    public function registrar(string $modelo, string $columnaUuid, ?string $clientUuid, callable $crear): array
    {
        $existente = $this->buscar($modelo, $columnaUuid, $clientUuid);
        if ($existente !== null) {
            return [$existente, true];
        }

        try {
            return [$crear(), false];
        } catch (QueryException $e) {
            // Dos reintentos del mismo uuid en paralelo: uno gana la carrera y el
            // otro choca con el indice unique. El que pierde recupera el registro
            // ya escrito en vez de devolver un error al guardia.
            if ($clientUuid !== null && $clientUuid !== '' && $this->esViolacionUnica($e)) {
                $registro = $this->buscar($modelo, $columnaUuid, $clientUuid);
                if ($registro !== null) {
                    return [$registro, true];
                }
            }

            throw $e;
        }
    }

    public function esViolacionUnica(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === self::VIOLACION_UNICA
            || ($e->errorInfo[0] ?? null) === self::VIOLACION_UNICA;
    }

    /**
     * Fecha real del evento. La envia el dispositivo porque pudo ocurrir horas
     * antes de que hubiera señal; si no llega, se asume que ocurre ahora.
     *
     * Un reloj de dispositivo adelantado no debe producir registros en el futuro,
     * asi que se recorta al momento actual.
     */
    public function ocurridoEn(?string $valor): string
    {
        $ahora = Carbon::now();

        if ($valor === null || $valor === '') {
            return $ahora->format('Y-m-d H:i:s');
        }

        try {
            $fecha = Carbon::parse($valor);
        } catch (\Exception $e) {
            return $ahora->format('Y-m-d H:i:s');
        }

        return $fecha->greaterThan($ahora)
            ? $ahora->format('Y-m-d H:i:s')
            : $fecha->format('Y-m-d H:i:s');
    }

    /** Momento en que el servidor recibe el registro. */
    public function sincronizadoEn(): string
    {
        return Carbon::now()->format('Y-m-d H:i:s');
    }
}
