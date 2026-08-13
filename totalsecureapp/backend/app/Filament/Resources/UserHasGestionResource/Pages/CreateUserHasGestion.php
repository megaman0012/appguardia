<?php

namespace App\Filament\Resources\UserHasGestionResource\Pages;

use App\Filament\Resources\UserHasGestionResource;
use App\helpers;
use Filament\Notifications\Notification;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Modules\Acceso\Models\user_has_gestions;

class CreateUserHasGestion extends CreateRecord
{
    protected static string $resource = UserHasGestionResource::class;

    protected function getFormActions(): array {
        return [
            $this->getCreateFormAction(), // solo "Guardar"
        ];
    }

    protected function getActions(): array
    {
        return [
            Actions\Action::make('Volver a Gestion')
                ->label('Volver')
                ->url(UserHasGestionResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    protected function getTitle(): string {
        return 'Crear Gestion';
    }

    protected function mutateFormDataBeforeCreate(array $data): array {

        if (user_has_gestions::where('ug_user_id', $data['ug_user_id'])->where( 'ug_finish', 0)->exists()) {
            Notification::make()->title('El usuario ya posee una gestion activa')->danger()->send();
            throw ValidationException::withMessages([]);
        }

        $data['ug_created_user'] = auth()->id();
        return $data;

    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'UserHasGestionResource', 'Create','NOTICE', 'Creacion User Has Gestion');
    }

}
