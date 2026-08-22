<?php

namespace Modules\PortalApi\Http\Controllers;

use App\Services\PortalContext;
use App\Services\PortalScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Base de la API del portal cliente (Fase 8). Solo lectura.
 *
 * Los controllers del portal no consultan modelos directamente: piden el
 * contexto y de ahi sale el builder, ya acotado a las instituciones del token.
 * Asi el filtro no es algo que haya que recordar aplicar en cada endpoint.
 */
abstract class PortalController extends Controller
{
    protected PortalScopeService $scope;

    public function __construct(PortalScopeService $scope)
    {
        $this->scope = $scope;
    }

    /**
     * Contexto validado de la consulta, o la respuesta de rechazo.
     *
     * @return array{0: ?JsonResponse, 1: ?PortalContext}
     */
    protected function contexto(Request $request): array
    {
        $usuario = auth('sanctum')->user();

        list($contexto, $motivo) = $this->scope->contextoPara($request, $usuario->id);

        if ($motivo !== null) {
            return [response()->json(['message' => $motivo], 403), null];
        }

        return [null, $contexto];
    }

    /**
     * Envoltorio uniforme de las listas paginadas.
     */
    protected function paginado(LengthAwarePaginator $pagina, callable $mapear, PortalContext $ctx): JsonResponse
    {
        return response()->json([
            'datos'      => array_map($mapear, $pagina->items()),
            'paginacion' => [
                'pagina'     => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
                'total'      => $pagina->total(),
                'ultima'     => $pagina->lastPage(),
            ],
            'rango'      => $ctx->rango(),
        ]);
    }
}
