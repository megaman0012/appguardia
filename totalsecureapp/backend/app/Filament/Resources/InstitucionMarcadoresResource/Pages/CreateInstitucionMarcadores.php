<?php

namespace App\Filament\Resources\InstitucionMarcadoresResource\Pages;

use App\Filament\Resources\InstitucionMarcadoresResource;
use App\Support\PerfilPanel;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CreateInstitucionMarcadores extends CreateRecord {
    protected static string $resource = InstitucionMarcadoresResource::class;

    /**
     * El local del marcador tiene que estar dentro del alcance de quien lo crea.
     *
     * `getEloquentQuery()` acota lo que se VE y lo que se puede editar, pero no
     * alcanza para esta pagina: el local viaja en un `Hidden` cuyo valor sale de
     * `?codigo=` de la URL, y un campo oculto es solo oculto para la pantalla
     * --no para quien arma la peticion--. Sin esta comprobacion, un Supervisor
     * podia crear un marcador en el local de otro cliente, que es la misma fuga
     * por la puerta de al lado.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $locales = PerfilPanel::localesVisibles();

        if ($locales !== null && !in_array((int) ($data['im_ins_code'] ?? 0), $locales, true)) {
            throw new AccessDeniedHttpException('No puede crear marcadores en ese local.');
        }

        return $data;
    }
}
