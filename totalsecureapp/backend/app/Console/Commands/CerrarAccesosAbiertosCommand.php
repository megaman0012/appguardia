<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Acceso;
use Modules\Administracion\Models\AccesoHistorial;

/**
 * Deja «Personas dentro» diciendo la verdad.
 *
 * **El sintoma.** La pantalla marcaba **9.769 personas adentro** sobre 9.776
 * accesos que existen: solo 7 cerrados en toda la historia. Nadie creyo nunca
 * esa cifra, y por eso la pantalla dejo de usarse.
 *
 * **La causa, y son dos problemas distintos mezclados.**
 *
 * La migracion `2026_08_21_100002_acceso_generalizado_migrate_data` creo
 * `ac_estado_acceso` con `default('en_curso')` y relleno bien lo que habia en
 * ese momento:
 *
 *     SET ac_estado_acceso = CASE WHEN ac_is_entrada = 1 THEN 'en_curso' ELSE 'completada' END
 *
 * Pero el ETL de la v1 importo los 9.769 accesos **tres semanas despues**, el
 * 2026-09-07, y la v1 no tiene esa columna: cada fila importada tomo el **valor
 * por defecto**, `en_curso`, sin mirar `ac_is_entrada`. El ETL nunca aplico la
 * regla que la migracion ya tenia escrita. (Corregido tambien en
 * `EtlV1::accesos()`, para que una reimportacion no lo repita.)
 *
 * Asi que de las 9.769 supuestas personas adentro:
 *
 * | | |
 * |---|---|
 * | Ya tienen salida registrada (`ac_is_entrada = 0`) | **6.436** — solo les falta el estado |
 * | Abiertas de verdad, sin fecha de salida | **3.333** |
 * | De esas, de los ultimos 7 dias | **5** |
 *
 * **Las dos se tratan distinto, a proposito:**
 *
 * - **Grupo A.** Ya salieron y el dato esta en la base. Se aplica la regla de la
 *   propia migracion y nada mas. **No se escribe historial**: esa salida ocurrio
 *   de verdad en su momento y anotar hoy una marca de salida seria inventar un
 *   evento que no paso.
 *
 * - **Grupo B.** Nunca se les registro salida. Se cierran, pero **sin fabricar
 *   una hora**: poner `now()` dejaria un acceso de abril de 2025 con diecisiete
 *   meses adentro, y ese dato despues se lee como real en los reportes. La fecha
 *   de salida queda igual a la de ingreso --que se lee como «duracion
 *   desconocida»-- y la razon queda escrita en `ac_observaciones` y en el
 *   historial, con la fecha de HOY, que es cuando de verdad se tomo la decision.
 *
 * Idempotente: los dos filtros solo alcanzan filas en `en_curso`, asi que
 * volver a correrlo no toca nada. Simula por defecto.
 */
class CerrarAccesosAbiertosCommand extends Command
{
    protected $signature = 'accesos:cerrar-abiertos
                            {--solo-estado : Solo el grupo A (corregir el estado mal importado)}
                            {--dias-gracia=0 : Deja abiertos los accesos de los ultimos N dias}
                            {--ejecutar : Sin esto solo muestra lo que haria}';

    protected $description = 'Corrige el estado de los accesos mal importados y cierra los que quedaron abiertos';

    /** Lo que se escribe en el acceso y en su historial. */
    private const NOTA = 'Cierre administrativo %s: el acceso quedo sin salida registrada. '
        . 'La fecha de salida se iguala a la de ingreso porque la hora real se desconoce.';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $gracia   = max(0, (int) $this->option('dias-gracia'));
        $hoy      = Carbon::now();

        if (! $ejecutar) {
            $this->warn('MODO SIMULACION. Nada se escribe. Agregue --ejecutar para aplicarlo.');
            $this->newLine();
        }

        // --- Grupo A: la regla que el ETL no aplico ---------------------------
        $grupoA = Acceso::query()
            ->where('ac_estado_acceso', Acceso::ESTADO_EN_CURSO)
            ->where('ac_is_entrada', 0);

        $nA = (clone $grupoA)->count();

        $this->line("Grupo A — ya tienen salida, les falta el estado: <fg=yellow>{$nA}</>");

        if ($ejecutar && $nA > 0) {
            $afectadas = (clone $grupoA)->update([
                'ac_estado_acceso' => Acceso::ESTADO_COMPLETADA,
                'ac_updated_at'    => $hoy,
            ]);
            $this->info("   corregidas: {$afectadas}");
        }

