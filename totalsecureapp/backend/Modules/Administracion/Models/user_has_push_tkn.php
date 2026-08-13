<?php

namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class user_has_push_tkn extends Model
{
    use HasFactory;
    protected $table = 'user_has_push_tkn';
    protected $primaryKey = 'pt_code';

    const CREATED_AT = 'pt_created_at';
    const UPDATED_AT = 'pt_updated_at';

    protected $fillable = [
        'pt_token',
        'pt_usu_id',
        'pt_ins_id',
        'pt_platform',
        'pt_device_name',
        'pt_active',
        'pt_env'
    ];

    protected $casts = [
        'pt_active' => 'boolean',
        'pt_created_at' => 'datetime',
        'pt_updated_at' => 'datetime',
    ];

    /*public function getPtTokenAttribute($value) {
        if (str_starts_with($value, 'ExponentPushToken[')) {
            return $value;
        }
        return "ExponentPushToken[{$value}]";
    }*/

    public function setPtTokenAttribute($value) {
        $this->attributes['pt_token'] = str_replace(['ExponentPushToken[', ']'], '', $value);
    }

}
