<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Administracion\Models\Alertas;
use Modules\Administracion\Models\Turno;

/**
 * Consultas cacheadas del dashboard (Fase 9).
 *
 * La invalidacion es por evento, no por TTL ciego: un supervisor que atiende una
 * alerta tiene que ver el contador bajar en el momento, no cuando venza un
 * timer. Los observers de Alertas y Turno llaman a invalidar() en cada escritura.
 *
 * El mecanismo es un contador de version por institucion incluido en la clave.
 * No se usa Cache::tags() porque el driver configurado es 'file', que no soporta
 * etiquetas (lanzaria BadMethodCallException); el contador de version funciona
 * con cualquier driver. Al subir la version las entradas viejas quedan
 * inalcanzables y el TTL las termina limpiando.
 *
 * El TTL es solo una red de seguridad por si algun camino de escritura se
 * saltara los observers, no el mecanismo principal.
 */
class DashboardStatsService
{
    public const DOMINIO_ALERTAS = 'alertas';
    public const DOMINIO_TURNOS = 'turnos';

    /** Red de seguridad, no el mecanismo de frescura. */
    private const TTL_SEGUNDOS = 600;

    /**
     * Alertas activas de una institucion, con su desglose por prioridad.
     *
     * @return array{activas: int, pendientes: int, en_atencion: int, criticas: int, altas: int, mas_antigua: ?string}
     */
    public function alertasActivas(int $insCode): array
    {
        return Cache::remember(
            $this->clave(self::DOMINIO_ALERTAS, "activas:{$insCode}", $insCode),
            self::TTL_SEGUNDOS,
            function () use ($insCode) {
                $base = fn () => Alertas::where('al_ins_code', $insCode)
                    ->where('al_estado', 1)
                    ->whereIn('al_estado_alerta', ['pendiente', 'en_atencion']);

                $masAntigua = $base()->orderBy('al_fecha')->value('al_fecha');

                return [
                    'activas'     => $base()->count(),
                    'pendientes'  => $base()->where('al_estado_alerta', 'pendiente')->count(),
                    'en_atencion' => $base()->where('al_estado_alerta', 'en_atencion')->count(),
                    'criticas'    => $base()->where('al_prioridad', 'critica')->count(),
                    'altas'       => $base()->where('al_prioridad', 'alta')->count(),
                    'mas_antigua' => $masAntigua ? (string) $masAntigua : null,
                ];
            }
        );
    }

    /**
     * Cumplimiento de turnos de una institucion en una fecha.
     *
     * @return array{total: int, con_entrada: int, con_salida: int, sin_marcar: int, con_tardanza: int, minutos_tardanza: int, porcentaje_entrada: float}
     */
    public function cumplimientoTurnos(int $insCode, ?string $fecha = null): array
    {
        $fecha = $fecha ?: now()->toDateString();

        return Cache::remember(
            $this->clave(self::DOMINIO_TURNOS, "cumplimiento:{$insCode}:{$fecha}", $insCode),
            self::TTL_SEGUNDOS,
            function () use ($insCode, $fecha) {
                $base = fn () => Turno::where('tu_ins_code', $insCode)
                    ->where('tu_fecha', $fecha);

                $total = $base()->count();
                $conEntrada = $base()->whereNotNull('tu_marcada_entrada')->count();

                return [
                    'total'              => $total,
                    'con_entrada'        => $conEntrada,
                    'con_salida'         => $base()->whereNotNull('tu_marcada_salida')->count(),
                    'sin_marcar'         => $base()->whereNull('tu_marcada_entrada')->count(),
                    'con_tardanza'       => $base()->where('tu_minutos_tardanza', '>', 0)->count(),
                    'minutos_tardanza'   => (int) $base()->sum('tu_minutos_tardanza'),
                    // Sin turnos programados el cumplimiento no es 0%, es "no aplica";
                    // se devuelve 0.0 pero 'total' permite distinguirlo.
                    'porcentaje_entrada' => $total > 0 ? round($conEntrada * 100 / $total, 1) : 0.0,
                ];
            }
        );
    }

    /**
     * Invalida lo cacheado de un dominio para una institucion.
     *
     * Lo llaman los observers en cada escritura. Sube la version, con lo que
     * todas las claves derivadas quedan inalcanzables de una vez, sin tener que
     * enumerarlas (que es justo lo que el driver 'file' no permite hacer).
     */
    public function invalidar(string $dominio, int $insCode): void
    {
        Cache::forever(
            $this->claveVersion($dominio, $insCode),
            $this->version($dominio, $insCode) + 1
        );
    }

    public function version(string $dominio, int $insCode): int
    {
        $clave = $this->claveVersion($dominio, $insCode);
        $version = Cache::get($clave);

        if ($version === null) {
            Cache::forever($clave, 1);
            return 1;
        }

        return (int) $version;
    }

    private function claveVersion(string $dominio, int $insCode): string
    {
        return "dashboard:version:{$dominio}:{$insCode}";
    }

    private function clave(string $dominio, string $sufijo, int $insCode): string
    {
        return "dashboard:{$dominio}:v" . $this->version($dominio, $insCode) . ":{$sufijo}";
    }
}
