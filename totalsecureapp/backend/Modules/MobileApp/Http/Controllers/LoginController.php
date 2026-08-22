<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use App\Mail\MailTrait;
use Mail;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Services\PermisosApiService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Modules\Administracion\Models\parametros;
use Modules\MobileApp\Models\users;
use Modules\Acceso\Models\user_has_gestions;

class LoginController extends Controller {

    use MailTrait;
    use generalTrait;

    protected $valid = [
        'rules' => [
            'usu_cedula' => 'required',
            'usu_password' => 'required|min:6',
        ],
        'messages' => [
            'usu_cedula.required' => 'La cedula es requerida para continuar.',
            'usu_password.min' => 'La contraseña debe tener minimo 6 caracteres.',
            'usu_password.required' => 'La contraseña es requerida para continuar.',
        ],
    ];

    protected $rules_solicitudpass = [
        'rules' => [
            'usu_cedula' => 'required'
        ],
        'messages' => [
            'usu_cedula.required' => 'La cedula es requerida para continuar.'
        ],
    ];



    public function login(Request $request) {

        $validator = Validator::make($request->all(), $this->valid['rules'], $this->valid['messages']);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $user = users::where('usu_cedula', $request->usu_cedula)->first();
        if(!$user){ return $this->message_json('errors', 'La cuenta no existe'); }

        if($user->usu_state == 0){ return $this->message_json('errors', 'La cuenta no esta activa'); }

        if( !Hash::check($request->usu_password, $user->usu_password) ) { return $this->message_json('errors', 'Clave Incorrecta'); }

        $usges = user_has_gestions::where('ug_user_id', $user->id )->where('ug_finish', 0)->first();
        if(!$usges){ return $this->message_json('errors', 'Usuario No Posee Gestion Asignada'); }

        //aqui agregar si el usuario tiene perfil de guardia o supervisor

        /*if (!$user->roles()->exists()) {
            return $this->message_json('errors', 'El usuario no posee perfil alguno asignado');
        }*/

        $pfs = $user->roles()->whereIn('name', ['Supervisor', 'Vigilante'])->get();
        if(!$pfs){
            return $this->message_json('errors', 'El usuario no posee perfiles de acceso');
        }

        // Abilities = permisos granulares de los roles del usuario (Fase 6),
        // sin los del panel web legacy (ver PermisosApiService).
        $abilities = app(PermisosApiService::class)
            ->paraRoles($pfs->pluck('id')->map(fn ($id) => (int) $id)->all());

        $acc = parametros::where('pr_descripcion', 'access')->first();
        if(!$acc){
            return $this->message_json('errors', 'Parametro acceso no definido');
        }

        //$user->tokens->each(function ($token) { $token->delete(); });
        $token = $user->createToken('MobileApp')->plainTextToken;
        $refreshToken = bin2hex(random_bytes(32));

        list($spTknId, $spTkn) = explode('|', $token);

        $user->tokens()->where('id', $spTknId)->update([
            'tokenable_gs'  => $usges->ug_code,
            'refresh_token' => $refreshToken,
            'expires_at'    => Carbon::now()->addSeconds(env('TOKEN_EXPIRE_IN'))
        ]);

        return response()->json([
            'access_token'  => $token,
            'refresh_token' => $refreshToken,
            'expires_in'    => env('TOKEN_EXPIRE_IN'),
            'usuario'       => array(
                'usu_nombres' => $user->usu_nmbcom,
                'usu_email' => $user->usu_email,
                'usu_acc' => $acc->pr_value
            ),
            'abilities' => $abilities,
            'perfiles'  => $pfs->pluck('name')->values()
        ]);

    }

    public function solicitud_cambiopass(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), $this->rules_solicitudpass['rules'], $this->rules_solicitudpass['messages']);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()]);
            }

            $cedula = $request->usu_cedula;
            $usuario = users::where('usu_cedula' , $cedula)->first();
            if ($usuario) {
                if ($usuario->usu_email == "" || !$this->valid_email($usuario->usu_email)) {
                    return $this->message_json('errors', 'Correo no válido');
                } else {
                    $aleatorio = rand(1000, 10000000);
                    $url = url('/') . '/login/cambiar_password/' . $aleatorio;
                    $arrData = array(
                        'email_vista' => "acceso::mail.cambiar_password",
                        'correo_receptor' => $usuario->usu_email,
                        'nombre_receptor' => $usuario->usu_nmbcom,
                        'cabecera_correo' => 'Solicitud de Cambio de Contraseña',
                        'url' => $url,
                    );
                    list($state, $msg) = $this->send_mail($arrData);
                    $usuario->remember_token = $aleatorio;
                    $usuario->save();
                    return response()->json([
                        'success' => true,
                        'message' => $state ? $msg : 'No se pudo enviar el correo, pero puede continuar con el cambio desde la app.',
                        'user_id' => $usuario->id,
                        'token' => $aleatorio,
                        'mail_sent' => $state,
                        'mail_error' => $state ? null : $msg,
                    ]);
                }
            } else {
                return $this->message_json('errors', 'Cedula No Valida' );
            }
        } catch (Exception $e) {
            return $this->message_json('errors', $e->getMessage() );
        }
    }

    public function procesar_cambiopass(Request $request)
    {
        try {
            $user_id = $request->user_id;
            $password = $request->password;
            $password2 = $request->password2;

            $rsUsuario = users::find($user_id);
            if (!$rsUsuario) {
                return $this->message_json('errors', 'Usuario no válido');
            }

            $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/';

            if ($password == "" || $password2 == "") {
                return $this->message_json('errors', 'Ingrese Contraseña o Repetir Contraseña');
            }

            if ($password != $password2) {
                return $this->message_json('errors', 'Contraseñas no coinciden');
            }

            if (strlen($password) < 8) {
                return $this->message_json('errors', 'Contraseña debe tener mínimo 8 caracteres');
            }

            if (!preg_match($pattern, $password)) {
                return $this->message_json('errors', 'Debe contener una minúscula, una mayúscula y un número');
            }

            if (Hash::check($password, $rsUsuario->usu_password)) {
                return $this->message_json('errors', 'Contraseña no debe ser la anterior');
            }

            $rsUsuario->usu_password = $password;
            $rsUsuario->remember_token = "";
            $rsUsuario->save();

            return response()->json(['success' => true, 'message' => 'Clave cambiada correctamente']);
        } catch (Exception $e) {
            return $this->message_json('errors', $e->getMessage());
        }
    }

}
