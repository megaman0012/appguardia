<?php

namespace Modules\MobileApp\Services;

use App\generalTrait;
use Modules\Administracion\Models\user_has_push_tkn;
use Modules\Administracion\Models\Alertas;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoNotificationService
{
    use generalTrait;
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    public function saveToken($token, $userId, $institucionId, $env = 'prod', $platform = null, $deviceName = null)
    {
        return user_has_push_tkn::updateOrCreate(
            ['pt_token' => $token],
            [
                'pt_usu_id' => $userId,
                'pt_ins_id' => $institucionId,
                'pt_platform' => $platform,
                'pt_device_name' => $deviceName,
                'pt_active' => true,
                'pt_env' => $env
            ]
        );
    }

    public function sendToInstitution($institucionId, $title, $body, $data = [])
    {

        /*$tokens = user_has_push_tkn::where('pt_ins_id', $institucionId)
            ->where('pt_active', true)
            ->pluck('pt_token')
            ->toArray();*/
        $entorno = env('PUSH_ENV', 'prod');

        $tokens = user_has_push_tkn::query()
            ->leftJoin('user_has_roles', 'user_has_push_tkn.pt_usu_id', '=', 'user_has_roles.user_id')
            ->leftJoin('roles', 'user_has_roles.role_id', '=', 'roles.id')
            ->where('user_has_push_tkn.pt_active', true)
            ->where('user_has_push_tkn.pt_env', $entorno)
            ->where(function ($q) use ($institucionId) {
                $q->where('user_has_push_tkn.pt_ins_id', $institucionId)
                    ->orWhere(function ($q2) {
                        $q2->where('roles.name', 'Consola Notificacion')
                            ->where('roles.estado', 1);
                    });
            })
            ->pluck('user_has_push_tkn.pt_token')
            ->unique()
            ->toArray();

        array_walk($tokens, function (&$token) {
            if (!str_starts_with($token, 'ExponentPushToken[')) {
                $token = "ExponentPushToken[{$token}]";
            }
        });

        if (empty($tokens)) {
            Log::info("No hay tokens para institución: {$institucionId}");
            return [
                'success' => false,
                'message' => 'No hay dispositivos registrados para esta institución',
                'sent' => 0
            ];
        }

        return $this->sendNotifications($tokens, $title, $body, $data);
    }

    public function sendToUser($userId, $title, $body, $data = [])
    {
        $tokens = user_has_push_tkn::where('pt_usu_id', $userId)
            ->where('pt_active', true)
            ->pluck('pt_token')
            ->toArray();

        if (empty($tokens)) {
            return [
                'success' => false,
                'message' => 'Usuario no tiene tokens activos',
                'sent' => 0
            ];
        }

        return $this->sendNotifications($tokens, $title, $body, $data);
    }

    public function sendToMultipleInstitutions(array $institucionIds, $title, $body, $data = [])
    {
        $tokens = user_has_push_tkn::whereIn('pt_ins_id', $institucionIds)
            ->where('pt_active', true)
            ->pluck('pt_token')
            ->toArray();

        return $this->sendNotifications($tokens, $title, $body, $data);
    }

    private function sendNotifications(array $tokens, $title, $body, $data = [])
    {
        $messages = [];

        foreach ($tokens as $token) {
            if ($this->isValidExpoPushToken($token)) {
                $messages[] = [
                    'to' => $token,
                    'sound' => $data["snd"] ?? 'default',
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                    'channelId' => $data["cid"] ?? 'default',
                    /*'android' => [
                        'channelId' => $data["cid"] ?? 'default', // debe coincidir con el id que creaste en la app
                    ],*/
                    'priority' => 'high',
                ];
            }
        }

        if (empty($messages)) {
            return [
                'success' => false,
                'message' => 'No hay tokens válidos',
                'sent' => 0
            ];
        }

        // Dividir en chunks de 100 (límite de Expo)
        $chunks = array_chunk($messages, 100);

        $totalSent = 0;
        $errors = [];

        foreach ($chunks as $chunk) {

            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Content-Type' => 'application/json',
                ])->post(self::EXPO_PUSH_URL, $chunk);

                if ($response->successful()) {
                    $result = $response->json();
                    $totalSent += count($chunk);

                    $al = new Alertas();
                    $al->al_ins_code = $data["ins"] ?? null;
                    $al->al_usu_id = $data["usu"] ?? null;
                    $al->al_lat = $data["lat"] ?? null;
                    $al->al_lng = $data["lng"] ?? null;
                    $al->al_anio = date('Y');
                    $al->al_fecha = date('Y-m-d H:i:s');
                    $al->al_estado_alerta = "Finalizada";
                    $al->al_estado = 1;
                    $al->al_observacion = $body;
                    $al->al_created_user = $data["usu"] ?? null;
                    $al->al_updated_user = $data["usu"] ?? null;
                    $al->save();

                    // Procesar errores individuales
                    if (isset($result['data'])) {
                        foreach ($result['data'] as $index => $ticket) {
                            if (isset($ticket['status']) && $ticket['status'] === 'error') {
                                $errors[] = $ticket;

                                // Si el token es inválido, desactivarlo
                                if (isset($ticket['details']['error']) &&
                                    $ticket['details']['error'] === 'DeviceNotRegistered') {
                                    $this->deactivateToken($chunk[$index]['to']);
                                }
                            }
                        }
                    }

                } else {
                    Log::error('Error enviando notificaciones', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    $this->control_log_api($data, 'NOTICE', json_encode([
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]));
                }
            } catch (\Exception $e) {
                Log::error('Excepción enviando notificaciones: ' . $e->getMessage());
                $errors[] = $e->getMessage();
                $this->control_log_api($data, 'NOTICE', json_encode($errors));
            }
        }

        $res = [
            'success' => true,
            'sent' => $totalSent,
            'total_tokens' => count($tokens),
            'errors' => $errors
        ];

        $this->control_log_api($data, 'NOTICE', json_encode($res));
        return $res;
    }

    private function isValidExpoPushToken($token)
    {
        return preg_match('/^ExponentPushToken\[.+\]$/', $token);
    }

    private function deactivateToken($token)
    {
        user_has_push_tkn::where('pt_token', $token)->update(['pt_active' => false]);
        Log::info("Token desactivado: {$token}");
    }

    public function removeToken($token)
    {
        $normalized = str_replace(['ExponentPushToken[', ']'], '', $token);
        return user_has_push_tkn::where('pt_token', $normalized)->update(['pt_active' => false]);
    }
}
