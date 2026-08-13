<?php

namespace App\Filament\Resources\UserHasInstitucionResource\Pages;

use App\Filament\Resources\UserHasInstitucionResource;
use App\helpers;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserHasInstitucion extends EditRecord
{
    protected static string $resource = UserHasInstitucionResource::class;

    protected function getFormActions(): array {
        return [ $this->getSaveFormAction() ];
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
        return 'Editar Usuario > Institucion';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['ui_updated_user'] = auth()->id();
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'UserHasInstitucionResource', 'Edit','NOTICE', 'Editar User Has Institucion');
    }


}
