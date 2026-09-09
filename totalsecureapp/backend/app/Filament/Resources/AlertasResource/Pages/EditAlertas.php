<?php

namespace App\Filament\Resources\AlertasResource\Pages;

use App\Filament\Resources\AlertasResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAlertas extends EditRecord
{
    protected static string $resource = AlertasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
