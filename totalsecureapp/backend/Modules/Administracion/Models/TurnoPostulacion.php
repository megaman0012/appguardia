<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un guardia diciendo "yo puedo cubrir ese turno".
 *
 * No lo asigna: lo propone. Quien decide es el supervisor, porque cubrir un
 * puesto ante el cliente es una responsabilidad de la empresa, no una carrera
 * por ver quién toca primero el botón.
 */
class TurnoPostulacion extends Model
{
    protected $table = 'turno_postulacion';
    protected $primaryKey = 'tp_id';

    public const POSTULADO = 'postulado';
    public const ACEPTADA  = 'aceptada';
    public const RECHAZADA = 'rechazada';
    public const RETIRADA  = 'retirada';

    protected $fillable = [
        'tp_tv_id', 'tp_usu_id', 'tp_estado', 'tp_observaciones',
        'tp_client_uuid', 'tp_ocurrido_en', 'tp_sincronizado_en',
    ];

    protected $casts = [
        'tp_ocurrido_en'     => 'datetime',
        'tp_sincronizado_en' => 'datetime',
    ];

    public function vacante(): BelongsTo
    {
        return $this->belongsTo(TurnoVacante::class, 'tp_tv_id', 'tv_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\Modules\MobileApp\Models\users::class, 'tp_usu_id', 'id');
    }

    public function scopeVigentes($query)
    {
        return $query->where('tp_estado', self::POSTULADO);
    }
}
