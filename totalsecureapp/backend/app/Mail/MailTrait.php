<?php

namespace App\Mail;

use Mail;
use View;

trait MailTrait {

    public function send_mail($arrData){
        try {
            Mail::send($arrData["email_vista"], $arrData, function ($m) use ($arrData) {
                $correo_emisor = isset($arrData["correo_emisor"]) ? $arrData["correo_emisor"] : env('MAIL_USERNAME');
                $nombre_emisor = isset($arrData["nombre_emisor"]) ? $arrData["nombre_emisor"] : env('MAIL_NOMBRE');
                $m->from($correo_emisor,$nombre_emisor);
                $m->to($arrData["correo_receptor"], $arrData["nombre_receptor"])->subject($arrData["cabecera_correo"]);
            });
            return [ true, 'Correo enviado correctamente.' ] ;
        } catch (\Exception $e) {
            return [ false,  'Error Mail : '.$e->getMessage() ];
        }
    }

}

