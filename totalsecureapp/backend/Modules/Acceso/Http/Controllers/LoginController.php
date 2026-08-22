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

    public function login_check_temp(Request $request){

        $credentials = $request->only('usu_cedula', 'password');

        try {

            $usuario = DB::connection('intranet')->table('tbl_usuario')
            ->join('permisos_aplicaciones', 'usu_id', '=', 'perm_usu_id')
            ->where('usu_user', $credentials['usu_cedula'])
            ->where('usu_pwd' , sha1($credentials['password']))
            ->where('usu_estado' , 1)
            ->where('perm_epicrisis' , 1)
            ->first();

            if (!$usuario) { return $this->message_json('errors', 'Usuario Incorrecto - Intranet'); }

            $usersystem = users::where('usu_cedula', $usuario->usu_user)->first();
            if(!$usersystem){ return $this->message_json('errors', 'Usuario No Existe - HagpAsist'); }
            if ($usersystem->usu_state !== 1){ return $this->message_json('errors', 'La cuenta no está activa'); }

            $usges = user_has_gestions::where('ug_user_id', $usersystem->id )->where('ug_finish', 0)->first();
            if(!$usges){ return $this->message_json('errors', 'Usuario No Posee Gestion Asignada'); }

            Auth::login($usersystem);
            Session::put('usuID', $usersystem->id);
            Session::put('usuGS', $usges->ug_code);
            Session::put('usuName', $usersystem->usu_nmbcom);
            Session::put('usuDN', $usersystem->usu_cedula);
            $this->control_trafico($request);
            return response()->json(array('success' => 'Informacion correcta, transfiriendo '));

        } catch (Exception $e) {
            return $this->message_json('errors', $e->getLine().': '.$e->getMessage());
        }

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
        } catch (Exception $e) {
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
        $rsUrl = role_has_permissions::Join('permissions', 'permissions.id', 'permission_id')
                        ->Join('permission_section', 'permission_section.ps_codigo', 'permissions.ps_codigo')
                        ->Join('roles', 'role_id', 'roles.id')
                        ->where('role_id', $code)
                        ->where('pr_state', 1)
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
            return response()->json(array('errors' => 'No tiene los permisos necesarios'));
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


    public function solicitud_cambiopass(Request $request)
    {
        try {
            $request->merge(array_map('trim', $request->all()));
            $validator = Validator::make($request->all(), $this->rules_solicitudpass);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()]);
            }
            $cedula = $request->cedula2;
            $usuario = users::where('usu_cedula' , $cedula)->first();
            if ($usuario) {
                if ($usuario->usu_email == "" || !$this->valid_email($usuario->usu_email)) {
                    return message_json('errors', 'Correo no válido');
                } else {
                    $aleatorio = rand(1000, 10000000);
                    $url = url('/') . '/acceso/cambiar_password/' . $aleatorio;
                    $arrData = array(
                        'email_vista' => "acceso::mail.cambiar_password",
                        'correo_receptor' => $usuario->usu_email,
                        'nombre_receptor' => $usuario->usu_nmbcom,
                        'cabecera_correo' => 'Solicitud de Cambio de Contraseña',
                        'url' => $url,
                    );

                    list($state, $msg) = $this->send_mail($arrData);
                    if($state){
                        $usuario->remember_token = $aleatorio;
                        $usuario->save();
                        return response()->json(['message' => $msg]);
                    }else{
                        return $this->message_json('errors', $msg );
                    }
                }
            } else {
                return $this->message_json('errors', 'Cedula No Valida' );
            }
        } catch (Exception $e) {
            return $this->message_json('errors', $e->getMessage() );
        }
    }

    public function cambiar_password($numero){
        $rsUsuario = users::where("remember_token",$numero)->first();
        if(!$rsUsuario){
            Session::flash('message-error', "No existe usuario");
            return Redirect::to('/acceso/login');
        }else{
            $arrData = array( 'user_id' => $rsUsuario->id );
            return view('acceso::login.cambiar_password',$arrData);
        }
    }

    public function procesar_cambiopass(Request $request){
        $request->merge(array_map('trim', $request->all()));
        $password = $request->input("password");
        $password2 = $request->input("password2");
        $user_id = $request->input("user_id");
        $rsUsuario = users::find($user_id);

        $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/';

        if($password == "" || $password2 == ""){
            Session::flash('message-error', "Ingrese Contraseña o Repetir Contraseña");
            return Redirect::to('/acceso/cambiar_password/'.$rsUsuario->remember_token);
        }else if($password != $password2){
            Session::flash('message-error', "Contraseñas no coinciden");
            return Redirect::to('/acceso/cambiar_password/'.$rsUsuario->remember_token);
        }else if($rsUsuario->usu_password == $password){
            Session::flash('message-error', "Contraseña no puede usuario");
            return Redirect::to('/acceso/cambiar_password/'.$rsUsuario->remember_token);
        }else if($rsUsuario->usu_password == Hash::make($password)){
            Session::flash('message-error', "Contraseña no debe ser la anterior");
            return Redirect::to('/acceso/cambiar_password/'.$rsUsuario->remember_token);
        }else if(strlen($password) < 8){
            Session::flash('message-error', "Contraseña debe tener mínimo 8 caracteres");
            return Redirect::to('/acceso/cambiar_password/'.$rsUsuario->remember_token);
        }else if(!preg_match($pattern, $password)){
            Session::flash('message-error', "Debe contener una minuscula, una mayuscula y un numero.");
            return Redirect::to('/acceso/cambiar_password/'.$rsUsuario->remember_token);
        }else{
            $rsUsuario->usu_password = Hash::make($password);
            $rsUsuario->remember_token = "";
            $rsUsuario->save();
            Session::flash('message-success', "Clave Cambiada");
            return Redirect::to('acceso/login');

        }
    }

}
