<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;

class tipo_especialidad extends Model{

    protected $table = "tipo_especialidad";
    protected $primaryKey = 'te_code';

    protected $fillable = [
        'te_code',
        'te_descripcion',
        'te_estado',

    ];

}
