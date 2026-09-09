<?php

namespace App\Filament\Resources\ProvinciaResource\Pages;

use App\Filament\Resources\ProvinciaResource;
use Filament\Actions;
use App\Filament\Pages\ListadoBase;

class ListProvincias extends ListadoBase
{
    protected static string $resource = ProvinciaResource::class;

    protected function accionesPropias(): array
    {
        return [Actions\CreateAction::make()];
    }
}
