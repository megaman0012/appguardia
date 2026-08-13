<?php

namespace App\Filament\Resources\InvMovimientoResource\Pages;

use App\Filament\Resources\InvMovimientoResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvMovimiento extends EditRecord
{
    protected static string $resource = InvMovimientoResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
