<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Administracion\Models\OrganizacionInstitucion;
use Modules\Administracion\Models\UserHasInstitucion;
use Modules\Administracion\Models\user_has_push_tkn;
use Illuminate\Support\Facades\Crypt;

class InstitucionController extends Controller{

    use generalTrait;

    public function allInstitucions(Request $request): JsonResponse {

        list($us, $tk) = $this->getSanctumSession($request);

        /*
         * ⚠️ **Tambien se filtra por `ins_estado`, y antes no.**
         *
         * Esto miraba solo el vinculo usuario-local (`ui_state`), asi que
         * **desactivar un local no lo quitaba de la tablet**: el guardia seguia
         * viendolo en la lista y podia elegirlo para trabajar. Un local retirado
         * solo desaparecia si ademas alguien se acordaba de desactivar los
         * vinculos uno por uno -- y son 18.707 filas en el caso que lo destapo.
         *
         * Se detecto al retirar 91 locales contra la lista final del cliente
         * (2026-09-21). Sin esta linea, desactivarlos no habria servido de nada.
         */
        $instituciones = UserHasInstitucion::where('ui_usu_id', $us->id)
            ->where('ui_state', 1)
            ->whereHas('institucion', fn ($q) => $q->where('ins_estado', true))
            ->get();
        //$token = user_has_push_tkn::where('pt_usu_id', $us->id )->where('pt_active', 1 )->first();

        $res = [];
        foreach ($instituciones as $inst) {
            $res[] = array(
                'ins_code' => $inst->institucion->ins_code,
                //'token' => $token->pt_token,
                'ins_descripcion' => $inst->institucion->ins_descripcion,
                'ins_direccion' => $inst->institucion->ins_direccion,
            );
        }

        return response()->json([ 'instituciones' => $res ]);

    }

}
