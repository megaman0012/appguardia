<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;

class InvListaProducto extends Model
{
    protected $table = 'inv_listas_productos';
    protected $primaryKey = 'lp_id';

    protected $fillable = [
        'lp_id',
        'lp_ins_code',
        'lp_nombre',
        'lp_descripcion',
        'lp_created_at',
        'lp_created_user',
        'lp_updated_user',
        'lp_estado'
    ];

    const CREATED_AT = 'lp_created_at';
    const UPDATED_AT = 'lp_updated_at';

    public function productos()
    {
        return $this->hasMany(
            InvListaProductoItem::class,
            'lpi_lp_id',
            'lp_id'
        )->where('lpi_estado', 1);
    }

    public function institucion(){
        return $this->belongsTo(OrganizacionInstitucion::class, 'lp_ins_code', 'ins_code');
    }
}
