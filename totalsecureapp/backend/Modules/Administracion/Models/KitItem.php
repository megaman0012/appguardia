<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un producto del kit, con su cantidad. */
class KitItem extends Model
{
    protected $table = 'inv_kit_item';

    protected $primaryKey = 'kii_id';

    const CREATED_AT = 'kii_created_at';
    const UPDATED_AT = 'kii_updated_at';

    protected $fillable = [
        'kii_ki_id',
        'kii_producto_id',
        'kii_cantidad',
    ];

    protected $casts = [
        'kii_cantidad' => 'decimal:2',
    ];

    public function kit(): BelongsTo
    {
        return $this->belongsTo(Kit::class, 'kii_ki_id', 'ki_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(ProductoCatalogo::class, 'kii_producto_id', 'ipc_id');
    }
}
