<?php

namespace App\Filament\Resources\OrganizacionResource\Pages;

use App\Filament\Resources\OrganizacionResource;
use Filament\Actions;
use App\Filament\Pages\ListadoBase;

class ListOrganizacions extends ListadoBase
{
    protected static string $resource = OrganizacionResource::class;

    protected function accionesPropias(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
