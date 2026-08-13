<?php

namespace App\Filament\Resources\UsersResource\Pages;

use App\Filament\Resources\UsersResource;
use App\helpers;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUsers extends EditRecord
{
    protected static string $resource = UsersResource::class;

    protected function getFormActions(): array {
        return [ $this->getSaveFormAction() ];
    }

    protected function getActions(): array {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('Volver a Usuarios')
                ->label('Volver')
                ->url(UsersResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_user'] = auth()->id();
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'UserResource', 'Edit','NOTICE', 'Editar User');
    }
}
