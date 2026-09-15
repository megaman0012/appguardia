<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Puesto;

/**
 * Crea el puesto de trabajo que le falta a cada local.
 *
 * **Por que hace falta.** La tabla `puesto` esta vacia, y sin puestos el modulo
 * de Turnos, el cuadrante y las vacantes no tienen nada que mostrar: un turno se
 * asigna a un puesto (`turno.tu_puesto_id`).
 *
 * **Por que un puesto por local, y no una reorganizacion.** La v1 no tenia
 * puestos: quien cargo los datos creo un «local» por cada posicion fisica --
 * «Cementerio Puerta 10», «Hospital Luis Vernaza Garita 3»--, asi que lo que hoy
 * figura como local **ya es** el puesto. `puestos:analizar` mostro que esos 137
 * locales son en realidad unos pocos sitios con varias posiciones cada uno.
 *
 * Agrupar de verdad --convertir cada sitio en un local y cada local actual en un
 * puesto suyo-- significaria reasignar accesos, rondas, biometrias y turnos ya
 * registrados a otro `ins_code`. Eso es una migracion de datos historicos, no
 * una carga. Este comando hace lo minimo que desbloquea Turnos **sin mover un
 * solo registro existente**: le da a cada local su puesto, con el mismo nombre.
 *
 * El sitio propuesto por `puestos:analizar` se guarda en `pu_descripcion`, para
 * no perder esa agrupacion cuando se decida hacer la reorganizacion de verdad.
 *
 * Es idempotente: `puesto` tiene unico (`pu_ins_code`, `pu_nombre`), y ademas se
 * comprueba antes. Correrlo dos veces no duplica nada.
 */
class CargarPuestosCommand extends Command
{
    protected $signature = 'puestos:cargar
                            {--csv= : CSV de puestos:analizar, para tomar el nombre del sitio}
                            {--ejecutar : Sin esto solo muestra lo que haria}';

    protected $description = 'Crea un puesto de trabajo por cada local activo (idempotente)';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $sitios = $this->sitiosDelCsv($this->option('csv'));

        $locales = DB::table('organizacion_institucion')
            ->where('ins_estado', true)
            ->orderBy('ins_descripcion')
            ->get(['ins_code', 'ins_descripcion']);

        $this->info(sprintf('Locales activos: %d', $locales->count()));
        if ($sitios !== []) {
            $this->info(sprintf('Sitios conocidos por el CSV: %d locales mapeados', count($sitios)));
        }
        $this->newLine();

        $crear = [];
        $yaEstan = 0;

        foreach ($locales as $local) {
            $nombre = trim((string) $local->ins_descripcion);

            if ($nombre === '') {
                $this->warn("  Local {$local->ins_code} sin descripcion: se omite.");
                continue;
            }

            $existe = Puesto::where('pu_ins_code', $local->ins_code)
                ->where('pu_nombre', $nombre)
                ->exists();

            if ($existe) {
                $yaEstan++;
                continue;
            }

            $crear[] = [
                'pu_ins_code'    => $local->ins_code,
                'pu_nombre'      => $nombre,
                'pu_descripcion' => $sitios[$local->ins_code] ?? null,
                'pu_estado'      => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        $this->line(sprintf('Ya tenian puesto: %d', $yaEstan));
        $this->line(sprintf('Se crearian:      %d', count($crear)));
        $this->newLine();

        foreach (array_slice($crear, 0, 15) as $p) {
            $this->line(sprintf('  [%s] %s%s',
                $p['pu_ins_code'],
                $p['pu_nombre'],
                $p['pu_descripcion'] ? '   (sitio: ' . $p['pu_descripcion'] . ')' : ''
            ));
        }
        if (count($crear) > 15) {
            $this->line(sprintf('  … y %d mas', count($crear) - 15));
        }
        $this->newLine();

        if ($crear === []) {
            $this->info('No hay nada que crear.');
            return self::SUCCESS;
        }

        if (!$ejecutar) {
            $this->warn('Simulacion: no se escribio nada. Agregue --ejecutar para aplicarlo.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($crear) {
            foreach (array_chunk($crear, 200) as $lote) {
                DB::table('puesto')->insert($lote);
            }
        });

        $this->info(sprintf('Creados %d puestos.', count($crear)));

        return self::SUCCESS;
    }

    /**
     * Lee el CSV de `puestos:analizar` y devuelve ins_code => sitio propuesto.
     *
     * @return array<int,string>
     */
    private function sitiosDelCsv(?string $ruta): array
    {
        if (!$ruta) {
            return [];
        }

        if (!is_readable($ruta)) {
            $this->warn("No se pudo leer {$ruta}: se sigue sin nombres de sitio.");
            return [];
        }

        $f = fopen($ruta, 'r');
        $cabecera = fgetcsv($f);
        if ($cabecera === false) {
            fclose($f);
            return [];
        }

        $iSitio = array_search('sitio_propuesto', $cabecera, true);
        $iCode  = array_search('ins_code', $cabecera, true);

        if ($iSitio === false || $iCode === false) {
            fclose($f);
            $this->warn('El CSV no tiene las columnas sitio_propuesto e ins_code.');
            return [];
        }

        $sitios = [];
        while (($fila = fgetcsv($f)) !== false) {
            if (!isset($fila[$iCode], $fila[$iSitio])) {
                continue;
            }
            $sitios[(int) $fila[$iCode]] = trim((string) $fila[$iSitio]);
        }
        fclose($f);

        return $sitios;
    }
}
