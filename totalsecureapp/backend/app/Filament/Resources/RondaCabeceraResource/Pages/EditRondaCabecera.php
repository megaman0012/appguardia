<?php

namespace App\Filament\Resources\RondaCabeceraResource\Pages;

use App\Filament\Resources\RondaCabeceraResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRondaCabecera extends EditRecord
{
    protected static string $resource = RondaCabeceraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
