<?php

namespace Modules\Formularios\Http\Controllers;

use App\generalTrait;
use DataTables;
//use Illuminate\Support\Facades\Input;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
//use Illuminate\Support\Facades\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

use Modules\Formularios\Models\form006Hs;
use Modules\Administracion\Models\tipo_documento;

class Form006Controller extends Controller{

    use generalTrait;

    public function index(){

        $docOpts = $this->generateFormOptions(
            tipo_documento::where('td_estado', 1)->get(),
            [ 'td_sigla', 'td_descripcion' ]
        );
        return view('formularios::form006.index', [ 'documentOptions' => $docOpts ]);

    }

    public function getbydoc(Request $request){

        try {
            $valid     = config('formularios.vldfrm006.getbydoc');
            $validator = Validator::make($request->all(), $valid['rules'], $valid['messages']);
            if ($validator->fails()) { return response()->json(['success' => false, 'errors' => $validator->errors()]); }
            $prmVal = $validator->validated();
            $resulset = form006Hs::getAllEpicrisis( $prmVal['pt_documento'], $prmVal['pt_tip_doc'] );
            if (count($resulset) > 0) {
                $tr = '';
                foreach ($resulset as $row) {
                    $tr.='<tr>';
                        $tr.='<td>'.trim('f'.htmlspecialchars($row->ingcsc).'-e'.htmlspecialchars($row->epictvo)).'</td>';
                        $tr.='<td>'.htmlspecialchars($row->epifecreg).'</td>';
                        $tr.='<td>'.htmlspecialchars($row->mpnomc).'</td>';
                        $tr.='<td>'.htmlspecialchars($row->mmnomm).'</td>';
                        $info = [
                            'nc' => trim(htmlspecialchars($row->mpcedu)),
                            'td' => htmlspecialchars($row->mptdoc),
                            'ic' => htmlspecialchars($row->ingcsc),
                            'ec' => htmlspecialchars($row->epictvo),
                        ];
                        $token = $this->CryptCypher(json_encode($info));
                        $tr.='<td><button data-token="'.$token.'" class="seepi btn btn-sm btn-outline-danger mt-2">Pdf</button></td>';
                    $tr.='</tr>';
                }

                $this->control_log( $request->all() );
                return response()->json( [ 'result' => 'success', 'content' => $tr ]);
            } else {
                return $this->message_json('errors', 'No existe informacion referente al numero y tipo de documento.');
            }
        } catch (\Exception $e) {
            $this->control_log( $request->all(), 'ERROR', $e->getMessage() );
            return $this->message_json('errors', $e->getMessage());
        }

    }

    public function document($token){

        $token = $this->CryptCypher( $token, 2 );
        $pr = json_decode($token);

        try {

            $patient         = form006Hs::getPatient($pr->nc, $pr->td);
            $patientage = [ '', '' ];
            if(!empty($patient)){
                $patientage = $this->calculoEdad(trim($patient[0]->mpfchn));
            }

            $cuadro          = form006Hs::CuadroClinico($pr->nc, $pr->td, $pr->ic, $pr->ec);
            $evolucion       = form006Hs::Evolucion($pr->nc, $pr->td, $pr->ic, $pr->ec);
            $hallazgos       = form006Hs::Hallazgos($pr->nc, $pr->td, $pr->ic, $pr->ec);
            $tratamiento     = form006Hs::Tratamiento($pr->nc, $pr->td, $pr->ic, $pr->ec);
            $indicaciones    = form006Hs::Indicaciones($pr->nc, $pr->td, $pr->ic, $pr->ec);
            $dxprincipal     = form006Hs::DxPrincipal($pr->nc, $pr->td, $pr->ic);
            $dxsec1          = form006Hs::DxSecundario1($pr->nc, $pr->td, $pr->ic);
            $dxsec2          = form006Hs::DxSecundario2($pr->nc, $pr->td, $pr->ic);
            $causaexterna    = form006Hs::CausaExterna($pr->nc, $pr->td);
            $egresavivo      = form006Hs::EgresaVivo($pr->nc, $pr->td, $pr->ic);
            $egresafallecido = form006Hs::EgresaFallecido($pr->nc, $pr->td, $pr->ic);
            $altamedica      = form006Hs::AltaMedica($pr->nc, $pr->td, $pr->ic);
            $altvoluntaria   = form006Hs::AltaVoluntaria($pr->nc, $pr->td, $pr->ic);
            $asintomatico    = form006Hs::Asintomatico($pr->nc, $pr->td, $pr->ic);
            $discapacidad    = form006Hs::Discapacidad($pr->nc, $pr->td, $pr->ic);
            $retironoautr    = form006Hs::RetiroNoAutr($pr->nc, $pr->td, $pr->ic);
            $defunmenos48    = form006Hs::DefunMenos48($pr->nc, $pr->td, $pr->ic);
            $defunmas48      = form006Hs::DefunMas48($pr->nc, $pr->td, $pr->ic);
            $diasestancia    = form006Hs::DiasEstancia($pr->nc, $pr->td, $pr->ic);
            $responsables    = form006Hs::Responsables($pr->nc, $pr->td, $pr->ic);
            $responsable     = form006Hs::Responsable($pr->nc, $pr->td, $pr->ic, $pr->ec);

            $arrData = array(
                'patient'         => $patient[0]         ?? null,
                'patientage'      => $patientage,
                'cuadro'          => $cuadro[0]          ?? null,
                'evolucion'       => $evolucion[0]       ?? null,
                'hallazgos'       => $hallazgos[0]       ?? null,
                'tratamiento'     => $tratamiento[0]     ?? null,
                'indicaciones'    => $indicaciones[0]    ?? null,
                'dxprincipal'     => $dxprincipal[0]     ?? null,
                'dxsec1'          => $dxsec1[0]          ?? null,
                'dxsec2'          => $dxsec2[0]          ?? null,
                'causaexterna'    => $causaexterna[0]    ?? null,
                'egresavivo'      => $egresavivo[0]      ?? null,
                'egresafallecido' => $egresafallecido[0] ?? null,
                'altamedica'      => $altamedica[0]      ?? null,
                'altvoluntaria'   => $altvoluntaria[0]   ?? null,
                'asintomatico'    => $asintomatico[0]    ?? null,
                'discapacidad'    => $discapacidad[0]    ?? null,
                'retironoautr'    => $retironoautr[0]    ?? null,
                'defunmenos48'    => $defunmenos48[0]    ?? null,
                'defunmas48'      => $defunmas48[0]      ?? null,
                'diasestancia'    => $diasestancia[0]    ?? null,
                'responsables'    => $responsables,
                'responsable'     => $responsable[0]     ?? null,
            );
            $this->control_log( $pr );
            return $this->makePdfStream('formularios::form006.pdf2021',$arrData, 'Epicrisis.pdf' );

        } catch (\Exception $e) {
            $this->control_log( $pr , 'ERROR', $e->getMessage() );
            return $this->message_json('errors', $e->getMessage());
        }
    }

}
