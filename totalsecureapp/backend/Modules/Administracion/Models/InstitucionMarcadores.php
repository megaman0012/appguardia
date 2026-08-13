<?php

namespace Modules\Administracion\Models;
use Modules\Acceso\Models\users;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstitucionMarcadores extends Model {
    use HasFactory;
    protected $table = 'institucion_marcadores';
    protected $primaryKey = 'im_code';
    public $timestamps = true;

    protected $fillable = [
        'im_code',
        'im_ins_code',
        'im_numero',
        'im_tipo',
        'im_descripcion',
        'im_lat',
        'im_lng',
        'im_estado',
        'im_created_user',
        'im_updated_user',
        'im_created_at',
        'im_updated_at'
    ];

    protected $casts = [
        'im_estado' => 'boolean',
        'im_created_at' => 'datetime',
        'im_updated_at' => 'datetime',
    ];

    const CREATED_AT = 'im_created_at';
    const UPDATED_AT = 'im_updated_at';

    public function institucion(){
        return $this->belongsTo(OrganizacionInstitucion::class, 'im_ins_code', 'ins_code');
    }

    public function createdUser(){
        return $this->belongsTo(User::class, 'im_created_user');
    }

    public function updatedUser(){
        return $this->belongsTo(User::class, 'im_updated_user');
    }

}
