<?php

namespace App\Filament\Resources\UserHasInstitucionResource\Pages;

use App\Filament\Resources\UserHasInstitucionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserHasInstitucions extends ListRecords
{
    protected static string $resource = UserHasInstitucionResource::class;

    protected function getActions(): array {
        return [ Actions\CreateAction::make()->label("Asignar Institucion") ];
    }

    protected function getTitle(): string {
        return 'Usuario > Institucion';
    }
}
