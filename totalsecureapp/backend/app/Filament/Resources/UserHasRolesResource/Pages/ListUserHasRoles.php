<?php

namespace App\Filament\Resources\UserHasRolesResource\Pages;

use App\Filament\Resources\UserHasRolesResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserHasRoles extends ListRecords
{
    protected static string $resource = UserHasRolesResource::class;

    protected function getActions(): array {
        return [
            Actions\CreateAction::make()->label('Asignar Perfil'),
        ];
    }

    protected function getTitle(): string {
        return 'Usuarios > Perfiles';
    }

}
