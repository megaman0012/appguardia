<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;

class persona extends Model{

    protected $table = "tipo_pais";
    protected $primaryKey = 'tp_code';







    protected $fillable = [
        'tp_code',
        'tp_sigla',
        'tp_descripcion',
        'tp_estado',
    ];

}
