<?php

namespace Modules\Administracion\Models;
use Modules\Acceso\Models\users;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccesoTransporte extends Model
{
    use HasFactory;

    protected $table = 'acceso_transporte'; // Nombre de la tabla
    protected $primaryKey = 'at_code';
    public $timestamps = true;

    protected $fillable = [
        'at_code',
        'at_patente',
        'at_sello',
        'at_neumaticos',
        'at_carro',
        'at_carga_llave',
        'at_kms',
        'at_estado',
        'at_created_at',
        'at_updated_at',
        'at_created_user',
        'at_updated_user',
    ];

    // Relación con la tabla Acceso (acceso_transporte)
    public function accesos()
    {
        return $this->hasMany(Acceso::class, 'ac_at_code', 'at_code');
    }

    // Relación con los usuarios (usuario que crea)
    public function createdUser()
    {
        return $this->belongsTo(User::class, 'at_created_user', 'id');
    }

    // Relación con los usuarios (usuario que actualiza)
    public function updatedUser()
    {
        return $this->belongsTo(User::class, 'at_updated_user', 'id');
    }
}
