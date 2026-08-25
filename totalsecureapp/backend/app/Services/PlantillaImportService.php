<?php

namespace App\Services;

use Modules\Administracion\Models\Plantilla;
use Modules\Administracion\Models\PlantillaAsignacion;
use Modules\Administracion\Models\PlantillaFranja;
use Modules\Administracion\Models\Puesto;
use Modules\Administracion\Models\UserHasInstitucion;
use Modules\MobileApp\Models\users;
use Illuminate\Support\Facades\DB;

/**
 * Carga un cuadrante desde un CSV.
 *
 * Importa FRANJAS y ASIGNACIONES, no turnos: los turnos se generan despues con
 * PlantillaTurnoService, que es quien revisa solapes y descansos sobre el
 * periodo concreto. Asi la carga masiva pasa por las mismas validaciones que la
 * carga manual.
 *
 * El local no va en el archivo: lo define la plantilla. Pedirlo en cada fila
 * solo abriria la puerta a que no coincida.
 */
class PlantillaImportService
{
    /** Columnas esperadas, en cualquier orden. */
    public const COLUMNAS = ['cedula', 'puesto', 'dia', 'hora_inicio', 'hora_fin'];

    /**
     * Dias aceptados. Se es flexible al leer y estricto al validar: la gente
     * escribe "lun", "Lunes", "LUN" o directamente el numero.
     */
    private const DIAS = [
        'lun' => 1, 'lunes' => 1, '1' => 1,
        'mar' => 2, 'martes' => 2, '2' => 2,
        'mie' => 3, 'mier' => 3, 'miercoles' => 3, '3' => 3,
        'jue' => 4, 'jueves' => 4, '4' => 4,
        'vie' => 5, 'viernes' => 5, '5' => 5,
        'sab' => 6, 'sabado' => 6, '6' => 6,
        'dom' => 7, 'domingo' => 7, '7' => 7,
    ];

    /**
     * Lee y valida el archivo SIN escribir nada.
     *
     * @return array{filas: array, errores: string[], avisos: string[]}
     */
    public function analizar(Plantilla $plantilla, string $rutaArchivo): array
    {
        $errores = [];
        $avisos  = [];
        $filas   = [];

        $contenido = @file_get_contents($rutaArchivo);
        if ($contenido === false || trim($contenido) === '') {
            return ['filas' => [], 'errores' => ['El archivo está vacío o no se pudo leer.'], 'avisos' => []];
        }

        $lineas = $this->aLineas($contenido);
        if (count($lineas) < 2) {
            return ['filas' => [], 'errores' => ['El archivo no tiene filas de datos.'], 'avisos' => []];
        }

        $separador = $this->detectarSeparador($lineas[0]);
        $encabezado = $this->normalizarEncabezado(str_getcsv($lineas[0], $separador));

        foreach (self::COLUMNAS as $columna) {
            if (!in_array($columna, $encabezado, true)) {
                $errores[] = sprintf('Falta la columna "%s" en el encabezado.', $columna);
            }
        }
        if ($errores) {
            return compact('filas', 'errores', 'avisos');
        }

        $catalogo = $this->catalogo($plantilla);
        $vistas = [];

        foreach (array_slice($lineas, 1) as $i => $linea) {
            $numero = $i + 2; // +1 por el encabezado, +1 porque las filas empiezan en 1
            if (trim($linea) === '') {
                continue;
            }

            $valores = str_getcsv($linea, $separador);
            $fila = $this->asociar($encabezado, $valores);

            $resultado = $this->validarFila($fila, $numero, $catalogo);

            if ($resultado['error'] !== null) {
                $errores[] = $resultado['error'];
                continue;
            }

            // La misma franja para el mismo guardia dos veces no es un error,
            // pero conviene decirlo: suele ser un copy-paste del Excel.
            $clave = implode('|', [
                $resultado['puesto_id'], $resultado['dia'],
                $resultado['hora_inicio'], $resultado['usuario_id'],
            ]);
            if (isset($vistas[$clave])) {
                $avisos[] = sprintf('Fila %d: repetida (ya estaba en la fila %d).', $numero, $vistas[$clave]);
                continue;
            }
            $vistas[$clave] = $numero;

            $filas[] = $resultado;
        }

        if (empty($filas) && empty($errores)) {
            $errores[] = 'El archivo no contiene ninguna fila válida.';
        }

        return compact('filas', 'errores', 'avisos');
    }

