<?php

namespace App\Filament\Resources\RolesResource\Pages;

use App\Filament\Resources\RolesResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;

class ListRoles extends ListadoBase
{
    protected static string $resource = RolesResource::class;

    protected function accionesPropias(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
