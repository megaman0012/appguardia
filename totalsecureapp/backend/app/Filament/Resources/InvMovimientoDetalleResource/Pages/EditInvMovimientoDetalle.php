<?php

namespace App\Filament\Resources\InvMovimientoDetalleResource\Pages;

use App\Filament\Resources\InvMovimientoDetalleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvMovimientoDetalle extends EditRecord
{
    protected static string $resource = InvMovimientoDetalleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
