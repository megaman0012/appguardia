<?php

namespace App\Filament\Resources\OrganizacionSedeResource\Pages;

use App\Filament\Resources\OrganizacionSedeResource;
use App\helpers;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganizacionSede extends CreateRecord
{
    protected static string $resource = OrganizacionSedeResource::class;
    protected function getFormActions(): array {
        return [
            $this->getCreateFormAction(),
        ];
    }
    protected function getActions(): array {
        return [
            Actions\Action::make('Volver a Organizacion Sede')
            ->label('Volver')
            ->url(OrganizacionSedeResource::getUrl())
            ->color('primary')
            ->icon('heroicon-o-arrow-left')
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['so_created_user'] = auth()->id();
        $data['so_updated_user'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'OrganizacionSedeResource', 'Create','NOTICE', 'Creacion Organizacion Sede');
    }
}
