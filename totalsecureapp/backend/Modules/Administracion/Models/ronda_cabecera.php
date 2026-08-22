<?php

namespace Modules\Administracion\Models;
use Modules\Acceso\Models\users;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;

class ronda_cabecera extends Model {
    use BelongsToInstitution;

    protected string $institutionColumn = 'rc_ins_code';

    use HasFactory;

    protected $table = 'ronda_cabecera';
    protected $primaryKey = 'rc_id';

    protected $fillable = [
        'rc_id',
        'rc_usu_code',
        'rc_ins_code',
        'rc_ug_code',
        'rc_fecha_inicio',
        'rc_fecha_fin',
        'rc_estado',
        'rc_estado_ronda',
        'rc_comentarios',
        'rc_lat_inicio',
        'rc_lng_inicio',
        'rc_lat_fin',
        'rc_lng_fin',
        'rc_created_at',
        'rc_created_user',
        'rc_updated_at',
        'rc_updated_user',
    ];

    const CREATED_AT = 'rc_created_at';
    const UPDATED_AT = 'rc_updated_at';

    public function users() {
        return $this->belongsTo(users::class, 'rc_usu_code', 'id');
    }

    public function institucion(){
        return $this->belongsTo(OrganizacionInstitucion::class, 'rc_ins_code', 'ins_code');
    }


    /*public function detalles()
    {
        return $this->hasMany(ronda_detalle::class, 'rd_rc_id');
    }*/
}
