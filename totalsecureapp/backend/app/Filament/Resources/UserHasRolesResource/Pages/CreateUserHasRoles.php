<?php

namespace App\Filament\Resources\UserHasRolesResource\Pages;

use App\Filament\Resources\UserHasRolesResource;
use App\helpers;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUserHasRoles extends CreateRecord
{
    protected static string $resource = UserHasRolesResource::class;
    protected function getFormActions(): array {
        return [
            $this->getCreateFormAction(), // solo "Guardar"
        ];
    }

    protected function getTitle(): string {
        return 'Asignacion de Perfil';
    }

    protected function getActions(): array
    {
        return [
            Actions\Action::make('Volver a Perfiles')
                ->label('Volver')
                ->url(UserHasRolesResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'UserHasRolesResource', 'Create','NOTICE', 'Creacion User Has Roles');
    }
}
