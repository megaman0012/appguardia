<?php

namespace App\Filament\Resources\AlertasResource\Pages;

use App\Filament\Resources\AlertasResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAlertas extends ListRecords
{
    protected static string $resource = AlertasResource::class;

    protected function getActions(): array { return []; }
}
