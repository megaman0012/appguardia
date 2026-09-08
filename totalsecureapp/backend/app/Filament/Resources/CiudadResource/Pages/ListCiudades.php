<?php

namespace App\Filament\Resources\CiudadResource\Pages;

use App\Filament\Resources\CiudadResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;

class ListCiudades extends ListadoBase
{
    protected static string $resource = CiudadResource::class;

    protected function accionesPropias(): array
    {
        return [Actions\CreateAction::make()];
    }
}
