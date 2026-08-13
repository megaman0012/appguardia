<?php

namespace App\Filament\Resources\InvListaProductoResource\Pages;

use App\Filament\Resources\InvListaProductoResource;
use App\helpers;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInvListaProducto extends CreateRecord
{
    protected static string $resource = InvListaProductoResource::class;

    protected function getTitle(): string {
        return 'Crear Listas';
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(), // solo "Guardar"
        ];
    }

    protected function getActions(): array
    {
        return [
            Actions\Action::make('Volver a Listas')
                ->label('Volver')
                ->url(InvListaProductoResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['lp_created_user'] = auth()->id();
        $data['lp_updated_user'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'InvListaProductoResource', 'Create','NOTICE', 'Creacion Lista');
    }

}
