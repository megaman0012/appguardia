<?php

namespace App\Filament\Resources\NovedadResource\Pages;

use App\Filament\Resources\NovedadResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;

class ListNovedads extends ListadoBase
{
    protected static string $resource = NovedadResource::class;
    protected function accionesPropias(): array { return []; }
}
