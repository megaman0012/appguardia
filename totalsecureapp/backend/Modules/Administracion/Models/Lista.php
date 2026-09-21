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
        // De que kit salio esta lista, y si se aparto de el. Ver
        // App\Services\Inventario\AplicadorDeKit.
        'li_kit_id',
        'li_modificada',
        'li_kit_huella',
        'li_nombre',
        'li_descripcion',
        'li_activo',
        'li_created_user',
        'li_updated_user',
    ];

    protected $casts = [
        'li_activo'     => 'boolean',
        'li_modificada' => 'boolean',
    ];

    public function kit(): BelongsTo
    {
        return $this->belongsTo(Kit::class, 'li_kit_id', 'ki_id');
    }

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
