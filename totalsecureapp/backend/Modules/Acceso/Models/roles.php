<?php

namespace Modules\Acceso\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Session;
use Illuminate\Database\Eloquent\Model;

class roles extends Model
{
    protected $table = "roles";

    /**
     * ⚠️ Sin esto el perfil no se puede guardar, y **falla en silencio**.
     *
     * Eloquent trae `$guarded = ['*']` por defecto, asi que un modelo que no
     * declara `$fillable` ignora toda asignacion masiva: `$record->update($datos)`
     * de Filament devuelve true y no escribe una sola columna. El formulario de
     * Perfiles estaba ademas vacio, asi que no habia nada que guardar y el
     * problema no se veia; al escribirlo, aparecia.
     *
     * `id` queda fuera a proposito: el perfil se referencia por nombre en
     * `PerfilPanel` y por id en `user_has_roles`.
     */
    protected $fillable = [
        'name',
        'descripcion',
        'estado',
        'visible',
    ];

    protected $casts = [
        'estado'  => 'boolean',
        'visible' => 'boolean',
    ];

/*********************************************************/
    public function users()
    {
        return $this->belongsToMany(users::class, 'user_has_roles', 'role_id', 'user_id');
    }

    /**
     * Los permisos del perfil.
     *
     * El pivote es `role_has_permissions` (`role_id`, `permission_id`), el mismo
     * que ya usa `Permission::roles()` en el otro sentido. Se declara aca para
     * que el panel pueda editarlos: hasta ahora los 111 vinculos solo se podian
     * tocar por SQL.
     *
     * ⚠️ **Esto NO gobierna el acceso al panel.** `PerfilPanel` decide por
     * NOMBRE de perfil (`Administrador`, `Supervisor`, ...), no por estos
     * permisos. Lo que se edita aca son los permisos granulares que consume la
     * **app movil** via `PermisosApiService` y el middleware `permission.api`.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_has_permissions',
            'role_id',
            'permission_id',
        );
    }
}
