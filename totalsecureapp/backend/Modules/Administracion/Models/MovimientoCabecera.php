<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MovimientoCabecera extends Model
{
    protected $table = 'inv_movimiento_cabecera';
    protected $primaryKey = 'mc_id';

    const CREATED_AT = 'mc_created_at';
    const UPDATED_AT = 'mc_updated_at';

    const TIPO_RECEPCION = 'recepcion';
    const TIPO_DEVOLUCION = 'devolucion';
    const TIPO_BAJA = 'baja';

    const ESTADO_PENDIENTE = 'pendiente';
    const ESTADO_COMPLETADO = 'completado';
    const ESTADO_CANCELADO = 'cancelado';

    protected $fillable = [
        'mc_id',
        'mc_ins_code',
        'mc_lista_id',
        'mc_tipo',
        'mc_usuario_id',
        'mc_fecha',
        'mc_lat',
        'mc_lng',
        'mc_observaciones',
        'mc_estado',
        'mc_created_user',
        'mc_updated_user',
    ];

    protected $casts = [
        'mc_fecha' => 'datetime',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(
            OrganizacionInstitucion::class,
            'mc_ins_code',
            'ins_code'
        );
    }

    public function lista(): BelongsTo
    {
        return $this->belongsTo(
            Lista::class,
            'mc_lista_id',
            'li_id'
        );
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\User::class,
            'mc_usuario_id',
            'id'
        );
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(
            MovimientoDetalle::class,
            'md_movimiento_id',
            'mc_id'
        );
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('mc_tipo', $tipo);
    }

    public function scopePorEstado($query, string $estado)
    {
        return $query->where('mc_estado', $estado);
    }

    public function scopePorInstitucion($query, int $insCode)
    {
        return $query->where('mc_ins_code', $insCode);
    }

    public function scopeCompletados($query)
    {
        return $query->where('mc_estado', self::ESTADO_COMPLETADO);
    }
}
