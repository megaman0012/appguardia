<?php

namespace App\Filament\Resources\NovedadResource\Pages;

use App\Filament\Resources\NovedadResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNovedad extends EditRecord
{
    protected static string $resource = NovedadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
