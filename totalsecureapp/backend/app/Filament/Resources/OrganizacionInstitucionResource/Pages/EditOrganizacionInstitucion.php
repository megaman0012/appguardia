<?php

namespace App\Filament\Resources\OrganizacionInstitucionResource\Pages;

use App\Support\PerfilPanel;

use App\Filament\Resources\OrganizacionInstitucionResource;
use App\helpers;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Resources\Components\Tabs;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Session;

class EditOrganizacionInstitucion extends EditRecord {


    protected static string $resource = OrganizacionInstitucionResource::class;

    /*
     * ⚠️ Aca habia un segundo `getHeaderActions()` con un «Volver» y un
     * `DeleteAction`, y era **codigo muerto**: en Filament 2 las acciones de
     * cabecera de una pagina salian de `getActions()`, que es el metodo de mas
     * abajo, y `getHeaderActions()` no se llamaba nunca. Se borro en vez de
     * fusionarlo: en Filament 3 `getHeaderActions()` **si** se llama, asi que
     * conservarlo habria estrenado un boton de borrar que este panel nunca
     * mostro.
     */

    public function getTabs(): array
    {
        return [
            'Formulario' => Tab::make()
                ->label('Local')
                ->schema($this->getFormSchema()),

            'Marcadores' => Tab::make()
                ->label('Marcadores')
                ->relationship('marcadores')
                ->record($this->record),
        ];
    }

    protected function getFormActions(): array {
        return [
            $this->getSaveFormAction()
                ->visible(fn () => PerfilPanel::puedeAdministrarLocales()),
        ];
    }

    protected function getHeaderActions(): array {
        return [
            Actions\Action::make('Volver a Organizacion Institucion')
            ->label('Volver')
            ->url(OrganizacionInstitucionResource::getUrl())
            ->color('primary')
            ->icon('heroicon-o-arrow-left')
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['ins_updated_user'] = auth()->id();
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'OrganizacionInstitucionResource', 'Edit','NOTICE', 'Editar Organizacion Institucion');
    }

}
