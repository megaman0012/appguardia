<?php

namespace App\Filament\Resources\RondaCabeceraResource\Pages;

use App\Filament\Resources\RondaCabeceraResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;

class ListRondaCabeceras extends ListadoBase {
    protected static string $resource = RondaCabeceraResource::class;
    protected function getActions(): array { return []; }
}
