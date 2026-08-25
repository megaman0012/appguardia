<?php

namespace App\Filament\Resources\TurnoResource\Pages;

use App\Filament\Resources\TurnoResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTurnos extends ListRecords
{
    protected static string $resource = TurnoResource::class;

    protected function getActions(): array
    {
        return [Actions\CreateAction::make()->label('Programar turno')];
    }
}
