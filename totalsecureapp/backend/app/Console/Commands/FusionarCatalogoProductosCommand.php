<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Convierte el catalogo de productos por local en uno global.
 *
 * **El problema, medido en produccion:** 532 filas que son **4 productos
 * distintos repetidos en 133 locales**. Un baston retractil es el mismo objeto
 * en los 133 sitios; lo que cambia por local es cuantos hay, y eso ya vive en
 * `inv_lista_item.lia_cantidad_default`. Agregar un producto eran 133
 * inserciones.
 *
 * **Que hace.** Agrupa por nombre normalizado, elige una fila superviviente por
 * grupo, **reapunta a ella todo lo que referenciaba a las hermanas** y borra las
 * hermanas. El superviviente queda con `ipc_ins_code` nulo, que es «global».
 *
 * Lo que se reapunta:
 *
 * | Tabla | Columna | Filas en produccion |
 * |---|---|---|
 * | `inv_lista_item` | `lia_producto_id` | 526 |
 * | `inv_movimiento_detalle` | `md_producto_id` | **23.799** |
 *
 * ⚠️ **El superviviente es el de `ipc_id` mas bajo, no uno cualquiera.** Tiene
 * que ser determinista: si dos corridas eligieran distinto, la segunda reapuntaria
 * el historico a otro id y los informes cambiarian solos.
 *
 * ⚠️ **`inv_lista_item` tiene un unico en (lista, producto).** Si una lista
 * tuviera dos productos que se fusionan en uno --hoy no pasa, esta comprobado--
 * el remapeo violaria ese unico. El comando lo detecta antes, suma las cantidades
 * en un solo item y descarta el sobrante, en vez de reventar a mitad.
 *
 * **La normalizacion del nombre es a proposito laxa** (recorta, colapsa espacios
 * y compara sin distinguir mayusculas): la duplicacion manual ya produjo erratas
 * --`SEGURIDA FISICA` frente a `SEGURIDAD FISICA` en los nombres de lista-- y
 * comparar literal dejaria fuera justo los casos que hay que unir. Las erratas
 * de verdad, las que cambian letras, **no** las une: eso hay que mirarlo a mano y
 * por eso el informe lista los grupos.
 *
 * Simula por defecto. Es idempotente: una vez fusionado no queda nada que
 * agrupar, asi que volver a correrlo no escribe.
 */
class FusionarCatalogoProductosCommand extends Command
{
    protected $signature = 'inventario:fusionar-catalogo
                            {--ejecutar : Sin esto solo muestra lo que haria}';

    protected $description = 'Unifica los productos duplicados por local en un catalogo global';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');

        if (! $ejecutar) {
            $this->warn('MODO SIMULACION. Nada se escribe. Agregue --ejecutar para aplicarlo.');
            $this->newLine();
        }

        $productos = DB::table('inv_producto_catalogo')
            ->select('ipc_id', 'ipc_nombre', 'ipc_ins_code')
            ->orderBy('ipc_id')
            ->get();

        $this->line('Catálogo actual: <fg=yellow>' . $productos->count() . '</> filas');

        // Agrupados por nombre normalizado. El primero de cada grupo sobrevive
        // porque la consulta viene ordenada por id.
        $grupos = $productos->groupBy(fn ($p) => $this->normalizar($p->ipc_nombre));

        $this->line('Productos distintos: <fg=yellow>' . $grupos->count() . '</>');
        $this->newLine();

        $totalBorrar = 0;
        $plan        = [];

        foreach ($grupos as $nombre => $filas) {
            $superviviente = $filas->first();
            $hermanas      = $filas->slice(1)->pluck('ipc_id')->all();

            $this->line(sprintf('  %-34s %3d filas → 1 (id %d)',
                mb_substr($nombre, 0, 32), $filas->count(), $superviviente->ipc_id));

            if ($hermanas === []) {
                continue;
            }

            $totalBorrar += count($hermanas);
            $plan[] = ['superviviente' => (int) $superviviente->ipc_id, 'hermanas' => $hermanas];
        }

        if ($plan === []) {
            $this->newLine();
            $this->info('No hay nada que fusionar: el catálogo ya es global.');

            return self::SUCCESS;
        }

