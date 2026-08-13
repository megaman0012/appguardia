<?php

namespace App\Filament\Resources\InvProductoResource\Pages;

use App\Filament\Resources\InvProductoResource;
use App\helpers;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInvProducto extends CreateRecord
{
    protected static string $resource = InvProductoResource::class;
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(), // solo "Guardar"
        ];
    }

    protected function getActions(): array
    {
        return [
            Actions\Action::make('Volver a Productos')
                ->label('Volver')
                ->url(InvProductoResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['pr_created_user'] = auth()->id();
        $data['pr_updated_user'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'InvProductoResource', 'Create', 'NOTICE', 'Creacion Producto');
    }

}
