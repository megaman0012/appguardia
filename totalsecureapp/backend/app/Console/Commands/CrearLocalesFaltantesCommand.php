<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Crea los locales que la operacion usa y el sistema todavia no tiene.
 *
 * Sale del archivo que reviso el cliente (`docs/locales-puestos-unificado.csv`):
 * cada fila es un PUESTO, y la columna `ins_code` dice a que local del sistema
 * corresponde. Las filas sin `ins_code` son puestos que la operacion usa y que
 * no existen como local -- la mayoria, puertas y garitas de los sitios grandes.
 *
 * Se crean como locales porque asi esta modelado hoy: **el marcaje solo se
 * vincula con un turno del mismo `ins_code`** (`TurnoService::buscarTurnoParaMarcaje`),
 * asi que un puesto sin local propio no puede tener turnos que casen con las
 * marcaciones de quien trabaja ahi.
 *
 * Idempotente: no crea un local si ya existe otro con la misma descripcion.
 */
class CrearLocalesFaltantesCommand extends Command
{
    protected $signature = 'locales:crear-faltantes
                            {csv : locales-puestos-unificado.csv}
                            {--ejecutar : Sin esto solo muestra lo que haria}';

    protected $description = 'Crea los locales de los puestos que todavia no tienen uno';

    public function handle(): int
    {
        $ruta = $this->argument('csv');

        if (!is_readable($ruta)) {
            $this->error("No se puede leer {$ruta}");
            return self::FAILURE;
        }

        $f = fopen($ruta, 'r');
        $cab = fgetcsv($f, 0, ';');
        $iCode = array_search('ins_code', $cab, true);
        $iLocal = array_search('local', $cab, true);
        $iPuesto = array_search('puesto', $cab, true);

        if ($iCode === false || $iLocal === false || $iPuesto === false) {
            fclose($f);
            $this->error('El CSV necesita las columnas ins_code, local y puesto (separadas por ;).');
            return self::FAILURE;
        }

        $existentes = DB::table('organizacion_institucion')
            ->pluck('ins_code', 'ins_descripcion')
            ->mapWithKeys(fn ($code, $desc) => [$this->clave($desc) => $code]);

        $crear = [];
        $yaEstan = 0;

        while (($fila = fgetcsv($f, 0, ';')) !== false) {
            if (trim((string) ($fila[$iCode] ?? '')) !== '') {
                continue;   // ya tiene local
            }

            $local = trim((string) ($fila[$iLocal] ?? ''));
            $puesto = trim((string) ($fila[$iPuesto] ?? ''));

            if ($local === '' || $puesto === '') {
                continue;
            }

            /*
             * El nombre lleva local y puesto: «H. LUIS VERNAZA — RONDA GENERAL».
             * Sin el puesto, los nueve puestos de un hospital se llamarian todos
             * igual y no habria forma de saber a cual asignar un turno.
             */
            $nombre = $local . ' — ' . $puesto;

            if ($existentes->has($this->clave($nombre))) {
                $yaEstan++;
                continue;
            }

            $crear[$this->clave($nombre)] = $nombre;
        }
        fclose($f);

        $this->line(sprintf('Ya existian: %d', $yaEstan));
        $this->line(sprintf('Se crearian: %d', count($crear)));
        $this->newLine();

        foreach (array_slice(array_values($crear), 0, 12) as $n) {
            $this->line('   ' . $n);
        }
        if (count($crear) > 12) {
            $this->line(sprintf('   … y %d mas', count($crear) - 12));
        }
        $this->newLine();

        if ($crear === []) {
            $this->info('No hay nada que crear.');
            return self::SUCCESS;
        }

        if (!$this->option('ejecutar')) {
            $this->warn('Simulacion: no se escribio nada. Agregue --ejecutar para crearlos.');
            return self::SUCCESS;
        }

        $creados = 0;

        DB::transaction(function () use ($crear, &$creados) {
            foreach ($crear as $nombre) {
                $code = DB::table('organizacion_institucion')->insertGetId([
                    'ins_descripcion' => $nombre,
                    'ins_estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'ins_code');

                // Su puesto de trabajo, para que Turnos pueda usarlo desde ya.
                DB::table('puesto')->insert([
                    'pu_ins_code' => $code,
                    'pu_nombre' => $nombre,
                    'pu_estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $creados++;
            }
        });

        $this->info(sprintf('Creados %d locales, con su puesto.', $creados));
        $this->warn('Revíselos en el panel: los que no correspondan se pueden desactivar.');

        return self::SUCCESS;
    }

    private function clave(string $s): string
    {
        $s = mb_strtoupper(trim($s));
        $s = strtr($s, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N','—'=>'-']);

        return preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9 ]/', ' ', $s));
    }
}
