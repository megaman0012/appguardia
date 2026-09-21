<?php

namespace App\Services\Inventario;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El inventario repartido, visto por cliente.

 * **distribuido** = suma de `inv_lista_item.lia_cantidad_default` de los locales
 * de ese cliente: lo que las listas dicen que debe haber en cada puesto.
 *
 * ⚠️ **Sale de las listas, no de los movimientos.** Una lista dice lo que *debe*
 * haber en el puesto; los movimientos dicen lo que se *conto* un dia concreto.
 * Para «cuanto equipo tiene este cliente» importa lo primero: un conteo con
 * faltantes de un turno no cambia lo que al puesto le corresponde.
 *
 * ⚠️ **Hubo una tercera cifra, «asignado», y se quito el 2026-09-21.** Salia de
 * `inv_stock_cliente`, que modelaba algo que este departamento no hace: la
 * operacion no maneja stock ni bodega, solo tiene o no tiene, y el equipo nuevo
 * o dañado va a otros departamentos. La tabla nunca tuvo una fila. Lo que si
 * hace falta vigilar --que un puesto se aparte del kit estandar-- se ve en el
 * listado de Listas, con su marca de «modificada», y no en el reporte que se le
 * entrega al cliente.
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
    /**
     * Una fila por (cliente, producto) con lo repartido.
     *
     * @param  int[]|null  $locales  Alcance: null = todos; [] = ninguno.
     * @return Collection<int, object>
     */
    public function porClienteYProducto(?array $locales = null): Collection
    {
        return $this->distribuidoPorClienteYProducto($locales)
            ->map(fn ($r) => (object) [
                'org_code'    => (int) $r->org_code,
                'producto_id' => (int) $r->producto_id,
                'distribuido' => (float) $r->cantidad,
            ])
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
            /*
             * ⚠️ Solo locales activos.
             *
             * Al retirar 91 locales contra la lista final del cliente
             * (2026-09-21) quedaron **59 listas con 236 items colgando de
             * puestos desactivados**. Sin este filtro el reporte que se le
             * entrega al cliente seguiria contando equipo en sitios donde ya no
             * se opera -- y nadie lo notaria, porque el numero sale mayor, no
             * roto.
             */
            ->where('i.ins_estado', true)
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
            /*
             * ⚠️ Solo locales activos.
             *
             * Al retirar 91 locales contra la lista final del cliente
             * (2026-09-21) quedaron **59 listas con 236 items colgando de
             * puestos desactivados**. Sin este filtro el reporte que se le
             * entrega al cliente seguiria contando equipo en sitios donde ya no
             * se opera -- y nadie lo notaria, porque el numero sale mayor, no
             * roto.
             */
            ->where('i.ins_estado', true)
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
