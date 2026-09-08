<?php

namespace App\Filament\Resources\AccesoResource\Pages;

use App\Filament\Resources\AccesoResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;

class ListAccesos extends ListadoBase {
    protected static string $resource = AccesoResource::class;
    protected function accionesPropias(): array { return []; }
}
