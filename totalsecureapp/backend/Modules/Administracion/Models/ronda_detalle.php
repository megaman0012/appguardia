<?php

namespace Modules\Administracion\Models;
use Modules\Acceso\Models\users;
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
    ];

    public function getImagenUrlAttribute(){
        $fecha = Carbon::parse($this->rd_fecha_hora)->format('Y/m/d');
        $imagePath = public_path('images/rondas/' . $fecha . '/' . $this->rd_foto);
        if (file_exists($imagePath) && !empty($this->rd_foto)) {
            return asset('images/rondas/' . $fecha . '/' . $this->rd_foto);
        }
        return null;
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
