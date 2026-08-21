<?php

namespace Modules\Administracion\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccesoHistorial extends Model
{
    use HasFactory;

    protected $table = 'acceso_historial';
    protected $primaryKey = 'ah_code';

    const MARCA_ENTRADA = 'entrada';
    const MARCA_SALIDA  = 'salida';

    protected $fillable = [
        'ah_ac_code',
        'ah_tipo_marca',
        'ah_fecha_hora',
        'ah_lat',
        'ah_lng',
        'ah_observaciones',
    ];

    public function acceso(): BelongsTo
    {
        return $this->belongsTo(Acceso::class, 'ah_ac_code', 'ac_code');
    }

    public static function registrar(
        int $acCode,
        string $tipoMarca,
        ?string $lat = null,
        ?string $lng = null,
        ?string $observaciones = null
    ): self {
        return self::create([
            'ah_ac_code'       => $acCode,
            'ah_tipo_marca'    => $tipoMarca,
            'ah_fecha_hora'    => Carbon::now(),
            'ah_lat'           => $lat,
            'ah_lng'           => $lng,
            'ah_observaciones' => $observaciones,
        ]);
    }
}
