<?php

namespace App\Filament\Resources\UserHasRolesResource\Pages;

use App\Filament\Resources\UserHasRolesResource;
use App\helpers;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserHasRoles extends EditRecord
{
    protected static string $resource = UserHasRolesResource::class;

    protected function getActions(): array { return []; }

    protected function afterSave(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'UserHasRolesResource', 'Edit','NOTICE', 'Editar User Has Roles');
    }

}
