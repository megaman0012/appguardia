<?php

namespace App\Filament\Resources\UserHasGestionResource\Pages;

use App\Filament\Resources\UserHasGestionResource;
use App\helpers;
use Filament\Notifications\Notification;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Modules\Acceso\Models\user_has_gestions;

class EditUserHasGestion extends EditRecord {
    protected static string $resource = UserHasGestionResource::class;
    protected function getActions(): array {
        return [
            Actions\Action::make('Volver a Gestion')
                ->label('Volver')
                ->url(UserHasGestionResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    protected function getFormActions(): array {
        return [ $this->getSaveFormAction() ];
    }

    protected function getTitle(): string {
        return 'Editar Gestion';
    }

    protected function mutateFormDataBeforeSave(array $data): array {

        if (empty($data['ug_egreso'])) {
            Notification::make()->title('Campo Egreso es obligatorio para continuar.')->danger()->send();
            throw ValidationException::withMessages([]);
        }

        if (user_has_gestions::where('ug_code', $this->record->ug_code)->where( 'ug_finish', 1)->exists()) {
            Notification::make()->title('El registro ya fue finalizado vuelva a la pagina anterior.')->danger()->send();
            throw ValidationException::withMessages([]);
        }

        $data['ug_finish'] = 1;
        $data['ug_updated_user'] = auth()->id();
        return $data;

    }

    protected function afterSave(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'UserHasGestionResource', 'Edit','NOTICE', 'Editar User Has Gestion');
    }


}
