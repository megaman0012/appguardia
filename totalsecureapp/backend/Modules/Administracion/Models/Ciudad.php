<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ciudad extends Model
{
    protected $table = 'ciudad';
    protected $primaryKey = 'cd_id';

    protected $fillable = ['cd_pr_id', 'cd_nombre', 'cd_estado'];

    protected $casts = ['cd_estado' => 'boolean'];

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'cd_pr_id', 'pr_id');
    }

    /** Los locales que están en esta ciudad. */
    public function locales(): HasMany
    {
        return $this->hasMany(OrganizacionInstitucion::class, 'ins_cd_id', 'cd_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('cd_estado', true);
    }

    /** Ciudades de un país, subiendo por la provincia. */
    public function scopeDelPais($query, $paisId)
    {
        return $query->whereHas('provincia', fn ($q) => $q->where('pr_pa_id', $paisId));
    }
}
