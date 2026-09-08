<?php

namespace App\Filament\Resources\OrganizacionInstitucionResource\Pages;

use App\Support\PerfilPanel;

use App\Filament\Resources\OrganizacionInstitucionResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;
use Session;

class ListOrganizacionInstitucions extends ListadoBase {
    protected static string $resource = OrganizacionInstitucionResource::class;

    protected function getActions(): array {
        return [ Actions\CreateAction::make()->label('Nueva Institucion')
            ->visible(fn () => PerfilPanel::puedeAdministrarLocales()), ];
    }

    protected function getTitle(): string {
        return 'Organizanizacion > Institucion';
    }

}
