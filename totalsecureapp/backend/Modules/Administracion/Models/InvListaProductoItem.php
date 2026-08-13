<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class InvListaProductoItem extends Pivot
{
    protected $table = 'inv_lista_producto_items';
    protected $primaryKey = 'lpi_id';

    protected $fillable = [
        'lpi_id',
        'lpi_lp_id',
        'lpi_pr_id',
        'lpi_cantidad',
        'lpi_estado',
        'lpi_created_at',
        'lpi_created_user',
        'lpi_updated_user'
    ];

    const CREATED_AT = 'lpi_created_at';
    const UPDATED_AT = 'lpi_updated_at';

    public function lista()
    {
        return $this->belongsTo(InvListaProducto::class, 'lpi_lp_id', 'lp_id');
    }

    public function producto()
    {
        return $this->belongsTo(InvProducto::class, 'lpi_pr_id', 'pr_id');
    }
}

