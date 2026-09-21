<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Pone los locales al dia contra la lista final del cliente.
 *
 * Toma un Excel con una fila por local --las columnas que usa son `Código`,
 * `Local`, `Cliente`, `Ciudad`, `Dirección`, `Email` y `Estado`-- y hace dos
 * cosas: **actualiza** los que estan en la lista y **desactiva** los que no.
 *
 * ⚠️ **Desactiva, NO borra, y la diferencia no es de estilo.** Con los 104
 * locales que la lista del 2026-09-21 dejaba fuera, un borrado real:
 *
 *  - **habria fallado**: 40 vacantes de turno apuntan a esos locales con una
 *    clave foranea `NO ACTION`;
 *  - habria destruido en cascada **3.388 movimientos de inventario con sus
 *    6.802 detalles**, 60 listas y 98 puestos;
 *  - y habria dejado **22.857 filas huerfanas** --18.707 vinculos
 *    usuario-local, **2.741 biometrias**, 1.149 rondas, accesos y alertas--
 *    porque esas tablas **no tienen clave foranea al local**. Eso no falla ni
 *    avisa: los reportes simplemente empiezan a mostrar vacios, y las
 *    biometrias son datos personales que no se pueden regenerar.
 *
 * Desactivar los saca de los selectores de puestos, vacantes, plantillas y del
 * cierre de turnos, deja el historico legible, y se deshace en un clic.
 *
 * ⚠️ **Hizo falta arreglar antes `InstitucionController::allInstitucions`**, que
 * miraba solo el vinculo usuario-local: sin eso, un local desactivado **seguia
 * apareciendo en la tablet del guardia** y la desactivacion no servia de nada.
 *
 * ⚠️ **El archivo tiene que estar dentro de `backend/`.** El contenedor monta
 * `backend/:/var/www`, no la raiz del monorepo: un Excel en `docs/` no lo ve.
 * Las rutas relativas se resuelven contra `storage/app/`.
 *
 * Simula por defecto. Es idempotente.
 */
class SincronizarLocalesCommand extends Command
{
    protected $signature = 'locales:sincronizar
                            {--archivo= : Excel con la lista final, relativo a storage/app/}
                            {--crear-clientes : Crea los clientes del archivo que no existan}
                            {--ejecutar : Sin esto solo muestra lo que haria}';

    protected $description = 'Actualiza los locales contra la lista final y desactiva los que sobran';

    private const COLUMNAS = [
        'ins_descripcion' => 'Código',   // se reemplaza abajo; ver mapa()
    ];

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $ruta     = $this->rutaDelArchivo();

        if ($ruta === null) {
            return self::FAILURE;
        }

        if (! $ejecutar) {
            $this->warn('MODO SIMULACION. Nada se escribe. Agregue --ejecutar para aplicarlo.');
            $this->newLine();
        }

        $filas = $this->leer($ruta);

        if ($filas === []) {
            $this->error('El archivo no tiene filas con código.');

            return self::FAILURE;
        }

        $this->line('Locales en el archivo: <fg=yellow>' . count($filas) . '</>');

        $codigos = array_map(fn ($f) => (int) $f['codigo'], $filas);

        // ── Clientes ────────────────────────────────────────────────────────
        $clientes  = DB::table('organizacion')->pluck('org_code', 'org_descripcion');
        $faltantes = collect($filas)->pluck('cliente')->filter()->unique()
            ->reject(fn ($c) => $clientes->has($c))->values();

        if ($faltantes->isNotEmpty()) {
            $this->newLine();
            $this->line('Clientes del archivo que no existen: <fg=yellow>' . $faltantes->implode(', ') . '</>');

            if (! $this->option('crear-clientes')) {
                $this->warn('  Sin --crear-clientes, esos locales se quedan con el cliente que ya tenían.');
            } elseif ($ejecutar) {
                foreach ($faltantes as $nombre) {
                    $id = DB::table('organizacion')->insertGetId([
                        'org_descripcion' => $nombre, 'org_razon_social' => $nombre,
                        'org_estado' => 1, 'created_at' => now(), 'updated_at' => now(),
                    ], 'org_code');

                    $clientes[$nombre] = $id;
                    $this->info("  creado: {$nombre} (código {$id})");
                }
            } else {
                /*
                 * En simulacion el cliente todavia no existe, asi que sus locales
                 * no contarian como «cambio de cliente» y el informe diria menos
                 * de lo que va a pasar. Se les da un codigo ficticio --negativo,
                 * que nunca es un org_code real-- solo para que el conteo sea
                 * honesto. No se escribe.
                 */
                foreach ($faltantes as $i => $nombre) {
                    $clientes[$nombre] = -1 - $i;
                }
            }
        }

