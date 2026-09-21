<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El kit de puesto: qué equipo lleva un puesto, y cuánto de cada cosa.
 *
 * Es la plantilla de la que salen las listas de inventario de cada local. Antes
 * no existía y había **132 listas de las que 130 eran idénticas**.
 *
 * ⚠️ El kit **no reemplaza** a `inv_lista`: la gobierna. La lista del local
 * sigue guardando sus items, porque es lo que lee la app del guardia y contra lo
 * que se registran los movimientos. Ver `App\Services\Inventario\AplicadorDeKit`.
 */
class Kit extends Model
{
    protected $table = 'inv_kit';

    protected $primaryKey = 'ki_id';

    const CREATED_AT = 'ki_created_at';
    const UPDATED_AT = 'ki_updated_at';

    protected $fillable = [
        'ki_nombre',
        'ki_descripcion',
        'ki_activo',
        'ki_created_user',
        'ki_updated_user',
    ];

    protected $casts = [
        'ki_activo' => 'boolean',
    ];

    /**
     * Las filas del pivote: producto + cantidad.
     *
     * ⚠️ `items()` y `productos()` NO son lo mismo, igual que en `Lista`. Esto
     * es el hasMany al pivote --lo que se edita y lo que lleva la cantidad--;
     * `productos()` devuelve modelos de producto, sin cantidad. Apuntar un
     * RelationManager a `productos` deja la columna de cantidad vacía; ya pasó
     * una vez en este proyecto.
     */
    public function items(): HasMany
    {
        return $this->hasMany(KitItem::class, 'kii_ki_id', 'ki_id');
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductoCatalogo::class,
            'inv_kit_item',
            'kii_ki_id',
            'kii_producto_id',
        )->withPivot('kii_cantidad');
    }

    /** Las listas de local que salieron de este kit. */
    public function listas(): HasMany
    {
        return $this->hasMany(Lista::class, 'li_kit_id', 'ki_id');
    }
}
