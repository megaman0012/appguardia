<?php

namespace App\Filament\Resources\VacanteResource\Pages;

use App\Filament\Resources\VacanteResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;

class ListVacantes extends ListadoBase
{
    protected static string $resource = VacanteResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Pedir refuerzo'),
        ];
    }

    protected function getTitle(): string
    {
        return 'Cobertura de turnos';
    }
}
