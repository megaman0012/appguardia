<?php

namespace Modules\Acceso\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Guard;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\RefreshesPermissionCache;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
class Permission extends Model implements PermissionContract
{
    use HasRoles;
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
        return config('permission.table_names.permissions', parent::getTable());
    }

    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? Guard::getDefaultName(static::class);

        $permission = static::getPermission(['name' => $attributes['name'], 'guard_name' => $attributes['guard_name']]);

        if ($permission) {
            throw PermissionAlreadyExists::create($attributes['name'], $attributes['guard_name']);
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
     * A permission can be applied to roles.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            config('permission.models.role'),
            config('permission.table_names.role_has_permissions'),
            static::columnaPivotePermiso(),
            static::columnaPivoteRol()
        );
    }

    /**
     * A permission belongs to some users of the model associated with its guard.
     */
    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            getModelForGuard($this->attributes['guard_name'] ?? config('auth.defaults.guard')),
            'model',
            config('permission.table_names.model_has_permissions'),
            static::columnaPivotePermiso(),
            config('permission.column_names.model_morph_key')
        );
    }

    public function sections(){
        return $this->hasOne(permission_section::class,'ps_codigo', 'ps_codigo');
    }

    /**
     * Find a permission by its name (and optionally guardName).
     *
     * @param  string|null  $guardName
     *
     * @throws \Spatie\Permission\Exceptions\PermissionDoesNotExist
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
    public static function findByName(\BackedEnum|string $name, ?string $guardName = null): PermissionContract
    {
        //$guardName = $guardName ?? Guard::getDefaultName(static::class);
        //$permission = static::getPermission(['name' => $name, 'guard_name' => $guardName]);
        $permission = static::getPermission(['name' => $name]);
        if (! $permission) {
            throw PermissionDoesNotExist::create($name, $guardName);
        }

        return $permission;
    }

    /**
     * Find a permission by its id (and optionally guardName).
     *
     * @param  string|null  $guardName
     *
     * @throws \Spatie\Permission\Exceptions\PermissionDoesNotExist
     */
    public static function findById(int|string $id, ?string $guardName = null): PermissionContract
    {
        //$guardName = $guardName ?? Guard::getDefaultName(static::class);
        //$permission = static::getPermission([(new static())->getKeyName() => $id, 'guard_name' => $guardName]);
        $permission = static::getPermission([(new static())->getKeyName() => $id]);

        if (! $permission) {
            throw PermissionDoesNotExist::withId($id, $guardName);
        }

        return $permission;
    }

    /**
     * Find or create permission by its name (and optionally guardName).
     *
     * @param  string|null  $guardName
     */
    public static function findOrCreate(\BackedEnum|string $name, ?string $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);
        $permission = static::getPermission(['name' => $name, 'guard_name' => $guardName]);

        if (! $permission) {
            return static::query()->create(['name' => $name, 'guard_name' => $guardName]);
        }

        return $permission;
    }

    /**
     * Get the current cached permissions.
     */
    protected static function getPermissions(array $params = [], bool $onlyOne = false): Collection
    {
        return app(PermissionRegistrar::class)
            ->setPermissionClass(static::class)
            ->getPermissions($params, $onlyOne);
    }

    /**
     * Get the current cached first permission.
     *
     * @return \Spatie\Permission\Contracts\Permission
     */
    protected static function getPermission(array $params = []): ?PermissionContract
    {
        return static::getPermissions($params, true)->first();
    }
}
