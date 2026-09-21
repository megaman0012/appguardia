<?php

namespace App\Console\Commands;

use App\Services\Inventario\AplicadorDeKit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Kit;

/**
 * Crea los kits a partir de las listas que ya existen, y las engancha.
 *
 * **Los datos ya dicen cuáles son los kits.** Hay 132 listas con cuatro nombres
 * distintos, y los nombres delatan la duplicación manual:
 *
 * | Nombre | Listas | Contenido |
 * |---|---|---|
 * | `SEGURIDAD FISICA` | 122 | los 4 productos, cantidad 1 |
 * | `SEGURIDAD AEROPORTUARIA` | 6 | **los mismos 4** |
 * | `SEGURIDA FISICA` | 3 | los mismos 4 — **errata** |
 * | `SEGURIDAD FISICA 1` | 1 | **3 productos**, le falta Forros |
 *
 * **Se agrupa por nombre normalizado, no por contenido.** Aunque hoy «física» y
 * «aeroportuaria» lleven exactamente lo mismo, la operación las nombra distinto
 * y eso es una distinción real que conviene conservar: fundirlas por tener el
 * mismo contenido hoy obligaría a separarlas de nuevo el día que difieran.
 *
 * **El contenido del kit sale de la variante más frecuente**, no de la primera
 * lista que aparezca. Con 122 listas iguales y 1 distinta, tomar la primera por
 * id podría fijar como estándar justo la excepción.
 *
 * Las listas cuyo contenido **no coincide** con el kit quedan marcadas como
 * `li_modificada`, que es lo que hace visible la excepción en vez de perderla.
 *
 * Simula por defecto. Idempotente: los kits se buscan por nombre.
 */
class CrearKitsDesdeListasCommand extends Command
{
    protected $signature = 'inventario:crear-kits
                            {--alias=* : Nombres que son el mismo kit: "SEGURIDA FISICA=SEGURIDAD FISICA"}
                            {--ejecutar : Sin esto solo muestra lo que haria}';

    protected $description = 'Crea los kits de puesto a partir de las listas existentes';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');

        if (! $ejecutar) {
            $this->warn('MODO SIMULACION. Nada se escribe. Agregue --ejecutar para aplicarlo.');
            $this->newLine();
        }

        $listas = DB::table('inv_lista')->select('li_id', 'li_nombre')->orderBy('li_id')->get();

        if ($listas->isEmpty()) {
            $this->info('No hay listas de las que sacar kits.');

            return self::SUCCESS;
        }

        $alias      = $this->alias();
        $contenidos = $this->contenidoDeCadaLista();
        $grupos     = $listas->groupBy(fn ($l) => $alias[$this->normalizar($l->li_nombre)]
            ?? $this->normalizar($l->li_nombre));

        $this->avisarDeNombresParecidos($grupos->keys()->all());

        $this->line('Listas: <fg=yellow>' . $listas->count() . '</> en <fg=yellow>'
            . $grupos->count() . '</> kit(s)');
        $this->newLine();

        foreach ($grupos as $nombre => $delGrupo) {
            $variantes = $delGrupo
                ->groupBy(fn ($l) => $this->huella($contenidos[$l->li_id] ?? []))
                ->sortByDesc(fn ($v) => $v->count());

            $estandar  = $variantes->first();
            $contenido = $contenidos[$estandar->first()->li_id] ?? [];
            $aparte    = $delGrupo->count() - $estandar->count();

            $this->line(sprintf('  <options=bold>%s</>', $nombre));
            $this->line(sprintf('    %d listas · %d producto(s) en el kit%s',
                $delGrupo->count(),
                count($contenido),
                $aparte > 0 ? " · <fg=yellow>{$aparte} se marcan como modificadas</>" : '',
            ));

            if (! $ejecutar) {
                continue;
            }

            $kit = Kit::firstOrCreate(
                ['ki_nombre' => $nombre],
                ['ki_descripcion' => 'Creado desde las listas existentes el ' . now()->toDateString(),
                 'ki_activo' => true],
            );

            $huellaDelKit = app(AplicadorDeKit::class)->huella($contenido);

            DB::transaction(function () use ($kit, $contenido, $delGrupo, $contenidos, $huellaDelKit) {
                foreach ($contenido as $productoId => $cantidad) {
                    DB::table('inv_kit_item')->updateOrInsert(
                        ['kii_ki_id' => $kit->ki_id, 'kii_producto_id' => $productoId],
                        ['kii_cantidad' => $cantidad, 'kii_created_at' => now(), 'kii_updated_at' => now()],
                    );
                }

                foreach ($delGrupo as $l) {
                    $suyo     = $contenidos[$l->li_id] ?? [];
                    $difiere  = $suyo != $contenido;

                    DB::table('inv_lista')->where('li_id', $l->li_id)->update([
                        'li_kit_id' => $kit->ki_id,
                        // Comparado, no asumido: la que difiera queda marcada.
                        'li_modificada' => $difiere,
                        /*
                         * La huella es la del CONTENIDO PROPIO de cada lista, no
                         * la del kit. Es lo que dice «asi estaba esta lista la
                         * ultima vez que se sincronizo»: a la que ya difiere hay
                         * que dejarla con su propia foto, o la primera
                         * sincronizacion la daria por intacta y la pisaria.
                         */
                        'li_kit_huella' => app(AplicadorDeKit::class)->huella($suyo),
                        'li_updated_at' => now(),
                    ]);
                }
            });
        }

