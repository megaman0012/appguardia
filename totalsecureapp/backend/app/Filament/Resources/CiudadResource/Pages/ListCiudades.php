<?php

namespace App\Filament\Resources\CiudadResource\Pages;

use App\Filament\Resources\CiudadResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCiudades extends ListRecords
{
    protected static string $resource = CiudadResource::class;

    protected function getActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
