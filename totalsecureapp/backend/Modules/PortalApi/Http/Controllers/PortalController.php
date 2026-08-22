<?php

namespace Modules\PortalApi\Http\Controllers;

use App\Services\PortalScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Base de la API del portal cliente (Fase 8).
 *
 * Solo lectura. Resuelve el alcance por institucion en un unico lugar para que
 * ningun endpoint pueda olvidarse de filtrar.
 */
abstract class PortalController extends Controller
{
    protected PortalScopeService $scope;

    public function __construct(PortalScopeService $scope)
    {
        $this->scope = $scope;
    }

    /**
     * Contexto de la consulta: instituciones autorizadas y rango de fechas.
     *
     * @return array{0: ?JsonResponse, 1: int[], 2: string, 3: string}
     *         [respuestaDeError, instituciones, desde, hasta]
     */
    protected function contexto(Request $request): array
    {
        $usuario = auth('sanctum')->user();

        list($instituciones, $motivo) = $this->scope->resolver($request, $usuario->id);

        if ($motivo !== null) {
            return [response()->json(['message' => $motivo], 403), [], '', ''];
        }

        list($desde, $hasta) = $this->scope->rango($request);

        return [null, $instituciones, $desde, $hasta];
    }

    /**
     * Envoltorio uniforme de las listas paginadas.
     */
    protected function paginado(LengthAwarePaginator $pagina, callable $mapear, array $meta = []): JsonResponse
    {
        return response()->json([
            'datos'      => array_map($mapear, $pagina->items()),
            'paginacion' => [
                'pagina'      => $pagina->currentPage(),
                'por_pagina'  => $pagina->perPage(),
                'total'       => $pagina->total(),
                'ultima'      => $pagina->lastPage(),
            ],
        ] + $meta);
    }
}
