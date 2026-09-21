<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\TurnoVacante;

/**
 * Turnos y cobertura de demostracion para un guardia concreto.
 *
 * **Por que hizo falta.** En la app del guardia no aparecia nada en «Mi horario»
 * ni en «Turnos disponibles», y no era un fallo: eran los datos.
 *
 *  1. **El guardia no tenia ni un turno.** La carga del 2026-09-17 alcanzo a 25
 *     guardias de los 269 de la malla --147 no cruzaban por nombre-- y este no
 *     estaba entre ellos.
 *  2. **Las vacantes no le podian salir por dos motivos a la vez:** las 9
 *     vigentes estaban en estado `detectada` y `scopeAbiertas()` solo trae las
 *     `abierta`; y ademas estaban en locales a los que **no esta vinculado**,
 *     que es lo que exige `VacanteService::motivoParaNoCubrir()`.
 *
 * ⚠️ **Esto escribe en produccion.** Todo lo que crea queda marcado con
 * «[DEMO]» en las observaciones y se puede quitar con `--limpiar`. No toca nada
 * que no haya creado el propio comando.
 *
 * Elige el local solo: el primero **activo, vinculado al guardia y con puestos**.
 * Sin las tres cosas la app no muestra el turno, y elegirlo a mano es el error
 * que llevo a que no apareciera nada.
 *
 * Simula por defecto.
 */
class DatosDeDemostracionCommand extends Command
{
    protected $signature = 'demo:turnos-y-cobertura
                            {--cedula= : Cédula del guardia}
                            {--local= : Código del local; sin esto, el que más puestos tenga}
                            {--dias=7 : Días hacia adelante}
                            {--atras=5 : Días hacia atrás, para que haya historial}
                            {--limpiar : Borra lo que creó este comando}
                            {--ejecutar : Sin esto solo muestra lo que haria}';

    protected $description = 'Carga turnos y vacantes de demostración para un guardia';

    private const MARCA = '[DEMO]';

    /** Para que `vacantesDemo()` pueda preguntarle al servicio por este guardia. */
    private int $guardiaId = 0;

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $cedula   = (string) $this->option('cedula');

        if ($cedula === '') {
            $this->error('Falta --cedula.');

            return self::FAILURE;
        }

        $guardia = DB::table('users')->where('usu_cedula', $cedula)->first();

        if (! $guardia) {
            $this->error("No existe el usuario {$cedula}.");

            return self::FAILURE;
        }

        $this->guardiaId = (int) $guardia->id;
        $this->line("Guardia: <fg=yellow>{$guardia->usu_nmbcom}</> (id {$guardia->id})");

        if ($this->option('limpiar')) {
            return $this->limpiar($ejecutar);
        }

        if (! $ejecutar) {
            $this->warn('MODO SIMULACION. Nada se escribe. Agregue --ejecutar para aplicarlo.');
        }

        // ── El local: activo, vinculado y con puestos ───────────────────────
        $candidatos = DB::table('user_has_institucion as ui')
            ->join('organizacion_institucion as i', 'i.ins_code', '=', 'ui.ui_ins_code')
            ->join('puesto as p', 'p.pu_ins_code', '=', 'i.ins_code')
            ->where('ui.ui_usu_id', $guardia->id)
            ->where('ui.ui_state', 1)
            ->where('i.ins_estado', true)
            ->where('p.pu_estado', true)
            ->select('i.ins_code', 'i.ins_descripcion', DB::raw('count(p.pu_id) puestos'))
            ->groupBy('i.ins_code', 'i.ins_descripcion')
            // El de mas puestos primero: en una demostracion un sitio con varias
            // posiciones se ve mucho mejor que una oficina con una sola.
            ->orderByDesc('puestos')
            ->orderBy('i.ins_code');

        if ($this->option('local') !== null && $this->option('local') !== '') {
            $candidatos->where('i.ins_code', (int) $this->option('local'));
        }

        $local = $candidatos->first();

        if (! $local) {
            $this->error('El guardia no tiene ningún local activo, vinculado y con puestos.');
            $this->line('  Sin las tres cosas la app no puede mostrarle turnos.');

            return self::FAILURE;
        }

        $puestos = DB::table('puesto')->where('pu_ins_code', $local->ins_code)
            ->where('pu_estado', true)->orderBy('pu_id')->get(['pu_id', 'pu_nombre']);

        $this->line("Local:   <fg=yellow>{$local->ins_descripcion}</> ({$puestos->count()} puestos)");
        $this->newLine();

