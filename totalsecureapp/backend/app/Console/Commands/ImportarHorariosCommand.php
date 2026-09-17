<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Carga la malla mensual de turnos desde el Excel de horarios.
 *
 * **Como esta hecho el archivo.** Cada hoja es una zona y contiene varios
 * bloques, uno por proyecto. Cada bloque trae su propia tabla
 * «NOMENCLATURA / TURNOS LABORAL / PROYECTO» y debajo la cuadricula: una fila por
 * guardia y una columna por dia, con una letra por celda.
 *
 * ⚠️ **La nomenclatura es POR PROYECTO, no global, y eso decide si la carga sale
 * bien.** `D1` es 10:00-16:00 en Cementerio, 07:00-13:00 en Neurociencias y
 * 08:00-20:00 en Chilenita; en C.C. y Loteria hay tres definiciones distintas de
 * `D`. Por eso se lee el documento EN ORDEN: cada tabla que aparece pisa la
 * anterior y vale para las filas que la siguen.
 *
 * ⚠️ **Las filas de encabezado se cuelan si no se las descarta.** Cada bloque
 * repite `L M M J V S D` en las columnas de dia, y eso parece una fila de marcas
 * --con `M` al doble, porque Martes y Miercoles comparten inicial--. Se descarta
 * toda fila que contenga `PROYECTO`, `DÍAS` o `FECHA`.
 *
 * **De donde sale el local de cada turno.** NO del nombre del proyecto en el
 * Excel, que habria que cruzar de forma difusa contra los 130 locales. Sale de
 * `user_has_institucion`: el guardia ya esta vinculado a su local en el sistema,
 * y ese dato es fiable. Si esta en mas de uno, el turno no se carga y aparece en
 * el informe.
 *
 * **Nada se escribe sin `--ejecutar`.** Y aun con el, solo entran las personas
 * cuyo nombre cruza de forma **inequivoca**: el resto sale listado para
 * revisarlo a mano, porque un turno asignado a la persona equivocada es peor que
 * un turno que falta.
 */
class ImportarHorariosCommand extends Command
{
    protected $signature = 'horarios:importar
                            {archivo : Ruta del .xlsx}
                            {--anio=2026 : Año de la malla}
                            {--mes=9 : Mes al que pertenecen los dias 7..30}
                            {--ejecutar : Sin esto solo informa}
                            {--csv= : Vuelca a CSV lo que no se pudo cruzar}
                            {--mapa= : CSV proyecto,puesto,ins_code que dice a que local va cada puesto}';

    protected $description = 'Importa la malla mensual de turnos desde el Excel de horarios';

    private const ZONAS = [
        'NORTE', 'CENTRO', 'CEMENTERIO', 'C.C Y LOTERIA', 'REGIONAL',
        'RANGER', 'SEMEDIC', 'CASA TOTAL', 'SUPERVISORES', 'DHL MECANO',
    ];

    /** Marcas que significan «no trabaja». */
    private const LIBRE = ['X', 'LIBRE'];

