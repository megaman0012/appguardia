<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoCatalogo extends Model
{
    protected $table = 'inv_producto_catalogo';
    protected $primaryKey = 'ipc_id';

    const CREATED_AT = 'ipc_created_at';
    const UPDATED_AT = 'ipc_updated_at';

    protected $fillable = [
        'ipc_id',
        'ipc_ins_code',
        'ipc_nombre',
        'ipc_descripcion',
        'ipc_especificacion',
        'ipc_activo',
        'ipc_created_user',
        'ipc_updated_user',
    ];

    protected $casts = [
        'ipc_activo' => 'boolean',
    ];

    public function getStockActualAttribute(): float
    {
        $recepciones = $this->detalles()
            ->whereHas('movimiento', function ($q) {
                $q->where('mc_tipo', 'recepcion')
                  ->where('mc_estado', 'completado');
            })
            ->sum('md_cantidad_real');

        $devoluciones = $this->detalles()
            ->whereHas('movimiento', function ($q) {
                $q->where('mc_tipo', 'devolucion')
                  ->where('mc_estado', 'completado');
            })
            ->sum('md_cantidad_real');

        $bajas = $this->detalles()
            ->whereHas('movimiento', function ($q) {
                $q->where('mc_tipo', 'baja')
                  ->where('mc_estado', 'completado');
            })
            ->sum('md_cantidad_real');

        return max(0, $recepciones - $devoluciones - $bajas);
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(
            OrganizacionInstitucion::class,
            'ipc_ins_code',
            'ins_code'
        );
    }

    public function listas(): BelongsToMany
    {
        return $this->belongsToMany(
            Lista::class,
            'inv_lista_item',
            'lia_producto_id',
            'lia_lista_id'
        )->withPivot('lia_cantidad_default', 'lia_activo');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(
            MovimientoDetalle::class,
            'md_producto_id',
            'ipc_id'
        );
    }

    public function scopeActivos($query)
    {
        return $query->where('ipc_activo', true);
    }

    /**
     * ⚠️ **No usar: el catalogo es global desde el 2026-09-19.**
     *
     * `ipc_ins_code` es nulo en todas las filas, asi que este alcance
     * **no devuelve ninguna** -- sin error y sin aviso. Se deja para que quien
     * lo busque encuentre esta nota en vez de reintroducirlo; ya causo que el
     * selector de productos de las listas quedara vacio.
     *
     * Que productos toca cada local lo dice su lista (`inv_lista_item`), no el
     * catalogo.
     *
     * @deprecated
     */
    public function scopePorInstitucion($query, int $insCode)
    {
        throw new \LogicException(
            'El catalogo de productos es global: filtrarlo por local no devuelve nada. '
            . 'Use la lista del local (inv_lista_item) para saber que productos le tocan.'
        );
    }
}
