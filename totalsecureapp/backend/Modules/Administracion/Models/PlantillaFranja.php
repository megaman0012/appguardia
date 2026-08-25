<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una franja del cuadrante: qué puesto se cubre, qué día y en qué horario.
 */
class PlantillaFranja extends Model
{
    protected $table = 'plantilla_franja';
    protected $primaryKey = 'pf_id';

    protected $fillable = [
        'pf_pl_id', 'pf_puesto_id', 'pf_dia_semana',
        'pf_hora_inicio', 'pf_hora_fin', 'pf_estado',
    ];

    protected $casts = [
        'pf_dia_semana' => 'integer',
        'pf_estado'     => 'boolean',
    ];

    /** ISO-8601, igual que Carbon::dayOfWeekIso. */
    public const DIAS = [
        1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
        5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
    ];

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(Plantilla::class, 'pf_pl_id', 'pl_id');
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'pf_puesto_id', 'pu_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(PlantillaAsignacion::class, 'pa_pf_id', 'pf_id');
    }

    public function getDiaNombreAttribute(): string
    {
        return self::DIAS[$this->pf_dia_semana] ?? '—';
    }

    /**
     * Un turno que termina antes de empezar cruza la medianoche
     * (p. ej. 22:00 → 06:00).
     */
    public function cruzaMedianoche(): bool
    {
        return $this->minutos($this->pf_hora_fin) <= $this->minutos($this->pf_hora_inicio);
    }

    public function getDescripcionAttribute(): string
    {
        return sprintf(
            '%s %s–%s%s',
            $this->dia_nombre,
            substr((string) $this->pf_hora_inicio, 0, 5),
            substr((string) $this->pf_hora_fin, 0, 5),
            $this->cruzaMedianoche() ? ' (+1d)' : ''
        );
    }

    private function minutos(?string $hora): int
    {
        [$h, $m] = array_pad(explode(':', (string) $hora), 2, 0);

        return ((int) $h) * 60 + ((int) $m);
    }
}
