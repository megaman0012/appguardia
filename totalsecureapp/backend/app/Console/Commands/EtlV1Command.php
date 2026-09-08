<?php

namespace App\Console\Commands;

use App\Services\Etl\EtlV1;
use Illuminate\Console\Command;
use Throwable;

/**
 * Migracion de datos de v1 a v2, por etapas.
 *
 *   php artisan etl:v1 --lista
 *   php artisan etl:v1 clientes
 *   php artisan etl:v1 --todo
 *
 * Las etapas van EN ORDEN: cada una depende de las claves ajenas que dejo la
 * anterior. Correr `locales` sin `clientes` deja los locales sin cliente.
 *
 * Cada etapa informa cuantas filas habia en el origen y cuantas quedaron en el
 * destino. **Que los dos numeros coincidan es la verificacion**; un "listo" sin
 * numeros no dice nada.
 */
class EtlV1Command extends Command
{
    protected $signature = 'etl:v1
        {etapa? : Etapa a correr}
        {--todo : Corre todas las etapas en orden}
        {--lista : Muestra las etapas y su estado}
        {--forzar : Permite escribir sobre tablas que ya tienen filas}';

    protected $description = 'Migra los datos de v1 (MariaDB) a v2 (PostgreSQL), por etapas';

    /**
     * Etapas en orden de dependencia. El orden NO es negociable: los locales
     * cuelgan de los clientes, los vinculos de los usuarios y los locales, y los
     * datos de operacion de todo lo anterior.
     */
    private const ETAPAS = [
        'clientes'   => 'Clientes (organizacion)',
        'locales'    => 'Locales, con su cliente y su ciudad resueltos',
        'usuarios'   => 'Usuarios',
        'gestiones'  => 'Gestiones (periodo de trabajo de cada usuario)',
        'roles'      => 'Roles de cada usuario, traducidos por nombre',
        'vinculos'   => 'Que locales ve cada usuario',
        'marcadores' => 'Puntos QR de cada local',
    ];

    public function handle(EtlV1 $etl): int
    {
        if ($this->option('lista')) {
            $this->mostrarEtapas();
            return self::SUCCESS;
        }

        $etapa = $this->argument('etapa');

        if (!$this->option('todo') && $etapa === null) {
            $this->error('Indicar una etapa, o --todo. Ver --lista.');
            return self::FAILURE;
        }

        if ($etapa !== null && !array_key_exists($etapa, self::ETAPAS)) {
            $this->error("Etapa desconocida: '{$etapa}'. Ver --lista.");
            return self::FAILURE;
        }

        $aCorrer = $etapa !== null ? [$etapa] : array_keys(self::ETAPAS);
        $etl = new EtlV1($this->option('forzar'));

        foreach ($aCorrer as $nombre) {
            $this->line('');
            $this->info("── {$nombre}: " . self::ETAPAS[$nombre]);

            try {
                $inicio = microtime(true);
                $r = $etl->{$nombre}();
                $seg = round(microtime(true) - $inicio, 1);
            } catch (Throwable $e) {
                // Recortado: una excepcion de PDO trae el INSERT completo con
                // sus 2.000 filas de parametros -- 145 KB de salida en la que no
                // se encuentra el error. La causa siempre esta al principio.
                $this->error('   FALLO: ' . mb_substr($e->getMessage(), 0, 400));
                // Se corta la cadena: seguir con la etapa siguiente cargaria
                // datos que cuelgan de lo que no entro.
                return self::FAILURE;
            }

            // Una etapa puede descartar filas A PROPOSITO (dos roles de v1 que
            // son uno en v2). En ese caso informa 'esperado', y es contra ese
            // numero que se compara: si no, la etapa se veria como un fallo.
            $esperado = $r['esperado'] ?? $r['origen'];
            $cuadra = $esperado === $r['destino'];

            $this->line(sprintf(
                '   origen %d  ->  destino %d%s   %s   (%ss)',
                $r['origen'],
                $r['destino'],
                $esperado !== $r['origen'] ? "  (esperado {$esperado})" : '',
                $cuadra ? 'CUADRA' : '*** NO CUADRA ***',
                $seg
            ));

            foreach ($r['avisos'] ?? [] as $aviso) {
                $this->warn("   aviso: {$aviso}");
            }
            foreach ($r['notas'] ?? [] as $nota) {
                $this->line("     - {$nota}");
            }

            if (!$cuadra) {
                $this->error('   Los conteos no cuadran: se detiene aqui.');
                return self::FAILURE;
            }
        }

        $this->line('');
        $this->info('Listo.');

        return self::SUCCESS;
    }

    private function mostrarEtapas(): void
    {
        $this->info('Etapas, en orden de dependencia:');
        foreach (self::ETAPAS as $nombre => $desc) {
            $this->line(sprintf('  %-12s %s', $nombre, $desc));
        }
        $this->line('');
        $this->line('  php artisan etl:v1 <etapa>     una etapa');
        $this->line('  php artisan etl:v1 --todo      todas, en orden');
    }
}
