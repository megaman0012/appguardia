<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccesoVisitante extends Model
{
    use HasFactory;

    protected $table = 'acceso_visitante';
    protected $primaryKey = 'avi_code';

    protected $fillable = [
        'avi_ac_code',
        'avi_motivo',
        'avi_area_visita',
        'avi_persona_visita',
        'avi_empresa_origen',
        'avi_personas_grupo',
        'avi_duracion_estimada',
    ];

    protected $casts = [
        'avi_personas_grupo' => 'integer',
    ];

    public function acceso(): BelongsTo
    {
        return $this->belongsTo(Acceso::class, 'avi_ac_code', 'ac_code');
    }
}
