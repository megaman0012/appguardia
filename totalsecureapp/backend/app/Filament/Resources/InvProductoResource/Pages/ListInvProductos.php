<?php

namespace App\Filament\Resources\InvProductoResource\Pages;

use App\Filament\Resources\InvProductoResource;
use Filament\Actions;
use App\Filament\Pages\ListadoBase;

class ListInvProductos extends ListadoBase
{
    protected static string $resource = InvProductoResource::class;

    protected function accionesPropias(): array {
        return [ Actions\CreateAction::make()->label('Nuevo Producto'), ];
    }

    public function getTitle(): string {
        return 'Productos';
    }
}
