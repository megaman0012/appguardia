<?php

namespace App\Filament\Resources\InvMovimientoResource\Pages;

use App\Filament\Resources\InvMovimientoResource;
use Filament\Actions;
use App\Filament\Pages\ListadoBase;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;

class ListInvMovimientos extends ListadoBase
{
    protected static string $resource = InvMovimientoResource::class;
    protected function accionesPropias(): array { return []; }
    public function getTitle(): string {
        return 'Movimientos Cabecera';
    }


}
