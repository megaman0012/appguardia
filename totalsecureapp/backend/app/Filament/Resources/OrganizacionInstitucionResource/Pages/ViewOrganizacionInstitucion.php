<?php

namespace App\Filament\Resources\OrganizacionInstitucionResource\Pages;

use App\Filament\Resources\OrganizacionInstitucionResource;
use App\Support\PerfilPanel;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ViewRecord;

/**
 * Vista de solo lectura de un local.
 *
 * El Supervisor necesita consultar sus locales y sus marcadores QR, pero no
 * modificarlos: observa la operacion, no la define. Antes llegaba a la pantalla
 * de edicion y solo se le ocultaba el boton de guardar, lo que dependia de que
 * la interfaz escondiera un boton en vez de que la ruta lo impidiera.
 *
 * Ahora la edicion esta cerrada por canEdit() y esta pagina le da la lectura.
 */
class ViewOrganizacionInstitucion extends ViewRecord
{
    protected static string $resource = OrganizacionInstitucionResource::class;

    protected function getActions(): array
    {
        return [
            // Solo aparece para quien si puede editar.
            Actions\EditAction::make()
                ->visible(fn () => PerfilPanel::puedeAdministrarLocales()),
        ];
    }

    protected function getTitle(): string
    {
        return 'Local';
    }
}
