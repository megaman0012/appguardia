<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Model;

class log_trafico extends Model{
    protected $table = "log_trafico";
    protected $primaryKey = 'lt_code';
}
