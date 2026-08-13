<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;

class tipo_documento extends Model{

    protected $table = "tipo_documento";
    protected $primaryKey = 'td_code';

    protected $fillable = [
        'td_code',
        'td_sigla',
        'td_descripcion',
        'td_estado',
    ];

}
