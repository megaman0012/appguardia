<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use App\Services\AlertaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Administracion\Models\Alertas;
use Modules\Acceso\Models\user_has_roles;

class AlertaController extends Controller
{
    use generalTrait;

    public function __construct(
        private AlertaService $alertaService
    ) {}

    protected array $todayRules = [
        'rules' => [
            'ins' => 'required',
        ],
        'messages' => [
            'ins.required' => 'Campo institucion es obligatorio',
        ]
    ];

    protected array $crearRules = [
        'rules' => [
            'ins' => 'required|integer',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'observacion' => 'required|string|max:1000',
            'prioridad' => 'nullable|in:baja,media,alta,critica',
        ],
        'messages' => [
            'ins.required' => 'Campo institucion es obligatorio',
            'lat.required' => 'Latitud es obligatoria',
            'lng.required' => 'Longitud es obligatoria',
            'observacion.required' => 'Observación es obligatoria',
            'prioridad.in' => 'Prioridad no válida',
        ]
    ];

    public function today(Request $request): JsonResponse
    {
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

        $alerts = $this->alertaService->obtenerAlertasActivas($request->ins);

        if (!$uR) {
            $alerts = $alerts->filter(function ($alerta) use ($request) {
                return $alerta->al_ins_code == $request->ins;
            });
        }

        return response()->json([
            'success' => true,
            'alerts' => $alerts,
            'console' => $uR ? 1 : 0,
        ]);
    }

    public function crear(Request $request): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), $this->crearRules['rules'], $this->crearRules['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        try {
            $alerta = $this->alertaService->crearAlerta([
                'institucion_id' => $request->ins,
                'usuario_id' => $us->id,
                'lat' => $request->lat,
                'lng' => $request->lng,
                'observacion' => $request->observacion,
                'prioridad' => $request->prioridad ?? 'media',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Alerta creada correctamente',
                'alert' => $alerta,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear alerta',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function atender(Request $request, int $alertaId): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), [
            'observacion' => 'required|string|max:1000',
        ], [
            'observacion.required' => 'Observación de atención es obligatoria',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        try {
            $alerta = Alertas::findOrFail($alertaId);
            $detalle = $this->alertaService->atenderAlerta($alerta, $us->id, $request->observacion);

            return response()->json([
                'success' => true,
                'message' => 'Alerta atendida correctamente',
                'detalle' => $detalle,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al atender alerta',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function cancelar(Request $request, int $alertaId): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), [
            'motivo' => 'required|string|max:1000',
        ], [
            'motivo.required' => 'Motivo de cancelación es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        try {
            $alerta = Alertas::findOrFail($alertaId);
            $this->alertaService->cancelarAlerta($alerta, $us->id, $request->motivo);

            return response()->json([
                'success' => true,
                'message' => 'Alerta cancelada correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar alerta',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function estadisticas(Request $request): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), [
            'ins' => 'required|integer',
            'periodo' => 'nullable|in:hoy,semana,mes',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $estadisticas = $this->alertaService->obtenerEstadisticas(
            $request->ins,
            $request->periodo ?? 'hoy'
        );

        return response()->json([
            'success' => true,
            'estadisticas' => $estadisticas,
        ]);
    }

    public function historial(Request $request, int $alertaId): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);

        $alerta = Alertas::with(['historial.usuario', 'detalle.usuarioAsignado'])
            ->findOrFail($alertaId);

        return response()->json([
            'success' => true,
            'alerta' => $alerta,
        ]);
    }
}
