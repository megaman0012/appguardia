<?php

namespace App\Filament\Resources\VacanteResource\Pages;

use App\Filament\Resources\VacanteResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Modules\Administracion\Models\TurnoVacante;

/**
 * Alta manual de una vacante.
 *
 * Es el caso del refuerzo que pide el cliente, o de la falta avisada por
 * teléfono antes de la hora. Como la carga una persona, no hace falta que otra
 * la confirme: nace ya ofrecida.
 */
class CreateVacante extends CreateRecord
{
    protected static string $resource = VacanteResource::class;

    protected function getTitle(): string
    {
        return 'Pedir cobertura';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tv_estado']      = TurnoVacante::ABIERTA;
        $data['tv_alcance']     = TurnoVacante::ALCANCE_LOCAL;
        $data['tv_abierta_por'] = \Session::get('usuID');
        $data['tv_abierta_en']  = Carbon::now();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
