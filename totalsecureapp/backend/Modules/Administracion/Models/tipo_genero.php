<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;

class tipo_genero extends Model{

    protected $table = "tipo_genero";
    protected $primaryKey = 'tg_code';

    protected $fillable = [
        'tg_code',
        'tg_sigla',
        'tg_descripcion',
        'tg_estado',
    ];

}
