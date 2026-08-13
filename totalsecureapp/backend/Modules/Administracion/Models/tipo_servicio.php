<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;

class tipo_servicio extends Model{

    protected $table = "tipo_servicio";
    protected $primaryKey = 'ts_code';

    protected $fillable = [
        'tg_code',
        'tg_descripcion',
        'tg_estado',
    ];

}
