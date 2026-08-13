<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Administracion\Models\Alertas;
use Modules\Administracion\Models\UserHasInstitucion;
use Modules\Acceso\Models\user_has_roles;

class AlertaController extends Controller
{
    use generalTrait;

    protected array $todayRules = [
        'rules' => [
            'ins' => 'required',
        ],
        'messages' => [
            'ins.required' => 'Campo institucion es obligatorio',
        ]
    ];

    public function today(Request $request){

        list($us, $tk) = $this->getSanctumSession($request);
        $validator = Validator::make($request->all(), $this->todayRules['rules'], $this->todayRules['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $uR = user_has_roles::where('user_id', $us->id)
            ->whereHas('roles', function ($q) {
                $q->where('estado', 1)
                  ->where('name', 'Consola Notificacion');
            })
            ->with('roles')
            ->first();
            //->where('role_id', 6)

        $query = Alertas::where('al_estado', 1)
            ->join('organizacion_institucion','alertas.al_ins_code','=','organizacion_institucion.ins_code')
            ->join('users', 'alertas.al_usu_id', '=', 'users.id')
            ->whereDate('al_fecha', date('Y-m-d'))
            ->orderBy('al_code', 'desc');
        if (!$uR) {
            $query->where('al_ins_code', $request->ins);
        }
        $alerts = $query->get();
        return response()->json([
            'alerts' => $alerts,
            'console' => $uR ? 1 : 0
        ]);

    }

}
