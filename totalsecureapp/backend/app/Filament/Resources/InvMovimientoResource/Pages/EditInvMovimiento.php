<?php

namespace App\Filament\Resources\InvMovimientoResource\Pages;

use App\Filament\Resources\InvMovimientoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvMovimiento extends EditRecord
{
    protected static string $resource = InvMovimientoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
