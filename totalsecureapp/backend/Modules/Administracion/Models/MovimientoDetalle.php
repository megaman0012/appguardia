<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoDetalle extends Model
{
    protected $table = 'inv_movimiento_detalle';
    protected $primaryKey = 'md_id';

    const CREATED_AT = 'md_created_at';
    const UPDATED_AT = 'md_updated_at';

    const ESTADO_OK = 'ok';
    const ESTADO_FALTA = 'falta';
    const ESTADO_DANADO = 'danado';

    protected $fillable = [
        'md_id',
        'md_movimiento_id',
        'md_producto_id',
        'md_cantidad_default',
        'md_cantidad_real',
        'md_recibido',
        'md_observacion',
        'md_estado',
        'md_created_user',
        'md_updated_user',
    ];

    protected $casts = [
        'md_cantidad_default' => 'decimal:2',
        'md_cantidad_real' => 'decimal:2',
        'md_recibido' => 'boolean',
    ];

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(
            MovimientoCabecera::class,
            'md_movimiento_id',
            'mc_id'
        );
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(
            ProductoCatalogo::class,
            'md_producto_id',
            'ipc_id'
        );
    }

    public function getDiferenciaAttribute(): float
    {
        return $this->md_cantidad_real - $this->md_cantidad_default;
    }

    public function getPorcentajeRecibidoAttribute(): float
    {
        if ($this->md_cantidad_default == 0) {
            return 0;
        }
        return round(($this->md_cantidad_real / $this->md_cantidad_default) * 100, 2);
    }
}
