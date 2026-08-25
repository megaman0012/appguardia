<?php

namespace App\Filament\Resources\PlantillaResource\Pages;

use App\Filament\Resources\PlantillaResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlantilla extends CreateRecord
{
    protected static string $resource = PlantillaResource::class;

    /**
     * Tras crear el cuadrante se va directo a su edicion: ahi estan las franjas,
     * que es donde realmente se arma. Un cuadrante sin franjas no sirve de nada.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['pl_created_user'] = auth()->id();
        $data['pl_updated_user'] = auth()->id();

        return $data;
    }
}
