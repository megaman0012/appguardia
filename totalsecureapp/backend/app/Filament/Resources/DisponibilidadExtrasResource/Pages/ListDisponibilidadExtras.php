<?php

namespace App\Filament\Resources\DisponibilidadExtrasResource\Pages;

use App\Filament\Resources\DisponibilidadExtrasResource;
use App\Filament\Pages\ListadoBase;

class ListDisponibilidadExtras extends ListadoBase
{
    protected static string $resource = DisponibilidadExtrasResource::class;

    public function getSubheading(): ?string
    {
        return 'Con «Cubre extras» activo, el guardia ve los turnos disponibles en la app '
            . 'y entra en la convocatoria por WhatsApp cuando un puesto queda vacío.';
    }
}
