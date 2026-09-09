<?php

namespace App\Filament\Resources\InvListaProductoResource\Pages;

use App\Filament\Resources\InvListaProductoResource;
use App\helpers;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvListaProducto extends EditRecord
{
    protected static string $resource = InvListaProductoResource::class;

    public function getTitle(): string { return 'Editar Listas'; }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('Volver a Sedes')
                ->label('Volver')
                ->url(InvListaProductoResource::getUrl())
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
        $data['lp_updated_user'] = auth()->id();
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'InvListaProductoResource', 'Edit','NOTICE', 'Editar Lista');
    }

}