        // ── Los que se quedan ───────────────────────────────────────────────
        $cambios = ['nombre' => 0, 'ciudad' => 0, 'direccion' => 0, 'email' => 0, 'cliente' => 0,
                    'reactivados' => 0, 'sin_base' => []];

        foreach ($filas as $f) {
            $actual = DB::table('organizacion_institucion')->where('ins_code', (int) $f['codigo'])->first();

            if ($actual === null) {
                $cambios['sin_base'][] = $f['codigo'];

                continue;
            }

            $nuevos = [];

            foreach ([['nombre', 'ins_descripcion'], ['ciudad', 'ins_ciudad'],
                      ['direccion', 'ins_direccion'], ['email', 'ins_email']] as [$k, $col]) {
                // Un campo vacio en el archivo NO borra lo que hay: la lista es
                // de locales, no un volcado completo de cada ficha.
                if ($f[$k] !== '' && $f[$k] !== trim((string) $actual->$col)) {
                    $nuevos[$col] = $f[$k];
                    $cambios[$k]++;
                }
            }

            $org = $clientes[$f['cliente']] ?? null;

            if ($org !== null && (int) $actual->ins_cliente_id !== (int) $org) {
                $nuevos['ins_cliente_id'] = $org;
                $cambios['cliente']++;
            }

            if ($f['estado'] === '1' && ! $actual->ins_estado) {
                $nuevos['ins_estado'] = true;
                $cambios['reactivados']++;
            }

            if ($nuevos !== [] && $ejecutar) {
                $nuevos['updated_at'] = now();
                DB::table('organizacion_institucion')->where('ins_code', $actual->ins_code)->update($nuevos);
            }
        }

        $this->newLine();
        $this->line('Actualizaciones sobre los que se quedan:');

        foreach (['nombre', 'ciudad', 'direccion', 'email', 'cliente', 'reactivados'] as $k) {
            if ($cambios[$k] > 0) {
                printf("  %-14s %s\n", $k, $cambios[$k]);
            }
        }

        if ($cambios['sin_base'] !== []) {
            $this->warn('  Códigos del archivo que no existen en la base: '
                . implode(', ', $cambios['sin_base']));
        }

        // ── Los que sobran ──────────────────────────────────────────────────
        $aDesactivar = DB::table('organizacion_institucion')
            ->where('ins_estado', true)
            ->whereNotIn('ins_code', $codigos);

        $n = (clone $aDesactivar)->count();

        $this->newLine();
        $this->line("Locales activos que NO están en el archivo: <fg=yellow>{$n}</>");
        $this->line('  Se <options=bold>desactivan</>, no se borran. Ver la cabecera del comando: '
            . 'borrarlos dejaría 22.857 filas huérfanas, incluidas 2.741 biometrías.');

        /*
         * ⚠️ **Un local con turnos programados NO se desactiva.**
         *
         * Paso de verdad el 2026-09-21: la lista dejaba fuera 4 locales que
         * tenian **268 turnos, 134 de ellos de hoy en adelante** --hasta el 4 de
         * octubre--. `CerrarTurnosDelDia` solo recorre locales activos, asi que
         * esos turnos **dejaron de cerrarse esa misma noche**, y nada lo
         * anuncio: no hay error, simplemente el puesto desaparece del proceso.
         *
         * Una lista de locales puede venir incompleta; la programacion de turnos
         * es un hecho. Ante la contradiccion gana el turno, y quien mande la
         * lista que decida.
         */
        $conTurnos = (clone $aDesactivar)
            ->whereIn('ins_code', function ($q) {
                $q->select('p.pu_ins_code')
                    ->from('puesto as p')
                    ->join('turno as t', 't.tu_puesto_id', '=', 'p.pu_id');
            })
            ->get(['ins_code', 'ins_descripcion']);

