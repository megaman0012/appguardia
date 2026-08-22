<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Alcance de datos de la API del portal cliente (Fase 8).
 *
 * Un cliente solo puede leer las instituciones que tiene asignadas en
 * user_has_institucion. Toda consulta del portal pasa por aqui: si el filtro de
 * institucion se resolviera en cada controller, alcanzaria con olvidarlo en uno
 * para filtrar datos de otro cliente.
 */
class PortalScopeService
{
    /** Tope de filas por pagina, para que un cliente no pida la tabla entera. */
    public const POR_PAGINA_MAX = 200;
    public const POR_PAGINA_DEFECTO = 50;

    /**
     * Codigos de institucion visibles para el usuario autenticado.
     *
     * @return int[]
     */
    public function institucionesDe(int $usuarioId): array
    {
        return DB::table('user_has_institucion')
            ->where('ui_usu_id', $usuarioId)
            ->where('ui_state', 1)
            ->orderBy('ui_ins_code')
            ->pluck('ui_ins_code')
            ->map(fn ($code) => (int) $code)
            ->all();
    }

    /**
     * Instituciones a consultar segun el request.
     *
     * Sin `ins_code` devuelve todas las del usuario. Con `ins_code` valida que
     * este entre las suyas: pedir una ajena es 403, no una respuesta vacia, para
     * no dejar que se sondee que codigos existen.
     *
     * @return array{0: int[], 1: ?string}  [instituciones, motivoDeRechazo]
     */
    public function resolver(Request $request, int $usuarioId): array
    {
        $propias = $this->institucionesDe($usuarioId);

        if (empty($propias)) {
            return [[], 'El usuario no tiene instituciones asignadas'];
        }

        $pedida = $request->input('ins_code');
        if ($pedida === null || $pedida === '') {
            return [$propias, null];
        }

        if (!in_array((int) $pedida, $propias, true)) {
            return [[], 'La institucion solicitada no esta asignada al usuario'];
        }

        return [[(int) $pedida], null];
    }

    /**
     * Contexto validado de la consulta, o el motivo del rechazo.
     *
     * Es el unico constructor de PortalContext: quien tiene un contexto ya paso
     * por la validacion de instituciones de resolver().
     *
     * @return array{0: ?PortalContext, 1: ?string}  [contexto, motivoDeRechazo]
     */
    public function contextoPara(Request $request, int $usuarioId): array
    {
        list($instituciones, $motivo) = $this->resolver($request, $usuarioId);

        if ($motivo !== null) {
            return [null, $motivo];
        }

        list($desde, $hasta) = $this->rango($request);

        return [
            new PortalContext($instituciones, $desde, $hasta, $this->porPagina($request)),
            null,
        ];
    }

    /**
     * Rango de fechas del reporte. Por defecto los ultimos 30 dias.
     *
     * @return array{0: string, 1: string}  [desde, hasta] en Y-m-d
     */
    public function rango(Request $request): array
    {
        $hasta = $this->fecha($request->input('hasta')) ?? Carbon::today();
        $desde = $this->fecha($request->input('desde')) ?? $hasta->copy()->subDays(30);

        // Un rango invertido devolveria siempre vacio sin explicar por que.
        if ($desde->greaterThan($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde->format('Y-m-d'), $hasta->format('Y-m-d')];
    }

    public function porPagina(Request $request): int
    {
        $valor = (int) $request->input('por_pagina', self::POR_PAGINA_DEFECTO);

        if ($valor < 1) {
            return self::POR_PAGINA_DEFECTO;
        }

        return min($valor, self::POR_PAGINA_MAX);
    }

    private function fecha(?string $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->startOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }
}
