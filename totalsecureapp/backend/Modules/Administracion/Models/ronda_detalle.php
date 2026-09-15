<?php

namespace Modules\Administracion\Models;

use App\Support\FotoDeEvidencia;use Modules\Acceso\Models\users;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ronda_detalle extends Model
{
    use HasFactory;

    protected $table = 'ronda_detalle';
    protected $primaryKey = 'rd_id';

    protected $fillable = [
        'rd_id',
        'rd_usu_id',
        'rd_ug_code',
        'rd_ins_code',
        'rd_rc_id',
        'rd_im_code',
        'rd_observacion',
        'rd_foto',
        'rd_fecha_hora',
        'rd_estado',
        'rd_lat',
        'rd_lng',
        'rd_created_at',
        'rd_updated_at',
        'rd_created_user',
        'rd_updated_user',
        'rd_client_uuid',
        'rd_sincronizado_en',
    ];

    public function getImagenUrlAttribute() {
        // La carpeta se resolvia aca reconstruyendola con la fecha del
        // registro, y eso perdia las fotos cuya sincronizacion cayo en
        // otro dia. Ver App\Support\FotoDeEvidencia.
        return FotoDeEvidencia::url($this->rd_foto, 'rondas', $this->rd_fecha_hora);
    }

    const CREATED_AT = 'rd_created_at';
    const UPDATED_AT = 'rd_created_at';

    public function rondaCabecera() {
        return $this->belongsTo(ronda_cabecera::class, 'rd_rc_id', 'rc_id');
    }

    public function marcador(){
        return $this->belongsTo(InstitucionMarcadores::class, 'rd_im_code', 'im_code');
    }

    public function users() {
        return $this->belongsTo(users::class, 'rd_usu_id', 'id');
    }
}
