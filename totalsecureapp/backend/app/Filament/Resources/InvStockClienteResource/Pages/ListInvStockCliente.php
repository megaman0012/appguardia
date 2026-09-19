<?php

namespace App\Filament\Resources\InvStockClienteResource\Pages;

use App\Filament\Pages\ListadoBase;
use App\Filament\Resources\InvStockClienteResource;
use Filament\Actions;

// Hereda de ListadoBase, no de ListRecords: es la convencion del panel y de ahi
// cuelgan las migas y el boton de descarga. Hay tests que lo verifican.
class ListInvStockCliente extends ListadoBase
{
    protected static string $resource = InvStockClienteResource::class;

    protected function accionesPropias(): array
    {
        return [Actions\CreateAction::make()->label('Asignar producto a cliente')];
    }
}
