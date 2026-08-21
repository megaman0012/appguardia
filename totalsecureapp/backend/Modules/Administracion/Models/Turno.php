<?php

namespace Modules\Administracion\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Turno extends Model
{
    use HasFactory;

    protected $table = 'turno';
    protected $primaryKey = 'tu_id';

    protected $fillable = [
        'tu_ins_code',
        'tu_usu_id',
        'tu_marcador_code',
        'tu_fecha',
        'tu_hora_inicio_prevista',
        'tu_hora_fin_prevista',
        'tu_bio_entrada_code',
        'tu_bio_salida_code',
        'tu_marcada_entrada',
        'tu_marcada_salida',
        'tu_minutos_tardanza',
        'tu_minutos_extras',
        'tu_observaciones',
        'tu_estado',
        'tu_state',
        'tu_created_user',
        'tu_updated_user',
    ];

    const CREATED_AT = 'tu_created_at';
    const UPDATED_AT = 'tu_updated_at';

    protected $casts = [
        'tu_fecha' => 'date',
        'tu_minutos_tardanza' => 'integer',
        'tu_minutos_extras' => 'integer',
        'tu_state' => 'boolean',
    ];

    // === RELACIONES ===

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\Modules\Acceso\Models\users::class, 'tu_usu_id', 'id');
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(OrganizacionInstitucion::class, 'tu_ins_code', 'ins_code');
    }

    public function marcador(): BelongsTo
    {
        return $this->belongsTo(InstitucionMarcadores::class, 'tu_marcador_code', 'im_code');
    }

    public function bioEntrada(): BelongsTo
    {
        return $this->belongsTo(user_has_biometria::class, 'tu_bio_entrada_code', 'bio_code');
    }

    public function bioSalida(): BelongsTo
    {
        return $this->belongsTo(user_has_biometria::class, 'tu_bio_salida_code', 'bio_code');
    }

    // === SCOPES ===

    public function scopeDelDia($query, $fecha = null)
    {
        $fecha = $fecha ?? Carbon::today()->toDateString();
        return $query->where('tu_fecha', $fecha);
    }

    public function scopeDeInstitucion($query, $insCode)
    {
        return $query->where('tu_ins_code', $insCode);
    }

    public function scopeDeUsuario($query, $userId)
    {
        return $query->where('tu_usu_id', $userId);
    }

    public function scopeProgramados($query)
    {
        return $query->where('tu_estado', 'programado');
    }

    public function scopeAusentes($query)
    {
        return $query->where('tu_estado', 'ausente');
    }

    public function scopeActivos($query)
    {
        return $query->where('tu_state', true);
    }

    // === ACCESORS ===

    public function getMinutosTardanzaDisplayAttribute(): ?string
    {
        if (is_null($this->tu_minutos_tardanza)) {
            return null;
        }
        $horas = intdiv($this->tu_minutos_tardanza, 60);
        $mins = $this->tu_minutos_tardanza % 60;
        return $horas > 0 ? "{$horas}h {$mins}min" : "{$mins}min";
    }

    public function getEstadoBadgeAttribute(): string
    {
        return match($this->tu_estado) {
            'programado' => 'Programado',
            'en_curso' => 'En Curso',
            'completado' => 'Completado',
            'ausente' => 'Ausente',
            'inasistente' => 'Inasistente',
            default => 'Desconocido',
        };
    }

    public function getMinutosExtrasDisplayAttribute(): ?string
    {
        if (is_null($this->tu_minutos_extras)) {
            return null;
        }
        $horas = intdiv($this->tu_minutos_extras, 60);
        $mins = $this->tu_minutos_extras % 60;
        return $horas > 0 ? "{$horas}h {$mins}min" : "{$mins}min";
    }
}
