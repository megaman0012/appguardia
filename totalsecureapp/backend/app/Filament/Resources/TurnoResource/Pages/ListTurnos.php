<?php

namespace App\Filament\Resources\TurnoResource\Pages;

use App\Filament\Resources\TurnoResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;

class ListTurnos extends ListadoBase
{
    protected static string $resource = TurnoResource::class;

    protected function getActions(): array
    {
        return [Actions\CreateAction::make()->label('Programar turno')];
    }
}
