<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    use HasFactory;
    protected $table = 'sede';
    protected $primaryKey = 'ps_code';
    public $timestamps = true;

    protected $fillable = [
        'ps_code',
        'ps_sigla',
        'ps_descripcion',
        'ps_estado',
        'created_at',
        'ps_created_user',
        'ps_updated_user'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    protected $attributes = [
        'ps_estado' => true,
    ];

    public function organizacionSede()
    {
        return $this->belongsTo(OrganizacionSede::class, 'ps_code', 'so_ps_code');
    }

}
