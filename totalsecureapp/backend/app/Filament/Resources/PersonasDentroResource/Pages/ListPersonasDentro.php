<?php

namespace App\Filament\Resources\PersonasDentroResource\Pages;

use App\Filament\Pages\ListadoBase;
use App\Filament\Resources\PersonasDentroResource;

class ListPersonasDentro extends ListadoBase
{
    protected static string $resource = PersonasDentroResource::class;

    public function getSubheading(): ?string
    {
        return 'Accesos con ingreso registrado y sin salida. Busque por documento, '
            . 'nombre, apellido o placa.';
    }
}
