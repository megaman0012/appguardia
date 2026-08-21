<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListaItem extends Model
{
    protected $table = 'inv_lista_item';
    protected $primaryKey = 'lia_id';

    const CREATED_AT = 'lia_created_at';
    const UPDATED_AT = 'lia_updated_at';

    protected $fillable = [
        'lia_id',
        'lia_lista_id',
        'lia_producto_id',
        'lia_cantidad_default',
        'lia_activo',
        'lia_created_user',
        'lia_updated_user',
    ];

    protected $casts = [
        'lia_cantidad_default' => 'decimal:2',
        'lia_activo' => 'boolean',
    ];

    public function lista(): BelongsTo
    {
        return $this->belongsTo(
            Lista::class,
            'lia_lista_id',
            'li_id'
        );
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(
            ProductoCatalogo::class,
            'lia_producto_id',
            'ipc_id'
        );
    }
}
