<?php

namespace App\Filament\Resources\AlertasResource\Pages;

use App\Filament\Resources\AlertasResource;
use Filament\Actions;
use App\Filament\Pages\ListadoBase;

class ListAlertas extends ListadoBase
{
    protected static string $resource = AlertasResource::class;

    protected function accionesPropias(): array { return []; }
}
