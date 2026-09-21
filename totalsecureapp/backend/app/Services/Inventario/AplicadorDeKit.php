<?php

namespace App\Services\Inventario;

use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Kit;
use Modules\Administracion\Models\Lista;

/**
 * Pone el kit en los locales, y sabe cuándo un local se apartó de él.
 *
 * **Por qué existe.** Había 132 listas y 130 idénticas: una plantilla copiada a
 * mano. Dar inventario a un local nuevo eran ~22 interacciones y agregar un
 * producto a todos, 132 ediciones.
 *
 * ⚠️ **El kit no reemplaza a la lista: la escribe.** `inv_lista` e
 * `inv_lista_item` conservan su forma, porque son lo que lee la app del guardia
 * y contra lo que se registran los 23.799 movimientos. Cambiar eso habría
 * tocado el camino operativo, que funciona bien.
 *
 * ### Qué cuenta como «modificada», y por qué importa
 *
 * Una lista está modificada cuando su contenido ya no coincide con **lo que el
 * kit decía la última vez que se le aplicó** — no con lo que el kit dice hoy.
 *
 * ⚠️ **Esa distinción es todo el mecanismo.** Comparar contra el kit actual
 * parece lo natural y se rompe en cuanto alguien **agrega un producto al kit**:
 * de golpe las 132 listas difieren, todas quedan marcadas como excepción, y el
 * cambio **no se propaga a ninguna** — exactamente al revés de lo que se busca.
 * Lo cazó un test. Por eso al sincronizar se guarda la huella del kit en
 * `li_kit_huella`, y la comparación responde la pregunta correcta: «¿alguien
 * tocó ESTA lista desde la última sincronización?».
 *
 * Se **calcula comparando**, no se confía en que alguien haya puesto la bandera.
 * Una bandera que hay que acordarse de levantar se olvida, y entonces una
 * sincronización del kit pisaría en silencio la excepción de un puesto.
 *
 * No se marca por cambiar el nombre o la descripción de la lista: eso no cambia
 * lo que el guardia cuenta.
 *
 * Una lista marcada **deja de recibir los cambios del kit**. Es lo que hace útil
 * la excepción: hoy un puesto distinto es indistinguible del resto.
 */
class AplicadorDeKit
{
    /**
     * Aplica el kit a los locales indicados.
     *
     * Crea la lista al local que no tenga, y reescribe los items de las que no
     * estén modificadas. Idempotente: aplicar dos veces el mismo kit no cambia
     * nada la segunda.
     *
     * @param  int[]  $locales
     * @param  bool   $forzar  Pisa también las listas modificadas, devolviéndolas al kit.
     * @return array{creadas:int, actualizadas:int, respetadas:int}
     */
    public function aplicar(Kit $kit, array $locales, bool $forzar = false): array
    {
        $r = ['creadas' => 0, 'actualizadas' => 0, 'respetadas' => 0];

        $items = $kit->items()->get()
            ->mapWithKeys(fn ($i) => [(int) $i->kii_producto_id => (float) $i->kii_cantidad])
            ->all();

        foreach ($locales as $insCode) {
            DB::transaction(function () use ($kit, $insCode, $items, $forzar, &$r) {
                $lista = Lista::where('li_ins_code', $insCode)->first();

                if ($lista === null) {
                    $lista = Lista::create([
                        'li_ins_code'   => $insCode,
                        'li_kit_id'     => $kit->ki_id,
                        'li_modificada' => false,
                        'li_nombre'     => $kit->ki_nombre,
                        'li_activo'     => true,
                    ]);

                    $this->escribirItems($lista, $items);
                    $lista->update(['li_kit_huella' => $this->huella($items)]);
                    $r['creadas']++;

                    return;
                }

                /*
                 * Una lista que ya se apartó del kit se respeta. Pisarla sería
                 * borrar en silencio la razón por la que ese puesto es distinto
                 * -- y sin dejar rastro de que existía.
                 */
                if (! $forzar && $lista->li_kit_id !== null && $this->esExcepcion($lista)) {
                    $lista->update(['li_modificada' => true]);
                    $r['respetadas']++;

                    return;
                }

                $this->escribirItems($lista, $items);

                $lista->update([
                    'li_kit_id'     => $kit->ki_id,
                    'li_modificada' => false,
                    'li_kit_huella' => $this->huella($items),
                ]);

                $r['actualizadas']++;
            });
        }

        return $r;
    }

