<?php

namespace App\Filament\Resources\InvMovimientoResource\Pages;

use App\Filament\Resources\InvMovimientoResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Toggle;

class ListInvMovimientos extends ListadoBase
{
    protected static string $resource = InvMovimientoResource::class;
    protected function getActions(): array { return []; }
    protected function getTitle(): string {
        return 'Movimientos Cabecera';
    }


}
