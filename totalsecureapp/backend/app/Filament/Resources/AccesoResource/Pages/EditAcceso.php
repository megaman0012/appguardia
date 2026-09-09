<?php

namespace App\Filament\Resources\AccesoResource\Pages;

use App\Filament\Resources\AccesoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAcceso extends EditRecord
{
    protected static string $resource = AccesoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
