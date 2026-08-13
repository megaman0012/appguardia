<?php

namespace App\Filament\Resources\InstitucionMarcadoresResource\Pages;

use App\Filament\Resources\InstitucionMarcadoresResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInstitucionMarcadores extends EditRecord
{
    protected static string $resource = InstitucionMarcadoresResource::class;

    protected function getActions(): array { return []; }
}
