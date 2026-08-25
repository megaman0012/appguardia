<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provincia extends Model
{
    protected $table = 'provincia';
    protected $primaryKey = 'pr_id';

    protected $fillable = ['pr_pa_id', 'pr_codigo', 'pr_nombre', 'pr_estado'];

    protected $casts = ['pr_estado' => 'boolean'];

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'pr_pa_id', 'pa_id');
    }

    public function ciudades(): HasMany
    {
        return $this->hasMany(Ciudad::class, 'cd_pr_id', 'pr_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('pr_estado', true);
    }

    public function scopeDelPais($query, $paisId)
    {
        return $query->where('pr_pa_id', $paisId);
    }
}
