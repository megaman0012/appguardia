<?php

namespace Modules\Acceso\Models;

use App\Support\PerfilPanel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Gate;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Session;


class users extends Authenticatable implements FilamentUser, HasName{

    use Notifiable;
    use HasRoles;

    protected $fillable = [
        'id', 'usu_cedula', 'usu_password', 'usu_tipdoc', 'usu_nmbcom', 'usu_ape1', 'usu_ape2', 'usu_nmb1', 'usu_nmb2', 'usu_email', 'usu_state',
        'usu_whatsapp', 'usu_acepta_whatsapp', 'usu_acepta_extras'
    ];

    protected $hidden = [
        'remember_token', 'usu_email_verified_at'
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class,'user_has_roles','user_id','role_id');
    }

    public function getAuthPassword(){
        return $this->usu_password;
    }

    public function can($permission, $arguments = []){
        return $this->roles()->whereHas('permissions', function ($query) use ($permission) {
            $query->where('permissions.name', $permission);
        })->exists();
    }

    public function getFilamentName(): string {
        return $this->usu_nmbcom;
    }

    public function canAccessFilament(): bool{
        return PerfilPanel::puedeEntrarAlPanel();
    }

    /*public static function boot() {
        parent::boot();
        static::saving(function ($model) { $model->usu_password = Hash::make('123456'); });
    }*/

}