    public function handle(): int
    {
        $archivo = $this->argument('archivo');

        if (!is_readable($archivo)) {
            $this->error("No se puede leer {$archivo}");
            return self::FAILURE;
        }

        $this->info('Leyendo el archivo…');
        $libro = IOFactory::createReaderForFile($archivo);
        $libro->setReadDataOnly(true);
        $libro = $libro->load($archivo);

        $mapa = $this->leerMapa($this->option('mapa'));
        if ($mapa === []) {
            $this->warn('Sin --mapa no se puede saber a que local va cada proyecto: '
                . 'la simulacion solo contara el cruce de nombres.');
        }

        $usuarios = $this->indiceDeUsuarios();
        $this->info(sprintf('Usuarios activos en el sistema: %d', count($usuarios['por_clave'])));

        $turnos = [];
        $sinCruce = [];
        $sinLocal = [];
        $sinHorario = [];
        $personas = 0;

        foreach (self::ZONAS as $zona) {
            $hoja = $libro->getSheetByName($zona);
            if (!$hoja) {
                $this->warn("Hoja «{$zona}» no encontrada, se omite.");
                continue;
            }

            $filas = $hoja->toArray(null, true, false, false);
            [$colDias, $colNombres, $colApellidos] = $this->columnas($filas);

            if ($colDias === []) {
                $this->warn("Hoja «{$zona}»: no se encontro la fila de fechas, se omite.");
                continue;
            }

            $nomenclatura = [];
            $proyecto = '(sin nombre)';
            $puesto = '';

            foreach ($filas as $indiceFila => $fila) {
                $textos = array_map(
                    fn ($c) => is_string($c) ? mb_strtoupper(trim($c)) : '',
                    $fila
                );

                // Una tabla de nomenclatura: vale para lo que viene despues.
                if (in_array('NOMENCLATURA', $textos, true)) {
                    $nomenclatura = array_merge(
                        $nomenclatura,
                        $this->leerNomenclatura($filas, $indiceFila)
                    );
                    continue;
                }

                // Encabezado de bloque: nombra el proyecto y no es un guardia.
                if (array_intersect(['PROYECTO', 'DÍAS', 'DIAS', 'FECHA'], $textos)) {
                    $k = array_search('PROYECTO', $textos, true);
                    if ($k !== false && is_string($fila[$k + 1] ?? null) && trim($fila[$k + 1]) !== '') {
                        $proyecto = trim($fila[$k + 1]);
                    }
                    continue;
                }

                // La columna de puestos solo se rellena en la primera fila de
                // cada grupo: las siguientes heredan el puesto anterior.
                $celdaPuesto = $fila[$colNombres - 2] ?? null;
                if (is_string($celdaPuesto) && trim($celdaPuesto) !== '') {
                    $puesto = trim($celdaPuesto);
                }

                $nombres = $fila[$colNombres] ?? null;
                $apellidos = $fila[$colApellidos] ?? null;

                if (!is_string($nombres) || !is_string($apellidos)) {
                    continue;
                }
                $nombres = trim($nombres);
                $apellidos = trim($apellidos);
                if ($nombres === '' || $apellidos === '' || in_array(mb_strtoupper($nombres), ['NOMBRES', 'CANTIDAD'], true)) {
                    continue;
                }

                $personas++;
                $persona = $apellidos . ' ' . $nombres;

                $usuario = $this->cruzar($nombres, $apellidos, $usuarios);
                if ($usuario === null) {
                    $sinCruce[] = [$zona, $persona];
                    continue;
                }

                /*
                 * El local sale del PROYECTO del bloque, via el mapa que se pasa
                 * con `--mapa`.
                 *
                 * ⚠️ No se deduce de `user_has_institucion`, que seria lo
                 * natural: en esta base **cada guardia esta vinculado a ~111
                 * locales**. Esa tabla dice a que locales tiene acceso, no donde
                 * trabaja, asi que no sirve para asignar un turno.
                 */
                /*
                 * El mapa se busca primero por PROYECTO + PUESTO y solo despues
                 * por proyecto a secas.
                 *
                 * ⚠️ Es lo que hace que el turno sea util: un proyecto como
                 * «CEMENTERIO GENERAL» o «H. LUIS VERNAZA» agrupa una docena de
                 * locales --una por puerta o garita-- y el marcaje solo se
                 * vincula con un turno **del mismo `ins_code`**
                 * (`TurnoService::buscarTurnoParaMarcaje`). Un turno cargado en
                 * el local equivocado deja al guardia como ausente aunque haya
                 * marcado.
                 */
                $insCode = $mapa[$this->clave($proyecto . ' ' . $puesto)]
                    ?? $mapa[$this->clave($proyecto)]
                    ?? null;
                if ($insCode === null) {
                    $sinLocal[] = [$zona, $persona, $proyecto];
                    continue;
                }
                $puestoId = $usuarios['puestos'][$insCode] ?? null;

                foreach ($colDias as $col => $dia) {
                    $marca = $fila[$col] ?? null;
                    if (!is_string($marca) || trim($marca) === '') {
                        continue;
                    }
                    $marca = mb_strtoupper(trim($marca));

                    if (in_array($marca, self::LIBRE, true)) {
                        continue;
                    }

                    $horario = $nomenclatura[$marca] ?? null;
                    if ($horario === null) {
                        $sinHorario[$zona . ' · ' . $marca] = ($sinHorario[$zona . ' · ' . $marca] ?? 0) + 1;
                        continue;
                    }

                    $turnos[] = [
                        'tu_ins_code' => $insCode,
                        'tu_usu_id' => $usuario->id,
                        'tu_puesto_id' => $puestoId,
                        'tu_fecha' => $this->fecha($dia),
                        'tu_hora_inicio_prevista' => $horario[0],
                        'tu_hora_fin_prevista' => $horario[1],
                        'tu_estado' => 'programado',
                        'tu_state' => true,
                        'tu_created_at' => now(),
                        'tu_updated_at' => now(),
                    ];
                }
            }
        }

        $this->newLine();
        $this->line(sprintf('Personas en la malla        : %d', $personas));
        $this->line(sprintf('  cruzadas con el sistema   : %d', $personas - count($sinCruce) - count($sinLocal)));
        $this->line(sprintf('  sin cruce de nombre       : %d', count($sinCruce)));
        $this->line(sprintf('  sin local para su proyecto: %d', count($sinLocal)));
        $this->line(sprintf('Turnos listos para cargar   : %d', count($turnos)));

        if ($sinHorario !== []) {
            $this->newLine();
            $this->warn('Marcas sin horario en su nomenclatura (no se cargan):');
            foreach ($sinHorario as $k => $n) {
                $this->line(sprintf('   %-28s %d celdas', $k, $n));
            }
        }

        if ($this->option('csv')) {
            $this->volcarCsv($this->option('csv'), $sinCruce, $sinLocal);
        }

        if ($turnos === []) {
            return self::SUCCESS;
        }

        if (!$this->option('ejecutar')) {
            $this->newLine();
            $this->warn('Simulacion: no se escribio nada. Agregue --ejecutar para cargar.');
            return self::SUCCESS;
        }

        $nuevos = $this->insertar($turnos);
        $this->info(sprintf('Turnos creados: %d (los repetidos se omitieron)', $nuevos));

        return self::SUCCESS;
    }

