<?php

namespace App\Filament\Resources\RondaDetalleResource\Pages;

use App\Filament\Resources\RondaDetalleResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRondaDetalle extends EditRecord
{
    protected static string $resource = RondaDetalleResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
