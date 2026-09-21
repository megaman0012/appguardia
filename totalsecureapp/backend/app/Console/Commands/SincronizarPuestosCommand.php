<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Pone los puestos al dia contra la lista final.
 *
 * Toma un Excel con una fila por puesto --columnas `Local`, `Puesto`,
 * `Descripción` y `Activo`-- y engancha cada uno a su local **por el nombre del
 * local**, porque el archivo no trae codigos.
 *
 * ⚠️ **Solo se cargan los puestos cuyo local esta ACTIVO.** La lista de puestos
 * del 2026-09-21 nombraba 103 locales, de los cuales **14 no existen**: son
 * nombres consolidados (`SWISSPORT` en vez de los cinco `SWISSPORT MATRIZ`,
 * `CARGA`…; `NEUROCIENCIAS` en vez de sus dos garitas). Es la reorganizacion que
 * el roadmap tenia pospuesta, y **contradice la lista de locales**, que mantiene
 * los sitios separados. Se decidio que manda la lista de locales: los puestos
 * que no casan **se informan y no se tocan**, en vez de crear locales que nadie
 * pidio. Cuando se decida consolidar, eso es una migracion de historico
 * --reasignar accesos, rondas, biometrias, turnos e inventario a otro
 * `ins_code`--, no una carga.
 *
 * ### Como se decide renombrar en vez de crear
 *
 * Los puestos actuales se generaron uno por local **con el nombre del local**
 * (`JBGYE CEMENTERIO`), y la lista les da nombre propio (`puesto 1`). Si a un
 * local le **sobra exactamente uno** y le **falta exactamente uno**, se
 * **renombra**: asi el puesto conserva su `pu_id` y con el sus turnos. Crear uno
 * nuevo y desactivar el viejo se llevaria por delante la programacion.
 *
 * Cuando hay varios de cada lado no se adivina: se crean los que faltan y los
 * que sobran se desactivan.
 *
 * ⚠️ **Un puesto con turnos nunca se desactiva.** `CerrarTurnosDelDia` recorre
 * locales activos y el cierre depende del puesto: apagarlo saca esos turnos del
 * proceso sin que nada lo avise. Ya paso con los locales el mismo dia.
 *
 * ⚠️ El archivo tiene que estar bajo `backend/`: el contenedor monta
 * `backend/:/var/www`, no la raiz del monorepo.
 *
 * Simula por defecto. Idempotente.
 */
class SincronizarPuestosCommand extends Command
{
    protected $signature = 'puestos:sincronizar
                            {--archivo= : Excel con la lista final, relativo a storage/app/}
                            {--ejecutar : Sin esto solo muestra lo que haria}';

    protected $description = 'Actualiza los puestos contra la lista final, sin tocar los locales';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $ruta     = $this->ruta();

        if ($ruta === null) {
            return self::FAILURE;
        }

        if (! $ejecutar) {
            $this->warn('MODO SIMULACION. Nada se escribe. Agregue --ejecutar para aplicarlo.');
            $this->newLine();
        }

        $filas = $this->leer($ruta);
        $this->line('Puestos en el archivo: <fg=yellow>' . count($filas) . '</>');

        $locales = DB::table('organizacion_institucion')->where('ins_estado', true)
            ->pluck('ins_descripcion', 'ins_code');
        $porNombre = $locales->mapWithKeys(fn ($d, $c) => [$this->norm($d) => (int) $c]);

        // ── Los que no tienen local activo ──────────────────────────────────
        $sinLocal = collect($filas)->reject(fn ($f) => $porNombre->has($this->norm($f['local'])));

        if ($sinLocal->isNotEmpty()) {
            $this->newLine();
            $this->warn($sinLocal->count() . ' puesto(s) SIN local activo — no se tocan:');

            foreach ($sinLocal->groupBy('local') as $nombre => $suyos) {
                $this->line(sprintf('   %-42s %d puesto(s)', mb_substr($nombre, 0, 40), $suyos->count()));
            }

            $this->line('   Son nombres consolidados o locales retirados. Ver la cabecera del comando.');
        }

        // ── Los que sí ──────────────────────────────────────────────────────
        $conTurnos = DB::table('turno')->distinct()->pluck('tu_puesto_id')->filter()
            ->map(fn ($v) => (int) $v)->all();

        $r = ['renombrados' => 0, 'actualizados' => 0, 'creados' => 0,
              'desactivados' => 0, 'respetados' => []];

