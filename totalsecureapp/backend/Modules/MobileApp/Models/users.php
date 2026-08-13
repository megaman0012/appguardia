<?php

namespace Modules\MobileApp\Models;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Modules\Acceso\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class users extends Authenticatable {

    use HasApiTokens, HasFactory, HasRoles;

    protected $fillable = [
        'id', 'usu_cedula', 'usu_tipdoc', 'usu_nmbcom', 'usu_ape1', 'usu_ape2', 'usu_nmb1', 'usu_nmb2', 'usu_email', 'usu_state'
    ];

    protected $hidden = [
        'usu_password', 'remember_token', 'usu_email_verified_at'
    ];

    public function roles(): BelongsToMany {
        return $this->belongsToMany(Role::class,'user_has_roles','user_id','role_id');
    }

    public function can($permission, $arguments = []){
        return $this->roles()->whereHas('permissions', function ($query) use ($permission) {
            $query->where('permissions.name', $permission);
        })->exists();
    }

    public static function boot() {
        parent::boot();
        static::saving(function ($model) {
            if ($model->isDirty('usu_password') && $model->usu_password) {
                $model->usu_password = Hash::make($model->usu_password);
            }
        });
    }

}
