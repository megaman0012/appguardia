<?php

namespace App\Filament\Resources\InvListaProductoResource\Pages;

use App\Filament\Resources\InvListaProductoResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;

class ListInvListaProductos extends ListadoBase
{
    protected static string $resource = InvListaProductoResource::class;

    protected function getActions(): array {
        return [ Actions\CreateAction::make()->label('Nueva Lista'), ];
    }

    protected function getTitle(): string {
        return 'Listas > Productos';
    }
}
