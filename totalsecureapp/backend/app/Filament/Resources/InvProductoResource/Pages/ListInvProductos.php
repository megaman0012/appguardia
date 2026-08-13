<?php

namespace App\Filament\Resources\InvProductoResource\Pages;

use App\Filament\Resources\InvProductoResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvProductos extends ListRecords
{
    protected static string $resource = InvProductoResource::class;

    protected function getActions(): array {
        return [ Actions\CreateAction::make()->label('Nuevo Producto'), ];
    }

    protected function getTitle(): string {
        return 'Productos';
    }
}
