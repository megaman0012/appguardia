<?php

namespace App\Filament\Resources\OrganizacionInstitucionResource\Pages;

use App\Filament\Resources\OrganizacionInstitucionResource;
use App\helpers;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganizacionInstitucion extends CreateRecord
{
    protected static string $resource = OrganizacionInstitucionResource::class;

    protected function getFormActions(): array {
        return [
            $this->getCreateFormAction(),
        ];
    }

    protected function getActions(): array {
        return [
            Actions\Action::make('Volver a Organizacion Institucion')
                ->label('Volver')
                ->url(OrganizacionInstitucionResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['ins_created_user'] = auth()->id();
        $data['ins_updated_user'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'OrganizacionInstitucionResource', 'Create','NOTICE', 'Creacion Organizacion Institucion');
    }

}
