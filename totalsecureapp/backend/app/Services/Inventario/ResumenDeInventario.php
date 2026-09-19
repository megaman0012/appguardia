<?php

namespace App\Services\Inventario;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El inventario visto por cliente: cuanto se le asigno y cuanto esta repartido.
 *
 * Es el unico lugar donde se resuelve esa comparacion, y lo usan **las dos**
 * pantallas que la necesitan: «Stock por cliente» y el resumen clientes x
 * productos. Tenerlo en un solo sitio es deliberado -- que las dos digan lo
 * mismo es justamente el punto, y en este proyecto ya hubo un caso de la misma
 * consulta copiada en dos recursos donde el agujero quedo en la copia que nadie
 * actualizo (ver `PerfilPanel::localesVisibles()`).
 *
 * Los tres numeros, y por que se distinguen:
 *
 * | | De donde sale | Que significa |
 * |---|---|---|
 * | **asignado** | `inv_stock_cliente.isc_cantidad` | Lo que la empresa le entrego al cliente. Es un dato **declarado**, alguien lo escribe |
 * | **distribuido** | suma de `inv_lista_item.lia_cantidad_default` de los locales de ese cliente | Lo que dicen que deberia haber en cada puesto |
 * | **diferencia** | asignado − distribuido | Negativa = se repartio mas de lo que hay. Eso es lo que antes era invisible |
 *
 * ⚠️ **«distribuido» sale de las listas, no de los movimientos.** Una lista dice
 * lo que *debe* haber en el puesto; los movimientos dicen lo que se *conto* un
 * dia concreto. Para saber si el reparto cuadra con lo asignado importa lo
 * primero: un conteo con faltantes no significa que se haya asignado de mas.
 *
 * ⚠️ **Los locales sin cliente no se pierden.** 51 de los 188 locales no tienen
 * `ins_cliente_id` (los creados el 2026-09-17). Se agrupan bajo un cliente nulo
 * en vez de descartarlos: si se filtraran, el resumen cuadraria en pantalla y
 * estaria mintiendo.
 */
class ResumenDeInventario
{
    /** Clave del grupo que junta los locales todavia sin cliente. */
    public const SIN_CLIENTE = 0;

    /**
     * Una fila por (cliente, producto) con los tres numeros.
     *
     * @param  int[]|null  $locales  Alcance: null = todos; [] = ninguno.
     * @return Collection<int, object>
     */
    public function porClienteYProducto(?array $locales = null): Collection
    {
        $distribuido = $this->distribuidoPorClienteYProducto($locales);
        $asignado    = $this->asignadoPorClienteYProducto($this->clientesVisibles($locales));

        // La union de las dos: un cliente puede tener asignado sin repartir
        // todavia, o repartido sin que nadie haya declarado la asignacion.
        $claves = $distribuido->keys()->merge($asignado->keys())->unique();

        return $claves
            ->map(function (string $clave) use ($distribuido, $asignado) {
                [$org, $producto] = array_map('intval', explode(':', $clave));

                $d = (float) ($distribuido[$clave]->cantidad ?? 0);
                $a = (float) ($asignado[$clave]->cantidad ?? 0);

                return (object) [
                    'org_code'    => $org,
                    'producto_id' => $producto,
                    'asignado'    => $a,
                    'distribuido' => $d,
                    'diferencia'  => $a - $d,
                ];
            })
            ->sortBy([['org_code', 'asc'], ['producto_id', 'asc']])
            ->values();
    }

