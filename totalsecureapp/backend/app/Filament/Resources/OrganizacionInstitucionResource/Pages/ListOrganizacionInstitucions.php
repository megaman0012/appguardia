<?php

namespace App\Filament\Resources\OrganizacionInstitucionResource\Pages;

use App\Support\PerfilPanel;

use App\Filament\Resources\OrganizacionInstitucionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Session;

class ListOrganizacionInstitucions extends ListRecords {
    protected static string $resource = OrganizacionInstitucionResource::class;

    protected function getActions(): array {
        return [ Actions\CreateAction::make()->label('Nueva Institucion')
            ->visible(fn () => PerfilPanel::puedeAdministrarLocales()), ];
    }

    protected function getTitle(): string {
        return 'Organizanizacion > Institucion';
    }

}
