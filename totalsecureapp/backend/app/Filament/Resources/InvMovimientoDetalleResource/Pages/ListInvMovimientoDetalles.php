<?php

namespace App\Filament\Resources\InvMovimientoDetalleResource\Pages;

use App\Filament\Resources\InvMovimientoDetalleResource;
use App\Filament\Resources\InvMovimientoResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;
use Modules\Administracion\Models\InvMovimiento;

class ListInvMovimientoDetalles extends ListadoBase
{
    protected static string $resource = InvMovimientoDetalleResource::class;

    protected function getActions(): array {
        return [
            Actions\Action::make('backmovi')
                ->label('Volver a Movimientos')
                ->url(InvMovimientoResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    protected function getTitle(): string {
        $mov = request()->query('mov');
        return 'Movimiento '.$mov.' Detalle';
    }

}
