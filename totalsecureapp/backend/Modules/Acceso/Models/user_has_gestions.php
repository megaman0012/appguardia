<?php

namespace Modules\Acceso\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class user_has_gestions extends Model {

    use HasFactory;
    protected $table = 'user_has_gestions';
    protected $primaryKey = 'ug_code';

    protected $fillable = [
        'ug_code',
        'ug_user_id',
        'ug_ingreso',
        'ug_egreso',
        'ug_finish',
        'ug_state',
        'ug_created_at',
        'ug_updated_at',
        'ug_created_user',
        'ug_updated_user',
    ];

    const CREATED_AT = 'ug_created_at';
    const UPDATED_AT = 'ug_updated_at';

    protected $casts = [
        'ug_finish' => 'boolean',
        'ug_state' => 'boolean',
    ];

    public function usuario() {
        return $this->belongsTo(users::class, 'ug_user_id', 'id');
    }

    public function createdBy() {
        return $this->belongsTo(users::class, 'ug_created_user', 'id');
    }

    public function updatedBy(){
        return $this->belongsTo(users::class, 'ug_updated_user', 'id');
    }

    protected static function booted(){
        static::saving(function ($model) {
            if ($model->ug_egreso) {
                $model->ug_finish = true;
            }
        });
    }
}
