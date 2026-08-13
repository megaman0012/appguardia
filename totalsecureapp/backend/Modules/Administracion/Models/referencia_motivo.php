<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;

class referencia_motivo extends Model{

    protected $table = "referencia_motivo";
    protected $primaryKey = 'rm_code';

    protected $fillable = [
        'rm_code',
        'rm_motivo',
        'rm_estado',
    ];

}
