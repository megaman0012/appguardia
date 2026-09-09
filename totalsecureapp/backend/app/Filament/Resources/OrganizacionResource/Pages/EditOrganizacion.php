<?php

namespace App\Filament\Resources\OrganizacionResource\Pages;

use App\Filament\Resources\OrganizacionResource;
use App\helpers;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrganizacion extends EditRecord
{
    protected static string $resource = OrganizacionResource::class;

    protected function getFormActions(): array {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('Volver a Organizaciones')
                ->label('Volver')
                ->url(OrganizacionResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    /*
     * ⚠️ La firma sigue a Filament 3: `EditRecord::save()` gano el segundo
     * parametro `$shouldSendSavedNotification`. Con la firma de la 2 es un
     * error fatal de PHP al cargar la clase, o sea aplicacion caida.
     */
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        try {
            parent::save($shouldRedirect);
        } catch (\Exception $e) {
            $this->notify('danger', $e->getMessage());
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['org_updated_user'] = auth()->id();
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'OrganizacionResource', 'Edit','NOTICE', 'Editar Organizacion');
    }


}
