<?php

namespace App\Filament\Resources\UserHasInstitucionResource\Pages;

use App\Filament\Resources\UserHasInstitucionResource;
use App\helpers;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUserHasInstitucion extends CreateRecord
{
    protected static string $resource = UserHasInstitucionResource::class;

    protected function getFormActions(): array {
        return [
            $this->getCreateFormAction(), // solo "Guardar"
        ];
    }

    protected function getActions(): array {
        return [
            Actions\Action::make('Volver a UsInst')
                ->label('Volver')
                ->url(UserHasInstitucionResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    protected function getTitle(): string {
        return 'Crear Usuario > Institucion';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['ui_created_user'] = auth()->id();
        $data['ui_updated_user'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'UserHasInstitucionResource', 'Create','NOTICE', 'Creacion User Has Institucion');
    }

}
