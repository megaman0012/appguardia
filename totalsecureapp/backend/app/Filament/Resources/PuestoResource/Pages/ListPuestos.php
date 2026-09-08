<?php

namespace App\Filament\Resources\PuestoResource\Pages;

use App\Filament\Resources\PuestoResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;

class ListPuestos extends ListadoBase
{
    protected static string $resource = PuestoResource::class;

    protected function accionesPropias(): array
    {
        return [Actions\CreateAction::make()->label('Nuevo puesto')];
    }
}
