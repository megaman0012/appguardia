<?php

namespace Modules\Acceso\Http\Controllers;

use App\generalTrait;
use App\Mail\MailTrait;
//use App\View\Components\Input;
use App\View\Components\Input;
use DB;
use Mail;
use Validator;
use Session;
use Cache;
use Redirect;
use Response;

//use Illuminate\Support\Facades\Input;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Hash;

use App\Services\PermisosApiService;
use App\Services\RecuperacionDeClave;
use Modules\Acceso\Models\users;
use Modules\Acceso\Models\user_has_gestions;
use Modules\Acceso\Models\role_has_permissions;

class LoginController extends Controller{

    use generalTrait;
    use MailTrait;

    public function index(Request $request){
        $this->borrar_sesion();
        return view('acceso::login.index');
    }

    public function borrar_sesion(){
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Session::forget('usuID');
        Session::forget('usuGS');
        Session::forget('usuName');
        Session::forget('usuDN');
        Session::forget('usuPF');
        Session::forget('url');
    }

    public function login_check(Request $request){
        $credentials = $request->only('usu_cedula', 'password');
        try {
            if (Auth::attempt($credentials)) {
                $usuario = Auth::user();
                if ($usuario->usu_state !== 1) {
                    Auth::logout();
                    return $this->message_json('errors', 'La cuenta no está activa');
                }

                $usges = user_has_gestions::where('ug_user_id', $usuario->id )->where('ug_finish', 0)->first();
                if(!$usges){ return $this->message_json('errors', 'Usuario No Posee Gestion Asignada'); }

                Auth::login($usuario);
                Session::put('usuID', $usuario->id);
                Session::put('usuGS', $usges->ug_code);
                Session::put('usuName', $usuario->usu_nmbcom);
                Session::put('usuDN', $usuario->usu_cedula);
                $this->control_trafico($request);
                return response()->json(array('success' => 'Informacion correcta, transfiriendo '));
            } else {
                return $this->message_json('errors', 'Credenciales Ingresadas Incorrectas');
            }
        } catch (\Exception $e) {
            return $this->message_json('errors', $e->getLine().': '.$e->getMessage());
        }
    }

    public function seleccionar_perfil(Request $request){
        if (Auth::user() == null) {
            return Redirect::to('/acceso/login');
        }

        $rsPerfil = Auth::user()
            ->roles()
            ->where('visible', 1)
            ->where('estado', 1)
            ->orderBy('name')
            ->get();

        $perfil = "";
        foreach ($rsPerfil as $key => $prf) {
            $perfil .= '<tr id="rowCount31" style="font-size: 12px;">';
                $perfil .= '<td align="center">'.$prf->name.'</td>';
                $perfil .= '<td align="center">'.$prf->descripcion.'</td>';
                $perfil .= '<td align="center">
                    <button type="button" data-code="'.($this->aesCypher($prf->id)).'" class="btn btn-sm btn-outline-secondary btn-block btnPerfil"><i class="fas fa-arrow-right"></i></button>
                </td>';
            $perfil .= '</tr>';
        }
        return view('acceso::login.seleccionar_perfil', [ 'perfil' => $perfil ]);
    }

    public function procesar_perfil(Request $request){

        $code = $request->input('code');
        $code = $this->aesCypher($code, 2);
        // Solo permisos de secciones WEB. Un Vigilante tiene 21 permisos moviles
        // (rondas.ver, acceso.registrar...) y ninguno web; sin este filtro el
        // primero de esa lista se usaba como destino y el navegador terminaba en
        // /rondas.ver, que no existe: un 404 en vez de una explicacion.
        $rsUrl = role_has_permissions::Join('permissions', 'permissions.id', 'permission_id')
                        ->Join('permission_section', 'permission_section.ps_codigo', 'permissions.ps_codigo')
                        ->Join('roles', 'role_id', 'roles.id')
                        ->where('role_id', $code)
                        ->where('pr_state', 1)
                        ->whereIn('permissions.ps_codigo', PermisosApiService::SECCIONES_WEB)
                        ->orderBy('ps_posicion')
                        ->orderBy('pr_posicion')
                        ->select(
                            'role_has_permissions.*',
                            'permissions.*',
                            'permission_section.*',
                            'roles.id as rol_id',
                            'roles.name as rol_name'
                        )
                        ->get();
        if (!isset($rsUrl[0])) {
            $rol = DB::table('roles')->where('id', $code)->value('name');

            // Se indica DONDE trabaja ese perfil, segun las secciones de permisos
            // que si tiene: 19 es el portal cliente, 10-18 la app movil.
            $secciones = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_id', $code)
                ->distinct()
                ->pluck('permissions.ps_codigo');

            if ($secciones->contains(19)) {
                $donde = 'Su acceso es el portal de cliente.';
            } elseif ($secciones->isNotEmpty()) {
                $donde = 'Su trabajo se realiza desde la aplicación móvil.';
            } else {
                $donde = 'Este perfil no tiene permisos asignados; contacte al administrador.';
            }

            return response()->json(array(
                'errors' => sprintf('El perfil %s no tiene acceso al panel web. %s', $rol ?: 'seleccionado', $donde),
            ));
        }

        Session::put('url', $rsUrl);
        Session::put('usuPF', $rsUrl[0]->rol_name);
        $this->control_trafico($request);
        return response()->json(array('link' => $rsUrl[0]->name));

    }

