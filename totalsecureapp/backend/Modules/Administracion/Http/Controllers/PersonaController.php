<?php

namespace Modules\Administracion\Http\Controllers;

use App\generalTrait;
use DB;
use DataTables;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Administracion\Models\persona;
use Modules\Administracion\Models\tipo_documento;
use Modules\Administracion\Models\tipo_genero;

class PersonaController extends Controller{

    use generalTrait;

    public function index(){

        $docOpts = $this->generateFormOptions( tipo_documento::where('td_estado', 1)->get(), [ 'td_code', 'td_descripcion' ] );
        $genPers = $this->generateFormOptions( tipo_genero::where('tg_estado', 1)->get(), [ 'tg_code', 'tg_descripcion' ] );
        return view('administracion::persona.index', ['documentOptions'=>$docOpts, 'generoPersona' => $genPers]);

    }

    public function datatable(){

        $resulset = persona::orderBy('pt_code', 'desc')->take(50)->get();
        return Datatables::of($resulset)
        ->addColumn('documento', function ($resulset) {
            return $resulset->pt_documento.' - '.$resulset->pt_tip_doc;
        })
        ->addColumn('accion', function ($resulset) {
            $data = "data-code=\"" . $resulset->pt_code . "\"";
            return "<button class='edit-modal btn btn-sm btn-outline-info gsTnt' " . $data . ">Gestionar</button>";
        })
        ->addColumn('estado', function ($resulset) {
            $checked = '';
            //if ($resulset->estado == 1) { $checked = 'checked'; }
            $checkbox = "<input type='checkbox' class='destroy'
            data-codigo=\"" . $resulset->pt_code . "\"
            id=\"" . $resulset->pt_code . "\" $checked/>
            <label for=\"" . $resulset->pt_code . "\"><span class='ui'></span></label>";
            return $checkbox;
        })
        ->rawColumns(['accion', 'estado'])
        ->make(true);
    }

    public function store(Request $request){
        DB::beginTransaction();
        try {
            $valid     = config('administracion.validation.store_person');
            $validator = Validator::make($request->all(), $valid['rules'], $valid['messages']);
            if ($validator->fails()) { return response()->json(['success' => false, 'errors' => $validator->errors()]); }
            $prmVal = $validator->validated();
            $prExs = persona::where('pt_documento', $prmVal['pt_documento'])->where('pt_tip_doc', $prmVal['pt_tip_doc'])->exists();
            if ($prExs) {
                return $this->message_json('errors', 'La persona ya se encuentra registrada.');
            }
            $prCre = persona::create($prmVal);
            DB::commit();
            return response()->json( [ 'success' => true, 'msg' => 'Persona Creada Correctamente.' ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->message_json('errors', $e->getMessage());
        }
    }

    public function update(Request $request){
        DB::beginTransaction();
        try {
            $valid     = config('administracion.validation.store_person');
            $validator = Validator::make($request->all(), $valid['rules'], $valid['messages']);
            if ($validator->fails()) { return response()->json(['success' => false, 'errors' => $validator->errors()]); }
            $prmVal = $validator->validated();
            $prExs = Persona::where('pt_documento', $prmVal['pt_documento'])->where('pt_tip_doc', $prmVal['pt_tip_doc'])->exists();
            $user = persona::findOrFail($prmVal['pt_code']);
            if ($prExs) {
                return $this->message_json('errors', 'La persona ya se encuentra registrada.');
            }
            $prCre = persona::create($prmVal);
            DB::commit();
            return response()->json( [ 'success' => true, 'msg' => 'Persona Creada Correctamente.' ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->message_json('errors', $e->getMessage());
        }
    }
}
