<?php

namespace Modules\Administracion\Models;

use App\Traits\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuadrante de cobertura de un local.
 *
 * Define, como patrón semanal, qué franjas hay que cubrir y quién las cubre.
 * De aquí se generan los turnos concretos de un período.
 */
class Plantilla extends Model
{
    use BelongsToInstitution;

    public const BORRADOR  = 'borrador';
    public const PUBLICADA = 'publicada';
    public const ARCHIVADA = 'archivada';

    protected string $institutionColumn = 'pl_ins_code';

    protected $table = 'plantilla';
    protected $primaryKey = 'pl_id';

    protected $fillable = [
        'pl_ins_code', 'pl_nombre', 'pl_estado',
        'pl_vigencia_desde', 'pl_vigencia_hasta', 'pl_observaciones',
        'pl_created_user', 'pl_updated_user',
    ];

    protected $casts = [
        'pl_vigencia_desde' => 'date',
        'pl_vigencia_hasta' => 'date',
    ];

    /**
     * El default vive en la BD, pero una instancia recien creada lo tendria en
     * null hasta releerla. Se declara tambien aqui para que el estado sea
     * consultable en el acto.
     */
    protected $attributes = [
        'pl_estado' => self::BORRADOR,
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(OrganizacionInstitucion::class, 'pl_ins_code', 'ins_code');
    }

    public function franjas(): HasMany
    {
        return $this->hasMany(PlantillaFranja::class, 'pf_pl_id', 'pl_id');
    }

    /** Turnos que generó esta plantilla. */
    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class, 'tu_plantilla_id', 'pl_id');
    }

    public function estaPublicada(): bool
    {
        return $this->pl_estado === self::PUBLICADA;
    }

    public function scopeActivas($query)
    {
        return $query->whereIn('pl_estado', [self::BORRADOR, self::PUBLICADA]);
    }
}
