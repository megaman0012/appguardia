<?php

namespace App\Filament\Resources\SedeResource\Pages;

use App\Filament\Resources\SedeResource;
use App\helpers;
use Filament\Forms\Components\Actions\Action;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSede extends CreateRecord
{
    protected static string $resource = SedeResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(), // solo "Guardar"
        ];
    }

    protected function getActions(): array
    {
        return [
            Actions\Action::make('Volver a Sedes')
                ->label('Volver')
                ->url(SedeResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['ps_created_user'] = auth()->id();
        $data['ps_updated_user'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'SedeResource', 'Create','NOTICE', 'Creacion Sede');
    }

}