        if ($conTurnos->isNotEmpty()) {
            $this->newLine();
            $this->warn('  ⚠ ' . $conTurnos->count() . ' de ellos tienen TURNOS PROGRAMADOS y NO se tocan:');

            foreach ($conTurnos as $l) {
                $t = DB::table('turno as t')->join('puesto as p', 'p.pu_id', '=', 't.tu_puesto_id')
                    ->where('p.pu_ins_code', $l->ins_code)->count();

                $this->line("      {$l->ins_code}  {$l->ins_descripcion}  ({$t} turnos)");
            }

            $this->line('      Desactivarlos los sacaría del cierre diario de turnos sin avisar.');
            $this->line('      Resuelva la contradicción entre la lista y la programación primero.');
        }

        if ($ejecutar && $n > 0) {
            $afectados = (clone $aDesactivar)
                ->whereNotIn('ins_code', $conTurnos->pluck('ins_code'))
                ->update(['ins_estado' => false, 'updated_at' => now()]);

            $this->info("  desactivados: {$afectados}");
        }

        if (! $ejecutar) {
            $this->newLine();
            $this->comment('Nada se escribió.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Hecho. Locales activos ahora: '
            . DB::table('organizacion_institucion')->where('ins_estado', true)->count());

        return self::SUCCESS;
    }

    private function rutaDelArchivo(): ?string
    {
        $archivo = (string) $this->option('archivo');

        if ($archivo === '') {
            $this->error('Falta --archivo.');

            return null;
        }

        $ruta = str_starts_with($archivo, '/') ? $archivo : storage_path('app/' . $archivo);

        if (! is_file($ruta)) {
            $this->error("No se encontró el archivo: {$ruta}");
            $this->line('  El contenedor monta backend/, no la raíz del monorepo: '
                . 'copie el Excel a backend/storage/app/.');

            return null;
        }

        return $ruta;
    }

    /**
     * Lee el Excel y devuelve filas normalizadas.
     *
     * Se resuelven las columnas **por su encabezado**, no por posición: un
     * export del panel puede cambiar el orden y una lectura posicional leería
     * la ciudad como dirección sin que nada falle.
     */
    private function leer(string $ruta): array
    {
        /*
         * PhpSpreadsheet directo y no la fachada `Excel`: en maatwebsite 4
         * `toArray()` exige un objeto `Import`, y aca no hay nada que importar
         * -- solo leer una hoja. PhpSpreadsheet ya viene como dependencia suya.
         */
        $libro = IOFactory::load($ruta);
        $hoja  = $libro->getSheet(0)->toArray(null, true, false, false);

        if ($hoja === []) {
            return [];
        }

        $cab = array_map(fn ($c) => trim((string) $c), array_shift($hoja));
        $idx = array_flip($cab);

        $mapa = [
            'codigo'    => 'Código',
            'nombre'    => 'Local',
            'cliente'   => 'Cliente',
            'ciudad'    => 'Ciudad',
            'direccion' => 'Dirección',
            'email'     => 'Email',
            'estado'    => 'Estado',
        ];

        $faltan = array_diff($mapa, $cab);

        if ($faltan !== []) {
            $this->warn('Columnas que no están en el archivo: ' . implode(', ', $faltan));
        }

        $filas = [];

        foreach ($hoja as $f) {
            $codigo = isset($idx['Código']) ? trim((string) ($f[$idx['Código']] ?? '')) : '';

            if ($codigo === '' || ! ctype_digit($codigo)) {
                continue;
            }

            $fila = [];

            foreach ($mapa as $k => $col) {
                $fila[$k] = isset($idx[$col]) ? trim((string) ($f[$idx[$col]] ?? '')) : '';
            }

            $filas[] = $fila;
        }

        return $filas;
    }
}
