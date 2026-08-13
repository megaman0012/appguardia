<?php

namespace App\Filament\Resources\OrganizacionSedeResource\Pages;

use App\Filament\Resources\OrganizacionSedeResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrganizacionSedes extends ListRecords
{
    protected static string $resource = OrganizacionSedeResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
