<?php

namespace Modules\Administracion\Models;
use Modules\Acceso\Models\users;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Acceso extends Model {

    use HasFactory;

    protected $table = 'acceso';
    protected $primaryKey = 'ac_code';
    //public $timestamps = true;

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
        'ac_empresa',
        'ac_temperatura',
        'ac_nombre_contrato',
        'ac_bicicleta',
        'ac_is_acomp',
        'ac_nomb_acomp',
        'ac_rut_acomp',
        'ac_patente',
        'ac_is_sello',
        'ac_is_neumatico',
        'ac_is_carro',
        'ac_pta_llave',
        'ac_kms',
        'ac_observaciones',
        'ac_foto',
        'ac_estado',
        'ac_created_at',
        'ac_updated_at',
        'ac_created_user',
        'ac_updated_user',
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

    public function institucion(): BelongsTo {
        return $this->belongsTo(OrganizacionInstitucion::class, 'ac_ins_code', 'ins_code');
    }

    public function accesoPersona(): BelongsTo {
        return $this->belongsTo(AccesoPersona::class, 'ac_ap_code', 'ap_code');
    }

    public function createdUser(): BelongsTo {
        return $this->belongsTo(users::class, 'ac_created_user', 'id');
    }

    public function updatedUser(): BelongsTo {
        return $this->belongsTo(users::class, 'ac_updated_user', 'id');
    }
}
