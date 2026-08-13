<?php

namespace App\Filament\Resources\RondaDetalleResource\Pages;

use App\Filament\Resources\RondaDetalleResource;
use App\Filament\Resources\RondaCabeceraResource;

use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRondaDetalles extends ListRecords
{
    protected static string $resource = RondaDetalleResource::class;

    protected function getActions(): array {
        return [
            Actions\Action::make('Volver a Rondas')
            ->label('Volver a Rondas')
            ->url(RondaCabeceraResource::getUrl())
            ->color('primary')
            ->icon('heroicon-o-arrow-left'),
        ];
    }

    protected function getTitle(): string {
        $ronda_id = request()->query('ronda');
        return 'Ronda '.$ronda_id.' Detalle';
    }

}
