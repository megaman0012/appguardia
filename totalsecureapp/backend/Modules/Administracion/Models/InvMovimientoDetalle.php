<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;

class InvMovimientoDetalle extends Model
{
    protected $table = 'inv_movimiento_detalles';
    protected $primaryKey = 'md_id';

    protected $fillable = [
        'md_id',
        'md_mov_id',
        'md_pr_id',
        'md_cant_asign',
        'md_cant_recep',
        'md_recep_obsv',
        'md_cant_devol',
        'md_cant_final',
        'md_created_at',
        'md_created_user',
        'md_updated_user',
        'md_exist',
        'md_estado'
    ];

    const CREATED_AT = 'md_created_at';
    const UPDATED_AT = 'md_updated_at';

    public function movimiento()
    {
        return $this->belongsTo(InvMovimiento::class, 'md_mov_id', 'mov_id');
    }

    public function producto()
    {
        return $this->belongsTo(InvProducto::class, 'md_pr_id', 'pr_id');
    }

}
