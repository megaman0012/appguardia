<?php

namespace App\Filament\Resources\InvProductoResource\Pages;

use App\Filament\Resources\InvProductoResource;
use App\helpers;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvProducto extends EditRecord
{
    protected static string $resource = InvProductoResource::class;

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
            Actions\Action::make('Volver a Productos')
                ->label('Volver')
                ->url(InvProductoResource::getUrl())
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
        $data['pr_updated_user'] = auth()->id();
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'InvProductoResource', 'Edit','NOTICE', 'Editar Producto');
    }
}
