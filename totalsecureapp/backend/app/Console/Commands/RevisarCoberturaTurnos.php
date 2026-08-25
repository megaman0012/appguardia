<?php

namespace App\Console\Commands;

use App\Services\VacanteService;
use Illuminate\Console\Command;

/**
 * Vigila los turnos que empezaron y nadie marcó.
 *
 * Corre cada pocos minutos, no una vez al día: `turnos:cerrar-dia` se ejecuta a
 * las 23:55, y enterarse a esa hora de que el puesto de las 06:00 quedó vacío no
 * sirve para cubrirlo. Este comando existe para avisar mientras todavía se puede
 * hacer algo.
 *
 * No convoca a nadie: deja la vacante en "por confirmar" para que el supervisor
 * verifique. Un guardia puede estar en su puesto con el teléfono sin señal.
 */
class RevisarCoberturaTurnos extends Command
{
    protected $signature = 'turnos:revisar-cobertura';
    protected $description = 'Detecta turnos sin marcaje, escala las vacantes sin postulantes y cierra las vencidas';

    public function __construct(private VacanteService $vacantes)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $detectadas = $this->vacantes->detectar();
        $escaladas  = $this->vacantes->escalarAlcance();
        $vencidas   = $this->vacantes->vencer();

        $this->info(sprintf(
            'Cobertura: %d por confirmar, %d abiertas a la ciudad, %d vencidas.',
            $detectadas,
            $escaladas,
            $vencidas
        ));

        return Command::SUCCESS;
    }
}
