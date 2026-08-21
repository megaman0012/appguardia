<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Acceso\Models\users;

class AlertaHistorial extends Model
{
    use HasFactory;

    protected $table = 'alertas_historial';
    protected $primaryKey = 'ah_id';
    public $timestamps = true;

    protected $fillable = [
        'ah_al_code',
        'ah_accion',
        'ah_usuario_id',
        'ah_descripcion',
    ];

    public function alerta()
    {
        return $this->belongsTo(Alertas::class, 'ah_al_code', 'al_code');
    }

    public function usuario()
    {
        return $this->belongsTo(users::class, 'ah_usuario_id', 'id');
    }

    public static function registrar(
        int $alertaId,
        string $accion,
        int $usuarioId,
        ?string $descripcion = null
    ): self {
        return self::create([
            'ah_al_code' => $alertaId,
            'ah_accion' => $accion,
            'ah_usuario_id' => $usuarioId,
            'ah_descripcion' => $descripcion,
        ]);
    }
}
