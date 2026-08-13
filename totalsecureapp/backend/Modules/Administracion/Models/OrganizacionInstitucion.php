<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizacionInstitucion extends Model
{
    use HasFactory;
    protected $table = 'organizacion_institucion';
    protected $primaryKey = 'ins_code';
    public $incrementing = true;

    protected $fillable = [
        'ins_so_code',
        'ins_descripcion',
        'ins_razon_social',
        'ins_direccion',
        'ins_ciudad',
        'ins_telefono',
        'ins_email',
        'ins_tipo',
        'ins_estado',
        'ins_created_user',
        'ins_updated_user'
    ];

    protected $dates = ['created_at', 'updated_at'];
    protected $attributes = [ 'ins_estado' => true ];

    public function organizacionSede()
    {
        return $this->belongsTo(OrganizacionSede::class, 'ins_so_code', 'so_code');
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class, OrganizacionSede::class);
    }

    public function organizacion()
    {
        return $this->belongsTo(Organizacion::class, OrganizacionSede::class);
    }

    public function marcadores()
    {
        return $this->hasMany(InstitucionMarcadores::class, 'im_ins_code', 'ins_code');
    }

}