    public function logout_check(Request $request){
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return Redirect::to('/acceso/login');
    }

    protected $rules_solicitudpass = [
        'cedula2' => 'required',
    ];


    /**
     * Pide el enlace para cambiar la clave, desde el portal.
     *
     * ⚠️ Antes generaba el token con `rand()`, lo guardaba en claro en
     * `remember_token` y **sin caducidad**, y respondia distinto segun la cedula
     * existiera o no. Ahora lo emite `RecuperacionDeClave`: hasheado, con
     * vencimiento, de un solo uso, y con la misma respuesta en todos los casos
     * para que no se pueda averiguar quien esta registrado.
     */
    public function solicitud_cambiopass(Request $request, RecuperacionDeClave $recuperacion)
    {
        $request->merge(array_map('trim', $request->all()));
        $validator = Validator::make($request->all(), $this->rules_solicitudpass);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        try {
            $usuario = users::where('usu_cedula', $request->cedula2)
                ->where('usu_state', 1)
                ->first();

            if ($usuario) {
                $recuperacion->emitir($usuario, url('/') . '/acceso/cambiar_password');
            }
        } catch (\Exception $e) {
            report($e);
        }

        return response()->json([
            'message' => 'Si la cédula está registrada y tiene un medio de contacto cargado, '
                . 'recibirá las instrucciones. Si no le llegan, pida el cambio a su supervisor.',
        ]);
    }

    /**
     * Abre el formulario, solo si el codigo del enlace es valido.
     *
     * El usuario autorizado queda en la SESION. Antes se pasaba su `user_id` a
     * la vista y volvia como campo del formulario, que es de donde
     * `procesar_cambiopass` lo tomaba: bastaba cambiar ese numero para cambiarle
     * la clave a cualquier otra persona.
     */
    public function cambiar_password($numero, RecuperacionDeClave $recuperacion){
        $rsUsuario = users::where('usu_reset_token', hash('sha256', $numero))->first();

        if (!$rsUsuario || $recuperacion->verificar($rsUsuario, $numero) !== null) {
            Session::flash('message-error', "El enlace no es válido o ya venció. Solicite uno nuevo.");
            return Redirect::to('/acceso/login');
        }

        Session::put('reset_usuario_id', $rsUsuario->id);

        return view('acceso::login.cambiar_password', ['user_id' => $rsUsuario->id]);
    }

    /**
     * Cambia la clave del usuario que abrio un enlace valido.
     *
     * ⚠️ **Antes tomaba `user_id` del propio formulario y no comprobaba ningun
     * token.** `POST /acceso/procesar_cambiopass` con `user_id=1` cambiaba la
     * clave del usuario 1. Es el mismo agujero que tenia la API, por la otra
     * puerta.
     *
     * Ahora el usuario sale de la sesion que dejo `cambiar_password` al validar
     * el codigo. Lo que venga en el formulario se ignora.
     */
    public function procesar_cambiopass(Request $request, RecuperacionDeClave $recuperacion){
        $request->merge(array_map('trim', $request->all()));
        $password = $request->input("password");
        $password2 = $request->input("password2");

        $rsUsuario = users::find(Session::get('reset_usuario_id'));

        if (!$rsUsuario) {
            Session::flash('message-error', "La sesión de cambio venció. Solicite un enlace nuevo.");
            return Redirect::to('/acceso/login');
        }

        $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/';

        if($password == "" || $password2 == ""){
            return $this->errorDeCambio("Ingrese Contraseña o Repetir Contraseña");
        }else if($password != $password2){
            return $this->errorDeCambio("Contraseñas no coinciden");
        }else if($password === $rsUsuario->usu_cedula){
            // Antes esto comparaba el HASH contra el texto plano diciendo
            // «Contraseña no puede usuario»: nunca era cierto. Queria comparar
            // contra la cedula, que es lo que hace ahora.
            return $this->errorDeCambio("La contraseña no puede ser su número de cédula");
        }else if(Hash::check($password, $rsUsuario->usu_password)){
            // Y esto era `usu_password == Hash::make($password)`, que tampoco es
            // cierto nunca: bcrypt sala distinto en cada llamada.
            return $this->errorDeCambio("Contraseña no debe ser la anterior");
        }else if(strlen($password) < 8){
            return $this->errorDeCambio("Contraseña debe tener mínimo 8 caracteres");
        }else if(!preg_match($pattern, $password)){
            return $this->errorDeCambio("Debe contener una minuscula, una mayuscula y un numero.");
        }else{
            $rsUsuario->usu_password = Hash::make($password);
            $rsUsuario->save();

            $recuperacion->invalidar($rsUsuario);
            Session::forget('reset_usuario_id');

            Session::flash('message-success', "Clave Cambiada");
            return Redirect::to('acceso/login');
        }
    }

    /**
     * Vuelve al formulario con el error, sin rehacer el enlace.
     *
     * Antes cada rechazo redirigia a `/acceso/cambiar_password/{remember_token}`,
     * o sea que **el token viajaba otra vez por la URL** en cada error, y hoy
     * ademas se consumiria el intento. La sesion ya guarda quien es.
     */
    private function errorDeCambio(string $mensaje)
    {
        Session::flash('message-error', $mensaje);

        return Redirect::back();
    }

}
