<?php

namespace Modules\Acceso\Models;

use Session;
use Illuminate\Database\Eloquent\Model;

class roles extends Model
{
    protected $table = "roles";
/*********************************************************/
    public function users()
    {
        return $this->belongsToMany(users::class, 'user_has_roles', 'role_id', 'user_id');
    }
}
