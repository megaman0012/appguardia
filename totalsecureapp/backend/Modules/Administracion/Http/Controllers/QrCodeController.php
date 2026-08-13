<?php

namespace Modules\Administracion\Http\Controllers;

use App\generalTrait;
use chillerlan\QRCode\QRCode;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use chillerlan\QRCode\QROptions;
use Modules\Administracion\Models\InstitucionMarcadores;

class QrCodeController extends Controller {

    use generalTrait;
    public function MarkerPointControl($id) {
        $dec = $this->CryptCypher($id, 0);
        $marcador = InstitucionMarcadores::with('institucion')->find($dec);
        if(!$marcador) {
            echo 'Se se encontro coincidencias del marcador soicitado';
            die();
        }
        $dec = $this->aesCypher($dec.'_TS');
        $institucion = $marcador->institucion;
        $qrcode = $this->generateQrCode($dec);
        $arrData = array(
            'qrcode'   => $qrcode,
            'marc' => $marcador,
            'inst' => $institucion,
        );
        return $this->makePdfStream('administracion::qrcode.pointcontrol',$arrData, 'QrPoint.pdf' );
    }

}