    /** Inserta evitando duplicar un turno ya cargado (mismo usuario, fecha y hora). */
    private function insertar(array $turnos): int
    {
        $creados = 0;

        DB::transaction(function () use ($turnos, &$creados) {
            foreach (array_chunk($turnos, 200) as $lote) {
                foreach ($lote as $t) {
                    $existe = DB::table('turno')
                        ->where('tu_usu_id', $t['tu_usu_id'])
                        ->where('tu_fecha', $t['tu_fecha'])
                        ->where('tu_hora_inicio_prevista', $t['tu_hora_inicio_prevista'])
                        ->exists();

                    if ($existe) {
                        continue;
                    }

                    DB::table('turno')->insert($t);
                    $creados++;
                }
            }
        });

        return $creados;
    }

    /** Fecha real del dia: los dias 1..6 pertenecen al mes siguiente. */
    private function fecha(int $dia): string
    {
        $anio = (int) $this->option('anio');
        $mes = (int) $this->option('mes');

        if ($dia < 7) {
            $mes++;
            if ($mes > 12) { $mes = 1; $anio++; }
        }

        return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    }

    /**
     * Columnas de dias, nombres y apellidos.
     *
     * @return array{0: array<int,int>, 1: int, 2: int}
     */
    private function columnas(array $filas): array
    {
        $colDias = [];
        $colNombres = 3;
        $colApellidos = 4;

        foreach (array_slice($filas, 0, 14) as $fila) {
            foreach ($fila as $j => $c) {
                if (!is_string($c)) continue;
                $v = mb_strtoupper(trim($c));
                if ($v === 'NOMBRES') $colNombres = $j;
                if ($v === 'APELLIDOS') $colApellidos = $j;
                if (str_starts_with($v, 'FECHA') && $colDias === []) {
                    for ($k = $j + 1; $k < count($fila); $k++) {
                        $d = $fila[$k] ?? null;
                        if (is_numeric($d) && $d >= 1 && $d <= 31) {
                            $colDias[$k] = (int) $d;
                        }
                    }
                }
            }
        }

        return [$colDias, $colNombres, $colApellidos];
    }

    /**
     * Lee la tabla de nomenclatura que empieza en la fila dada.
     *
     * @return array<string, array{0:string,1:string}> marca => [inicio, fin]
     */
    /**
     * Lee la tabla de nomenclatura que empieza en la fila `$indice`.
     *
     * ⚠️ Recibe el INDICE y no la fila. Antes buscaba su posicion con
     * `array_search($filaCabecera, $filas, true)`, y eso fallaba: la comparacion
     * estricta de arrays no encuentra la fila cuando hay celdas con tipos
     * distintos, asi que la tabla se descartaba y **todas las marcas de esa hoja
     * quedaban sin horario**. Se veia como «CENTRO · D: 137 celdas sin
     * nomenclatura» aunque la tabla estuviera ahi.
     */
    private function leerNomenclatura(array $filas, int $indice): array
    {
        $filaCabecera = $filas[$indice] ?? [];

        $col = null;
        foreach ($filaCabecera as $j => $c) {
            if (is_string($c) && mb_strtoupper(trim($c)) === 'NOMENCLATURA') { $col = $j; break; }
        }
        if ($col === null) return [];

        $tabla = [];
        for ($i = $indice + 1; $i < min($indice + 16, count($filas)); $i++) {
            $marca = $filas[$i][$col] ?? null;
            $horario = $filas[$i][$col + 1] ?? null;

            if (!is_string($marca) || trim($marca) === '') continue;
            $marca = mb_strtoupper(trim($marca));
            if (mb_strlen($marca) > 4) break;

            $rango = $this->rango((string) $horario);
            if ($rango !== null) {
                $tabla[$marca] = $rango;
            }
        }

        return $tabla;
    }

