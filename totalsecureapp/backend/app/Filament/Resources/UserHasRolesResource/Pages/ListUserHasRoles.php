<?php

namespace App\Filament\Resources\UserHasRolesResource\Pages;

use App\Filament\Resources\UserHasRolesResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;

class ListUserHasRoles extends ListadoBase
{
    protected static string $resource = UserHasRolesResource::class;

    protected function accionesPropias(): array {
        return [
            Actions\CreateAction::make()->label('Asignar Perfil'),
        ];
    }

    protected function getTitle(): string {
        return 'Usuarios > Perfiles';
    }

}
