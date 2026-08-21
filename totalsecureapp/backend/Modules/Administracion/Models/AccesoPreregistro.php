<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AccesoPreregistro extends Model
{
    use HasFactory;

    protected $table = 'acceso_preregistro';
    protected $primaryKey = 'apr_code';

    const ESTADO_PENDIENTE = 'pendiente';
    const ESTADO_LLEGO     = 'llego';
    const ESTADO_CANCELADO = 'cancelado';

    protected $fillable = [
        'apr_ins_code',
        'apr_ap_code',
        'apr_fecha_estimada',
        'apr_hora_estimada',
        'apr_motivo',
        'apr_area_visita',
        'apr_estado',
        'apr_token',
        'apr_created_user',
    ];

    protected $casts = [
        'apr_fecha_estimada' => 'date:Y-m-d',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(OrganizacionInstitucion::class, 'apr_ins_code', 'ins_code');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(AccesoPersona::class, 'apr_ap_code', 'ap_code');
    }

    public function estaPendiente(): bool
    {
        return $this->apr_estado === self::ESTADO_PENDIENTE;
    }

    public function generarToken(): string
    {
        $this->apr_token = Str::random(40);
        $this->save();

        return $this->apr_token;
    }
}
