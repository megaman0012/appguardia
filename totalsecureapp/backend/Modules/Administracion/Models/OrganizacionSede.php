<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizacionSede extends Model
{
    use HasFactory;
    protected $table = 'organizacion_sede';
    protected $primaryKey = 'so_code';
    public $timestamps = true;

    protected $fillable = [
        'so_ps_code',
        'so_org_code',
        'so_estado',
        'so_created_user',
        'so_updated_user'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    protected $attributes = [
        'so_estado' => true,
    ];

    public function organizacion(){
        return $this->belongsTo(Organizacion::class, 'so_org_code');
    }

    public function sede(){
        return $this->belongsTo(Sede::class, 'so_ps_code');
    }

}
