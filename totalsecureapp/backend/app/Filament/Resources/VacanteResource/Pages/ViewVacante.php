<?php

namespace App\Filament\Resources\VacanteResource\Pages;

use App\Filament\Resources\VacanteResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * Detalle de una vacante y quiénes se postularon.
 *
 * Es de solo lectura a propósito: una vacante no se edita, se resuelve. Las
 * decisiones (ofrecerla, elegir quién cubre, cerrarla) son acciones del listado,
 * y cada una deja registrado quién la tomó.
 */
class ViewVacante extends ViewRecord
{
    protected static string $resource = VacanteResource::class;

    protected function getTitle(): string
    {
        return 'Vacante';
    }
}
