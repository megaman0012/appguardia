<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organizacion extends Model
{
    use HasFactory;
    protected $table = 'organizacion';
    protected $primaryKey = 'org_code';
    public $timestamps = true;

    protected $fillable = [
        'org_code',
        'org_descripcion',
        'org_razon_social',
        'org_direccion',
        'org_ciudad',
        'org_pais',
        'org_telefono',
        'org_email',
        'org_tipo',
        'org_website',
        'org_numero_registro',
        'org_estado',
        'org_created_user',
        'org_updated_user',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    protected $attributes = [
        'org_estado' => true,
    ];


}
