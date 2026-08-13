<?php

namespace App\Filament\Resources\RondaCabeceraResource\Pages;

use App\Filament\Resources\RondaCabeceraResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRondaCabecera extends EditRecord
{
    protected static string $resource = RondaCabeceraResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
