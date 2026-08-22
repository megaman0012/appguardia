<?php

namespace Modules\Administracion\Models;

use App\Traits\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Modules\Acceso\Models\users;

class InvMovimiento extends Model
{
    use BelongsToInstitution;

    protected string $institutionColumn = 'mov_ins_code';

    protected $table = 'inv_movimientos';
    protected $primaryKey = 'mov_id';

    protected $fillable = [
        'mov_id',
        'mov_ins_code',
        'mov_lp_id',
        'mov_tipo',
        'mov_recep_asig_user',
        'mov_recep_asig_fecha',
        'mov_recep_asig_obsv',
        'mov_recep_user',
        'mov_recep_fecha',
        'mov_recep_obsv',
        'mov_recep_lat',
        'mov_recep_lng',
        'mov_devol_user',
        'mov_devol_fecha',
        'mov_devol_obsv',
        'mov_devol_lat',
        'mov_devol_lng',
        'mov_devol_entreg_user',
        'mov_devol_entreg_fecha',
        'mov_devol_entreg_obsv',
        'mov_created_user',
        'mov_updated_user',
        'mov_estado',
    ];

    const CREATED_AT = 'mov_created_at';
    const UPDATED_AT = 'mov_updated_at';

    public function recep_asig_user() {
        return $this->belongsTo(users::class, 'mov_recep_asig_user', 'id');
    }
    public function recep_user() {
        return $this->belongsTo(users::class, 'mov_recep_user', 'id');
    }
    public function devol_user() {
        return $this->belongsTo(users::class, 'mov_devol_user', 'id');
    }
    public function devol_aprob_user() {
        return $this->belongsTo(users::class, 'mov_devol_aprob_user', 'id');
    }

    public function institucion(){
        return $this->belongsTo(OrganizacionInstitucion::class, 'mov_ins_code', 'ins_code');
    }

    public function lista(){
        return $this->belongsTo(InvListaProducto::class, 'mov_lp_id', 'lp_id');
    }

    public function detalles()
    {
        return $this->hasMany(InvMovimientoDetalle::class, 'md_mov_id', 'mov_id');
    }
}
