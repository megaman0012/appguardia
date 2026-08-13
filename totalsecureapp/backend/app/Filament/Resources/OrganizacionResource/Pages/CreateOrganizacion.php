<?php

namespace App\Filament\Resources\OrganizacionResource\Pages;

use App\Filament\Resources\OrganizacionResource;
use App\Filament\Resources\SedeResource;
use App\helpers;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganizacion extends CreateRecord
{
    protected static string $resource = OrganizacionResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(), // solo "Guardar"
        ];
    }

    protected function getActions(): array
    {
        return [
            Actions\Action::make('Volver a Organizaciones')
                ->label('Volver')
                ->url(OrganizacionResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['org_created_user'] = auth()->id();
        $data['org_updated_user'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'OrganizacionResource', 'Create','NOTICE', 'Creacion Organizacion');
    }

}
