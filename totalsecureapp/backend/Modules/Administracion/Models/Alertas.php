<?php

namespace Modules\Administracion\Models;
use Modules\Acceso\Models\users;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alertas extends Model {
    use HasFactory;
    protected $table = 'alertas';
    protected $primaryKey = 'al_code';
    public $timestamps = true;

    protected $fillable = [
        'al_ins_code',
        'al_usu_id',
        'al_lat',
        'al_lng',
        'al_anio',
        'al_estado_alerta',
        'al_estado',
        'al_observacion',
        'al_created_user',
        'al_updated_user',
    ];

    public function institucion(){
        return $this->belongsTo(OrganizacionInstitucion::class, 'al_ins_code', 'ins_code');
    }

    public function usuario(){
        return $this->belongsTo(users::class, 'al_usu_id', 'id');
    }
}