    /**
     * Marca la lista como excepción si alguien la tocó desde la sincronización.
     *
     * Se llama después de editar los items de una lista en el panel.
     *
     * ⚠️ **Solo escala a «sí», nunca desmarca.** Desmarcar automáticamente
     * porque el contenido vuelva a coincidir borraría una excepción declarada al
     * migrar --una lista que ya difería del kit antes de que el kit existiera--
     * y la siguiente sincronización la pisaría. Para devolver una lista al kit
     * está la acción explícita, que aplica con `forzar`.
     */
    public function refrescarBandera(Lista $lista): bool
    {
        if ($lista->li_kit_id === null) {
            return false;
        }

        if ($lista->li_modificada) {
            return true;
        }

        if (! $this->fueEditada($lista)) {
            return false;
        }

        $lista->update(['li_modificada' => true]);

        return true;
    }

    /**
     * ¿Hay que dejar esta lista en paz al aplicar el kit?
     *
     * Son dos cosas distintas y conviene no confundirlas:
     *
     *  - **La bandera** dice «esto es una excepción declarada». La pone la
     *    migración cuando una lista ya difería del kit, y `refrescarBandera()`
     *    cuando alguien la edita.
     *  - **La huella** detecta ediciones *posteriores* a la última
     *    sincronización, que es lo que alimenta la bandera.
     *
     * Mirar solo la huella deja pasar la excepción que venía de la migración:
     * su huella es la de su propio contenido, así que «coincide» y se pisaría.
     * Lo cazó un test.
     */
    private function esExcepcion(Lista $lista): bool
    {
        return (bool) $lista->li_modificada || $this->fueEditada($lista);
    }

    /**
     * ¿Alguien tocó los items de esta lista desde la última sincronización?
     *
     * Compara el conjunto {producto => cantidad} de los items **activos** contra
     * `li_kit_huella`, que es lo que el kit decía al aplicarlo. Un item
     * desactivado no lo cuenta el guardia, así que para esto no existe.
     *
     * Sin huella guardada la lista es anterior al kit: se la considera intacta y
     * la primera sincronización la pondrá al día.
     */
    private function fueEditada(Lista $lista): bool
    {
        if ($lista->li_kit_huella === null) {
            return false;
        }

        return $this->huellaDeLaLista($lista) !== $lista->li_kit_huella;
    }

    /**
     * Huella del contenido de un kit.
     *
     * Publica porque `inventario:crear-kits` tiene que escribir **exactamente la
     * misma** al enganchar las listas existentes: si difiriera, la primera
     * sincronizacion veria modificadas las 132 listas.
     *
     * @param array<int, float> $items
     */
    public function huella(array $items): string
    {
        ksort($items);

        return hash('sha256', json_encode($items));
    }

    private function huellaDeLaLista(Lista $lista): string
    {
        $items = DB::table('inv_lista_item')
            ->where('lia_lista_id', $lista->li_id)
            ->where('lia_activo', true)
            ->pluck('lia_cantidad_default', 'lia_producto_id')
            ->mapWithKeys(fn ($v, $k) => [(int) $k => (float) $v])
            ->all();

        return $this->huella($items);
    }

    /**
     * Deja la lista con exactamente los items del kit.
     *
     * ⚠️ **Actualiza y desactiva en vez de borrar y reinsertar.** Borrar las
     * filas cambiaría sus `lia_id`, y aunque hoy nada los referencia, es la
     * clase de cambio que rompe un informe guardado o una integración meses
     * después. Los que sobran se desactivan, que además conserva el rastro de
     * que ese producto estuvo en el puesto.
     *
     * @param  array<int, float>  $items
     */
    private function escribirItems(Lista $lista, array $items): void
    {
        $existentes = DB::table('inv_lista_item')
            ->where('lia_lista_id', $lista->li_id)
            ->pluck('lia_id', 'lia_producto_id')
            ->all();

        foreach ($items as $productoId => $cantidad) {
            if (isset($existentes[$productoId])) {
                DB::table('inv_lista_item')
                    ->where('lia_id', $existentes[$productoId])
                    ->update([
                        'lia_cantidad_default' => $cantidad,
                        'lia_activo'           => true,
                        'lia_updated_at'       => now(),
                    ]);

                continue;
            }

            DB::table('inv_lista_item')->insert([
                'lia_lista_id'         => $lista->li_id,
                'lia_producto_id'      => $productoId,
                'lia_cantidad_default' => $cantidad,
                'lia_activo'           => true,
                'lia_created_at'       => now(),
            ]);
        }

        $sobran = array_diff_key($existentes, $items);

        if ($sobran !== []) {
            DB::table('inv_lista_item')
                ->whereIn('lia_id', array_values($sobran))
                ->update(['lia_activo' => false, 'lia_updated_at' => now()]);
        }
    }
}
