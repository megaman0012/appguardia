<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToInstitution;

class OrganizacionInstitucion extends Model
{
    use HasFactory;
    use BelongsToInstitution;

    protected $table = 'organizacion_institucion';
    protected $primaryKey = 'ins_code';
    public $incrementing = true;

    protected $fillable = [
        'ins_descripcion',
        'ins_razon_social',
        'ins_direccion',
        'ins_ciudad',
        'ins_telefono',
        'ins_email',
        'ins_tipo',
        'ins_estado',
        'ins_radio_tolerancia_metros',
        'ins_cd_id',
        'ins_cliente_id',
        'ins_created_user',
        'ins_updated_user'
    ];

    protected $dates = ['created_at', 'updated_at'];
    protected $attributes = [ 'ins_estado' => true ];
    protected $casts = [ 'ins_radio_tolerancia_metros' => 'integer' ];

    public function marcadores()
    {
        return $this->hasMany(InstitucionMarcadores::class, 'im_ins_code', 'ins_code');
    }


    /** Puestos de trabajo definidos en este local. */
    public function puestos()
    {
        return $this->hasMany(Puesto::class, 'pu_ins_code', 'ins_code');
    }

    // ── Jerarquía territorial ──

    /** Ciudad donde está el local. De aquí se sube a provincia y país. */
    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'ins_cd_id', 'cd_id');
    }

    /** Cliente dueño del local (p. ej. DHL). Uno solo, aunque opere en varios países. */
    public function cliente()
    {
        return $this->belongsTo(Organizacion::class, 'ins_cliente_id', 'org_code');
    }

    /**
     * Locales de un país, subiendo ciudad → provincia → país.
     *
     * Es el filtro que acota al rol Lider Operativo. Un local sin ciudad
     * asignada NO aparece: es preferible que falte a que se cuele en el
     * alcance de un país que no le corresponde.
     */
    public function scopeDelPais($query, $paisId)
    {
        return $query->whereHas('ciudad.provincia', fn ($q) => $q->where('pr_pa_id', $paisId));
    }

    public function scopeDelCliente($query, $clienteId)
    {
        return $query->where('ins_cliente_id', $clienteId);
    }
}
