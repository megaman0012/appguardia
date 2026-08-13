<?php

namespace Modules\Acceso\Models;

use Session;
use Illuminate\Database\Eloquent\Model;

class user_has_roles extends Model{

    protected $table = "user_has_roles";
    protected $primaryKey = 'ru_code';
    public $timestamps = false;
    protected $fillable = [
        'ru_code', 'role_id', 'user_id'
    ];

    public function users(){
        return $this->belongsTo(users::class, 'user_id', 'id');
    }
    public function roles(){
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

}