        $turnos = $this->planificar($guardia, $local, $puestos->first());

        $this->line('Turnos a crear: <fg=yellow>' . count($turnos) . '</>');

        foreach (array_slice($turnos, 0, 4) as $t) {
            $this->line(sprintf('   %s  %s-%s  %s', $t['tu_fecha'],
                substr($t['tu_hora_inicio_prevista'], 0, 5), substr($t['tu_hora_fin_prevista'], 0, 5),
                $t['tu_estado']));
        }

        $this->line('   …');

        /*
         * ⚠️ **Los turnos se insertan ANTES de calcular las vacantes**, y en una
         * transaccion que se deshace si es simulacion.
         *
         * `vacantesDemo()` le pregunta a `VacanteService` si el guardia puede
         * cubrir cada candidata, y ese servicio mira la jornada **que hay en la
         * base**. Calculando las vacantes primero, el servicio no veia los
         * turnos que estaban a punto de crearse: las daba todas por buenas y
         * despues, ya insertadas, chocaban con la jornada y la pantalla volvia a
         * salir vacia. Es el mismo error de fondo que veniamos a arreglar.
         */
        DB::beginTransaction();

        try {
            foreach ($turnos as $t) {
                // Idempotente: un guardia no tiene dos turnos iguales el mismo dia.
                $ya = DB::table('turno')
                    ->where('tu_usu_id', $t['tu_usu_id'])
                    ->where('tu_fecha', $t['tu_fecha'])
                    ->where('tu_hora_inicio_prevista', $t['tu_hora_inicio_prevista'])
                    ->exists();

                if (! $ya) {
                    DB::table('turno')->insert($t);
                }
            }

            $vacantes = $this->vacantesDemo($local, $puestos);

            $this->line('Vacantes a abrir: <fg=yellow>' . count($vacantes) . '</>');

            foreach ($vacantes as $v) {
                $this->line(sprintf('   %s  %s-%s  %s', $v['tv_fecha'],
                    substr($v['tv_hora_inicio'], 0, 5), substr($v['tv_hora_fin'], 0, 5), $v['tv_motivo']));

                $ya = DB::table('turno_vacante')
                    ->where('tv_ins_code', $v['tv_ins_code'])
                    ->where('tv_fecha', $v['tv_fecha'])
                    ->where('tv_hora_inicio', $v['tv_hora_inicio'])
                    ->exists();

                if (! $ya) {
                    DB::table('turno_vacante')->insert($v);
                }
            }

            $ejecutar ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        if (! $ejecutar) {
            $this->newLine();
            $this->comment('Nada se escribió.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Hecho.');
        $this->line('  turnos del guardia: ' . DB::table('turno')->where('tu_usu_id', $guardia->id)->count());
        $this->line('  vacantes abiertas:  ' . DB::table('turno_vacante')->where('tv_estado', TurnoVacante::ABIERTA)->count());

        return self::SUCCESS;
    }

    /**
     * Dos días de trabajo y uno libre, que es el patrón real de la malla.
     *
     * Los pasados quedan `completado` y los futuros `programado`: así la
     * pantalla de cumplimiento tiene historia que mostrar y no solo una lista
     * vacía hacia adelante.
     */
    private function planificar(object $guardia, object $local, object $puesto): array
    {
        $turnos = [];
        $hoy    = Carbon::today();

        for ($i = -((int) $this->option('atras')); $i <= (int) $this->option('dias'); $i++) {
            if ($this->esLibre($i)) {
                continue;
            }

            $dia     = $hoy->copy()->addDays($i);
            $nocturno = $i % 6 === 1;   // alterna alguna noche, como la malla real

            $turnos[] = [
                'tu_usu_id'               => $guardia->id,
                'tu_ins_code'             => $local->ins_code,
                'tu_puesto_id'            => $puesto->pu_id,
                'tu_fecha'                => $dia->toDateString(),
                'tu_hora_inicio_prevista' => $nocturno ? '19:00:00' : '07:00:00',
                'tu_hora_fin_prevista'    => $nocturno ? '07:00:00' : '19:00:00',
                'tu_estado'               => $i < 0 ? 'completado' : 'programado',
                'tu_state'                => true,
                'tu_observaciones'        => self::MARCA . ' turno de demostración',
                'tu_created_at'           => now(),
                'tu_updated_at'           => now(),
            ];
        }

        return $turnos;
    }

    /** Dos días de trabajo y uno libre. */
    private function esLibre(int $i): bool
    {
        return $i % 3 === 2;
    }

    /**
     * Vacantes que el guardia **de verdad puede cubrir**.
     *
     * ⚠️ **Son varias condiciones y se cumplen todas, o la pantalla sale vacía
     * sin decir por qué.** Es exactamente lo que pasaba antes:
     *
     *  1. Estado `abierta` — `scopeAbiertas()` no trae las `detectada`, que era
     *     el estado de las 9 que ya existían.
     *  2. En un local al que esté **vinculado**.
     *  3. Que no **solape** con su propia jornada.
     *  4. Y que le quede **descanso suficiente** entre turnos.
     *
     * Las dos últimas se me pasaron por separado: primero puse las vacantes en
     * días que ya trabajaba, y al moverlas a sus días libres seguían cayendo
     * pegadas a un turno nocturno --«descansaría solo 0h»--.
     *
     * Por eso esto **no reimplementa las reglas: se las pregunta a
     * `VacanteService`**, que es quien las decide de verdad. Se proponen
     * candidatas y se queda con las que el servicio acepta. Así el comando no se
     * desincroniza el día que cambie el descanso mínimo.
     */
    private function vacantesDemo(object $local, $puestos): array
    {
        $svc    = app(\App\Services\VacanteService::class);
        $hoy    = Carbon::today();
        $out    = [];
        $vistas = [];

        $plantilla = [['refuerzo', '07:00:00', '19:00:00'],
                      ['enfermedad', '19:00:00', '07:00:00'],
                      ['permiso', '07:00:00', '19:00:00']];

        // Se mira más allá de la ventana de turnos: ahí es donde hay hueco de
        // verdad con un patrón de dos días de trabajo y uno libre.
        for ($i = 1; $i <= (int) $this->option('dias') + 10 && count($out) < 3; $i++) {
            foreach ($plantilla as $n => [$motivo, $desde, $hasta]) {
                if (count($out) >= 3) {
                    break;
                }

                $fila = [
                    'tv_ins_code'      => $local->ins_code,
                    'tv_puesto_id'     => $puestos[count($out) % $puestos->count()]->pu_id,
                    'tv_fecha'         => $hoy->copy()->addDays($i)->toDateString(),
                    'tv_hora_inicio'   => $desde,
                    'tv_hora_fin'      => $hasta,
                    'tv_motivo'        => $motivo,
                    'tv_estado'        => TurnoVacante::ABIERTA,
                    'tv_alcance'       => TurnoVacante::ALCANCE_LOCAL,
                    'tv_abierta_en'    => now(),
                    'tv_observaciones' => self::MARCA . ' vacante de demostración',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];

                // Sin guardar: solo para que el servicio la juzgue.
                $candidata = new TurnoVacante($fila);
                $candidata->tv_id = 0;

                // Una sola por fecha y hora: la clave con la que se comprueba
                // que no se duplique al insertar es (local, fecha, inicio), asi
                // que dos candidatas iguales se pisan y solo entra una.
                $clave = $fila['tv_fecha'] . $fila['tv_hora_inicio'];

                if (isset($vistas[$clave])) {
                    continue;
                }

                if ($svc->motivoParaNoCubrir($candidata, (int) $this->guardiaId) === null) {
                    $vistas[$clave] = true;
                    $out[] = $fila;
                }
            }
        }

        if ($out === []) {
            $this->warn('  No se encontró ninguna fecha que el guardia pueda cubrir: '
                . 'su jornada no deja descanso suficiente en la ventana mirada.');
        }

        return $out;
    }

    private function limpiar(bool $ejecutar): int
    {
        $t = DB::table('turno')->where('tu_observaciones', 'like', self::MARCA . '%');
        $v = DB::table('turno_vacante')->where('tv_observaciones', 'like', self::MARCA . '%');

        $this->line('Turnos de demostración:   ' . (clone $t)->count());
        $this->line('Vacantes de demostración: ' . (clone $v)->count());

        if (! $ejecutar) {
            $this->comment('Agregue --ejecutar para borrarlos.');

            return self::SUCCESS;
        }

        // Las postulaciones cuelgan de la vacante: primero ellas.
        DB::table('turno_postulacion')
            ->whereIn('tp_tv_id', (clone $v)->pluck('tv_id'))->delete();

        (clone $v)->delete();
        (clone $t)->delete();

        $this->info('Borrados.');

        return self::SUCCESS;
    }
}
