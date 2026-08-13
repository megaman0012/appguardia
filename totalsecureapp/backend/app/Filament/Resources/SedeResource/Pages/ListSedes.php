<?php

namespace App\Filament\Resources\SedeResource\Pages;

use App\Filament\Resources\SedeResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSedes extends ListRecords
{
    protected static string $resource = SedeResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
