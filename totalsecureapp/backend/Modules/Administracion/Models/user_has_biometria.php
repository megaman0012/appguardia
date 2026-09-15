<?php

namespace Modules\Administracion\Models;

use App\Support\FotoDeEvidencia;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Acceso\Models\user_has_gestions;
use Modules\Acceso\Models\users;
use App\Traits\BelongsToInstitution;


class user_has_biometria extends Model {

    use HasFactory;
    use BelongsToInstitution;

    protected string $institutionColumn = 'bio_ins_code';

    protected $table = 'user_has_biometria';
    protected $primaryKey = 'bio_code';
    protected $fillable = [
        'bio_code',
        'bio_user_id',
        'bio_ug_code',
        'bio_image_name',
        'bio_lat',
        'bio_lng',
        'bio_ins_code',
        'bio_state',
        'bio_tu_code',
        'bio_created_user',
        'bio_updated_user',
        'bio_client_uuid',
        'bio_sincronizado_en',
    ];

    protected $dates = [
        'bio_created_at',
        'bio_updated_at',
    ];

    const CREATED_AT = 'bio_created_at';
    const UPDATED_AT = 'bio_updated_at';

    public function usuario(): BelongsTo {
        return $this->belongsTo(users::class, 'bio_user_id', 'id');
    }

    public function institucion(): BelongsTo {
        return $this->belongsTo(OrganizacionInstitucion::class, 'bio_ins_code', 'ins_code');
    }

    public function userHasGestions(): BelongsTo {
        return $this->belongsTo(user_has_gestions::class, 'bio_ug_code');
    }

    public function turno(): BelongsTo {
        return $this->belongsTo(Turno::class, 'bio_tu_code', 'tu_id');
    }

    public function getImagenUrlAttribute(): ?string {
        // La carpeta se resolvia aca reconstruyendola con la fecha del
        // registro, y eso perdia las fotos cuya sincronizacion cayo en
        // otro dia. Ver App\Support\FotoDeEvidencia.
        return FotoDeEvidencia::url($this->bio_image_name, 'biometria', $this->bio_created_at);
    }

}
