<?php

namespace App;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use PDF;

use \mervick\aesEverywhere\AES256;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;
use Session;
use Modules\Administracion\Models\log_trafico;
use Modules\Administracion\Models\log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;

trait generalTrait{

    public static function aesCypher( $text, $type = 1, $password = '' ){
        $password = $password == '' ? env('APP_KEY')  : $password;
        return $type == 1 ? AES256::encrypt($text, $password) : AES256::decrypt($text, $password);
    }

    public static function CryptCypher($text, $type = 1){
        if( $type == 1 ){
            return Crypt::encryptString($text);
        }else{
            return Crypt::decryptString($text);
        }
    }

    public static function makePdfStream($view, $arrData, $name, $setpaper = 0){
        $pdf = PDF::loadView($view, $arrData);
        if($setpaper==1){
            $pdf->setPaper('A4', 'portrait');
        }
        $pdf->getDomPDF()->setHttpContext(
            stream_context_create([
                'ssl' => [
                    'allow_self_signed'=> TRUE,
                    'verify_peer' => FALSE,
                    'verify_peer_name' => FALSE,
                ]
            ])
        );
        return $pdf->stream($name, ['Attachment' => 0]);
    }

    function message_json($tipo, $mensaje){
        $arr = array( 'success' => false , $tipo =>
            array('' =>
                array($mensaje)));
        $arr = response()->json($arr);
        return $arr;
    }

    function calculoEdad($fch_nac){
        $fch_nac = Carbon::parse($fch_nac, 'America/Guayaquil');
        $fechaActual = Carbon::now('America/Guayaquil');
        $anos = $fechaActual->diffInYears($fch_nac);
        $meses = $fechaActual->diffInMonths($fch_nac);
        $dias = $fechaActual->diffInDays($fch_nac);
        $horas = $fechaActual->diffInHours($fch_nac);
        if ($anos >= 1) {
            return [ $anos , 'A'];
        } elseif ($meses >= 1) {
            return [ $meses, 'M'];
        } elseif ($dias >= 1) {
            return [ $dias , 'D'];
        } else {
            return [ $horas, 'H'];
        }
    }

    function generateFormOptions($model, $filter){
        $opt = '<option value="">Seleccione una opcion</option>';
        foreach ($model as $key => $value) {
            $opt .= '<option value="'.$value->{$filter[0]}.'">'.$value->{$filter[1]}.'</option>';
        }
        return $opt;
    }

    function control_trafico($request){
        $lt = new log_trafico();
        $lt->lt_fecha = date("Y-m-d H:i:s");
        $lt->lt_user_id = Session::get('usuID');
        $lt->lt_user_id_gs = Session::get('usuGS');
        $lt->lt_address = $request->ip();
        $lt->lt_perfil = Session::get('usuPF') ?? 'Inicio Sesion';
        $lt->save();
    }

    public static function control_log_filament( $input, $recurso, $funcion, $type = 'NOTICE', $obsr = ''){

        $ruta = Route::current()->uri();
        $parametros = Route::current()->parameters();
        if (!empty($parametros)) {
            foreach ($parametros as $key => $valor) {
                $ruta = str_replace('{' . $key . '}', $valor, $ruta);
            }
        }
        /*$accion = Route::currentRouteAction();
        list($controlador, $funcion) = explode('@', $accion);*/
        $metodo = Request::method();

        $log = new log();
        $log->lg_year       = date("Y");
        $log->lg_date       = date("Y-m-d H:i:s");
        $log->lg_ctrl       = $recurso;
        $log->lg_func       = $funcion;
        $log->lg_user_id    = Session::get('usuID');
        $log->lg_user_id_gs = Session::get('usuGS');
        $log->lg_reqs       = json_encode( $input );
        $log->lg_urls       = $ruta;
        $log->lg_mthd       = $metodo;
        $log->lg_type       = $type;
        $log->lg_obsr       = $obsr;
        $log->save();

    }

    function control_log( $input, $type = 'NOTICE', $obsr = ''){

        $ruta = Route::current()->uri();
        $parametros = Route::current()->parameters();
        if (!empty($parametros)) {
            foreach ($parametros as $key => $valor) {
                $ruta = str_replace('{' . $key . '}', $valor, $ruta);
            }
        }
        $accion = Route::currentRouteAction();
        list($controlador, $funcion) = explode('@', $accion);
        $metodo = Request::method();

        $log = new log();
        $log->lg_year       = date("Y");
        $log->lg_date       = date("Y-m-d H:i:s");
        $log->lg_ctrl       = $controlador;
        $log->lg_func       = $funcion;
        $log->lg_user_id    = Session::get('usuID');
        $log->lg_user_id_gs = Session::get('usuGS');
        $log->lg_reqs       = json_encode( $input );
        $log->lg_urls       = $ruta;
        $log->lg_mthd       = $metodo;
        $log->lg_type       = $type;
        $log->lg_obsr       = $obsr;
        $log->save();

    }

    function control_log_api( $input, $type = 'NOTICE', $obsr = ''){

        $ruta = Route::current()->uri();
        $parametros = Route::current()->parameters();
        if (!empty($parametros)) {
            foreach ($parametros as $key => $valor) {
                $ruta = str_replace('{' . $key . '}', $valor, $ruta);
            }
        }
        $accion = Route::currentRouteAction();
        list($controlador, $funcion) = explode('@', $accion);
        $metodo = Request::method();

        $log = new log();
        $log->lg_year       = date("Y");
        $log->lg_date       = date("Y-m-d H:i:s");
        $log->lg_ctrl       = $controlador;
        $log->lg_func       = $funcion;
        $log->lg_user_id    = $input["usu"];
        $log->lg_reqs       = json_encode( $input );
        $log->lg_urls       = $ruta;
        $log->lg_mthd       = $metodo;
        $log->lg_type       = $type;
        $log->lg_obsr       = $obsr;
        $log->save();

    }

    function storeFiles($folder, $file, $user){
        $fileName = $user. '_' . time() . '.' . $file->getClientOriginalExtension();
        $datePath = Carbon::now()->format('Y/m/d');
        $directoryPath = public_path('images/'.$folder. '/' . $datePath);
        if (!file_exists($directoryPath)) { mkdir($directoryPath, 0777, true); }
        //return Storage::disk('public')->put($directoryPath . '/' . $fileName, file_get_contents($file));
        return [ $file->move($directoryPath, $fileName), $fileName];
    }

    function getSanctumSession($request){
        $user = auth('sanctum')->user();
        list($spTknId, $spTkn) = explode('|', $request->bearerToken());
        $token = $user->tokens()->where('id', $spTknId)->first();
        return [$user, $token];
    }

    function valid_email($address): bool {
        return (!preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $address)) ? FALSE : TRUE;
    }

    function generateQrCode($text){
        $options = new QROptions([
            'version'    => 5,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel'   => QRCode::ECC_L,
        ]);
        return (new QRCode($options))->render($text);
    }

}
