<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\PerfilPanel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Session;
use Modules\Administracion\Models\UserHasInstitucion;

/**
 * Acota las cifras de un widget al alcance del perfil que esta mirando.
 *
 * **Existe porque los widgets acotaban solo por `user_has_institucion`**, y con
 * eso un Administrador veia CEROS: su alcance es global, asi que no esta
 * vinculado a ningun local y la lista salia vacia. El escritorio quedaba en
 * blanco justo para el perfil que tiene que verlo todo.
 *
 * Los tres alcances de `PerfilPanel`, que es la unica fuente de verdad de esto:
 *
 *  - **global** (Administrador, Consola) -> `null`, sin filtro. La Consola
 *    trabaja de madrugada resolviendo faltas de cualquier local: acotarla la
 *    dejaria sin ver justo la que tiene que resolver.
 *  - **por pais** (Lider Operativo) -> los locales de su(s) pais(es).
 *  - **por institucion** (Supervisor, Vigilante) -> sus locales.
 *
 * `[]` y `null` NO son lo mismo: `null` es «sin filtro» y `[]` es «no ve nada».
 * Un lider sin paises asignados cae en `[]` a proposito -- una configuracion
 * incompleta no debe convertirse en acceso global.
 */
trait AcotaPorAlcance
{
    /**
     * Locales que puede ver el perfil actual.
     *
     * @return int[]|null null = sin filtro; [] = no ve nada
     */
    protected function localesEnAlcance(): ?array
    {
        if (PerfilPanel::alcanceEsGlobal()) {
            return null;
        }

        if (PerfilPanel::alcanceEsPorInstitucion()) {
            $usuId = Session::get('usuID');

            if (!$usuId) {
                return [];
            }

            return UserHasInstitucion::where('ui_usu_id', $usuId)
                ->where('ui_state', 1)
                ->pluck('ui_ins_code')
                ->map(fn ($c) => (int) $c)
                ->all();
        }

        return PerfilPanel::localesDelUsuario();
    }

    /**
     * Aplica el alcance a una consulta, por la columna del local que tenga.
     *
     * Se pasa el nombre de la columna porque cada tabla la llama distinto
     * (`bio_ins_code`, `ac_ins_code`, `rc_ins_code`…), herencia de coredt360.
     */
    protected function acotar(Builder $query, string $columnaLocal): Builder
    {
        $locales = $this->localesEnAlcance();

        if ($locales === null) {
            return $query;
        }

        return empty($locales)
            ? $query->whereRaw('1 = 0')
            : $query->whereIn($columnaLocal, $locales);
    }
}
