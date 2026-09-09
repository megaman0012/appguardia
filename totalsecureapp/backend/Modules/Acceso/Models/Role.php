<?php

namespace Modules\Acceso\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Guard;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\RefreshesPermissionCache;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
class Role extends Model implements RoleContract
{
    use HasPermissions;
    use RefreshesPermissionCache;

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        parent::__construct($attributes);

        $this->guarded[] = $this->primaryKey;
    }

    public function getTable()
    {
        return config('permission.table_names.roles', parent::getTable());
    }

    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? Guard::getDefaultName(static::class);

        $params = ['name' => $attributes['name'], 'guard_name' => $attributes['guard_name']];
        if (config('permission.teams')) {
            if (array_key_exists(config('permission.column_names.team_foreign_key'), $attributes)) {
                $params[config('permission.column_names.team_foreign_key')] = $attributes[config('permission.column_names.team_foreign_key')];
            } else {
                $attributes[config('permission.column_names.team_foreign_key')] = getPermissionsTeamId();
            }
        }
        if (static::findByParam($params)) {
            throw RoleAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        return static::query()->create($attributes);
    }

    /**
     * Nombre de la columna pivote del rol.
     *
     * ⚠️ Antes se leia `PermissionRegistrar::$pivotRole`, que en
     * **spatie/laravel-permission 6 dejo de ser estatica** y paso a propiedad
     * de instancia. Usarla asi lanza «Access to undeclared static property» en
     * tiempo de ejecucion: **44 rutas de la API devolvian 500** por esto, y el
     * unico sintoma era el 500.
     *
     * Se resuelve del mismo config del que la libreria la resolvia en la v5
     * (`PermissionRegistrar::initializeCache()`), con el mismo valor por
     * defecto. En este proyecto los dos estan en `null`, o sea `role_id` y
     * `permission_id`.
     */
    protected static function columnaPivoteRol(): string
    {
        return config('permission.column_names.role_pivot_key') ?: 'role_id';
    }

    /** Nombre de la columna pivote del permiso. Ver columnaPivoteRol(). */
    protected static function columnaPivotePermiso(): string
    {
        return config('permission.column_names.permission_pivot_key') ?: 'permission_id';
    }

    /**
     * A role may be given various permissions.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            config('permission.models.permission'),
            config('permission.table_names.role_has_permissions'),
            static::columnaPivoteRol(),
            static::columnaPivotePermiso()
        );
    }

    /**
     * A role belongs to some users of the model associated with its guard.
     */
    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            getModelForGuard($this->attributes['guard_name'] ?? config('auth.defaults.guard')),
            'model',
            config('permission.table_names.model_has_roles'),
            static::columnaPivoteRol(),
            config('permission.column_names.model_morph_key')
        );
    }

    /**
     * Find a role by its name and guard name.
     *
     * @param  string|null  $guardName
     * @return \Spatie\Permission\Contracts\Role|\Spatie\Permission\Models\Role
     *
     * @throws \Spatie\Permission\Exceptions\RoleDoesNotExist
     */
    /*
     * ⚠️ Las firmas siguen al contrato de **spatie/laravel-permission**, que las
     * endurecio dos veces: la 6 pidio `?string $guardName` e `int|string $id`,
     * y la 8 agrego `BackedEnum|string $name` (permite enums como nombre de rol
     * o permiso). Con las de la v5 (`$guardName = null`, `int $id`) PHP aborta con
     * «Declaration must be compatible with Spatie\Permission\Contracts\...»
     * **antes de arrancar**: no es un aviso, es un error fatal que tumba
     * cualquier comando.
     *
     * Este proyecto usa de Spatie solo los contratos: los modelos extienden
     * `Model` a secas y las tablas reales son propias (`user_has_roles` con
     * `ru_code`, `role_has_permissions`, `permission_section`). El guardia no se
     * usa, asi que el parametro se acepta y se ignora.
     */
    public static function findByName(\BackedEnum|string $name, ?string $guardName = null): RoleContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $role = static::findByParam(['name' => $name, 'guard_name' => $guardName]);

        if (! $role) {
            throw RoleDoesNotExist::named($name);
        }

        return $role;
    }

    /**
     * Find a role by its id (and optionally guardName).
     *
     * @param  string|null  $guardName
     * @return \Spatie\Permission\Contracts\Role|\Spatie\Permission\Models\Role
     */
    public static function findById(int|string $id, ?string $guardName = null): RoleContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $role = static::findByParam([(new static())->getKeyName() => $id, 'guard_name' => $guardName]);

        if (! $role) {
            throw RoleDoesNotExist::withId($id);
        }

        return $role;
    }

    /**
     * Find or create role by its name (and optionally guardName).
     *
     * @param  string|null  $guardName
     * @return \Spatie\Permission\Contracts\Role|\Spatie\Permission\Models\Role
     */
    public static function findOrCreate(\BackedEnum|string $name, ?string $guardName = null): RoleContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $role = static::findByParam(['name' => $name, 'guard_name' => $guardName]);

        if (! $role) {
            return static::query()->create(['name' => $name, 'guard_name' => $guardName] + (config('permission.teams') ? [config('permission.column_names.team_foreign_key') => getPermissionsTeamId()] : []));
        }

        return $role;
    }

    protected static function findByParam(array $params = [])
    {
        $query = static::query();

        if (config('permission.teams')) {
            $query->where(function ($q) use ($params) {
                $q->whereNull(config('permission.column_names.team_foreign_key'))
                    ->orWhere(config('permission.column_names.team_foreign_key'), $params[config('permission.column_names.team_foreign_key')] ?? getPermissionsTeamId());
            });
            unset($params[config('permission.column_names.team_foreign_key')]);
        }

        foreach ($params as $key => $value) {
            $query->where($key, $value);
        }

        return $query->first();
    }

    /**
     * Determine if the user may perform the given permission.
     *
     * @param  string|Permission  $permission
     *
     * @throws \Spatie\Permission\Exceptions\GuardDoesNotMatch
     */
    public function hasPermissionTo(string|int|\Spatie\Permission\Contracts\Permission|\BackedEnum $permission, ?string $guardName = null): bool
    {
        if (config('permission.enable_wildcard_permission', false)) {
            return $this->hasWildcardPermission($permission, $this->getDefaultGuardName());
        }

        $permissionClass = $this->getPermissionClass();

        if (is_string($permission)) {
            $permission = $permissionClass->findByName($permission, $this->getDefaultGuardName());
        }

        if (is_int($permission)) {
            $permission = $permissionClass->findById($permission, $this->getDefaultGuardName());
        }

        if (! $this->getGuardNames()->contains($permission->guard_name)) {
            throw GuardDoesNotMatch::create($permission->guard_name, $this->getGuardNames());
        }

        return $this->permissions->contains($permission->getKeyName(), $permission->getKey());
    }
}
