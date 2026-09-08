<?php

namespace App\Filament\Resources\InstitucionMarcadoresResource\Pages;

use App\Filament\Resources\InstitucionMarcadoresResource;
use App\Filament\Resources\OrganizacionInstitucionResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;

class ListInstitucionMarcadores extends ListadoBase
{
    protected static string $resource = InstitucionMarcadoresResource::class;

    protected function getActions(): array
    {
        return [
            Actions\Action::make('Volver a Intituciones')
            ->label('Volver')
            ->url(OrganizacionInstitucionResource::getUrl())
            ->color('primary')
            ->icon('heroicon-o-arrow-left'),
            Actions\CreateAction::make()->label('Nuevo Marcador')
            ->url(fn($record) =>
                InstitucionMarcadoresResource::getUrl('create', [
                    'codigo' => request()->query('codigo'),
                ])
            ),
        ];
    }

    protected function getTitle(): string {
        $descripcion = urldecode(request()->query('descripcion'));
        return 'Marcadores de '.$descripcion;
    }
}
