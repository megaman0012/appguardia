<?php

namespace Modules\Administracion\Models;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Acceso\Models\users;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccesoPersona extends Model
{
    use HasFactory;

    protected $table = 'acceso_persona';
    protected $primaryKey = 'ap_code';
    //public $timestamps = true;

    protected $fillable = [
        'ap_code',
        'ap_documento',
        'ap_tip_doc',
        'ap_nombres',
        'ap_apellidos',
        'ap_estado',
        'ap_created_at',
        'ap_updated_at',
        'ap_created_user',
        'ap_updated_user',
    ];

    const CREATED_AT = 'ap_created_at';
    const UPDATED_AT = 'ap_updated_at';

    public function accesos(): HasMany {
        return $this->hasMany(Acceso::class, 'ac_ap_code', 'ac_code');
    }

    public function createdUser(): BelongsTo {
        return $this->belongsTo(users::class, 'ap_created_user', 'id');
    }

    public function updatedUser(): BelongsTo {
        return $this->belongsTo(users::class, 'ap_updated_user', 'id');
    }
}