        if ($this->option('solo-estado')) {
            $this->newLine();
            $this->comment('--solo-estado: el grupo B no se toca.');

            return self::SUCCESS;
        }

        // --- Grupo B: cierre administrativo -----------------------------------
        $grupoB = Acceso::query()
            ->where('ac_estado_acceso', Acceso::ESTADO_EN_CURSO)
            ->whereNull('ac_is_salida_fecha');

        if ($gracia > 0) {
            // Los recientes pueden ser gente realmente adentro: esos los resuelve
            // la operacion desde la pantalla, uno por uno.
            $grupoB->where(function ($q) use ($hoy, $gracia) {
                $q->whereNull('ac_created_at')
                  ->orWhere('ac_created_at', '<', $hoy->copy()->subDays($gracia));
            });
        }

        $nB       = (clone $grupoB)->count();
        $sinFecha = (clone $grupoB)->whereNull('ac_created_at')->count();

        $this->newLine();
        $this->line("Grupo B — abiertos de verdad, cierre administrativo: <fg=yellow>{$nB}</>");

        if ($sinFecha > 0) {
            $this->line("   de esos, sin fecha de ingreso: <fg=yellow>{$sinFecha}</> "
                . '(quedan sin fecha de salida: no hay de donde sacarla)');
        }

        if ($gracia > 0) {
            $quedan = Acceso::query()
                ->where('ac_estado_acceso', Acceso::ESTADO_EN_CURSO)
                ->whereNull('ac_is_salida_fecha')
                ->whereNotNull('ac_created_at')
                ->where('ac_created_at', '>=', $hoy->copy()->subDays($gracia))
                ->count();

            $this->line("   se dejan abiertos por --dias-gracia={$gracia}: <fg=green>{$quedan}</>");
        }

        if (! $ejecutar) {
            $this->newLine();
            $this->comment('Nada se escribio.');

            return self::SUCCESS;
        }

        if ($nB === 0) {
            return self::SUCCESS;
        }

        $nota    = sprintf(self::NOTA, $hoy->toDateString());
        $cerrados = 0;

        // Por lotes: son miles de filas y cada una lleva su fila de historial.
        (clone $grupoB)->orderBy('ac_code')->chunkById(500, function ($accesos) use ($nota, $hoy, &$cerrados) {
            DB::transaction(function () use ($accesos, $nota, $hoy, &$cerrados) {
                foreach ($accesos as $acc) {
                    $acc->ac_is_entrada = 0;

                    /*
                     * La de ingreso, NO la de ahora. Ver la cabecera.
                     *
                     * ⚠️ **`getRawOriginal` y no `$acc->ac_created_at`.** El
                     * modelo tiene un accessor sobre esa columna que hace dos
                     * cosas que aqui estorban:
                     *
                     *   - devuelve **cadena vacia** cuando el valor es nulo, y
                     *     Postgres corta la corrida entera con «invalid input
                     *     syntax for type timestamp» (131 accesos de produccion
                     *     no tienen fecha de ingreso);
                     *   - **convierte a America/Guayaquil** para mostrar. Copiar
                     *     ese valor de vuelta a la base --que guarda sin zona--
                     *     correria la hora de las 3.202 filas restantes.
                     *
                     * El crudo es lo que hay que copiar: se esta moviendo un dato
                     * de una columna a otra, no mostrandoselo a nadie.
                     */
                    $ingreso = $acc->getRawOriginal('ac_created_at');

                    if ($ingreso !== null && $ingreso !== '') {
                        $acc->ac_is_salida_fecha = $ingreso;
                    }
                    $acc->ac_estado_acceso   = Acceso::ESTADO_COMPLETADA;
                    $acc->ac_observaciones   = trim(($acc->ac_observaciones ?? '') . "\n" . $nota);
                    $acc->ac_updated_at      = $hoy;
                    $acc->save();

                    // Sin coordenadas: nadie estuvo en la puerta para esto.
                    AccesoHistorial::registrar(
                        $acc->ac_code,
                        AccesoHistorial::MARCA_SALIDA,
                        null,
                        null,
                        $nota,
                    );

                    $cerrados++;
                }
            });
        }, 'ac_code');

        $this->info("   cerrados: {$cerrados}");

        $this->newLine();
        $this->line('Quedan adentro: <fg=green>'
            . Acceso::where('ac_estado_acceso', Acceso::ESTADO_EN_CURSO)->count() . '</>');

        return self::SUCCESS;
    }
}
