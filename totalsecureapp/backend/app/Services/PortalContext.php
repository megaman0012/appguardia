<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * Contexto ya validado de una consulta del portal cliente (Fase 8).
 *
 * Existe para que el filtro por institucion no se pueda olvidar: la unica forma
 * de empezar una consulta del portal es consulta(), que ya viene acotada. Antes
 * el controller recibia el arreglo de instituciones y tenia que acordarse de
 * aplicarlo; un endpoint nuevo que lo olvidara devolvia los datos de todos los
 * clientes y aun asi pasaba los tests.
 *
 * Solo lo construye PortalScopeService::contextoPara(), despues de verificar que
 * las instituciones pertenecen al usuario.
 */
final class PortalContext
{
    /** @var int[] */
    private array $instituciones;
    private string $desde;
    private string $hasta;
    private int $porPagina;

    /**
     * @param  int[]  $instituciones
     */
    public function __construct(array $instituciones, string $desde, string $hasta, int $porPagina)
    {
        $this->instituciones = $instituciones;
        $this->desde = $desde;
        $this->hasta = $hasta;
        $this->porPagina = $porPagina;
    }

    /**
     * Consulta acotada a las instituciones del contexto y, si se indica columna,
     * al rango de fechas.
     *
     * @param  class-string  $modelo  Modelo con el trait BelongsToInstitution.
     * @param  string|null  $columnaFecha  Columna de fecha del recurso; null cuando
     *                                     el recurso no se filtra por fecha.
     */
    public function consulta(string $modelo, ?string $columnaFecha = null): Builder
    {
        $consulta = $modelo::forInstitutions($this->instituciones);

        if ($columnaFecha !== null) {
            $consulta->whereBetween($columnaFecha, [$this->inicio(), $this->fin()]);
        }

        return $consulta;
    }

    /** @return int[] */
    public function instituciones(): array
    {
        return $this->instituciones;
    }

    public function porPagina(): int
    {
        return $this->porPagina;
    }

    /** @return array{desde: string, hasta: string} */
    public function rango(): array
    {
        return ['desde' => $this->desde, 'hasta' => $this->hasta];
    }

    public function inicio(): string
    {
        return $this->desde . ' 00:00:00';
    }

    public function fin(): string
    {
        return $this->hasta . ' 23:59:59';
    }
}
