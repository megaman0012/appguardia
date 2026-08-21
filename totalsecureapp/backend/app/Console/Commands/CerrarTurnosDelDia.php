<?php

namespace App\Console\Commands;

use App\Services\TurnoService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Administracion\Models\OrganizacionInstitucion;

class CerrarTurnosDelDia extends Command
{
    protected $signature = 'turnos:cerrar-dia';
    protected $description = 'Cierra turnos programados sin marcacion de entrada del dia actual';

    protected TurnoService $turnoService;

    public function __construct(TurnoService $turnoService)
    {
        parent::__construct();
        $this->turnoService = $turnoService;
    }

    public function handle(): int
    {
        $this->info('Cerrando turnos sin marcacion del dia: ' . Carbon::today()->toDateString());

        $instituciones = OrganizacionInstitucion::where('ins_estado', true)->get();
        $totalMarcados = 0;

        foreach ($instituciones as $inst) {
            $marcados = $this->turnoService->cerrarTurnosSinMarcacion($inst->ins_code);
            if ($marcados > 0) {
                $this->info("  Institucion {$inst->ins_descripcion}: {$marcados} turnos marcados como ausente");
            }
            $totalMarcados += $marcados;
        }

        $this->info("Total: {$totalMarcados} turnos marcados como ausente");
        return Command::SUCCESS;
    }
}
