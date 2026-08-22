<?php

namespace Modules\Administracion\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Modules\Acceso\Models\users;

class Novedad extends Model
{
    use BelongsToInstitution;

    protected string $institutionColumn = 'nv_ins_code';

    use HasFactory;
    protected $table = 'novedad';

    protected $primaryKey = 'nv_id';

    public $timestamps = true;

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $fillable = [
        'nv_usu_id',
        'nv_ug_code',
        'nv_ins_code',
        'nv_observacion',
        'nv_foto',
        'nv_fecha_hora',
        'nv_estado',
        'nv_lat',
        'nv_lng',
        'nv_created_at',
        'nv_updated_at',
        'nv_created_user',
        'nv_updated_user',
        'nv_client_uuid',
        'nv_sincronizado_en',
    ];

    const CREATED_AT = 'nv_created_at';
    const UPDATED_AT = 'nv_updated_at';

    public function getImagenUrlAttribute(){
        $fecha = Carbon::parse($this->nv_fecha_hora)->format('Y/m/d');
        $imagePath = public_path('images/novedad/' . $fecha . '/' . $this->nv_foto);
        if (file_exists($imagePath) && !empty($this->nv_foto)) {
            return asset('images/novedad/' . $fecha . '/' . $this->nv_foto);
        }
        return null;
    }

    public function users() {
        return $this->belongsTo(users::class, 'nv_usu_id', 'id');
    }

    public function institucion(){
        return $this->belongsTo(OrganizacionInstitucion::class, 'nv_ins_code', 'ins_code');
    }

}
