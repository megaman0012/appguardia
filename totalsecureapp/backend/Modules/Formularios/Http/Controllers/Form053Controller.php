<?php

namespace Modules\Formularios\Http\Controllers;

use App\generalTrait;
use DB;
use DataTables;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

use Modules\Administracion\Models\tipo_documento;
use Modules\Administracion\Models\tipo_genero;
use Modules\Administracion\Models\referencia_motivo;
use Modules\Administracion\Models\tipo_servicio;
use Modules\Administracion\Models\tipo_especialidad;

class Form053Controller extends Controller{

    use generalTrait;

    public function index(){
        $docOpts = $this->generateFormOptions( tipo_documento::where('td_estado', 1)->get(), [ 'td_code', 'td_descripcion' ] );
        $genPers = $this->generateFormOptions( tipo_genero::where('tg_estado', 1)->get(), [ 'tg_code', 'tg_descripcion' ] );
        $motRfer = $this->generateFormOptions( referencia_motivo::where('rm_estado', 1)->get(), [ 'rm_code', 'rm_motivo' ] );
        $Special = $this->generateFormOptions( tipo_especialidad::where('te_estado', 1)->get(), [ 'te_code', 'te_descripcion' ] );
        $Service = $this->generateFormOptions( tipo_servicio::where('ts_estado', 1)->get(), [ 'ts_code', 'ts_descripcion' ] );

        return view('formularios::form053.index', [
            'documentOptions'=>$docOpts,
            'generoPersona' => $genPers,
            'motivoReferencia' => $motRfer,
            'especialidad'=>$Special,
            'servicio'=>$Service,
        ]);
    }
}
