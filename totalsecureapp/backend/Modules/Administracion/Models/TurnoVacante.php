<?php

namespace Modules\Administracion\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un puesto que quedó vacío y hay que llenar.
 *
 * Nace de tres formas distintas (el guardia no marcó, avisó que no viene, o el
 * cliente pidió refuerzo) pero es siempre el mismo objeto: un turno concreto,
 * en un local concreto, que alguien tiene que cubrir.
 */
class TurnoVacante extends Model
{
    protected $table = 'turno_vacante';
    protected $primaryKey = 'tv_id';

    /** El sistema la detectó; espera que una persona confirme que es real. */
    public const DETECTADA = 'detectada';
    /** Confirmada y ofrecida a los guardias. */
    public const ABIERTA = 'abierta';
    public const CUBIERTA = 'cubierta';
    public const CANCELADA = 'cancelada';
    /** Pasó la hora y nadie la cubrió. */
    public const VENCIDA = 'vencida';

    public const MOTIVOS = [
        'falta'      => 'No marcó entrada',
        'aviso'      => 'Avisó que no viene',
        'enfermedad' => 'Enfermedad',
        'permiso'    => 'Permiso',
        'baja'       => 'Baja del guardia',
        'refuerzo'   => 'Refuerzo solicitado',
    ];

    /** Motivos que el guardia puede reportar él mismo desde la app. */
    public const MOTIVOS_DEL_GUARDIA = ['aviso', 'enfermedad', 'permiso'];

    /**
     * El guardia dejó la empresa: sus turnos futuros no son ausencias suyas.
     *
     * Marcarlos como ausencia le cargaría una falta por cada día que le quedaba
     * programado, y ensuciaría el cumplimiento del local con turnos que nadie
     * esperaba que cubriera.
     */
    public const BAJA = 'baja';

    public const ESTADOS = [
        self::DETECTADA => 'Por confirmar',
        self::ABIERTA   => 'Abierta',
        self::CUBIERTA  => 'Cubierta',
        self::CANCELADA => 'Cancelada',
        self::VENCIDA   => 'Vencida',
    ];

    /** Ola 1: solo el local. */
    public const ALCANCE_LOCAL = 'local';
    /** Ola 2: cualquier local de la misma ciudad. */
    public const ALCANCE_CIUDAD = 'ciudad';

    protected $fillable = [
        'tv_turno_id', 'tv_ins_code', 'tv_puesto_id', 'tv_usu_id_ausente',
        'tv_fecha', 'tv_hora_inicio', 'tv_hora_fin',
        'tv_motivo', 'tv_estado', 'tv_alcance',
        'tv_abierta_por', 'tv_abierta_en',
        'tv_turno_cobertura_id', 'tv_confirmada_por', 'tv_confirmada_en',
        'tv_observaciones',
    ];

    protected $casts = [
        'tv_fecha'         => 'date',
        'tv_abierta_en'    => 'datetime',
        'tv_confirmada_en' => 'datetime',
    ];

    // === RELACIONES ===

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'tv_turno_id', 'tu_id');
    }

    public function turnoCobertura(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'tv_turno_cobertura_id', 'tu_id');
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(OrganizacionInstitucion::class, 'tv_ins_code', 'ins_code');
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'tv_puesto_id', 'pu_id');
    }

    public function ausente(): BelongsTo
    {
        return $this->belongsTo(\Modules\MobileApp\Models\users::class, 'tv_usu_id_ausente', 'id');
    }

    public function postulaciones(): HasMany
    {
        return $this->hasMany(TurnoPostulacion::class, 'tp_tv_id', 'tv_id');
    }

    /** Solo las que siguen en pie: es lo que el supervisor tiene que mirar. */
    public function postulacionesVigentes(): HasMany
    {
        return $this->hasMany(TurnoPostulacion::class, 'tp_tv_id', 'tv_id')
            ->where('tp_estado', TurnoPostulacion::POSTULADO);
    }

    // === ESTADO ===

    public function estaViva(): bool
    {
        return in_array($this->tv_estado, [self::DETECTADA, self::ABIERTA], true);
    }

    public function admitePostulaciones(): bool
    {
        return $this->tv_estado === self::ABIERTA && !$this->yaTermino();
    }

    /**
     * El turno ya terminó, así que cubrirlo no tiene sentido.
     *
     * Lo que descarta una vacante es que TERMINE, no que empiece: una falta se
     * detecta después de la hora de entrada, así que toda cobertura llega tarde
     * por definición. Cubrir un turno de 06:00 a las 06:40 es justamente para lo
     * que existe esto.
     */
    public function yaTermino(): bool
    {
        return $this->fin()->isPast();
    }

    public function inicio(): Carbon
    {
        return Carbon::parse($this->tv_fecha->toDateString() . ' ' . $this->tv_hora_inicio);
    }

    public function fin(): Carbon
    {
        $fin = Carbon::parse($this->tv_fecha->toDateString() . ' ' . $this->tv_hora_fin);

        // Turno que cruza la medianoche: termina al día siguiente.
        return $fin->lte($this->inicio()) ? $fin->addDay() : $fin;
    }

    public function getDescripcionAttribute(): string
    {
        return sprintf(
            '%s, %s de %s a %s',
            optional($this->puesto)->pu_nombre ?? 'Sin puesto',
            $this->tv_fecha->format('d/m/Y'),
            substr((string) $this->tv_hora_inicio, 0, 5),
            substr((string) $this->tv_hora_fin, 0, 5)
        );
    }

    // === SCOPES ===

    public function scopeVivas($query)
    {
        return $query->whereIn('tv_estado', [self::DETECTADA, self::ABIERTA]);
    }

    public function scopeAbiertas($query)
    {
        return $query->where('tv_estado', self::ABIERTA);
    }
}
