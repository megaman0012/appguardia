<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\MobileApp\Services\ExpoNotificationService;

class NotificacionController extends Controller
{

    use generalTrait;
    protected ExpoNotificationService $notificationService;

    public function __construct(ExpoNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    protected array $saveTokenRules = [
        'rules' => [
            'tkn' => 'required',
            'ins' => 'required',
            'env' => 'required',
        ],
        'messages' => [
            'tkn.required' => 'Campo Signature es obligatorio',
            'ins.required' => 'Campo institucion es obligatorio',
            'env.required' => 'Campo ambiente es obligatorio',
        ]
    ];

    public function saveToken(Request $request)
    {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), $this->saveTokenRules['rules'], $this->saveTokenRules['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        try {
            $pushToken = $this->notificationService->saveToken( $request->tkn, $us->id, $request->ins, $request->env, $request->ptf, $request->dvn );
            return response()->json([
                'success' => true,
                'message' => 'Token e Institucion guardados correctamente',
                //'data' => $pushToken
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar Token e Institucion',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function removeToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $this->notificationService->removeToken($request->token);

        return response()->json([
            'success' => true,
            'message' => 'Token eliminado correctamente'
        ]);
    }

    protected array $sendToInstitutionRules = [
        'rules' => [
            'tkn' => 'required',
            'ins' => 'required',
            'tit' => 'required',
            'bod' => 'required',
            'dat' => 'required',
        ],
        'messages' => [
            'tkn.required' => 'Campo Signature es obligatorio',
            'ins.required' => 'Campo Institucion es obligatorio',
            'tit.required' => 'Campo Titulo es obligatorio',
            'bod.required' => 'Campo Cuerpo es obligatorio',
            'dat.required' => 'Campo Data es obligatorio',
        ]
    ];
    public function sendToInstitution(Request $request)
    {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), $this->sendToInstitutionRules['rules'], $this->sendToInstitutionRules['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $data = $request->dat;
        $data["ins"] = $request->ins;
        $data["usu"] = $us->id;
        $data["tit"] = $request->tit;
        $data["bod"] = $request->bod;

        $result = $this->notificationService->sendToInstitution(
            $request->ins,
            $request->tit,
            $request->bod,
            $data ?? []
        );

        return response()->json($result);
    }

    public function sendToUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array'
        ]);

        $result = $this->notificationService->sendToUser(
            $request->user_id,
            $request->title,
            $request->body,
            $request->data ?? []
        );

        return response()->json($result);
    }

    public function sendBulk(Request $request)
    {
        $request->validate([
            'institucion_ids' => 'required|array',
            'institucion_ids.*' => 'integer',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array'
        ]);

        $result = $this->notificationService->sendToMultipleInstitutions(
            $request->institucion_ids,
            $request->title,
            $request->body,
            $request->data ?? []
        );

        return response()->json($result);
    }
}
