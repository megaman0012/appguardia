<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccesoVehiculo extends Model
{
    use HasFactory;

    protected $table = 'acceso_vehiculo';
    protected $primaryKey = 'av_code';

    protected $fillable = [
        'av_ac_code',
        'av_patente',
        'av_empresa',
        'av_is_sello',
        'av_is_neumatico',
        'av_is_carro',
        'av_pta_llave',
        'av_kms',
        'av_color',
        'av_marca',
        'av_modelo',
        'av_anio',
    ];

    protected $casts = [
        'av_is_sello'     => 'boolean',
        'av_is_neumatico' => 'boolean',
        'av_is_carro'     => 'boolean',
        'av_pta_llave'    => 'boolean',
        'av_anio'         => 'integer',
    ];

    public function acceso(): BelongsTo
    {
        return $this->belongsTo(Acceso::class, 'av_ac_code', 'ac_code');
    }
}
