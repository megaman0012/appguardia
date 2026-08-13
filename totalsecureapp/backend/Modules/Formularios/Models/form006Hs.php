<?php

namespace Modules\Formularios\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class form006Hs extends Model{

    static $db =  null;
    public static function addConnection(){
        if (is_null(self::$db)) {
            self::$db = DB::connection('hagphosv');
        }
    }

    public static function getPatient($nc, $td){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.getpatient");
        $rsulset = self::$db->select($sql, [$nc, $td]);
        return $rsulset;
    }

    public static function getAllEpicrisis($nc, $td){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.getallepicrisis");
        $rsulset = self::$db->select($sql, [$nc, $td]);
        return $rsulset;
    }

    public static function CuadroClinico($nc, $td, $ic, $ec){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.cuadroclinico");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic, $ec]);
        return $rsulset;
    }

    public static function Evolucion($nc, $td, $ic, $ec){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.evolucion");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic, $ec]);
        return $rsulset;
    }

    public static function Hallazgos($nc, $td, $ic, $ec){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.hallazgos");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic, $ec]);
        return $rsulset;
    }

    public static function Tratamiento($nc, $td, $ic, $ec){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.tratamiento");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic, $ec]);
        return $rsulset;
    }

    public static function Indicaciones($nc, $td, $ic, $ec){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.indicaciones");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic, $ec]);
        return $rsulset;
    }

    public static function DxPrincipal($nc, $td, $ic){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.dxprincipal");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic]);
        return $rsulset;
    }

    public static function DxSecundario1($nc, $td, $ic){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.dxsecundario1");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic]);
        return $rsulset;
    }

    public static function DxSecundario2($nc, $td, $ic){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.dxsecundario2");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic]);
        return $rsulset;
    }

    public static function CausaExterna($nc, $td){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.causaexterna");
        $rsulset = self::$db->select($sql, [$nc, $td]);
        return $rsulset;
    }

    public static function EgresaVivo($nc, $td, $ic){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.egresavivo");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic]);
        return $rsulset;
    }

    public static function EgresaFallecido($nc, $td, $ic){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.egresafallecido");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic]);
        return $rsulset;
    }

    public static function AltaMedica($nc, $td, $ic){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.altamedica");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic]);
        return $rsulset;
    }

    public static function AltaVoluntaria($nc, $td, $ic){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.altavoluntaria");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic]);
        return $rsulset;
    }

    public static function Asintomatico($nc, $td, $ic){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.asintomatico");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic]);
        return $rsulset;
    }

    public static function Discapacidad($nc, $td, $ic){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.discapacidad");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic]);
        return $rsulset;
    }

    public static function RetiroNoAutr($nc, $td, $ic){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.retironoautr");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic]);
        return $rsulset;
    }

    public static function DefunMenos48($nc, $td, $ic){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.defunmenos48");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic]);
        return $rsulset;
    }

    public static function DefunMas48($nc, $td, $ic){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.defunmas48");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic]);
        return $rsulset;
    }

    public static function DiasEstancia($nc, $td, $ic){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.diasestancia");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic]);
        return $rsulset;
    }

    public static function Responsables($nc, $td, $ic){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.responsables");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic]);
        return $rsulset;
    }

    public static function Responsable($nc, $td, $ic, $ec){
        form006Hs::addConnection();
        $sql = config("formularios.qryfrm006hs.responsable");
        $rsulset = self::$db->select($sql, [$nc, $td, $ic, $ec]);
        return $rsulset;
    }

}
