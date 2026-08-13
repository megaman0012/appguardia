<?php

namespace App\Filament\Resources\InvListaProductoResource\Pages;

use App\Filament\Resources\InvListaProductoResource;
use App\helpers;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvListaProducto extends EditRecord
{
    protected static string $resource = InvListaProductoResource::class;

    protected function getTitle(): string { return 'Editar Listas'; }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getActions(): array
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

    public function save(bool $shouldRedirect = true): void
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
