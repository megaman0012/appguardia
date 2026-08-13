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
        $instituciones = UserHasInstitucion::where( 'ui_usu_id', $us->id )->where( 'ui_state', 1 )->get();
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