    /**
     * El desglose de UN cliente por local y producto, para abrir la fila.
     *
     * @return Collection<int, object>
     */
    public function porLocalYProducto(int $orgCode, ?array $locales = null): Collection
    {
        $q = DB::table('inv_lista_item as it')
            ->join('inv_lista as l', 'l.li_id', '=', 'it.lia_lista_id')
            ->join('organizacion_institucion as i', 'i.ins_code', '=', 'l.li_ins_code')
            ->where('it.lia_activo', true)
            ->where('l.li_activo', true)
            ->select(
                'i.ins_code',
                'i.ins_descripcion',
                'it.lia_producto_id as producto_id',
                DB::raw('sum(it.lia_cantidad_default) as cantidad'),
            )
            ->groupBy('i.ins_code', 'i.ins_descripcion', 'it.lia_producto_id')
            ->orderBy('i.ins_descripcion');

        $orgCode === self::SIN_CLIENTE
            ? $q->whereNull('i.ins_cliente_id')
            : $q->where('i.ins_cliente_id', $orgCode);

        $this->acotar($q, $locales, 'i.ins_code');

        return $q->get();
    }

    /** @return Collection<string, object> indexada por "org:producto" */
    private function distribuidoPorClienteYProducto(?array $locales): Collection
    {
        $q = DB::table('inv_lista_item as it')
            ->join('inv_lista as l', 'l.li_id', '=', 'it.lia_lista_id')
            ->join('organizacion_institucion as i', 'i.ins_code', '=', 'l.li_ins_code')
            ->where('it.lia_activo', true)
            ->where('l.li_activo', true)
            ->select(
                // Los locales sin cliente van al grupo 0, no se descartan.
                DB::raw('coalesce(i.ins_cliente_id, 0) as org_code'),
                'it.lia_producto_id as producto_id',
                DB::raw('sum(it.lia_cantidad_default) as cantidad'),
            )
            ->groupBy(DB::raw('coalesce(i.ins_cliente_id, 0)'), 'it.lia_producto_id');

        $this->acotar($q, $locales, 'i.ins_code');

        return $q->get()->keyBy(fn ($r) => ((int) $r->org_code) . ':' . ((int) $r->producto_id));
    }

    /**
     * Que clientes alcanza un perfil, deducido de sus locales.
     *
     * ⚠️ **Hace falta porque `inv_stock_cliente` no tiene local.** El alcance del
     * panel viene en locales, pero lo asignado esta a nivel de cliente, asi que
     * hay que traducir. Sin esta traduccion un supervisor **sin ningun local
     * vinculado seguia viendo los totales asignados de todos los clientes**: el
     * lado «distribuido» se acotaba y el «asignado» no. Lo cazo un test.
     *
     * @return int[]|null null = todos
     */
    private function clientesVisibles(?array $locales): ?array
    {
        if ($locales === null) {
            return null;
        }

        if (empty($locales)) {
            return [];
        }

        return DB::table('organizacion_institucion')
            ->whereIn('ins_code', $locales)
            ->select(DB::raw('distinct coalesce(ins_cliente_id, 0) as org'))
            ->pluck('org')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @param  int[]|null  $clientes  null = todos; [] = ninguno.
     * @return Collection<string, object> indexada por "org:producto"
     */
    private function asignadoPorClienteYProducto(?array $clientes): Collection
    {
        $q = DB::table('inv_stock_cliente')
            ->where('isc_activo', true)
            ->select(
                'isc_org_code as org_code',
                'isc_producto_id as producto_id',
                DB::raw('sum(isc_cantidad) as cantidad'),
            )
            ->groupBy('isc_org_code', 'isc_producto_id');

        $this->acotar($q, $clientes, 'isc_org_code');

        return $q->get()->keyBy(fn ($r) => ((int) $r->org_code) . ':' . ((int) $r->producto_id));
    }

    /**
     * Aplica el alcance del perfil.
     *
     * `null` = ve todo; `[]` = **no ve nada**. Confundir los dos convierte a un
     * supervisor sin locales vinculados en acceso global: es la misma distincion
     * que hace `PerfilPanel::localesVisibles()`, de donde viene este parametro.
     */
    private function acotar($query, ?array $locales, string $columna): void
    {
        if ($locales === null) {
            return;
        }

        empty($locales)
            ? $query->whereRaw('1 = 0')
            : $query->whereIn($columna, $locales);
    }
}
