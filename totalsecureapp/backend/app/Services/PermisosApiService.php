<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Permisos que la API expone a sus clientes (app movil y portal).
 *
 * El catalogo de permisos es compartido con el panel web legacy, pero sus
 * secciones (Administracion, Formularios) no le sirven a ningun cliente de la
 * API: no tienen esas pantallas. Devolverlas inflaba el conteo que ve el usuario
 * en su perfil (22 en vez de 21 para un Vigilante) y le entregaba nombres de
 * permisos de un panel al que no entra.
 *
 * Vive en un solo lugar porque hay dos consumidores —el login y la seleccion de
 * perfil— que antes tenian su propia consulta escrita a mano y podian divergir.
 */
class PermisosApiService
{
    /**
     * Secciones del panel web legacy, excluidas de la API.
     *
     * Se excluyen estas en vez de permitir solo las moviles (10-18) para que un
     * rol de una seccion nueva —como el Portal Cliente, que es la 19— no quede
     * sin permisos por olvido.
     */
    public const SECCIONES_WEB_LEGACY = [1, 2];

    /**
     * Nombres de permisos activos de un conjunto de roles, sin los del panel web.
     *
     * @param  int[]  $roleIds
     * @return string[]
     */
    public function paraRoles(array $roleIds): array
    {
        if (empty($roleIds)) {
            return [];
        }

        return DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereIn('role_has_permissions.role_id', $roleIds)
            ->where('permissions.pr_state', 1)
            ->whereNotIn('permissions.ps_codigo', self::SECCIONES_WEB_LEGACY)
            ->distinct()
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->all();
    }

    /** @return string[] */
    public function paraRol(int $roleId): array
    {
        return $this->paraRoles([$roleId]);
    }
}
