<?php

namespace App\Filament\Resources\InvMovimientoDetalleResource\Pages;

use App\Filament\Resources\InvMovimientoDetalleResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvMovimientoDetalle extends EditRecord
{
    protected static string $resource = InvMovimientoDetalleResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
