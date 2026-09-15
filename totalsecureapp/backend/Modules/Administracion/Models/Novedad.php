<?php

namespace Modules\Administracion\Models;

use App\Support\FotoDeEvidencia;
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
        // La carpeta se resolvia aca reconstruyendola con `nv_fecha_hora`, y eso
        // perdia las fotos de las novedades que la tablet sincroniza al dia
        // siguiente. Ver App\Support\FotoDeEvidencia.
        return FotoDeEvidencia::url($this->nv_foto, 'novedad', $this->nv_fecha_hora);
    }

    public function users() {
        return $this->belongsTo(users::class, 'nv_usu_id', 'id');
    }

    public function institucion(){
        return $this->belongsTo(OrganizacionInstitucion::class, 'nv_ins_code', 'ins_code');
    }

}
