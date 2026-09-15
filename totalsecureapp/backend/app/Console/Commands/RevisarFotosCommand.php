<?php

namespace App\Console\Commands;

use App\Support\FotoDeEvidencia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cuenta las fotos de evidencia que no estan donde el registro dice, y las
 * reengancha.
 *
 * ⚠️ **El desperfecto que busca.** `storeFiles()` armaba la carpeta con la hora
 * del servidor y los modelos la reconstruian con la fecha del hecho. Para lo que
 * se crea y sincroniza el mismo dia coinciden; **lo que la tablet envia con
 * retraso queda con la foto en una carpeta y el registro apuntando a otra**. La
 * foto sigue en el disco y no se ve en ninguna parte.
 *
 * Con `--reparar` se escribe en la columna la ruta relativa donde la foto esta
 * de verdad, que es la forma en que se guarda desde ahora. Es una correccion de
 * datos: se ejecuta con `--reparar` solo despues de mirar el conteo.
 */
class RevisarFotosCommand extends Command
{
    protected $signature = 'fotos:revisar
                            {--reparar : Escribe la ruta corregida en los registros que se pudieron reenganchar}';

    protected $description = 'Busca fotos de evidencia que el registro no encuentra, y opcionalmente las repara';

    /** tabla => [pk, columna de la foto, modulo en disco, columna de fecha] */
    private const MODULOS = [
        'novedad'            => ['nv_id',   'nv_foto',        'novedad',   'nv_fecha_hora'],
        'ronda_detalle'      => ['rd_id',   'rd_foto',        'rondas',    'rd_fecha_hora'],
        'bitacora'           => ['bt_id',   'bt_foto',        'bitacora',  'bt_fecha_hora'],
        'acceso'             => ['ac_code', 'ac_foto',        'accesos',   'ac_created_at'],
        'user_has_biometria' => ['bio_code',  'bio_image_name', 'biometria', 'bio_created_at'],
    ];

    public function handle(): int
    {
        $reparar = (bool) $this->option('reparar');
        $totalPerdidas = 0;
        $totalReenganchadas = 0;

        foreach (self::MODULOS as $tabla => [$pk, $campo, $modulo, $colFecha]) {
            if (!\Illuminate\Support\Facades\Schema::hasTable($tabla)) {
                $this->warn("Tabla {$tabla}: no existe, se omite.");
                continue;
            }

            $enSuSitio = 0; $reenganchadas = 0; $perdidas = 0; $conFoto = 0;
            $correcciones = [];

            DB::table($tabla)
                ->whereNotNull($campo)
                ->where($campo, '!=', '')
                ->select([$pk, $campo, $colFecha])
                ->orderBy($pk)
                ->chunk(500, function ($filas) use (
                    $pk, $campo, $modulo, $colFecha,
                    &$enSuSitio, &$reenganchadas, &$perdidas, &$conFoto, &$correcciones
                ) {
                    foreach ($filas as $fila) {
                        $conFoto++;
                        $guardado = (string) $fila->$campo;
                        $fecha = $fila->$colFecha;

                        // Donde dice el registro que esta.
                        if (str_contains($guardado, '/')) {
                            $declarada = $guardado;
                        } else {
                            $declarada = $fecha
                                ? $modulo . '/' . \Carbon\Carbon::parse($fecha)->format('Y/m/d') . '/' . $guardado
                                : null;
                        }

                        if ($declarada && file_exists(public_path('images/' . $declarada))) {
                            $enSuSitio++;
                            continue;
                        }

                        // Donde esta de verdad, si esta.
                        $real = FotoDeEvidencia::rutaRelativa($guardado, $modulo, $fecha);

                        if ($real !== null) {
                            $reenganchadas++;
                            $correcciones[] = [$fila->$pk, $real];
                        } else {
                            $perdidas++;
                        }
                    }
                });

            $this->line(sprintf(
                '%-20s con foto: %6d   en su sitio: %6d   %srecuperables: %5d%s   sin rastro: %5d',
                $tabla, $conFoto, $enSuSitio,
                $reenganchadas > 0 ? "\e[33m" : '', $reenganchadas, $reenganchadas > 0 ? "\e[0m" : '',
                $perdidas
            ));

            $totalPerdidas += $perdidas;
            $totalReenganchadas += $reenganchadas;

            if ($reparar && $correcciones !== []) {
                DB::transaction(function () use ($tabla, $pk, $campo, $correcciones) {
                    foreach ($correcciones as [$id, $ruta]) {
                        DB::table($tabla)->where($pk, $id)->update([$campo => $ruta]);
                    }
                });
                $this->info(sprintf('   → %d registros corregidos en %s', count($correcciones), $tabla));
            }
        }

        $this->newLine();
        $this->line(sprintf('Recuperables: %d   Sin rastro en disco: %d', $totalReenganchadas, $totalPerdidas));

        if (!$reparar && $totalReenganchadas > 0) {
            $this->warn('Nada se modifico. Use --reparar para reenganchar las recuperables.');
        }

        /*
         * «Sin rastro» no siempre es una foto perdida: puede ser un registro que
         * nunca llego a subirla, o una limpieza de disco anterior. Por eso el
         * comando no lo trata como error ni intenta nada con esos.
         */
        return self::SUCCESS;
    }
}
