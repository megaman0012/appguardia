<?php

namespace App\Filament\Resources\AccesoResource\Pages;

use App\Filament\Resources\AccesoResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAcceso extends EditRecord
{
    protected static string $resource = AccesoResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
