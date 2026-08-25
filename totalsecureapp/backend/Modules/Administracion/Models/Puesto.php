<?php

namespace Modules\Administracion\Models;

use App\Traits\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Puesto de trabajo dentro de un Local.
 *
 * Es la posición que cubre un guardia durante su turno (garita, andén, sala de
 * monitoreo). Distinto del marcador QR, que es un punto de paso en una ronda.
 */
class Puesto extends Model
{
    use BelongsToInstitution;

    protected string $institutionColumn = 'pu_ins_code';

    protected $table = 'puesto';
    protected $primaryKey = 'pu_id';

    protected $fillable = [
        'pu_ins_code',
        'pu_nombre',
        'pu_descripcion',
        'pu_lat',
        'pu_lng',
        'pu_estado',
        'pu_created_user',
        'pu_updated_user',
    ];

    protected $casts = ['pu_estado' => 'boolean'];

    /** El local al que pertenece el puesto. */
    public function institucion(): BelongsTo
    {
        return $this->belongsTo(OrganizacionInstitucion::class, 'pu_ins_code', 'ins_code');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class, 'tu_puesto_id', 'pu_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('pu_estado', true);
    }

    /** Nombre con su local, para los selectores. */
    public function getNombreCompletoAttribute(): string
    {
        return $this->pu_nombre . ' — ' . (optional($this->institucion)->ins_descripcion ?? 'sin local');
    }
}
