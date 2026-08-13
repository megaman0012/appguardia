<?php

namespace Modules\Administracion\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Acceso\Models\users;

class Bitacora extends Model
{
    use HasFactory;
    protected $table = 'bitacora';

    protected $primaryKey = 'bt_id';

    //public $timestamps = true;

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $fillable = [
        'bt_usu_id',
        'bt_ug_code',
        'bt_ins_code',
        'bt_observacion',
        'bt_foto',
        'bt_fecha_hora',
        'bt_estado',
        'bt_lat',
        'bt_lng',
        'bt_created_at',
        'bt_updated_at',
        'bt_created_user',
        'bt_updated_user',
    ];

    const CREATED_AT = 'bt_created_at';
    const UPDATED_AT = 'bt_updated_at';

    public function getImagenUrlAttribute(){
        $fecha = Carbon::parse($this->bt_fecha_hora)->format('Y/m/d');
        $imagePath = public_path('images/bitacora/' . $fecha . '/' . $this->bt_foto);
        if (file_exists($imagePath) && !empty($this->bt_foto)) {
            return asset('images/bitacora/' . $fecha . '/' . $this->bt_foto);
        }
        return null;
    }

    public function users() {
        return $this->belongsTo(users::class, 'bt_usu_id', 'id');
    }

    public function institucion(){
        return $this->belongsTo(OrganizacionInstitucion::class, 'bt_ins_code', 'ins_code');
    }

}
