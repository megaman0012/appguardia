<?php

namespace App\Filament\Resources\KitResource\Pages;

use App\Filament\Pages\ListadoBase;
use App\Filament\Resources\KitResource;
use Filament\Actions;

// Hereda de ListadoBase, no de ListRecords: es la convencion del panel y de ahi
// cuelgan las migas y el boton de descarga. Hay tests que lo verifican.
class ListKits extends ListadoBase
{
    protected static string $resource = KitResource::class;

    protected function accionesPropias(): array
    {
        return [Actions\CreateAction::make()->label('Nuevo kit')];
    }
}
