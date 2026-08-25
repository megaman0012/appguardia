<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un aviso que el sistema intentó entregar.
 *
 * Se guarda el intento, no el éxito: un aviso que no salió es justamente el que
 * hay que poder ver. Cuando un puesto amanece vacío, esta tabla es la que
 * responde si el guardia se enteró o no.
 */
class AvisoEnvio extends Model
{
    protected $table = 'aviso_envio';
    protected $primaryKey = 'ae_id';

    public const ENVIADO = 'enviado';
    public const FALLIDO = 'fallido';
    /** No se intentó: sin número, sin consentimiento o canal apagado. */
    public const OMITIDO = 'omitido';

    public const RESULTADOS = [
        self::ENVIADO => 'Enviado',
        self::FALLIDO => 'Falló',
        self::OMITIDO => 'No se intentó',
    ];

    protected $fillable = [
        'ae_usu_id', 'ae_canal', 'ae_tipo', 'ae_titulo', 'ae_cuerpo',
        'ae_destino', 'ae_resultado', 'ae_detalle', 'ae_tv_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\Modules\MobileApp\Models\users::class, 'ae_usu_id', 'id');
    }

    public function vacante(): BelongsTo
    {
        return $this->belongsTo(TurnoVacante::class, 'ae_tv_id', 'tv_id');
    }
}
