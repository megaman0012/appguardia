<?php

namespace Modules\Administracion\Models;

use Modules\Acceso\Models\users;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Acceso extends Model {
    use BelongsToInstitution;

    protected string $institutionColumn = 'ac_ins_code';


    use HasFactory;

    protected $table = 'acceso';
    protected $primaryKey = 'ac_code';

    // Tipos de acceso
    const TIPO_PEATONAL  = 'peatonal';
    const TIPO_VEHICULAR = 'vehicular';
    const TIPO_PROVEEDOR = 'proveedor';
    const TIPO_EMPLEADO  = 'empleado';
    const TIPO_VISITANTE = 'visitante';

    const TIPOS_VALIDOS = [
        self::TIPO_PEATONAL,
        self::TIPO_VEHICULAR,
        self::TIPO_PROVEEDOR,
        self::TIPO_EMPLEADO,
        self::TIPO_VISITANTE,
    ];

    // Tipos que requieren detalle vehicular obligatorio
    const TIPOS_CON_VEHICULO = [self::TIPO_VEHICULAR];

    // Tipos que llevan detalle de visitante (motivo, area, etc.)
    const TIPOS_CON_VISITA = [self::TIPO_VISITANTE, self::TIPO_PROVEEDOR];

    // Estados del acceso
    const ESTADO_PROGRAMADA = 'programada';
    const ESTADO_EN_CURSO   = 'en_curso';
    const ESTADO_COMPLETADA = 'completada';
    const ESTADO_CANCELADA  = 'cancelada';

    protected $fillable = [
        'ac_code',
        'ac_usu_id',
        'ac_ug_code',
        'ac_ins_code',
        'ac_tipo',
        'ac_is_entrada',
        'ac_is_salida_fecha',
        'ac_ap_code',
        'ac_lat',
        'ac_lng',
        'ac_lat_sal',
        'ac_lng_sal',
        'ac_estado_acceso',
        'ac_token',
        'ac_temperatura',
        'ac_bicicleta',
        'ac_is_acomp',
        'ac_nomb_acomp',
        'ac_rut_acomp',
        'ac_observaciones',
        'ac_foto',
        'ac_estado',
        'ac_created_at',
        'ac_updated_at',
        'ac_created_user',
        'ac_updated_user',
        'ac_client_uuid',
        'ac_sincronizado_en',
    ];

    protected $casts = [
        'ac_bicicleta' => 'boolean',
        'ac_is_acomp'  => 'boolean',
        'ac_estado'    => 'boolean',
    ];

    const CREATED_AT = 'ac_created_at';
    const UPDATED_AT = 'ac_updated_at';

    public function getAcCreatedAtAttribute($value): string {
        if (!$value) return '';
        return Carbon::parse($value)->timezone('America/Guayaquil')->format('Y-m-d H:i:s');
    }

    public function getImagenUrlAttribute(): ?string {
        $fecha = Carbon::parse($this->ac_created_at)->format('Y/m/d');
        $imagePath = public_path('images/accesos/' . $fecha . '/' . $this->ac_foto);
        if (file_exists($imagePath) && !empty($this->ac_foto)) {
            return asset('images/accesos/' . $fecha . '/' . $this->ac_foto);
        }
        return null;
    }

    public function getTiempoPermanenciaAttribute(): ?string {
        if (!$this->ac_is_salida_fecha) return null;

        $entrada = Carbon::parse($this->getRawOriginal('ac_created_at'));
        $salida  = Carbon::parse($this->ac_is_salida_fecha);
        $diff    = $entrada->diff($salida);

        if ($diff->days > 0) {
            return "{$diff->days}d {$diff->h}h {$diff->i}m";
        }
        if ($diff->h > 0) {
            return "{$diff->h}h {$diff->i}m";
        }
        return "{$diff->i}m";
    }

    // ── Relaciones ──

    public function institucion(): BelongsTo {
        return $this->belongsTo(OrganizacionInstitucion::class, 'ac_ins_code', 'ins_code');
    }

    public function persona(): BelongsTo {
        return $this->belongsTo(AccesoPersona::class, 'ac_ap_code', 'ap_code');
    }

    /**
     * Alias de compatibilidad con codigo existente (frontend/controller legado)
     */
    public function accesoPersona(): BelongsTo {
        return $this->persona();
    }

    public function vehiculo(): HasOne {
        return $this->hasOne(AccesoVehiculo::class, 'av_ac_code', 'ac_code');
    }

    public function visitante(): HasOne {
        return $this->hasOne(AccesoVisitante::class, 'avi_ac_code', 'ac_code');
    }

    public function historial(): HasMany {
        return $this->hasMany(AccesoHistorial::class, 'ah_ac_code', 'ac_code');
    }

    public function createdUser(): BelongsTo {
        return $this->belongsTo(users::class, 'ac_created_user', 'id');
    }

    public function updatedUser(): BelongsTo {
        return $this->belongsTo(users::class, 'ac_updated_user', 'id');
    }

    // ── Scopes ──

    public function scopePorTipo(Builder $query, string $tipo): Builder {
        return $query->where('ac_tipo', $tipo);
    }

    public function scopePorInstitucion(Builder $query, int $insCode): Builder {
        return $query->where('ac_ins_code', $insCode);
    }

    public function scopeEstado(Builder $query, string $estado): Builder {
        return $query->where('ac_estado_acceso', $estado);
    }

    public function scopeEntradas(Builder $query): Builder {
        return $query->where('ac_is_entrada', 1);
    }

    public function scopeSalidas(Builder $query): Builder {
        return $query->where('ac_is_entrada', 0);
    }

    // ── Helpers ──

    public function esVehicular(): bool {
        return in_array($this->ac_tipo, self::TIPOS_CON_VEHICULO, true);
    }

    public function esPeatonal(): bool {
        return $this->ac_tipo === self::TIPO_PEATONAL;
    }

    public function enCurso(): bool {
        return $this->ac_estado_acceso === self::ESTADO_EN_CURSO;
    }
}
