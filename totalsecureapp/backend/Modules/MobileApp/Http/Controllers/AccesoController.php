<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use App\Services\AccesoService;
use App\Services\PresenceValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Administracion\Models\Acceso;
use Modules\Administracion\Models\UserHasInstitucion;

class AccesoController extends Controller{

    use generalTrait;

    protected PresenceValidationService $presenceService;
    protected AccesoService $accesoService;

    public function __construct(PresenceValidationService $presenceService, AccesoService $accesoService)
    {
        $this->presenceService = $presenceService;
        $this->accesoService = $accesoService;
    }

    /**
     * Registrar un acceso (generalizado: peatonal, vehicular, proveedor, empleado, visitante)
     */
    public function acceso(Request $request): JsonResponse {

        list($us, $tk) = $this->getSanctumSession($request);

        try {
            $datos = $request->all();

            // La foto se mueve a disco antes de la transaccion
            if ($request->hasFile('file')) {
                list($fileMoved, $fileName) = $this->storeFiles('accesos', $request->file('file'), $us->id.'_'.$tk->tokenable_gs);
                if(!$fileMoved){
                    return $this->message_json('errors', 'Error al cargar imagen a servidor' );
                }
                $datos['ac_foto'] = $fileName;
            }

            $acc = $this->accesoService->registrar($datos, $us->id, $tk->tokenable_gs);

            if (!empty($datos['ac_foto'])) {
                $acc->ac_foto = $datos['ac_foto'];
                $acc->save();
            }

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()]);
        } catch (\Exception $e) {
            return $this->message_json('errors', $e->getMessage());
        }

        return response()->json([
            'message' => 'Acceso registrado con éxito',
            'ac_code' => $acc->ac_code,
            'tipo'    => $acc->ac_tipo,
        ]);
    }

    /**
     * Listar accesos por institucion y fecha (con detalles segun tipo)
     */
    public function getAccesosByInst(Request $request): JsonResponse {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), [
            'date' => 'required',
            'ins_code' => 'required',
        ], [
            'date.required' => 'Campo fecha es obligatorio',
            'ins_code.required' => 'Campo intitucion es obligatorio',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $accesos = Acceso::with(['persona', 'vehiculo', 'visitante', 'historial'])
            ->whereDate('ac_created_at', $request->date)
            ->where( 'ac_ins_code', $request->ins_code )
            ->where( 'ac_estado', 1 )
            ->orderBy('ac_code', 'desc')
            ->get();

        $res = [];
        foreach ($accesos as $ac) {
            $ac->ac_foto = $ac->imagenUrl;
            $ac->tiempo_permanencia = $ac->tiempo_permanencia;
            $res[] = $ac->toArray();
        }

        return response()->json([
            'acAccByIns' => $res
        ]);
    }

    /**
     * Registrar salida de un acceso en curso
     */
    public function accesOut(Request $request): JsonResponse {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), [
            'code' => 'required',
            'ins' => 'required',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ], [
            'code.required' => 'Campo codigo es obligatorio',
            'ins.required' => 'Campo intitucion es obligatorio',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        try {
            $acc = $this->accesoService->registrarSalida(
                (int) $request->code,
                $request->lat,
                $request->lng
            );
        } catch (\Exception $e) {
            return $this->message_json('errors', $e->getMessage());
        }

        return response()->json(['message' => 'Salida registrada con éxito']);
    }

    /**
     * Pre-registro de visitante esperado
     */
    public function preregistro(Request $request): JsonResponse {
        list($us, $tk) = $this->getSanctumSession($request);

        try {
            $preregistro = $this->accesoService->crearPreregistro($request->all(), $us->id);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()]);
        } catch (\Exception $e) {
            return $this->message_json('errors', $e->getMessage());
        }

        return response()->json([
            'message' => 'Pre-registro creado con éxito',
            'apr_code' => $preregistro->apr_code,
            'token'   => $preregistro->apr_token,
        ]);
    }

    /**
     * Listar pre-registros por institucion (y fecha opcional)
     */
    public function listPreregistros(Request $request): JsonResponse {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), [
            'ins_code' => 'required|integer',
            'date' => 'nullable|date',
        ], [
            'ins_code.required' => 'Campo institucion es obligatorio',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $preregistros = $this->accesoService->listarPreregistros(
            (int) $request->ins_code,
            $request->date
        );

        return response()->json(['preregistros' => $preregistros]);
    }

    /**
     * Cancelar un pre-registro pendiente
     */
    public function cancelarPreregistro(Request $request): JsonResponse {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), [
            'apr_code' => 'required|integer',
        ], [
            'apr_code.required' => 'Campo codigo pre-registro es obligatorio',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        try {
            $this->accesoService->cancelarPreregistro((int) $request->apr_code);
        } catch (\Exception $e) {
            return $this->message_json('errors', $e->getMessage());
        }

        return response()->json(['message' => 'Pre-registro cancelado con éxito']);
    }
}
