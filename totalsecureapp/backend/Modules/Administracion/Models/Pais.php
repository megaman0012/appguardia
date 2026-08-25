<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * País donde la empresa tiene operaciones (Fase jerarquía territorial).
 *
 * Es el nivel por el que se acota al rol Lider Operativo.
 */
class Pais extends Model
{
    protected $table = 'pais';
    protected $primaryKey = 'pa_id';

    protected $fillable = ['pa_iso2', 'pa_iso3', 'pa_nombre', 'pa_estado'];

    protected $casts = ['pa_estado' => 'boolean'];

    public function provincias(): HasMany
    {
        return $this->hasMany(Provincia::class, 'pr_pa_id', 'pa_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('pa_estado', true);
    }
}
