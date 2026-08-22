<?php

namespace Modules\PortalApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Administracion\Models\OrganizacionInstitucion;

class InstitucionController extends PortalController
{
    /**
     * GET api/portal/instituciones
     *
     * Instituciones visibles para el token. Es el punto de entrada del portal:
     * de aqui salen los ins_code validos para el resto de los endpoints.
     */
    public function index(Request $request): JsonResponse
    {
        $usuario = auth('sanctum')->user();
        $codigos = $this->scope->institucionesDe($usuario->id);

        if (empty($codigos)) {
            return response()->json(['message' => 'El usuario no tiene instituciones asignadas'], 403);
        }

        $instituciones = OrganizacionInstitucion::whereIn('ins_code', $codigos)
            ->orderBy('ins_descripcion')
            ->get();

        return response()->json([
            'datos' => $instituciones->map(fn ($ins) => [
                'ins_code'                   => (int) $ins->ins_code,
                'ins_descripcion'            => $ins->ins_descripcion,
                'ins_direccion'              => $ins->ins_direccion,
                'ins_ciudad'                 => $ins->ins_ciudad,
                'ins_tipo'                   => $ins->ins_tipo,
                'ins_radio_tolerancia_metros' => $ins->ins_radio_tolerancia_metros,
            ])->values(),
        ]);
    }
}
