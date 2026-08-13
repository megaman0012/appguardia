<?php

namespace App\Filament\Resources\NovedadResource\Pages;

use App\Filament\Resources\NovedadResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNovedad extends EditRecord
{
    protected static string $resource = NovedadResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
