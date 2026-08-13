<?php

namespace App\Filament\Resources\UsersResource\Pages;

use App\Filament\Resources\UsersResource;
use App\helpers;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateUsers extends CreateRecord
{
    protected static string $resource = UsersResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(), // solo "Guardar"
        ];
    }

    protected function getActions(): array
    {
        return [
            Actions\Action::make('Volver a Usuarios')
                ->label('Volver')
                ->url(UsersResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array {
        $data['created_user'] = auth()->id();
        $data['updated_user'] = auth()->id();
        $data['usu_password'] = Hash::make('123456');
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'UserResource', 'Create','NOTICE', 'Creacion User');
    }

}
