<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuantos equipos de un producto tiene asignados un cliente.
 *
 * Es el nivel intermedio entre el catalogo global y las listas de cada local:
 * el catalogo dice **que** existe, esto **cuanto** le toca al cliente, y la
 * lista **donde** esta.
 *
 * ⚠️ Cantidad declarada, no contador. No se mueve con recepciones ni
 * devoluciones. Ver `App\Services\Inventario\ResumenDeInventario`.
 */
class StockCliente extends Model
{
    protected $table = 'inv_stock_cliente';

    protected $primaryKey = 'isc_id';

    const CREATED_AT = 'isc_created_at';
    const UPDATED_AT = 'isc_updated_at';

    protected $fillable = [
        'isc_org_code',
        'isc_producto_id',
        'isc_cantidad',
        'isc_observacion',
        'isc_activo',
        'isc_created_user',
        'isc_updated_user',
    ];

    protected $casts = [
        'isc_cantidad' => 'decimal:2',
        'isc_activo'   => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'isc_org_code', 'org_code');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(ProductoCatalogo::class, 'isc_producto_id', 'ipc_id');
    }
}