        $idsHermanas = collect($plan)->pluck('hermanas')->flatten()->all();

        $items = DB::table('inv_lista_item')->whereIn('lia_producto_id', $idsHermanas)->count();
        $movs  = DB::table('inv_movimiento_detalle')->whereIn('md_producto_id', $idsHermanas)->count();

        $this->newLine();
        $this->line("Se reapuntan  <fg=yellow>{$items}</> items de lista y <fg=yellow>{$movs}</> detalles de movimiento");
        $this->line("Se eliminan   <fg=yellow>{$totalBorrar}</> filas duplicadas del catálogo");

        $choques = $this->choquesDeItems($plan);

        if ($choques > 0) {
            $this->line("Items que chocan con el único (lista, producto): <fg=yellow>{$choques}</> "
                . '— se suman las cantidades en uno solo');
        }

        if (! $ejecutar) {
            $this->newLine();
            $this->comment('Nada se escribió.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan as $g) {
                $this->resolverChoques($g['superviviente'], $g['hermanas']);

                DB::table('inv_lista_item')
                    ->whereIn('lia_producto_id', $g['hermanas'])
                    ->update(['lia_producto_id' => $g['superviviente']]);

                DB::table('inv_movimiento_detalle')
                    ->whereIn('md_producto_id', $g['hermanas'])
                    ->update(['md_producto_id' => $g['superviviente']]);

                DB::table('inv_producto_catalogo')
                    ->whereIn('ipc_id', $g['hermanas'])
                    ->delete();

                // Nulo = global. Es lo que distingue un producto del catalogo
                // nuevo de uno que todavia arrastra su local de origen.
                DB::table('inv_producto_catalogo')
                    ->where('ipc_id', $g['superviviente'])
                    ->update(['ipc_ins_code' => null, 'ipc_updated_at' => now()]);
            }
        });

        $this->newLine();
        $this->info('Hecho. Catálogo: ' . DB::table('inv_producto_catalogo')->count() . ' filas, '
            . DB::table('inv_producto_catalogo')->whereNull('ipc_ins_code')->count() . ' globales.');

        return self::SUCCESS;
    }

    /**
     * Recorta, colapsa espacios y quita mayusculas.
     *
     * No quita acentos: «Baston» y «Bastón» son el mismo producto mal escrito, y
     * unirlos automaticamente esconderia un error de carga que conviene ver.
     */
    private function normalizar(string $nombre): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $nombre)));
    }

    /** Cuantos items quedarian repetidos en la misma lista tras el remapeo. */
    private function choquesDeItems(array $plan): int
    {
        $n = 0;

        foreach ($plan as $g) {
            $n += DB::table('inv_lista_item as a')
                ->join('inv_lista_item as b', 'b.lia_lista_id', '=', 'a.lia_lista_id')
                ->whereIn('a.lia_producto_id', $g['hermanas'])
                ->where('b.lia_producto_id', $g['superviviente'])
                ->count();
        }

        return $n;
    }

    /**
     * Si la lista ya tiene el superviviente, **suma la cantidad** del duplicado
     * en el que se queda y borra el otro.
     *
     * Descartarlo a secas perderia cantidad esperada, y dejarlo violaria el
     * unico `uk_lista_producto` a mitad de la transaccion.
     */
    private function resolverChoques(int $superviviente, array $hermanas): void
    {
        $repetidos = DB::table('inv_lista_item as a')
            ->join('inv_lista_item as b', 'b.lia_lista_id', '=', 'a.lia_lista_id')
            ->whereIn('a.lia_producto_id', $hermanas)
            ->where('b.lia_producto_id', $superviviente)
            ->select('a.lia_id as sobra', 'b.lia_id as queda',
                     'a.lia_cantidad_default as cant_sobra')
            ->get();

        foreach ($repetidos as $r) {
            DB::table('inv_lista_item')->where('lia_id', $r->queda)
                ->increment('lia_cantidad_default', (float) $r->cant_sobra);

            DB::table('inv_lista_item')->where('lia_id', $r->sobra)->delete();
        }
    }
}
