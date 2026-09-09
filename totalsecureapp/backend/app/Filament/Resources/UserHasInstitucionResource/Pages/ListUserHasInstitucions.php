<?php

namespace App\Filament\Resources\UserHasInstitucionResource\Pages;

use App\Filament\Resources\UserHasInstitucionResource;
use Filament\Actions;
use App\Filament\Pages\ListadoBase;

class ListUserHasInstitucions extends ListadoBase
{
    protected static string $resource = UserHasInstitucionResource::class;

    protected function accionesPropias(): array {
        return [ Actions\CreateAction::make()->label("Asignar Institucion") ];
    }

    public function getTitle(): string {
        return 'Usuario > Institucion';
    }
}
