<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Lista extends Model
{
    protected $table = 'inv_lista';
    protected $primaryKey = 'li_id';

    const CREATED_AT = 'li_created_at';
    const UPDATED_AT = 'li_updated_at';

    protected $fillable = [
        'li_id',
        'li_ins_code',
        'li_nombre',
        'li_descripcion',
        'li_activo',
        'li_created_user',
        'li_updated_user',
    ];

    protected $casts = [
        'li_activo' => 'boolean',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(
            OrganizacionInstitucion::class,
            'li_ins_code',
            'ins_code'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            ListaItem::class,
            'lia_lista_id',
            'li_id'
        )->where('lia_activo', true);
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductoCatalogo::class,
            'inv_lista_item',
            'lia_lista_id',
            'lia_producto_id'
        )->withPivot('lia_cantidad_default', 'lia_activo');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(
            MovimientoCabecera::class,
            'mc_lista_id',
            'li_id'
        );
    }

    public function scopeActivas($query)
    {
        return $query->where('li_activo', true);
    }

    public function scopePorInstitucion($query, int $insCode)
    {
        return $query->where('li_ins_code', $insCode);
    }
}
