<?php


namespace Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Acceso\Models\users;

class UserHasInstitucion extends Model {

    use HasFactory;
    protected $table = 'user_has_institucion';
    protected $primaryKey = 'ui_code';

    protected $fillable = [
        'ui_usu_id',
        'ui_ins_code',
        'ui_state',
        'ui_created_at',
        'ui_updated_at',
        'ui_created_user',
        'ui_updated_user',
    ];

    protected $casts = [
        'ui_state' => 'integer',
        'ui_created_at' => 'datetime',
        'ui_updated_at' => 'datetime',
    ];

    const CREATED_AT = 'ui_updated_at';
    const UPDATED_AT = 'ui_updated_at';

    public function usuario(): BelongsTo {
        return $this->belongsTo(users::class, 'ui_usu_id', 'id');
    }

    public function institucion(): BelongsTo {
        return $this->belongsTo(OrganizacionInstitucion::class, 'ui_ins_code', 'ins_code');
    }

    public function createdBy(): BelongsTo {
        return $this->belongsTo(users::class, 'ui_created_user', 'id');
    }

    public function updatedBy(): BelongsTo {
        return $this->belongsTo(users::class, 'ui_updated_user', 'id');
    }
}
