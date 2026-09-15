<?php

namespace Modules\Administracion\Models;

use App\Support\FotoDeEvidencia;
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

    public function getImagenUrlAttribute() {
        // La carpeta se resolvia aca reconstruyendola con la fecha del
        // registro, y eso perdia las fotos cuya sincronizacion cayo en
        // otro dia. Ver App\Support\FotoDeEvidencia.
        return FotoDeEvidencia::url($this->bt_foto, 'bitacora', $this->bt_fecha_hora);
    }

    public function users() {
        return $this->belongsTo(users::class, 'bt_usu_id', 'id');
    }

    public function institucion(){
        return $this->belongsTo(OrganizacionInstitucion::class, 'bt_ins_code', 'ins_code');
    }

}
