<?php

namespace App\Filament\Resources\OrganizacionSedeResource\Pages;

use App\Filament\Resources\OrganizacionSedeResource;
use App\helpers;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrganizacionSede extends EditRecord
{
    protected static string $resource = OrganizacionSedeResource::class;

    protected function getFormActions(): array {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getActions(): array {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('Volver a Organizacion Sede')
                ->label('Volver')
                ->url(OrganizacionSedeResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    public function save(bool $shouldRedirect = true): void {
        try {
            parent::save($shouldRedirect);
        } catch (\Exception $e) {
            $this->notify('danger', $e->getMessage());
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['so_updated_user'] = auth()->id();
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'OrganizacionSedeResource', 'Edit','NOTICE', 'Editar Organizacion Sede');
    }

}