    /**
     * «07:00 - 19:00 = 12H», «7:00 - 19:00», «07H30-17H30 (10 H)» → ['07:00','19:00'].
     */
    private function rango(string $texto): ?array
    {
        if (preg_match_all('/(\d{1,2})\s*[:Hh]\s*(\d{2})/', $texto, $m, PREG_SET_ORDER) && count($m) >= 2) {
            return [
                sprintf('%02d:%02d', (int) $m[0][1], (int) $m[0][2]),
                sprintf('%02d:%02d', (int) $m[1][1], (int) $m[1][2]),
            ];
        }

        return null;
    }

    /**
     * Indice de usuarios por conjunto de palabras del nombre, mas sus locales y
     * los puestos de cada local.
     */
    private function indiceDeUsuarios(): array
    {
        $porClave = [];

        foreach (DB::table('users')->where('usu_state', 1)->get() as $u) {
            $armado = trim(implode(' ', array_filter([$u->usu_ape1, $u->usu_ape2, $u->usu_nmb1, $u->usu_nmb2])));

            foreach ([$armado, (string) $u->usu_nmbcom] as $variante) {
                $clave = $this->clave($variante);
                if ($clave === '') continue;
                $porClave[$clave][] = $u;
            }
        }

        $locales = [];
        foreach (DB::table('user_has_institucion')->where('ui_state', 1)->get() as $v) {
            $locales[$v->ui_usu_id][] = (int) $v->ui_ins_code;
        }

        $puestos = [];
        foreach (DB::table('puesto')->where('pu_estado', true)->get() as $p) {
            $puestos[(int) $p->pu_ins_code] ??= (int) $p->pu_id;
        }

        return ['por_clave' => $porClave, 'locales' => $locales, 'puestos' => $puestos];
    }

    /** Las palabras del nombre, normalizadas y ordenadas: el orden no importa. */
    private function clave(string $texto): string
    {
        $t = mb_strtoupper(trim($texto));
        $t = strtr($t, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N','Ü'=>'U']);
        $t = preg_replace('/[^A-Z ]/', ' ', $t);
        $palabras = array_values(array_filter(explode(' ', $t)));
        sort($palabras);

        return implode(' ', $palabras);
    }

    /** Devuelve el usuario si el cruce es inequivoco; si no, null. */
    private function cruzar(string $nombres, string $apellidos, array $usuarios)
    {
        $clave = $this->clave($apellidos . ' ' . $nombres);
        $candidatos = $usuarios['por_clave'][$clave] ?? [];

        // Un solo candidato con el nombre completo igual: es el.
        if (count($candidatos) === 1) {
            return $candidatos[0];
        }

        /*
         * Varios candidatos con el mismo nombre exacto: no se elige ninguno.
         * Asignarle el turno a la persona equivocada es peor que dejarlo sin
         * cargar, porque despues aparece como falta de alguien que si fue.
         */
        return null;
    }

    /**
     * Lee el CSV `proyecto,ins_code`.
     *
     * @return array<string,int> clave normalizada del proyecto => ins_code
     */
    private function leerMapa(?string $ruta): array
    {
        if (!$ruta || !is_readable($ruta)) {
            return [];
        }

        $f = fopen($ruta, 'r');
        $cab = fgetcsv($f);
        if ($cab === false) { fclose($f); return []; }

        $iP = array_search('proyecto', $cab, true);
        $iC = array_search('ins_code', $cab, true);
        $iU = array_search('puesto', $cab, true);

        if ($iP === false || $iC === false) {
            fclose($f);
            $this->warn('El mapa necesita las columnas proyecto e ins_code.');
            return [];
        }

        $mapa = [];
        while (($fila = fgetcsv($f)) !== false) {
            if (!isset($fila[$iP], $fila[$iC])) {
                continue;
            }

            $code = trim((string) $fila[$iC]);

            // «DESCARTAR» marca lo que el cliente dijo que no existe como local.
            if ($code === '' || !ctype_digit($code)) {
                continue;
            }

            $puesto = $iU !== false ? trim((string) ($fila[$iU] ?? '')) : '';

            $mapa[$this->clave($fila[$iP] . ' ' . $puesto)] = (int) $code;

            // Un proyecto con un solo puesto tambien se busca por su nombre solo.
            $mapa[$this->clave($fila[$iP])] ??= (int) $code;
        }
        fclose($f);

        $this->info(sprintf('Mapa de proyectos: %d entradas', count($mapa)));

        return $mapa;
    }

    private function volcarCsv(string $ruta, array $sinCruce, array $sinLocal): void
    {
        $f = fopen($ruta, 'w');
        fputcsv($f, ['motivo', 'zona', 'persona', 'detalle']);

        foreach ($sinCruce as [$zona, $persona]) {
            fputcsv($f, ['sin cruce de nombre', $zona, $persona, '']);
        }
        foreach ($sinLocal as [$zona, $persona, $proyecto]) {
            fputcsv($f, ['sin local para su proyecto', $zona, $persona, $proyecto]);
        }

        fclose($f);
        $this->info("Detalle volcado en {$ruta}");
    }
}
