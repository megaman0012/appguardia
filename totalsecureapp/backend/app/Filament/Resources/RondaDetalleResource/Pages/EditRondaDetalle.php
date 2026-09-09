<?php

namespace App\Filament\Resources\RondaDetalleResource\Pages;

use App\Filament\Resources\RondaDetalleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRondaDetalle extends EditRecord
{
    protected static string $resource = RondaDetalleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
