<?php

namespace App\Filament\Resources\BitacoraResource\Pages;

use App\Filament\Resources\BitacoraResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;

class ListBitacoras extends ListadoBase
{
    protected static string $resource = BitacoraResource::class;
    protected function getActions(): array { return []; }
}
