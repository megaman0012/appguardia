<?php

namespace Modules\Acceso\Models;

use Session;
use Illuminate\Database\Eloquent\Model;

class permission_section extends Model
{
    protected $table = "permission_section";

    public function permission(){
        return $this->belongsTo(Permission::class, 'ps_codigo');
    }

}
