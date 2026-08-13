<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;

class InvProducto extends Model
{
    protected $table = 'inv_productos';
    protected $primaryKey = 'pr_id';

    protected $fillable = [
        'pr_id',
        'pr_nombre',
        'pr_descripcion',
        'pr_especificacion',
        'pr_stock_actual',
        'pr_created_at',
        'pr_created_user',
        'pr_updated_user',
        'pr_estado',
    ];

    const CREATED_AT = 'pr_created_at';
    const UPDATED_AT = 'pr_updated_at';

    public function listas()
    {
        return $this->belongsToMany(
            InvListaProducto::class,
            'inv_lista_producto_items',
            'lpi_pr_id',
            'lpi_lp_id'
        )->withPivot('lpi_cantidad');
    }
}

