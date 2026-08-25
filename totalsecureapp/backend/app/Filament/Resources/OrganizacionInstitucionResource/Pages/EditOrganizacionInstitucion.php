<?php

namespace App\Filament\Resources\OrganizacionInstitucionResource\Pages;

use App\Support\PerfilPanel;

use App\Filament\Resources\OrganizacionInstitucionResource;
use App\helpers;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Resources\Components\Tabs;
use Filament\Pages\Actions;
use Filament\Pages\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Session;

class EditOrganizacionInstitucion extends EditRecord {


    protected static string $resource = OrganizacionInstitucionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Volver')
                ->url(OrganizacionInstitucionResource::getUrl('index')),
            Actions\DeleteAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'Formulario' => Tab::make()
                ->label('Institución')
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

    protected function getActions(): array {
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