        if (! $ejecutar) {
            $this->newLine();
            $this->comment('Nada se escribió.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info(sprintf('Hecho. %d kits, %d listas enganchadas, %d marcadas como modificadas.',
            DB::table('inv_kit')->count(),
            DB::table('inv_lista')->whereNotNull('li_kit_id')->count(),
            DB::table('inv_lista')->where('li_modificada', true)->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * `--alias="A=B"` → los grupos de A se cuentan como B.
     *
     * @return array<string, string>
     */
    private function alias(): array
    {
        $out = [];

        foreach ((array) $this->option('alias') as $par) {
            if (! str_contains($par, '=')) {
                continue;
            }

            [$de, $a] = explode('=', $par, 2);
            $out[$this->normalizar($de)] = $this->normalizar($a);
        }

        return $out;
    }

    /**
     * Señala los nombres sospechosamente parecidos, **sin unirlos**.
     *
     * La duplicación manual dejó `SEGURIDA FISICA` junto a `SEGURIDAD FISICA`.
     * Unirlos solo sería adivinar: dos kits pueden llamarse parecido y ser
     * distintos de verdad, y fundirlos por su nombre borraría uno sin rastro.
     * Así que se avisa y se ofrece el comando exacto para hacerlo a mano.
     */
    private function avisarDeNombresParecidos(array $nombres): void
    {
        $sospechosos = [];

        foreach ($nombres as $a) {
            foreach ($nombres as $b) {
                if ($a >= $b) {
                    continue;
                }

                // Hasta 3 letras de diferencia sobre nombres largos: caza
                // erratas y sufijos («… 1»), no nombres realmente distintos.
                if (levenshtein($a, $b) <= 3 && min(mb_strlen($a), mb_strlen($b)) >= 8) {
                    $sospechosos[] = [$a, $b];
                }
            }
        }

        if ($sospechosos === []) {
            return;
        }

        $this->newLine();
        $this->warn('Hay nombres que parecen el mismo kit mal escrito:');

        foreach ($sospechosos as [$a, $b]) {
            $this->line("  «{$a}»  vs  «{$b}»");
        }

        $this->line('  Si son el mismo, vuelva a correrlo con:');

        foreach ($sospechosos as [$a, $b]) {
            $this->line("    --alias=\"{$a}={$b}\"");
        }

        $this->newLine();
    }

    /** @return array<int, array<int, float>> lista => [producto => cantidad] */
    private function contenidoDeCadaLista(): array
    {
        $out = [];

        foreach (DB::table('inv_lista_item')->where('lia_activo', true)->get() as $i) {
            $out[(int) $i->lia_lista_id][(int) $i->lia_producto_id] = (float) $i->lia_cantidad_default;
        }

        foreach ($out as &$c) {
            ksort($c);
        }

        return $out;
    }

    /** Firma comparable del contenido, para agrupar variantes. */
    private function huella(array $contenido): string
    {
        ksort($contenido);

        return json_encode($contenido);
    }

    /**
     * Recorta, colapsa espacios y quita mayúsculas.
     *
     * Absorbe espaciado y mayúsculas, **no erratas**: `SEGURIDA` y `SEGURIDAD`
     * son letras distintas y no se unen solas, a propósito. Las erratas se
     * señalan en el informe y se unen con `--alias`, que es una decisión de
     * quien mira, no del comando.
     */
    private function normalizar(string $nombre): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $nombre)));
    }
}