    /**
     * Analiza y, si no hay errores, escribe las franjas y asignaciones.
     *
     * Reemplaza el contenido de la plantilla: el archivo es la fuente de verdad
     * de ese cuadrante. Los turnos ya generados no se tocan aqui; se regeneran
     * despues, y esa regeneracion respeta los marcajes.
     *
     * @return array{filas: array, errores: string[], avisos: string[], franjas: int, asignaciones: int}
     */
    public function importar(Plantilla $plantilla, string $rutaArchivo): array
    {
        $analisis = $this->analizar($plantilla, $rutaArchivo);

        if (!empty($analisis['errores'])) {
            return $analisis + ['franjas' => 0, 'asignaciones' => 0];
        }

        return DB::transaction(function () use ($plantilla, $analisis) {
            $plantilla->franjas()->delete(); // las asignaciones caen en cascada

            $franjasPorClave = [];
            $asignaciones = 0;

            foreach ($analisis['filas'] as $fila) {
                $clave = $fila['puesto_id'] . '|' . $fila['dia'] . '|' . $fila['hora_inicio'];

                if (!isset($franjasPorClave[$clave])) {
                    $franjasPorClave[$clave] = PlantillaFranja::create([
                        'pf_pl_id'       => $plantilla->pl_id,
                        'pf_puesto_id'   => $fila['puesto_id'],
                        'pf_dia_semana'  => $fila['dia'],
                        'pf_hora_inicio' => $fila['hora_inicio'],
                        'pf_hora_fin'    => $fila['hora_fin'],
                    ]);
                }

                PlantillaAsignacion::create([
                    'pa_pf_id'  => $franjasPorClave[$clave]->pf_id,
                    'pa_usu_id' => $fila['usuario_id'],
                ]);
                $asignaciones++;
            }

            return $analisis + [
                'franjas'      => count($franjasPorClave),
                'asignaciones' => $asignaciones,
            ];
        });
    }

    /**
     * CSV de ejemplo con los puestos del local ya escritos.
     *
     * Que el lider no tenga que tipear los nombres evita la mitad de los errores
     * de importacion.
     */
    public function plantillaDeEjemplo(Plantilla $plantilla): string
    {
        $lineas = [implode(';', self::COLUMNAS)];

        $puestos = Puesto::where('pu_ins_code', $plantilla->pl_ins_code)
            ->where('pu_estado', true)
            ->orderBy('pu_nombre')
            ->pluck('pu_nombre');

        if ($puestos->isEmpty()) {
            $lineas[] = '# Este local no tiene puestos definidos todavía';
            return implode("\n", $lineas) . "\n";
        }

        foreach ($puestos as $puesto) {
            foreach (['LUN', 'MAR', 'MIE', 'JUE', 'VIE'] as $dia) {
                $lineas[] = implode(';', ['CEDULA_DEL_GUARDIA', $puesto, $dia, '06:00', '14:00']);
            }
        }

        return implode("\n", $lineas) . "\n";
    }

    // ── Interno ──

    /**
     * Normaliza el contenido a lineas UTF-8.
     *
     * Excel en Windows exporta en Windows-1252 y con BOM, y separa las lineas
     * con CRLF (o CR solo, si viene de un Mac viejo). Sin esto, un archivo
     * perfectamente valido falla con "puesto no encontrado" porque el nombre
     * trae los acentos rotos.
     *
     * @return string[]
     */
    private function aLineas(string $contenido): array
    {
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido); // BOM UTF-8

        if (!mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        $contenido = str_replace(["\r\n", "\r"], "\n", $contenido);

        return explode("\n", $contenido);
    }

    /**
     * Excel en configuracion regional española separa con punto y coma, no con
     * coma. Se decide por cual aparece mas en el encabezado.
     */
    private function detectarSeparador(string $encabezado): string
    {
        return substr_count($encabezado, ';') > substr_count($encabezado, ',') ? ';' : ',';
    }

