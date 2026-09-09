<?php

namespace App\Filament\Resources\PaisResource\Pages;

use App\Filament\Resources\PaisResource;
use Filament\Actions;
use App\Filament\Pages\ListadoBase;

class ListPais extends ListadoBase
{
    protected static string $resource = PaisResource::class;

    protected function accionesPropias(): array
    {
        return [Actions\CreateAction::make()];
    }
}
