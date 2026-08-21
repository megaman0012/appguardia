<?php

namespace Modules\Administracion\Models;

use Modules\Acceso\Models\users;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Alertas extends Model
{
    use HasFactory;

    protected $table = 'alertas';
    protected $primaryKey = 'al_code';
    public $timestamps = true;

    protected $fillable = [
        'al_ins_code',
        'al_usu_id',
        'al_lat',
        'al_lng',
        'al_anio',
        'al_estado_alerta',
        'al_estado',
        'al_prioridad',
        'al_observacion',
        'al_created_user',
        'al_updated_user',
    ];

    protected $casts = [
        'al_lat' => 'float',
        'al_lng' => 'float',
        'al_anio' => 'integer',
        'al_estado' => 'integer',
        'al_fecha' => 'datetime',
    ];

    public function institucion()
    {
        return $this->belongsTo(OrganizacionInstitucion::class, 'al_ins_code', 'ins_code');
    }

    public function usuario()
    {
        return $this->belongsTo(users::class, 'al_usu_id', 'id');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(AlertaDetalle::class, 'ad_al_code', 'al_code');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(AlertaHistorial::class, 'ah_al_code', 'al_code');
    }

    public function asignacionActual(): HasOne
    {
        return $this->hasOne(AlertaDetalle::class, 'ad_al_code', 'al_code')
                    ->latest('ad_fecha_asignacion');
    }

    public function scopePendientes($query)
    {
        return $query->where('al_estado_alerta', 'pendiente');
    }

    public function scopeEnAtencion($query)
    {
        return $query->where('al_estado_alerta', 'en_atencion');
    }

    public function scopePorInstitucion($query, int $insCode)
    {
        return $query->where('al_ins_code', $insCode);
    }

    public function scopePorPrioridad($query, string $prioridad)
    {
        return $query->where('al_prioridad', $prioridad);
    }

    public function scopeDelDia($query, $fecha = null)
    {
        return $query->whereDate('al_fecha', $fecha ?? now());
    }

    public function getTiempoRespuestaAttribute(): ?int
    {
        if ($this->al_estado_alerta !== 'finalizada') {
            return null;
        }

        $detalle = $this->detalle()->whereNotNull('ad_fecha_atencion')->first();
        if (!$detalle || !$detalle->ad_fecha_atencion) {
            return null;
        }

        return $this->al_fecha->diffInSeconds($detalle->ad_fecha_atencion);
    }

    public function getEstaRetrasadaAttribute(): bool
    {
        if (in_array($this->al_estado_alerta, ['finalizada', 'cancelada'])) {
            return false;
        }

        $minutosEspera = match($this->al_prioridad) {
            'critica' => 5,
            'alta' => 15,
            'media' => 30,
            'baja' => 60,
            default => 30,
        };

        return $this->al_fecha->diffInMinutes(now()) > $minutosEspera;
    }

    public function getNivelEscalamientoAttribute(): int
    {
        $minutosTranscurridos = $this->al_fecha->diffInMinutes(now());

        return match(true) {
            $minutosTranscurridos >= 60 => 3,
            $minutosTranscurridos >= 30 => 2,
            $minutosTranscurridos >= 15 => 1,
            default => 0,
        };
    }
}
