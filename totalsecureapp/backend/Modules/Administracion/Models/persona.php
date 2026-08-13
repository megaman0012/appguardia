<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;

class persona extends Model{

    protected $table = "persona";
    protected $primaryKey = 'pt_code';

    protected $fillable = [
        'pt_code',
        'pt_documento',
        'pt_tip_doc',
        'pt_nmb_comp',
        'pt_ape1',
        'pt_ape2',
        'pt_nmb1',
        'pt_nmb2',
        'pt_fch_nac',
        'pt_pais',
        'pt_provincia',
        'pt_ciudad',
        'pt_parroquia',
        'pt_direccion',
        'pt_estado',
    ];

}
