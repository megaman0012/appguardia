<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Acceso\Models\users;

class AlertaDetalle extends Model
{
    use HasFactory;

    protected $table = 'alertas_detalle';
    protected $primaryKey = 'ad_id';
    public $timestamps = true;

    protected $fillable = [
        'ad_al_code',
        'ad_usuario_asignado',
        'ad_prioridad',
        'ad_estado',
        'ad_fecha_asignacion',
        'ad_fecha_atencion',
        'ad_tiempo_respuesta_seg',
        'ad_observacion_atencion',
        'ad_created_user',
    ];

    protected $casts = [
        'ad_fecha_asignacion' => 'datetime',
        'ad_fecha_atencion' => 'datetime',
        'ad_tiempo_respuesta_seg' => 'integer',
    ];

    public function alerta()
    {
        return $this->belongsTo(Alertas::class, 'ad_al_code', 'al_code');
    }

    public function usuarioAsignado()
    {
        return $this->belongsTo(users::class, 'ad_usuario_asignado', 'id');
    }

    public function scopeActivas($query)
    {
        return $query->whereIn('ad_estado', ['asignada', 'en_revision']);
    }

    public function scopeEscaladas($query)
    {
        return $query->where('ad_estado', 'escalada');
    }

    public function marcarEnRevision(): void
    {
        $this->update(['ad_estado' => 'en_revision']);
    }

    public function marcarResuelta(string $observacion): void
    {
        $this->update([
            'ad_estado' => 'resuelta',
            'ad_fecha_atencion' => now(),
            'ad_tiempo_respuesta_seg' => $this->alerta->al_fecha->diffInSeconds(now()),
            'ad_observacion_atencion' => $observacion,
        ]);

        $this->alerta->update(['al_estado_alerta' => 'finalizada']);
    }
}
