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
use App\Services\RecuperacionDeClave;
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

        // Todos los roles activos del usuario, no una lista fija.
        // Antes estaba hardcodeado a ['Supervisor','Vigilante'], asi que un
        // Cliente del portal (o un Administrador) recibia perfiles y abilities
        // vacios. La autorizacion real la hace CheckPermission contra la base,
        // por eso el portal igual funcionaba, pero la respuesta del login
        // mentia sobre lo que el usuario puede hacer.
        $pfs = $user->roles()->where('estado', 1)->get();
        if($pfs->isEmpty()){
            // isEmpty() y no !$pfs: una Collection vacia es truthy, asi que la
            // comprobacion anterior nunca se disparaba.
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
            // Ver config/sanctum.php: `env()` devuelve texto y Carbon 3 exige
            // int|float. Con env() directo esto respondia 500 y la app movil no
            // podia iniciar sesion.
            'expires_at'    => Carbon::now()->addSeconds(config('sanctum.expiracion_token_movil'))
        ]);

        return response()->json([
            'access_token'  => $token,
            'refresh_token' => $refreshToken,
            'expires_in'    => config('sanctum.expiracion_token_movil'),
            'usuario'       => array(
                'usu_nombres' => $user->usu_nmbcom,
                'usu_email' => $user->usu_email,
                'usu_acc' => $acc->pr_value,
                // Interruptor de "quiero cubrir turnos extra": viaja en el login
                // para que la pantalla de perfil lo muestre sin otra consulta.
                'usu_acepta_extras' => (bool) $user->usu_acepta_extras
            ),
            'abilities' => $abilities,
            'perfiles'  => $pfs->pluck('name')->values()
        ]);

    }

    /**
     * Pide un codigo para cambiar la clave.
     *
     * ⚠️ **Antes esto entregaba la cuenta.** Devolvia `user_id` y el token en la
     * respuesta a cambio de una cedula -- que en Ecuador no es un secreto -- y el
     * endpoint es publico en internet. Con esos dos datos, `procesar_paswchg`
     * cambiaba la clave de quien fuera.
     *
     * Ahora el codigo sale por un canal que solo alcanza al dueno de la cuenta, y
     * la respuesta es **siempre la misma**, exista o no la cedula: si cambiara,
     * serviria para averiguar quien esta registrado.
     */
    public function solicitud_cambiopass(Request $request, RecuperacionDeClave $recuperacion)
    {
        $validator = Validator::make($request->all(), $this->rules_solicitudpass['rules'], $this->rules_solicitudpass['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        try {
            $usuario = users::where('usu_cedula', $request->usu_cedula)
                ->where('usu_state', 1)
                ->first();

            if ($usuario) {
                $recuperacion->emitir($usuario);
            }
        } catch (\Exception $e) {
            // Tampoco el error puede distinguir una cedula registrada de una que
            // no lo esta: se registra y se responde igual que siempre.
            report($e);
        }

        return response()->json([
            'success' => true,
            'message' => 'Si la cédula está registrada y tiene un medio de contacto cargado, '
                . 'recibirá un código. Si no le llega, pida el cambio a su supervisor.',
        ]);
    }

    /**
     * Cambia la clave, contra un codigo emitido y todavia valido.
     *
     * ⚠️ **Antes bastaba `user_id`.** No pedia token, no pedia autenticacion, y el
     * `user_id` es un entero secuencial: mandar `{"user_id":1,...}` cambiaba la
     * clave del usuario 1. Sobre un endpoint publicado en internet, eso es la
     * cuenta de cualquiera, incluidas las cinco de Administrador.
     *
     * Ahora se identifica por cedula + codigo. El `user_id` ya no se acepta: era
     * justamente lo que hacia falta adivinar, y no hay nada que adivinar en un
     * entero consecutivo.
     */
    public function procesar_cambiopass(Request $request, RecuperacionDeClave $recuperacion)
    {
        $validator = Validator::make($request->all(), [
            'usu_cedula' => 'required',
            'codigo'     => 'required|string',
            'password'   => 'required|string|min:8',
            'password2'  => 'required|string|same:password',
        ], [
            'usu_cedula.required' => 'La cedula es requerida para continuar.',
            'codigo.required'     => 'Ingrese el código que recibió.',
            'password.required'   => 'Ingrese Contraseña',
            'password.min'        => 'Contraseña debe tener mínimo 8 caracteres',
            'password2.required'  => 'Ingrese Repetir Contraseña',
            'password2.same'      => 'Contraseñas no coinciden',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        try {
            $usuario = users::where('usu_cedula', $request->usu_cedula)
                ->where('usu_state', 1)
                ->first();

            /*
             * Un usuario que no existe recibe el mismo mensaje que un codigo
             * equivocado. Distinguirlos convertiria este endpoint en una forma
             * de averiguar que cedulas estan registradas.
             */
            if (!$usuario) {
                return $this->message_json('errors', 'Código incorrecto.');
            }

            if ($motivo = $recuperacion->verificar($usuario, $request->codigo)) {
                return $this->message_json('errors', $motivo);
            }

            $password = $request->password;

            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
                return $this->message_json('errors', 'Debe contener una minúscula, una mayúscula y un número');
            }

            /*
             * `Hash::check` y no `==`. La comprobacion original era
             * `$rsUsuario->usu_password == Hash::make($password)`, que **nunca es
             * cierta**: bcrypt usa una sal distinta cada vez, asi que dos hashes
             * de la misma clave no coinciden. La regla «no debe ser la anterior»
             * no bloqueaba nada.
             */
            if (Hash::check($password, $usuario->usu_password)) {
                return $this->message_json('errors', 'Contraseña no debe ser la anterior');
            }

            /*
             * La otra comprobacion muerta que habia aca comparaba el hash contra
             * el texto plano diciendo «no puede ser el usuario»; queria comparar
             * contra `usu_cedula`. Eso si vale la pena, y ahora se hace de verdad.
             */
            if ($password === $usuario->usu_cedula) {
                return $this->message_json('errors', 'La contraseña no puede ser su número de cédula');
            }

            $usuario->usu_password = $password;
            $usuario->save();

            // Un codigo sirve una sola vez.
            $recuperacion->invalidar($usuario);

            return response()->json(['success' => true, 'message' => 'Clave cambiada correctamente']);
        } catch (\Exception $e) {
            report($e);

            return $this->message_json('errors', 'No se pudo cambiar la clave. Intente de nuevo.');
        }
    }

}