    /** @return string[] */
    private function normalizarEncabezado(array $columnas): array
    {
        return array_map(function ($c) {
            $c = strtolower(trim((string) $c));
            $c = strtr($c, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);

            return preg_replace('/[^a-z_]/', '', str_replace(' ', '_', $c));
        }, $columnas);
    }

    private function asociar(array $encabezado, array $valores): array
    {
        $fila = [];
        foreach ($encabezado as $i => $columna) {
            $fila[$columna] = isset($valores[$i]) ? trim((string) $valores[$i]) : '';
        }

        return $fila;
    }

    /** Puestos y guardias validos de este local, resueltos una sola vez. */
    private function catalogo(Plantilla $plantilla): array
    {
        $puestos = Puesto::where('pu_ins_code', $plantilla->pl_ins_code)
            ->where('pu_estado', true)
            ->get()
            ->mapWithKeys(fn ($p) => [$this->clave($p->pu_nombre) => $p->pu_id]);

        $vinculados = UserHasInstitucion::where('ui_ins_code', $plantilla->pl_ins_code)
            ->where('ui_state', 1)
            ->pluck('ui_usu_id')
            ->flip();

        $guardias = users::where('usu_state', 1)
            ->get()
            ->mapWithKeys(fn ($u) => [trim($u->usu_cedula) => $u]);

        return compact('puestos', 'vinculados', 'guardias');
    }

    /** Para comparar nombres sin que un acento o una mayuscula rompan la carga. */
    private function clave(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return preg_replace('/\s+/', ' ', $texto);
    }

    /**
     * @return array{error: ?string, usuario_id?: int, puesto_id?: int, dia?: int, hora_inicio?: string, hora_fin?: string}
     */
    private function validarFila(array $fila, int $numero, array $catalogo): array
    {
        $fallo = fn (string $motivo) => ['error' => sprintf('Fila %d: %s', $numero, $motivo)];

        $cedula = $fila['cedula'] ?? '';
        if ($cedula === '') {
            return $fallo('falta la cédula.');
        }

        $guardia = $catalogo['guardias'][$cedula] ?? null;
        if (!$guardia) {
            return $fallo(sprintf('no existe un usuario activo con la cédula %s.', $cedula));
        }

        // Sin el vinculo no podria marcar: la app rechazaria su marcaje.
        if (!$catalogo['vinculados']->has($guardia->id)) {
            return $fallo(sprintf('%s no está vinculado a este local.', $guardia->usu_nmbcom));
        }

        $puestoId = $catalogo['puestos'][$this->clave($fila['puesto'] ?? '')] ?? null;
        if (!$puestoId) {
            return $fallo(sprintf('el puesto "%s" no existe en este local.', $fila['puesto'] ?? ''));
        }

        $dia = self::DIAS[$this->clave($fila['dia'] ?? '')] ?? null;
        if (!$dia) {
            return $fallo(sprintf('día "%s" no reconocido (use LUN..DOM o 1..7).', $fila['dia'] ?? ''));
        }

        $inicio = $this->hora($fila['hora_inicio'] ?? '');
        $fin    = $this->hora($fila['hora_fin'] ?? '');

        if ($inicio === null) {
            return $fallo(sprintf('hora de entrada inválida: "%s".', $fila['hora_inicio'] ?? ''));
        }
        if ($fin === null) {
            return $fallo(sprintf('hora de salida inválida: "%s".', $fila['hora_fin'] ?? ''));
        }
        if ($inicio === $fin) {
            return $fallo('la entrada y la salida son la misma hora.');
        }

        return [
            'error'       => null,
            'usuario_id'  => $guardia->id,
            'puesto_id'   => $puestoId,
            'dia'         => $dia,
            'hora_inicio' => $inicio,
            'hora_fin'    => $fin,
            'guardia'     => $guardia->usu_nmbcom,
        ];
    }

    /** Acepta 6:00, 06:00 y 06:00:00. Excel a veces guarda la hora sin el cero. */
    private function hora(string $valor): ?string
    {
        $valor = trim($valor);
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $valor, $m)) {
            return null;
        }

        $h = (int) $m[1];
        $i = (int) $m[2];
        if ($h > 23 || $i > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $h, $i);
    }
}