        foreach ($locales as $code => $desc) {
            $delArchivo = collect($filas)
                ->filter(fn ($f) => ($porNombre[$this->norm($f['local'])] ?? null) === (int) $code)
                ->values();

            if ($delArchivo->isEmpty()) {
                continue;   // local que la lista no menciona: se deja como esta
            }

            $enBase = DB::table('puesto')->where('pu_ins_code', $code)->get(['pu_id', 'pu_nombre']);
            $mapa   = $enBase->mapWithKeys(fn ($q) => [$this->norm($q->pu_nombre) => (int) $q->pu_id]);

            $casan    = $delArchivo->filter(fn ($f) => $mapa->has($this->norm($f['puesto'])));
            $faltan   = $delArchivo->reject(fn ($f) => $mapa->has($this->norm($f['puesto'])))->values();
            $usados   = $casan->map(fn ($f) => $mapa[$this->norm($f['puesto'])])->all();
            $sobran   = $enBase->reject(fn ($q) => in_array((int) $q->pu_id, $usados, true))->values();

            foreach ($casan as $f) {
                if ($ejecutar) {
                    $this->guardar($mapa[$this->norm($f['puesto'])], $f);
                }

                $r['actualizados']++;
            }

            /*
             * Uno sobra y uno falta: es el puesto autogenerado con el nombre del
             * local, al que la lista le da nombre propio. Renombrar conserva su
             * `pu_id` y con el sus turnos.
             */
            if ($faltan->count() === 1 && $sobran->count() === 1) {
                if ($ejecutar) {
                    $this->guardar((int) $sobran[0]->pu_id, $faltan[0]);
                }

                $r['renombrados']++;

                continue;
            }

            foreach ($faltan as $f) {
                if ($ejecutar) {
                    DB::table('puesto')->insert([
                        'pu_ins_code'    => $code,
                        'pu_nombre'      => $f['puesto'],
                        'pu_descripcion' => $f['descripcion'] !== '' ? $f['descripcion'] : null,
                        'pu_estado'      => $f['activo'] !== '0',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);
                }

                $r['creados']++;
            }

            foreach ($sobran as $q) {
                if (in_array((int) $q->pu_id, $conTurnos, true)) {
                    $r['respetados'][] = "{$desc} / {$q->pu_nombre}";

                    continue;
                }

                if ($ejecutar) {
                    DB::table('puesto')->where('pu_id', $q->pu_id)
                        ->update(['pu_estado' => false, 'updated_at' => now()]);
                }

                $r['desactivados']++;
            }
        }

        $this->newLine();
        $this->line('Sobre los locales activos:');

        foreach (['renombrados', 'actualizados', 'creados', 'desactivados'] as $k) {
            printf("  %-16s %s\n", $k, $r[$k]);
        }

        if ($r['respetados'] !== []) {
            $this->newLine();
            $this->warn('  ⚠ ' . count($r['respetados']) . ' puesto(s) sobran pero TIENEN TURNOS y no se tocan:');

            foreach ($r['respetados'] as $x) {
                $this->line("      {$x}");
            }

            $this->line('      Desactivarlos sacaría esos turnos del cierre diario sin avisar.');
        }

        if (! $ejecutar) {
            $this->newLine();
            $this->comment('Nada se escribió.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Hecho. Puestos activos: '
            . DB::table('puesto')->where('pu_estado', true)->count());

        return self::SUCCESS;
    }

    private function guardar(int $puId, array $f): void
    {
        DB::table('puesto')->where('pu_id', $puId)->update([
            'pu_nombre'      => $f['puesto'],
            // Una descripcion vacia en el archivo no borra la que hay.
            'pu_descripcion' => $f['descripcion'] !== ''
                ? $f['descripcion']
                : DB::raw('pu_descripcion'),
            'pu_estado'      => $f['activo'] !== '0',
            'updated_at'     => now(),
        ]);
    }

    private function norm(string $s): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $s)));
    }

    private function ruta(): ?string
    {
        $a = (string) $this->option('archivo');

        if ($a === '') {
            $this->error('Falta --archivo.');

            return null;
        }

        $ruta = str_starts_with($a, '/') ? $a : storage_path('app/' . $a);

        if (! is_file($ruta)) {
            $this->error("No se encontró: {$ruta}");
            $this->line('  El contenedor monta backend/, no la raíz del monorepo.');

            return null;
        }

        return $ruta;
    }

    /** Columnas por encabezado, no por posición. */
    private function leer(string $ruta): array
    {
        $hoja = IOFactory::load($ruta)->getSheet(0)->toArray(null, true, false, false);
        $cab  = array_map(fn ($c) => trim((string) $c), array_shift($hoja));
        $idx  = array_flip($cab);

        $mapa = ['local' => 'Local', 'puesto' => 'Puesto',
                 'descripcion' => 'Descripción', 'activo' => 'Activo'];

        $filas = [];

        foreach ($hoja as $f) {
            $fila = [];

            foreach ($mapa as $k => $col) {
                $fila[$k] = isset($idx[$col]) ? trim((string) ($f[$idx[$col]] ?? '')) : '';
            }

            if ($fila['puesto'] === '' || $fila['local'] === '') {
                continue;
            }

            $filas[] = $fila;
        }

        return $filas;
    }
}
