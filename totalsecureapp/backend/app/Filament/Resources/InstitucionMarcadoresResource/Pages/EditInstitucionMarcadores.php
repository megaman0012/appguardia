<?php

namespace App\Filament\Resources\InstitucionMarcadoresResource\Pages;

use App\Filament\Resources\InstitucionMarcadoresResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInstitucionMarcadores extends EditRecord
{
    protected static string $resource = InstitucionMarcadoresResource::class;

    protected function getHeaderActions(): array { return []; }
}
