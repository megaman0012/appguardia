<?php

namespace App\Filament\Resources\AccesoResource\Pages;

use App\Filament\Resources\AccesoResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccesos extends ListRecords {
    protected static string $resource = AccesoResource::class;
    protected function getActions(): array { return []; }
}
