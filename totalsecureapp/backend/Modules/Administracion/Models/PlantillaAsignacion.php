<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\MobileApp\Models\users;

/**
 * Quién cubre una franja, con vigencia propia.
 *
 * La vigencia permite reemplazos y rotaciones sin rehacer la plantilla: se acota
 * la asignación existente y se agrega otra para el período del reemplazo.
 */
class PlantillaAsignacion extends Model
{
    protected $table = 'plantilla_asignacion';
    protected $primaryKey = 'pa_id';

    protected $fillable = ['pa_pf_id', 'pa_usu_id', 'pa_desde', 'pa_hasta', 'pa_estado'];

    protected $casts = [
        'pa_desde'  => 'date',
        'pa_hasta'  => 'date',
        'pa_estado' => 'boolean',
    ];

    public function franja(): BelongsTo
    {
        return $this->belongsTo(PlantillaFranja::class, 'pa_pf_id', 'pf_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(users::class, 'pa_usu_id', 'id');
    }

    /** Sin fechas, la asignación vale siempre. */
    public function vigenteEn(\Carbon\Carbon $fecha): bool
    {
        if (!$this->pa_estado) {
            return false;
        }
        if ($this->pa_desde && $fecha->lt($this->pa_desde)) {
            return false;
        }
        if ($this->pa_hasta && $fecha->gt($this->pa_hasta)) {
            return false;
        }

        return true;
    }
}
